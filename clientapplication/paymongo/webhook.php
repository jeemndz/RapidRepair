<?php
require_once "config.php";
include __DIR__ . "/../../db.php";

header("Content-Type: application/json");

// ============================================
// 📥 GET PAYLOAD
// ============================================
$payload = file_get_contents("php://input");
$signatureHeader = $_SERVER["HTTP_PAYMONGO_SIGNATURE"] ?? "";

// ============================================
// 📝 LOGGER
// ============================================
function logWebhook($message, $data = null)
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message;

    if ($data !== null) {
        $line .= " | " . json_encode($data);
    }

    file_put_contents(__DIR__ . "/webhook.log", $line . PHP_EOL, FILE_APPEND);
}

// ============================================
// 🔐 VERIFY SIGNATURE
// ============================================
function verifyPayMongoSignature($payload, $signatureHeader, $secret)
{
    if (!$signatureHeader || !$secret) return false;

    $parts = [];

    foreach (explode(",", $signatureHeader) as $segment) {
        $kv = explode("=", trim($segment), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    $signature = $parts["v1"] ?? $parts["te"] ?? null;

    if (!isset($parts["t"]) || !$signature) return false;

    $signedPayload = $parts["t"] . "." . $payload;
    $expected = hash_hmac("sha256", $signedPayload, $secret);

    return hash_equals($expected, $signature);
}

// ============================================
// ❌ INVALID SIGNATURE
// ============================================
if (!verifyPayMongoSignature($payload, $signatureHeader, $PAYMONGO_WEBHOOK_SECRET)) {
    logWebhook("❌ Invalid signature");
    http_response_code(400);
    echo json_encode(["error" => "Invalid signature"]);
    exit;
}

// ============================================
// 📦 PARSE EVENT
// ============================================
$event = json_decode($payload, true);

if (!$event) {
    logWebhook("❌ Invalid JSON");
    http_response_code(400);
    exit;
}

$eventType = $event["data"]["attributes"]["type"] ?? "";
logWebhook("📩 Event received: " . $eventType);

// ============================================
// ✅ ONLY HANDLE SUCCESS PAYMENT
// ============================================
if ($eventType !== "checkout_session.payment.paid") {
    http_response_code(200);
    echo json_encode(["message" => "Ignored"]);
    exit;
}

// ============================================
// 📌 EXTRACT DATA
// ============================================
$checkoutData = $event["data"]["attributes"]["data"] ?? [];
$checkoutSessionId = $checkoutData["id"] ?? null;
$checkoutAttributes = $checkoutData["attributes"] ?? [];

if (!$checkoutSessionId) {
    logWebhook("❌ Missing checkout_session_id");
    http_response_code(400);
    exit;
}

// Get payment info
$payment = $checkoutAttributes["payments"][0] ?? [];
$paymentAttributes = $payment["attributes"] ?? [];

$paymongoPaymentId = $payment["id"] ?? null;

// Detect method
$rawMethod = $paymentAttributes["source"]["type"]
    ?? $paymentAttributes["payment_method"]["type"]
    ?? "card";

$paymentMethod = "Card";

if ($rawMethod === "gcash") {
    $paymentMethod = "GCash";
} elseif ($rawMethod === "paymaya") {
    $paymentMethod = "PayMaya";
} elseif ($rawMethod === "grab_pay") {
    $paymentMethod = "GrabPay";
} elseif ($rawMethod === "qrph") {
    $paymentMethod = "QRPH";
} elseif ($rawMethod === "dob") {
    $paymentMethod = "Bank Transfer";
}

// References
$transactionReference = $paymongoPaymentId ?: $checkoutSessionId;
$gcashReference = ($paymentMethod === "GCash") ? $transactionReference : null;

// ============================================
// 💾 DATABASE UPDATE
// ============================================
mysqli_begin_transaction($conn);

try {

    // 1️⃣ UPDATE PAYMENT
    $stmt = mysqli_prepare($conn, "
        UPDATE subscription_payments
        SET 
            payment_status = 'Paid',
            payment_method = ?,
            transaction_reference = ?,
            gcash_reference = ?,
            paymongo_payment_id = ?,
            paid_at = NOW(),
            updated_at = NOW()
        WHERE checkout_session_id = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $paymentMethod,
        $transactionReference,
        $gcashReference,
        $paymongoPaymentId,
        $checkoutSessionId
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 2️⃣ ACTIVATE SUBSCRIPTION
    $stmt2 = mysqli_prepare($conn, "
        UPDATE subscriptions s
        INNER JOIN subscription_payments p 
            ON s.subscription_id = p.subscription_id
        SET 
            s.status = 'active',
            s.updated_at = NOW()
        WHERE p.checkout_session_id = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param($stmt2, "s", $checkoutSessionId);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    mysqli_commit($conn);

    logWebhook("✅ Payment updated successfully", [
        "checkout_session_id" => $checkoutSessionId,
        "method" => $paymentMethod
    ]);

} catch (Throwable $e) {
    mysqli_rollback($conn);
    logWebhook("❌ DB ERROR", $e->getMessage());

    http_response_code(500);
    exit;
}

// ============================================
// ✅ RESPONSE
// ============================================
http_response_code(200);
echo json_encode(["status" => "success"]);