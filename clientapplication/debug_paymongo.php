<?php
/**
 * Debug script to verify Paymongo credentials are accessible
 * Access via: http://yoursite/clientapplication/debug_paymongo.php
 */

// Try all methods to access environment variables
echo "<h2>Paymongo Credentials Debug</h2>";

echo "<h3>Method 1: getenv()</h3>";
$pk_getenv = getenv('PAYMONGO_PUBLIC_KEY');
$sk_getenv = getenv('PAYMONGO_SECRET_KEY');
echo "PAYMONGO_PUBLIC_KEY: " . ($pk_getenv ? "✓ Found: " . substr($pk_getenv, 0, 10) . "..." : "✗ Not found") . "<br>";
echo "PAYMONGO_SECRET_KEY: " . ($sk_getenv ? "✓ Found: " . substr($sk_getenv, 0, 10) . "..." : "✗ Not found") . "<br>";

echo "<h3>Method 2: \$_ENV</h3>";
$pk_env = $_ENV['PAYMONGO_PUBLIC_KEY'] ?? null;
$sk_env = $_ENV['PAYMONGO_SECRET_KEY'] ?? null;
echo "PAYMONGO_PUBLIC_KEY: " . ($pk_env ? "✓ Found: " . substr($pk_env, 0, 10) . "..." : "✗ Not found") . "<br>";
echo "PAYMONGO_SECRET_KEY: " . ($sk_env ? "✓ Found: " . substr($sk_env, 0, 10) . "..." : "✗ Not found") . "<br>";

echo "<h3>Method 3: \$_SERVER</h3>";
$pk_server = $_SERVER['PAYMONGO_PUBLIC_KEY'] ?? null;
$sk_server = $_SERVER['PAYMONGO_SECRET_KEY'] ?? null;
echo "PAYMONGO_PUBLIC_KEY: " . ($pk_server ? "✓ Found: " . substr($pk_server, 0, 10) . "..." : "✗ Not found") . "<br>";
echo "PAYMONGO_SECRET_KEY: " . ($sk_server ? "✓ Found: " . substr($sk_server, 0, 10) . "..." : "✗ Not found") . "<br>";

echo "<h3>All PAYMONGO keys in \$_SERVER:</h3>";
$paymongo_keys = array_filter($_SERVER, function($key) {
    return strpos($key, 'PAYMONGO') !== false;
}, ARRAY_FILTER_USE_KEY);

if (empty($paymongo_keys)) {
    echo "No PAYMONGO keys found in \$_SERVER<br>";
} else {
    foreach ($paymongo_keys as $key => $value) {
        echo "$key: " . substr($value, 0, 10) . "...<br>";
    }
}

echo "<h3>Test Gateway Initialization:</h3>";
include __DIR__ . "/paymongo_helper.php";
$gateway = initializePaymongoGateway();
if ($gateway) {
    echo "✓ Gateway initialized successfully<br>";
} else {
    echo "✗ Failed to initialize gateway<br>";
}
?>
