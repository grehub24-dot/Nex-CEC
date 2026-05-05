<?php
// api/test.php
// Simple diagnostic page

phpinfo();

echo "<br><br><hr><br>";
echo "<h2>Environment Variables Check:</h2>";
echo "SUPABASE_URL: " . (getenv('SUPABASE_URL') ? "✅ SET" : "❌ MISSING") . "<br>";
echo "SUPABASE_ANON_KEY: " . (getenv('SUPABASE_ANON_KEY') ? "✅ SET" : "❌ MISSING") . "<br>";
echo "SUPABASE_SERVICE_ROLE_KEY: " . (getenv('SUPABASE_SERVICE_ROLE_KEY') ? "✅ SET" : "❌ MISSING") . "<br>";

echo "<br><h2>Database Connection Test:</h2>";
try {
    require_once __DIR__ . '/../lib/Supabase.php';
    $supabase = new SupabaseClient();
    $tables = $supabase->table('users')->select('id')->limit(1)->get();
    echo "✅ Supabase Client Loaded. Connection appears to work.<br>";
    echo "Users found: " . count($tables) . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
