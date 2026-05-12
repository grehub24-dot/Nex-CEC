<?php
// includes/SessionHandler.php
// Database-backed session handler using Supabase (PostgREST)
// Replaces unreliable file-based /tmp sessions on Vercel serverless

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $url;
    private $key;
    private $table = 'sessions';

    public function __construct() {
        $this->url = rtrim(getenv('SUPABASE_URL'), '/');
        $this->key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_ANON_KEY');
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($sessionId): string {
        $result = $this->request('GET', "/rest/v1/{$this->table}?select=data&id=eq." . rawurlencode($sessionId) . "&limit=1");
        if (is_array($result) && isset($result[0]['data'])) {
            return $result[0]['data'];
        }
        return '';
    }

    public function write($sessionId, $data): bool {
        $payload = [
            'id' => $sessionId,
            'data' => $data,
            'last_accessed' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $this->request('POST', "/rest/v1/{$this->table}?on_conflict=id", $payload, [
            'Prefer: resolution=merge-duplicates',
            'Prefer: return=minimal',
        ]);
        return true;
    }

    public function destroy($sessionId): bool {
        $this->request('DELETE', "/rest/v1/{$this->table}?id=eq." . rawurlencode($sessionId));
        return true;
    }

    public function gc($maxLifetime): int {
        $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - $maxLifetime);
        $result = $this->request('DELETE', "/rest/v1/{$this->table}?last_accessed=lt." . rawurlencode($cutoff), null, [
            'Prefer: return=representation',
        ]);
        $count = is_array($result) ? count($result) : 0;
        return $count;
    }

    /**
     * Make a direct HTTP request to the Supabase REST API.
     */
    private function request($method, $path, $data = null, $extraHeaders = []) {
        $url = $this->url . $path;
        $headers = array_merge([
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}",
            "Content-Type: application/json",
        ], $extraHeaders);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($data !== null && in_array($method, ['POST', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            error_log("SessionHandler Supabase Error [{$httpCode}]: " . substr($response, 0, 500));
            return null;
        }

        // DELETE with return=representation may return empty body (204 No Content)
        if ($method === 'DELETE' && $httpCode === 204) {
            return [];
        }

        if ($response === false || trim($response) === '') {
            return null;
        }

        return json_decode($response, true);
    }
}
