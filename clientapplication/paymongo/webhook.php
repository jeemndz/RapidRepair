<?php
require_once "config.php";

$payload = file_get_contents("php://input");
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

function verifySignature($payload, $signatureHeader, $secret)
{
    if (!$signatureHeader) return false;

    $parts = [];
    foreach (explode(',', $signatureHeader) as $item) {
        list($k, $v) = explode('=', $item, 2);
        $parts[$k] = $v;
    }

    if (!isset($parts['t']) || !isset($parts['v1'])) {
        return false;
    }

    $signedPayload = $parts['t'] . "." . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

    return hash_equals($expectedSignature, $parts['v1']);
}

// 🔒 VERIFY REQUEST
if (!verifySignature($payload, $signature, $PAYMONGO_WEBHOOK_SECRET)) {
    http_response_code(400);
    echo "Invalid signature";
    exit;
}

// ✅ VALID PAYMONGO EVENT
$event = json_decode($payload, true);

file_put_contents("webhook.log", date("Y-m-d H:i:s") . " " . $payload . PHP_EOL, FILE_APPEND);

$type = $event["data"]["attributes"]["type"] ?? null;

if ($type === "payment.paid") {
    // ✅ SUCCESS PAYMENT
    // update DB
    // create tenant
}

if ($type === "payment.failed") {
    // ❌ FAILED PAYMENT
    // update DB
}

http_response_code(200);
echo "OK";