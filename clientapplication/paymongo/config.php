<?php

/**
 * ============================================
 * 🔐 PAYMONGO CONFIGURATION
 * ============================================
 */

// You said you want it hardcoded — OK for testing
$PAYMONGO_SECRET_KEY = "sk_test_Z6ynXiE7Ywm2KmTCoKsHZnz2";

// 👉 Add your webhook secret from PayMongo dashboard
$PAYMONGO_WEBHOOK_SECRET = "whsk_j2z67VgiLucpqtkJn6J38ZSF";

/**
 * ============================================
 * 🌐 BASE URL (AUTO DETECT LOCAL / AZURE)
 * ============================================
 */

$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (
    strpos($host, 'localhost') !== false ||
    strpos($host, '127.0.0.1') !== false
);

// Force HTTPS on Azure
$protocol = $isLocal ? "http://" : "https://";

// Base path
$basePath = "/RapidRepair/clientapplication/paymongo";

// Local vs Azure URL
if ($isLocal) {
    $BASE_URL = $protocol . $host . $basePath;
} else {
    // Your Azure domain
    $BASE_URL = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net/clientapplication/paymongo";
}

/**
 * ============================================
 * 🧪 DEBUG MODE (TURN OFF IN PRODUCTION)
 * ============================================
 */

$DEBUG_MODE = true;

if ($DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

/**
 * ============================================
 * 📝 HELPER: LOGGING (FOR WEBHOOK DEBUG)
 * ============================================
 */

function logPaymongo($message, $data = null)
{
    $logFile = __DIR__ . "/webhook.log";

    $log = "[" . date("Y-m-d H:i:s") . "] " . $message;

    if ($data !== null) {
        $log .= " | " . json_encode($data);
    }

    file_put_contents($logFile, $log . PHP_EOL, FILE_APPEND);
}

/**
 * ============================================
 * 🔐 VERIFY WEBHOOK SIGNATURE
 * ============================================
 */

function verifyPaymongoSignature($payload, $signatureHeader, $secret)
{
    if (!$signatureHeader || !$secret) {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $segment) {
        $kv = explode('=', trim($segment), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    if (!isset($parts['t']) || !isset($parts['te'])) {
        return false;
    }

    $signedPayload = $parts['t'] . "." . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

    return hash_equals($expectedSignature, $parts['te']);
}