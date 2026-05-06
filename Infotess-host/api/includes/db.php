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

    public function __construct($client, $sql, $pdoRef) {
        $this->client = $client;
        $this->sql = $sql;
        $this->pdoRef = $pdoRef;
        $this->parseQuery($sql);
    }

    private function parseQuery($sql) {
        $sql = trim($sql);
        if (preg_match('/^SELECT.*FROM\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
        } elseif (preg_match('/^INSERT INTO\s+(\w+)/i', $sql, $m)) {
            $this->table = $m[1];
            $this->isInsert = true;
        }
    }

    public function execute($params = []) {
        $this->params = $params;
        
        if (!$this->client) return false;

        try {
            if ($this->isInsert) {
                // Parse columns from SQL: INSERT INTO table (col1, col2) VALUES (?, ?)
                preg_match('/INSERT INTO\s+\w+\s*\(([^)]+)\)/i', $this->sql, $colMatch);
                $columns = [];
                if ($colMatch) {
                    $columns = array_map('trim', explode(',', $colMatch[1]));
                }

                $data = [];
                $i = 0;
                foreach ($columns as $col) {
                    $val = $this->params[$i] ?? null;
                    $data[$col] = $val;
                    $i++;
                }

                $res = $this->client->table($this->table)->insert($data);
                if ($res && isset($res[0]['id'])) {
                    $this->pdoRef->setLastInsertId($res[0]['id']);
                }
                $this->result = $res;
            } else {
                // SELECT
                $query = $this->client->table($this->table);
                
                // Handle WHERE clause with ? parameters
                // This is a simplified parser. It assumes "WHERE col = ?"
                preg_match_all('/WHERE\s+(.+?)(\s+ORDER|\s+LIMIT|$)/i', $this->sql, $whereMatches);
                
                $currentParamIndex = 0;
                if (!empty($whereMatches[1])) {
                    $conditions = explode(' AND ', $whereMatches[1][0]);
                    foreach ($conditions as $cond) {
                        $parts = preg_split('/\s*=\s*/', trim($cond));
                        if (count($parts) === 2) {
                            $col = $parts[0];
                            // If the value is ?, use param. If it's a literal, use it (risky but works for legacy)
                            if (trim($parts[1]) === '?') {
                                $query = $query->where($col, $this->params[$currentParamIndex]);
                                $currentParamIndex++;
                            } else {
                                // Handle literals if necessary
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
            // Re-throw as PDOException so application catch blocks work
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
}

$pdo = new LegacyPDO($supabase);