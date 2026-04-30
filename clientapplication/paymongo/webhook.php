<?php
require_once "config.php";
include __DIR__ . "/../../db.php";

header("Content-Type: application/json");

$payload = file_get_contents("php://input");
$signatureHeader = $_SERVER["HTTP_PAYMONGO_SIGNATURE"] ?? "";

function logWebhook($message, $data = null)
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message;

    if ($data !== null) {
        $line .= " | " . json_encode($data);
    }

    file_put_contents(__DIR__ . "/webhook.log", $line . PHP_EOL, FILE_APPEND);
}

function verifyPayMongoSignature($payload, $signatureHeader, $secret)
{
    if ($secret === "" || $signatureHeader === "") {
        return false;
    }

    $parts = [];

    foreach (explode(",", $signatureHeader) as $segment) {
        $kv = explode("=", trim($segment), 2);

        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    if (!isset($parts["t"]) || !isset($parts["v1"])) {
        return false;
    }

    $signedPayload = $parts["t"] . "." . $payload;
    $expectedSignature = hash_hmac("sha256", $signedPayload, $secret);

    return hash_equals($expectedSignature, $parts["v1"]);
}

if (!verifyPayMongoSignature($payload, $signatureHeader, $PAYMONGO_WEBHOOK_SECRET)) {
    logWebhook("Invalid signature");
    http_response_code(400);
    echo json_encode(["error" => "Invalid signature"]);
    exit;
}

$event = json_decode($payload, true);

if (!$event) {
    logWebhook("Invalid JSON");
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

logWebhook("Webhook received", $event);

$eventType = $event["data"]["attributes"]["type"] ?? "";

if ($eventType !== "checkout_session.payment.paid") {
    http_response_code(200);
    echo json_encode(["message" => "Event ignored"]);
    exit;
}

$checkoutData = $event["data"]["attributes"]["data"] ?? [];
$checkoutSessionId = $checkoutData["id"] ?? null;
$checkoutAttributes = $checkoutData["attributes"] ?? [];

if (!$checkoutSessionId) {
    logWebhook("Missing checkout session ID");
    http_response_code(400);
    echo json_encode(["error" => "Missing checkout session ID"]);
    exit;
}

$payment = $checkoutAttributes["payments"][0] ?? null;

$paymongoPaymentId = $payment["id"] ?? null;
$paymentAttributes = $payment["attributes"] ?? [];

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
} elseif ($rawMethod === "card") {
    $paymentMethod = "Card";
}

$transactionReference = $paymongoPaymentId ?: $checkoutSessionId;
$gcashReference = ($paymentMethod === "GCash") ? $transactionReference : null;

$rawPayload = json_encode($event);

$updateStmt = mysqli_prepare($conn, "
    UPDATE subscription_payments
    SET
        payment_status = 'Paid',
        payment_method = ?,
        transaction_reference = ?,
        gcash_reference = ?,
        paymongo_payment_id = ?,
        paid_at = NOW(),
        raw_payload = ?,
        updated_at = NOW()
    WHERE checkout_session_id = ?
    LIMIT 1
");

if (!$updateStmt) {
    logWebhook("Prepare failed", mysqli_error($conn));
    http_response_code(500);
    echo json_encode(["error" => "Database prepare failed"]);
    exit;
}

mysqli_stmt_bind_param(
    $updateStmt,
    "ssssss",
    $paymentMethod,
    $transactionReference,
    $gcashReference,
    $paymongoPaymentId,
    $rawPayload,
    $checkoutSessionId
);

mysqli_stmt_execute($updateStmt);

if (mysqli_stmt_affected_rows($updateStmt) <= 0) {
    logWebhook("No matching payment found", [
        "checkout_session_id" => $checkoutSessionId,
        "paymongo_payment_id" => $paymongoPaymentId
    ]);
}

mysqli_stmt_close($updateStmt);

http_response_code(200);
echo json_encode(["status" => "success"]);