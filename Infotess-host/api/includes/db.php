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
            // Extract SET columns
            if (preg_match('/SET\s+([\s\S]+?)\s+WHERE/i', $sql, $setMatch)) {
                $setPart = $setMatch[1];
                preg_match_all('/(\w+)\s*=/i', $setPart, $colMatches);
                $this->updateColumns = $colMatches[1] ?? [];
            }
        } elseif (preg_match('/^DELETE\s+FROM\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
            $this->isDelete = true;
        }
    }

    public function execute($params = []) {
        // Convert empty strings to null for PostgreSQL integer/numeric columns
        $this->params = array_map(function($v) {
            return $v === '' ? null : $v;
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
                // Build data object from SET columns + params
                $data = [];
                $paramIndex = 0;
                
                // SET columns consume params first
                foreach ($this->updateColumns as $col) {
                    $data[$col] = $this->params[$paramIndex] ?? null;
                    $paramIndex++;
                }
                
                // Parse WHERE clause for the filter
                preg_match_all('/WHERE\s+([\s\S]+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $query = $this->client->table($this->table);
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $parts = preg_split('/\s*=\s*/', trim($cond));
                        if (count($parts) === 2) {
                            $col = $parts[0];
                            $val = trim($parts[1]);
                            if ($val === '?') {
                                $query = $query->where($col, $this->params[$paramIndex]);
                                $paramIndex++;
                            }
                        }
                    }
                }
                
                try {
                    $this->result = $query->update($data);
                } catch (Exception $updateEx) {
                    // Strip unknown columns and retry (handles schema mismatches gracefully)
                    if (strpos($updateEx->getMessage(), 'Could not find') !== false || strpos($updateEx->getMessage(), 'PGRST204') !== false) {
                        // Extract unknown column name from error: "Could not find the 'col' column"
                        if (preg_match("/the '(\w+)' column/", $updateEx->getMessage(), $colMatch)) {
                            $badCol = $colMatch[1];
                            unset($data[$badCol]);
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
                // DELETE FROM table WHERE id = ?
                preg_match_all('/WHERE\s+([\s\S]+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $query = $this->client->table($this->table);
                $paramIndex = 0;
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $parts = preg_split('/\s*=\s*/', trim($cond));
                        if (count($parts) === 2) {
                            $col = $parts[0];
                            $val = trim($parts[1]);
                            if ($val === '?') {
                                $query = $query->where($col, $this->params[$paramIndex]);
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
                preg_match_all('/WHERE\s+([\s\S]+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $currentParamIndex = 0;
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $parts = preg_split('/\s*=\s*/', trim($cond));
                        if (count($parts) === 2) {
                            $col = $parts[0];
                            $val = trim($parts[1]);
                            if ($val === '?') {
                                $query = $query->where($col, $this->params[$currentParamIndex]);
                                $currentParamIndex++;
                            } elseif (strpos($val, ':') === 0) {
                                $paramName = substr($val, 1);
                                $query = $query->where($col, $this->params[$paramName] ?? null);
                            }
                        }
                    }
                }

                // Handle LIMIT
                if (preg_match('/LIMIT\s+(\d+)/i', $this->sql, $limitMatch)) {
                    $query = $query->limit((int)$limitMatch[1]);
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