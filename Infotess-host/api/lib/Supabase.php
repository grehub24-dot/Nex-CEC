<?php
// lib/Supabase.php

class SupabaseClient {
    private $url;
    private $key;
    private $table;
    private $filters = [];
    private $columns = '*';

    public function __construct() {
        $this->url = rtrim(getenv('SUPABASE_URL'), '/');
        // Use Service Role Key for backend operations (bypasses RLS)
        // Use ANON Key for frontend operations (respects RLS)
        $this->key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_ANON_KEY');
    }

    public function table($table) {
        $newClient = clone $this;
        $newClient->table = $table;
        return $newClient;
    }

    public function select($columns = '*') {
        $newClient = clone $this;
        $newClient->columns = $columns;
        return $newClient;
    }

    public function where($column, $value) {
        $newClient = clone $this;
        $newClient->filters[] = "$column=eq.$value";
        return $newClient;
    }

    public function in($column, $values) {
        $newClient = clone $this;
        // PostgREST uses in.(val1,val2,val3) syntax
        $valStr = implode(',', (array)$values);
        $newClient->filters[] = "$column=in.($valStr)";
        return $newClient;
    }

    public function like($column, $pattern) {
        $newClient = clone $this;
        $newClient->filters[] = "$column=like.$pattern";
        return $newClient;
    }

    public function order($column, $ascending = true) {
        $newClient = clone $this;
        $newClient->filters[] = "order=$column" . ($ascending ? ".asc" : ".desc");
        return $newClient;
    }

    public function get() {
        if (!$this->table) throw new Exception("Table not set.");
        
        $url = "$this->url/rest/v1/{$this->table}?select={$this->columns}";
        if (!empty($this->filters)) {
            $url .= "&" . implode("&", $this->filters);
        }

        return $this->request('GET', $url);
    }

    public function first() {
        $results = $this->limit(1)->get();
        return $results ? $results[0] : null;
    }

    public function limit($count) {
        $newClient = clone $this;
        $newClient->filters[] = "limit=$count";
        return $newClient;
    }

    public function insert($data) {
        if (!$this->table) throw new Exception("Table not set.");
        $url = "$this->url/rest/v1/{$this->table}";
        return $this->request('POST', $url, $data);
    }

    public function update($data) {
        if (!$this->table || empty($this->filters)) throw new Exception("Table and filters required for update.");
        $url = "$this->url/rest/v1/{$this->table}";
        if (!empty($this->filters)) {
            $url .= "?" . implode("&", $this->filters);
        }
        return $this->request('PATCH', $url, $data);
    }

    public function delete() {
        if (!$this->table || empty($this->filters)) throw new Exception("Table and filters required for delete.");
        $url = "$this->url/rest/v1/{$this->table}";
        if (!empty($this->filters)) {
            $url .= "?" . implode("&", $this->filters);
        }
        return $this->request('DELETE', $url);
    }

    private function request($method, $url, $data = null) {
        $ch = curl_init();
        $headers = [
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        if ($data && in_array($method, ['POST', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Supabase API Error: $httpCode - $response");
        }

        return json_decode($response, true);
    }
}
