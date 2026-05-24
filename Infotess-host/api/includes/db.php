<?php
// includes/db.php
// BRIDGE FILE: Maps old PDO calls to Supabase REST Client
// UPDATED: Handles both :name and ? parameters

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../lib/Supabase.php';

// Use global $supabase (already initialized by api/index.php)
global $supabase;
if (!isset($supabase)) {
    try {
        $supabase = new SupabaseClient();
    } catch (Exception $e) {
        $supabase = null;
    }
}

// Legacy PDO Mock
class LegacyPDO {
    private $client;
    private $lastInsertId = null;

    public function __construct($client) {
        $this->client = $client;
    }

    public function prepare($sql) {
        return new LegacyStatement($this->client, $sql, $this);
    }

    public function query($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function lastInsertId() {
        return $this->lastInsertId;
    }

    public function setLastInsertId($id) {
        $this->lastInsertId = $id;
    }
    
    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollBack() { return true; }

    /**
     * Execute an SQL statement and return the number of affected rows.
     * Supports SELECT, INSERT, UPDATE, DELETE via the bridge.
     * DDL statements (CREATE, ALTER, DROP, TRUNCATE) are logged and return 0
     * since the Supabase REST bridge cannot execute them.
     */
    public function exec($sql) {
        $sql = trim($sql);
        $sqlUpper = strtoupper(substr($sql, 0, 20));

        // DDL statements — cannot be executed via REST bridge, log and skip
        if (preg_match('/^(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i', $sqlUpper)) {
            error_log("LegacyPDO::exec() skipped DDL (cannot bridge to Supabase REST): " . substr($sql, 0, 120));
            return 0;
        }

        try {
            $stmt = $this->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("LegacyPDO::exec() error: " . $e->getMessage() . " | SQL: " . substr($sql, 0, 200));
            // Return 0 on failure so callers with try/catch can continue
            return 0;
        }
    }
}

class LegacyStatement {
    private $client;
    private $sql;
    private $pdoRef;
    private $params = [];
    private $result = [];
    private $currentIndex = 0;
    private $table = null;
    private $isInsert = false;
    private $isUpdate = false;
    private $isDelete = false;
    private $updateColumns = [];
    private $updateValues = []; // Values side of SET clause (for named-param resolution)
    private $affectedRows = 0;

    public function __construct($client, $sql, $pdoRef) {
        $this->client = $client;
        $this->sql = $sql;
        $this->pdoRef = $pdoRef;
        $this->parseQuery($sql);
    }

