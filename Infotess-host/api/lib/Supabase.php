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
    /**
     * Create a new Storage bucket (idempotent — safe to call if it already exists).
     *
     * @param string $bucket Bucket name (e.g. 'profiles')
     * @param bool   $public Whether the bucket should be publicly readable (default true)
     * @return array         Decoded JSON response
     * @throws Exception     On creation failure (other than conflict)
     */
    public function createBucket(string $bucket, bool $public = true): array {
        $url = "$this->url/storage/v1/bucket";
        $body = json_encode([
            'name' => $bucket,
            'public' => $public,
            'file_size_limit' => 2 * 1024 * 1024, // 2 MB
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
        ]);

        $ch = curl_init();
        $headers = [
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}",
            "Content-Type: application/json"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Supabase Storage createBucket curl error: " . ($curlError ?: 'unknown'));
        }

        // 409 Conflict means the bucket already exists — that's fine
        if ($httpCode >= 400 && $httpCode !== 409) {
            throw new Exception("Supabase Storage createBucket Error (HTTP $httpCode): $response");
        }

        return json_decode($response, true) ?: [];
    }

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

    public function offset($count) {
        $newClient = clone $this;
        $newClient->filters[] = "offset=$count";
        return $newClient;
    }

    public function insert($data, array $options = []) {
        if (!$this->table) throw new Exception("Table not set.");
        $url = "$this->url/rest/v1/{$this->table}";

        // Support ON CONFLICT via PostgREST query parameter
        if (!empty($options['on_conflict'])) {
            $url .= '?on_conflict=' . urlencode($options['on_conflict']);
        }

        $preferHeader = "Prefer: return=representation";
        if (!empty($options['ignore_duplicates'])) {
            $preferHeader .= ",resolution=ignore-duplicates";
        }

        return $this->request('POST', $url, $data, [$preferHeader]);
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

    /**
     * Execute raw SQL via Supabase.
     *
     * Tries two approaches in order:
     *   1. Management API (https://api.supabase.com/v1/projects/{ref}/sql) — requires SUPABASE_PAT
     *   2. Direct SQL endpoint (https://{ref}.supabase.co/sql) — deprecated on newer projects
     *
     * Designed for DDL (ALTER TABLE, etc.) that cannot be run through PostgREST.
     * Throws a clear error with manual SQL instructions if both approaches fail.
     *
     * @param string $sql Raw SQL statement to execute
     * @return array      Decoded JSON response
     * @throws Exception  On failure (with instructions to run SQL manually)
     */
    public function executeSql(string $sql): array {
        $pat = getenv('SUPABASE_PAT');

        // Approach 1: Management API (requires PAT)
        if (!empty($pat)) {
            try {
                // Extract project ref from the Supabase URL
                $parsedUrl = parse_url($this->url);
                $host = $parsedUrl['host'] ?? '';
                $ref = str_replace('.supabase.co', '', $host);

                $mgmtUrl = "https://api.supabase.com/v1/projects/$ref/sql";
                $mgmtBody = json_encode(['query' => $sql]);

                $ch = curl_init();
                $mgmtHeaders = [
                    "Authorization: Bearer $pat",
                    "Content-Type: application/json"
                ];

                curl_setopt_array($ch, [
                    CURLOPT_URL => $mgmtUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_HTTPHEADER => $mgmtHeaders,
                    CURLOPT_POSTFIELDS => $mgmtBody,
                    CURLOPT_SSL_VERIFYPEER => true
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response !== false && $httpCode < 400) {
                    return json_decode($response, true) ?: [];
                }

                error_log("Supabase Management API Error (HTTP $httpCode): $response");
            } catch (Exception $e) {
                error_log("Supabase Management API exception: " . $e->getMessage());
            }
        }

        // Approach 2: Direct SQL endpoint (deprecated on newer Supabase projects)
        try {
            $url = "$this->url/sql";
            $body = json_encode(['query' => $sql]);

            $ch = curl_init();
            $headers = [
                "apikey: {$this->key}",
                "Authorization: Bearer {$this->key}",
                "Content-Type: application/json"
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode < 400) {
                return json_decode($response, true) ?: [];
            }

            error_log("Supabase SQL endpoint Error (HTTP $httpCode): " . ($response ?: $curlError));
        } catch (Exception $e) {
            error_log("Supabase SQL endpoint exception: " . $e->getMessage());
        }

        // Both approaches failed — provide clear manual instructions
        $parsedUrl = parse_url($this->url);
        $host = $parsedUrl['host'] ?? 'your-project.supabase.co';
        $projectRef = str_replace('.supabase.co', '', $host);

        $message = "Cannot execute SQL automatically. ";
        if (empty($pat)) {
            $message .= "To enable automatic SQL execution, add a Supabase Personal Access Token (SUPABASE_PAT) to your .env file. ";
            $message .= "Generate one at: https://supabase.com/dashboard/account/tokens\n";
        }
        $message .= "Otherwise, run this SQL manually in your Supabase Dashboard SQL Editor:\n";
        $message .= "  URL: https://supabase.com/dashboard/project/$projectRef/sql/new\n";
        $message .= "  SQL: $sql";

        throw new Exception($message);
    }

    private function request($method, $url, $data = null, array $customHeaders = []) {
        $ch = curl_init();
        $headers = $customHeaders ?: [
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ];
        // Ensure required auth headers are always present
        if (!in_array("apikey: {$this->key}", $headers)) {
            array_unshift($headers, "apikey: {$this->key}", "Authorization: Bearer {$this->key}");
        }
        if (!in_array("Content-Type: application/json", $headers)) {
            $headers[] = "Content-Type: application/json";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        // Send JSON body for POST/PATCH only when data is provided.
        // Use array() !== null check so that [] (empty array) is still sent
        // (PostgREST rejects empty-body requests with PGRST102).
        if ($data !== null && in_array($method, ['POST', 'PATCH'])) {
            $encoded = json_encode($data);
            if ($encoded === false) {
                throw new Exception("Supabase API Error: Failed to encode request data as JSON.");
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
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
