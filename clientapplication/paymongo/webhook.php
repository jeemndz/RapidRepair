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
    if (!$signatureHeader || !$secret) {
        return false;
    }

    $parts = [];

    foreach (explode(",", $signatureHeader) as $segment) {
        $kv = explode("=", trim($segment), 2);

        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    $signature = $parts["v1"] ?? $parts["te"] ?? null;

    if (!isset($parts["t"]) || !$signature) {
        return false;
    }

    $signedPayload = $parts["t"] . "." . $payload;
    $expected = hash_hmac("sha256", $signedPayload, $secret);

    return hash_equals($expected, $signature);
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

$eventType = $event["data"]["attributes"]["type"] ?? "";
logWebhook("Event received", ["event_type" => $eventType]);

if ($eventType !== "checkout_session.payment.paid") {
    http_response_code(200);
    echo json_encode(["message" => "Ignored"]);
    exit;
}

$checkoutData = $event["data"]["attributes"]["data"] ?? [];
$checkoutSessionId = $checkoutData["id"] ?? null;
$checkoutAttributes = $checkoutData["attributes"] ?? [];

if (!$checkoutSessionId) {
    logWebhook("Missing checkout_session_id");
    http_response_code(400);
    echo json_encode(["error" => "Missing checkout_session_id"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Local Payment ID from description
|--------------------------------------------------------------------------
| Example:
| Tenant ID: 1 | Payment ID: 5
*/
$description = $checkoutAttributes["description"] ?? "";

preg_match('/Payment ID:\s*(\d+)/', $description, $matches);
$localPaymentId = isset($matches[1]) ? (int) $matches[1] : 0;

if ($localPaymentId <= 0) {
    logWebhook("Missing local Payment ID", [
        "description" => $description,
        "checkout_session_id" => $checkoutSessionId
    ]);

    http_response_code(400);
    echo json_encode(["error" => "Missing local Payment ID"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get PayMongo Payment Info
|--------------------------------------------------------------------------
*/
$payment = $checkoutAttributes["payments"][0] ?? [];
$paymentAttributes = $payment["attributes"] ?? [];

$paymongoPaymentId = $payment["id"] ?? null;

$rawMethod = $paymentAttributes["source"]["type"]
    ?? $paymentAttributes["payment_method"]["type"]
    ?? $checkoutAttributes["payment_method_used"]
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

logWebhook("Matching payment", [
    "local_payment_id" => $localPaymentId,
    "checkout_session_id" => $checkoutSessionId,
    "paymongo_payment_id" => $paymongoPaymentId,
    "payment_method" => $paymentMethod
]);

mysqli_begin_transaction($conn);

try {
    /*
    |--------------------------------------------------------------------------
    | 1. Update subscription_payments using local payment_id
    |--------------------------------------------------------------------------
    */
    $stmt = mysqli_prepare($conn, "
        UPDATE subscription_payments
        SET 
            payment_status = 'Paid',
            payment_method = ?,
            checkout_session_id = ?,
            paymongo_payment_id = ?,
            transaction_reference = ?,
            gcash_reference = ?,
            paid_at = NOW(),
            updated_at = NOW()
        WHERE payment_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Payment update prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssssi",
        $paymentMethod,
        $checkoutSessionId,
        $paymongoPaymentId,
        $transactionReference,
        $gcashReference,
        $localPaymentId
    );

    mysqli_stmt_execute($stmt);
    $affectedPaymentRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affectedPaymentRows <= 0) {
        logWebhook("No payment row updated", [
            "local_payment_id" => $localPaymentId,
            "checkout_session_id" => $checkoutSessionId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Activate related subscription
    |--------------------------------------------------------------------------
    */
    $stmt2 = mysqli_prepare($conn, "
        UPDATE subscriptions s
        INNER JOIN subscription_payments p 
            ON s.subscription_id = p.subscription_id
        SET 
            s.status = 'active',
            s.updated_at = NOW()
        WHERE p.payment_id = ?
        LIMIT 1
    ");

    if (!$stmt2) {
        throw new Exception("Subscription update prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt2, "i", $localPaymentId);
    mysqli_stmt_execute($stmt2);
    $affectedSubRows = mysqli_stmt_affected_rows($stmt2);
    mysqli_stmt_close($stmt2);

    mysqli_commit($conn);

    logWebhook("Payment webhook processed", [
        "local_payment_id" => $localPaymentId,
        "payment_rows_affected" => $affectedPaymentRows,
        "subscription_rows_affected" => $affectedSubRows
    ]);

} catch (Throwable $e) {
    mysqli_rollback($conn);

    logWebhook("DB ERROR", [
        "message" => $e->getMessage(),
        "local_payment_id" => $localPaymentId
    ]);

    http_response_code(500);
    echo json_encode(["error" => "Database update failed"]);
    exit;
}

http_response_code(200);
echo json_encode(["status" => "success"]);