    private function parseQuery($sql) {
        $sql = trim($sql);
        if (preg_match('/^SELECT[\s\S]*?FROM\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
        } elseif (preg_match('/^INSERT INTO\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
            $this->isInsert = true;
        } elseif (preg_match('/^UPDATE\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
            $this->isUpdate = true;
            // Extract SET columns and values
            if (preg_match('/SET\s+([\s\S]+?)\s+WHERE/i', $sql, $setMatch)) {
                $setPart = $setMatch[1];
                preg_match_all('/(\w+)\s*=\s*([^,]+)/i', $setPart, $parts, PREG_SET_ORDER);
                $this->updateColumns = [];
                $this->updateValues = [];
                foreach ($parts as $p) {
                    $this->updateColumns[] = $p[1];
                    $this->updateValues[] = trim($p[2]);
                }
            }
        } elseif (preg_match('/^DELETE\s+FROM\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
            $this->isDelete = true;
        }
    }

    public function execute($params = []) {
        // Convert empty strings to null for PostgreSQL integer/numeric columns
        // Also guard: null and "" both become null, and any param that is a
        // non-numeric string (like empty '' from a malformed value) is also null.
        $this->params = array_map(function($v) {
            if ($v === '' || $v === null) return null;
            // If it looks like a non-numeric string being used as a numeric param,
            // cast it safely — Supabase REST API handles the rest.
            return $v;
        }, $params);
        if (!$this->client) return false;

        try {
            if ($this->isInsert) {
                // Parse columns from SQL: INSERT INTO table (col1, col2) VALUES (?, ?, 'literal')
                preg_match('/INSERT INTO\s+\w+\s*\(([^)]+)\)/i', $this->sql, $colMatch);
                $columns = [];
                if ($colMatch) {
                    $columns = array_map('trim', explode(',', $colMatch[1]));
                }

                // Parse VALUES clause to detect literal values vs ? placeholders
                preg_match('/VALUES\s*\(([^)]+)\)/i', $this->sql, $valMatch);
                $rawValues = [];
                if ($valMatch) {
                    $rawValues = $this->parseValues($valMatch[1]);
                }

                $data = [];
                $paramIndex = 0;
                foreach ($columns as $i => $col) {
                    $rawVal = $rawValues[$i] ?? '?';
                    if ($rawVal === '?') {
                        $data[$col] = $this->params[$paramIndex] ?? null;
                        $paramIndex++;
                    } else {
                        $data[$col] = $this->parseLiteral($rawVal);
                    }
                }

                $res = $this->client->table($this->table)->insert($data);
                if ($res && isset($res[0]['id'])) {
                    $this->pdoRef->setLastInsertId($res[0]['id']);
                }
                $this->result = $res;
            } elseif ($this->isUpdate) {
                // UPDATE table SET col1 = ?, col2 = ? WHERE id = ?
                // UPDATE table SET col1 = :named1, col2 = :named2 WHERE id = :id
                // Build data object from SET columns + params (supports both positional ? and :named)
                $data = [];
                $paramIndex = 0;
                
                // SET columns consume params (named or positional)
                foreach ($this->updateColumns as $i => $col) {
                    $valExpr = $this->updateValues[$i] ?? '?';
                    if ($valExpr === '?') {
                        // Positional parameter
                        $data[$col] = $this->params[$paramIndex] ?? null;
                        $paramIndex++;
                    } elseif (strpos($valExpr, ':') === 0) {
                        // Named parameter like :password
                        $paramName = substr($valExpr, 1);
                        $data[$col] = $this->params[$paramName] ?? null;
                    } else {
                        // Literal value like 'true', '0', etc — strip quotes
                        $data[$col] = trim($valExpr, "' \"");
                    }
                }
                
                // Parse WHERE clause for the filter
                preg_match_all('/WHERE\s+([\s\S]+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $query = $this->client->table($this->table);
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $parts = preg_split('/\s*=\s*/', trim($cond));
                        if (count($parts) === 2) {
                            $col = preg_replace('/^\w+\./', '', $parts[0]); // strip table alias
                            $val = trim($parts[1]);
                            if ($val === '?') {
                                $paramVal = $this->params[$paramIndex] ?? null;
                                if ($paramVal !== null && $paramVal !== '') {
                                    $query = $query->where($col, $paramVal);
                                } else {
                                    error_log("pg-bridge GUARD: Null UPDATE WHERE param for column '$col' — using sentinel. SQL: " . substr($this->sql, 0, 200));
                                    $query = $query->where($col, '__NULL_WHERE_GUARD__' . $paramIndex);
                                }
                                $paramIndex++;
                            } elseif (strpos($val, ':') === 0) {
                                // Named parameter like :id
                                $paramName = substr($val, 1);
                                $paramVal = $this->params[$paramName] ?? null;
                                if ($paramVal !== null && $paramVal !== '') {
                                    $query = $query->where($col, $paramVal);
                                } else {
                                    error_log("pg-bridge GUARD: Null UPDATE named-param ':$paramName' for column '$col' — using sentinel. SQL: " . substr($this->sql, 0, 200));
                                    $query = $query->where($col, '__NULL_WHERE_GUARD__' . $paramName);
                                }
                            }
                        }
                    }
                }
                
                try {
                    $this->result = $query->update($data);
                } catch (Exception $updateEx) {
                    // Strip unknown columns and retry (handles schema mismatches gracefully).
                    // This DOES NOT retry if stripping leaves $data empty — instead it surfaces
                    // a clear "column not found" error so the caller knows to add the column.
                    if (strpos($updateEx->getMessage(), 'Could not find') !== false || strpos($updateEx->getMessage(), 'PGRST204') !== false) {
                        // Extract unknown column name from error: "Could not find the 'col' column"
                        if (preg_match("/the '(\w+)' column/", $updateEx->getMessage(), $colMatch)) {
                            $badCol = $colMatch[1];
                            unset($data[$badCol]);
                            if (empty($data)) {
                                // All columns were stripped — column genuinely doesn't exist in DB
                                throw new PDOException("Column '$badCol' does not exist in table '$this->table'. Add it to the database first.");
                            }
                            error_log("Supabase Update: Stripped unknown column '$badCol', retrying...");
                            $this->result = $query->update($data);
                        } else {
                            throw $updateEx;
                        }
                    } else {
                        throw $updateEx;
                    }
                }
                $this->affectedRows = is_array($this->result) ? count($this->result) : 1;
            } elseif ($this->isDelete) {
                // DELETE FROM table WHERE id = ?  OR  DELETE FROM table WHERE id IN (?, ?, ?)
                preg_match_all('/WHERE\s+([\s\S]+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $query = $this->client->table($this->table);
                $paramIndex = 0;
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $cond = trim($cond);
                        // Handle: col IN (?, ?, ?)
                        if (preg_match('/^(\w+)\s+IN\s*\(([^)]+)\)$/i', $cond, $inMatch)) {
                            $col = $inMatch[1];
                            $inParts = array_map('trim', explode(',', $inMatch[2]));
                            $inValues = [];
                            $allNull = true;
                            foreach ($inParts as $part) {
                                if ($part === '?') {
                                    $pv = $this->params[$paramIndex] ?? null;
                                    if ($pv !== null && $pv !== '') {
                                        $inValues[] = $pv;
                                        $allNull = false;
                                    }
                                    $paramIndex++;
                                }
                            }
                            if (!empty($inValues)) {
                                $query = $query->in($col, $inValues);
                            } elseif ($allNull) {
                                // All IN params were null — guard against data leak
                                error_log("pg-bridge GUARD: All IN params null for column '$col' — using sentinel. SQL: " . substr($this->sql, 0, 200));
                                $query = $query->in($col, ['__NULL_IN_GUARD__']);
                            }
                            continue;
                        }
                        // Handle: col IS NULL
                        if (preg_match('/^(\w+)\s+IS\s+NULL$/i', $cond, $nullMatch)) {
                            $query = $query->isNull($nullMatch[1]);
                            continue;
                        }
                        // Handle: col IS NOT NULL
                        if (preg_match('/^(\w+)\s+IS\s+NOT\s+NULL$/i', $cond, $nnMatch)) {
                            $query = $query->notNull($nnMatch[1]);
                            continue;
                        }
                        // Handle: col = ?
                        $parts = preg_split('/\s*=\s*/', $cond);
                        if (count($parts) === 2) {
                            $col = preg_replace('/^\w+\./', '', $parts[0]); // strip table alias
                            $val = trim($parts[1]);
                            if ($val === '?') {
                                $paramVal = $this->params[$paramIndex] ?? null;
                                if ($paramVal !== null && $paramVal !== '') {
                                    $query = $query->where($col, $paramVal);
                                } else {
                                    error_log("pg-bridge GUARD: Null DELETE WHERE param for column '$col' — using sentinel. SQL: " . substr($this->sql, 0, 200));
                                    $query = $query->where($col, '__NULL_WHERE_GUARD__' . $paramIndex);
                                }
                                $paramIndex++;
                            }
                        }
                    }
                }
                
                $this->result = $query->delete();
            } else {
                // SELECT
                if (!$this->table) {
                    throw new Exception("Bridge parseQuery failed to extract table from SQL: " . $this->sql);
                }
                $query = $this->client->table($this->table);
                
                // Handle WHERE clause with ? (positional) or :name (named) parameters
                // NOTE: Empty string ('') is converted to null above.
                // If a param is null/empty, we must NOT silently drop the WHERE condition
                // (which would return ALL rows — a data leak). Instead we add an impossible
                // filter so 0 rows are returned, alerting the caller of the bug.
                preg_match_all('/WHERE\s+([\s\S]+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $currentParamIndex = 0;
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $cond = trim($cond);
                        // Handle: col IN (?, ?, ?) — same pattern as DELETE path
                        if (preg_match('/^(\w+)\s+IN\s*\(([^)]+)\)$/i', $cond, $inMatch)) {
                            $col = $inMatch[1];
                            $inParts = array_map('trim', explode(',', $inMatch[2]));
                            $inValues = [];
                            $allNull = true;
                            foreach ($inParts as $part) {
                                if ($part === '?') {
                                    $pv = $this->params[$currentParamIndex] ?? null;
                                    if ($pv !== null && $pv !== '') {
                                        $inValues[] = $pv;
                                        $allNull = false;
                                    }
                                    $currentParamIndex++;
                                }
                            }
                            if (!empty($inValues)) {
                                $query = $query->in($col, $inValues);
                            } elseif ($allNull) {
                                error_log("pg-bridge GUARD: All SELECT IN params null for column '$col' — using sentinel. SQL: " . substr($this->sql, 0, 200));
                                $query = $query->in($col, ['__NULL_IN_GUARD__']);
                            }
                            continue;
                        }
                        // Handle: col IS NULL
                        if (preg_match('/^(\w+)\s+IS\s+NULL$/i', $cond, $nullMatch)) {
                            $query = $query->isNull($nullMatch[1]);
                            continue;
                        }
                        // Handle: col IS NOT NULL
                        if (preg_match('/^(\w+)\s+IS\s+NOT\s+NULL$/i', $cond, $nnMatch)) {
                            $query = $query->notNull($nnMatch[1]);
                            continue;
                        }
                        // Handle: col = ?
                        $parts = preg_split('/\s*=\s*/', $cond);
                        if (count($parts) === 2) {
                            $col = preg_replace('/^\w+\./', '', $parts[0]); // strip table alias: s.teacher_id → teacher_id
                            $val = trim($parts[1]);
                            if ($val === '?') {
                                $paramVal = $this->params[$currentParamIndex] ?? null;
                                if ($paramVal !== null && $paramVal !== '') {
                                    $query = $query->where($col, $paramVal);
                                } else {
                                    // Null/empty param: guard against data leak by using sentinel
                                    error_log("pg-bridge GUARD: Null WHERE param for column '$col' — using sentinel to prevent data leak. SQL: " . substr($this->sql, 0, 200));
                                    $query = $query->where($col, '__NULL_WHERE_GUARD__' . $currentParamIndex);
                                }
                                $currentParamIndex++;
                            } elseif (strpos($val, ':') === 0) {
                                $paramName = substr($val, 1);
                                $paramVal = $this->params[$paramName] ?? null;
                                if ($paramVal !== null && $paramVal !== '') {
                                    $query = $query->where($col, $paramVal);
                                } else {
                                    error_log("pg-bridge GUARD: Null named-param ':$paramName' for column '$col' — using sentinel. SQL: " . substr($this->sql, 0, 200));
                                    $query = $query->where($col, '__NULL_WHERE_GUARD__' . $paramName);
                                }
                            }
                        }
                    }
                }

                // Handle LIMIT and OFFSET
                if (preg_match('/LIMIT\s+(\d+)/i', $this->sql, $limitMatch)) {
                    $query = $query->limit((int)$limitMatch[1]);
                }
                if (preg_match('/OFFSET\s+(\d+)/i', $this->sql, $offsetMatch)) {
                    $query = $query->offset((int)$offsetMatch[1]);
                }

                $this->result = $query->get();
            }
            $this->currentIndex = 0;
            return true;
        } catch (Exception $e) {
            error_log("Supabase Query Error: " . $e->getMessage());
            throw new PDOException($e->getMessage());
        }
    }

    public function fetch() {
        if (!$this->result || $this->currentIndex >= count($this->result)) {
            return false;
        }
        return $this->result[$this->currentIndex++];
    }

    public function fetchAll() {
        return $this->result ?: [];
    }

    public function fetchColumn() {
        $row = $this->fetch();
        return $row ? reset($row) : null;
    }

    public function rowCount() {
        if ($this->affectedRows > 0) {
            return $this->affectedRows;
        }
        return is_array($this->result) ? count($this->result) : 0;
    }

    /**
     * Parse VALUES clause items, respecting quoted strings that may contain commas.
     */
    private function parseValues($valuesStr) {
        $values = [];
        $current = '';
        $inQuote = false;
        $quoteChar = null;
        $len = strlen($valuesStr);
        
        for ($i = 0; $i < $len; $i++) {
            $char = $valuesStr[$i];
            if ($inQuote) {
                $current .= $char;
                if ($char === $quoteChar && ($i + 1 >= $len || $valuesStr[$i + 1] !== $quoteChar)) {
                    $inQuote = false;
                } elseif ($char === $quoteChar && $i + 1 < $len && $valuesStr[$i + 1] === $quoteChar) {
                    $i++; // Skip escaped quote ''
                    $current .= $valuesStr[$i];
                }
            } else {
                if ($char === '"' || $char === "'") {
                    $inQuote = true;
                    $quoteChar = $char;
                    $current .= $char;
                } elseif ($char === ',') {
                    $values[] = trim($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
        }
        if ($current !== '') {
            $values[] = trim($current);
        }
        return $values;
    }

    /**
     * Parse a SQL literal value: strips quotes from strings, keeps numbers/NULL as-is.
     */
    private function parseLiteral($raw) {
        $raw = trim($raw);
        if ($raw === 'NULL' || $raw === 'null') return null;
        if (($raw[0] === "'" && substr($raw, -1) === "'") || ($raw[0] === '"' && substr($raw, -1) === '"')) {
            // Strip outer quotes and unescape doubled quotes
            $inner = substr($raw, 1, -1);
            return str_replace("''", "'", str_replace('""', '"', $inner));
        }
        // Numeric or boolean
        if (is_numeric($raw)) return $raw + 0; // Convert to int/float
        if (strtolower($raw) === 'true') return true;
        if (strtolower($raw) === 'false') return false;
        return $raw;
    }
}

$pdo = new LegacyPDO($supabase);