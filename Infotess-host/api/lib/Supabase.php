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

    // ==========================================
    // Storage API (file uploads to Supabase buckets)
    // ==========================================

    /**
     * Upload a file to a Supabase Storage bucket.
     *
     * @param string $bucket    Bucket name (e.g. 'profiles', 'executives', 'gallery')
     * @param string $path      File path within bucket (e.g. 'student_123_1712345678.jpg')
     * @param string $fileData  Raw binary file contents
     * @param string $contentType MIME type of the file
     * @return array            Decoded JSON response from Supabase
     * @throws Exception        On upload failure
     */
    public function uploadFile(string $bucket, string $path, string $fileData, string $contentType = 'application/octet-stream'): array {
        $url = "$this->url/storage/v1/object/$bucket/$path";
        $ch = curl_init();
        $headers = [
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}",
            "Content-Type: $contentType"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $fileData,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Supabase Storage curl error: " . ($curlError ?: 'unknown'));
        }

        if ($httpCode >= 400) {
            throw new Exception("Supabase Storage Upload Error (HTTP $httpCode): $response");
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Get the public URL for a file in a Supabase Storage bucket.
     *
     * @param string $bucket  Bucket name
     * @param string $path    File path within bucket
     * @return string         Full public URL
     */
    public function getPublicUrl(string $bucket, string $path): string {
        return "$this->url/storage/v1/object/public/$bucket/$path";
    }

    /**
     * Delete a file from a Supabase Storage bucket.
     *
     * @param string $bucket  Bucket name
     * @param string $path    File path within bucket
     * @return array          Decoded JSON response
     * @throws Exception      On deletion failure
     */
    public function deleteFile(string $bucket, string $path): array {
        $url = "$this->url/storage/v1/object/$bucket/$path";
        $ch = curl_init();
        $headers = [
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 && $httpCode !== 404) {
            throw new Exception("Supabase Storage Delete Error: $httpCode - $response");
        }

        return json_decode($response, true) ?: [];
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
        // URL-encode value so that spaces and special chars are handled correctly
        $newClient->filters[] = "$column=eq." . rawurlencode($value);
        return $newClient;
    }

    public function in($column, $values) {
        $newClient = clone $this;
        // PostgREST uses in.(val1,val2,val3) syntax
        $valStr = implode(',', array_map('rawurlencode', (array)$values));
        $newClient->filters[] = "$column=in.($valStr)";
        return $newClient;
    }

    public function like($column, $pattern) {
        $newClient = clone $this;
        // URL-encode so that spaces and special chars are handled correctly;
        // rawurlencode preserves the % wildcard by encoding it as %25,
        // which PostgREST decodes back to % before applying the LIKE filter
        $newClient->filters[] = "$column=like." . rawurlencode($pattern);
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
