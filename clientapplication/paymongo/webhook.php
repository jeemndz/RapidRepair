<?php
require_once "config.php";
include __DIR__ . "/../../db.php";

header("Content-Type: application/json");

$payload = file_get_contents("php://input");
$signatureHeader = $_SERVER["HTTP_PAYMONGO_SIGNATURE"] ?? "";

function logWebhook($message, $data = null) {
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message;
    if ($data !== null) $line .= " | " . json_encode($data);
    file_put_contents(__DIR__ . "/webhook.log", $line . PHP_EOL, FILE_APPEND);
}

function verifyPayMongoSignature($payload, $signatureHeader, $secret) {
    if (!$signatureHeader || !$secret) return false;

    $parts = [];
    foreach (explode(",", $signatureHeader) as $segment) {
        $kv = explode("=", trim($segment), 2);
        if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
    }

    $signature = $parts["v1"] ?? $parts["te"] ?? null;
    if (!isset($parts["t"]) || !$signature) return false;

    $expected = hash_hmac("sha256", $parts["t"] . "." . $payload, $secret);
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

logWebhook("Raw webhook received", $event);

/*
|--------------------------------------------------------------------------
| Support both payload shapes:
| 1. Event wrapper:
|    data.attributes.type = checkout_session.payment.paid
|    data.attributes.data = checkout_session object
|
| 2. Direct checkout_session object:
|    id = cs_xxx
|    type = checkout_session
|--------------------------------------------------------------------------
*/

$eventType = $event["data"]["attributes"]["type"] ?? null;

if ($eventType === "checkout_session.payment.paid") {
    $checkoutData = $event["data"]["attributes"]["data"] ?? [];
} elseif (($event["type"] ?? "") === "checkout_session") {
    $checkoutData = $event;
    $eventType = "checkout_session.payment.paid";
} else {
    logWebhook("Ignored event", ["event_type" => $eventType]);
    http_response_code(200);
    echo json_encode(["message" => "Ignored"]);
    exit;
}

$checkoutSessionId = $checkoutData["id"] ?? null;
$checkoutAttributes = $checkoutData["attributes"] ?? [];

if (!$checkoutSessionId) {
    logWebhook("Missing checkout session ID");
    http_response_code(400);
    echo json_encode(["error" => "Missing checkout session ID"]);
    exit;
}

$payment = $checkoutAttributes["payments"][0] ?? null;

if (!$payment) {
    logWebhook("No payment object found", [
        "checkout_session_id" => $checkoutSessionId
    ]);
    http_response_code(400);
    echo json_encode(["error" => "No payment found"]);
    exit;
}

$paymentAttributes = $payment["attributes"] ?? [];
$paymentStatus = $paymentAttributes["status"] ?? "";

if ($paymentStatus !== "paid") {
    logWebhook("Payment not paid", [
        "checkout_session_id" => $checkoutSessionId,
        "payment_status" => $paymentStatus
    ]);
    http_response_code(200);
    echo json_encode(["message" => "Payment not paid"]);
    exit;
}

$description = $checkoutAttributes["description"] ?? $paymentAttributes["description"] ?? "";

preg_match('/Tenant ID:\s*(\d+)/', $description, $tenantMatch);
preg_match('/Plan ID:\s*(\d+)/', $description, $planMatch);
preg_match('/Billing Cycle:\s*(monthly|quarterly|yearly)/', $description, $cycleMatch);

$tenantId = (int)($tenantMatch[1] ?? 0);
$planId = (int)($planMatch[1] ?? 0);
$billingCycle = $cycleMatch[1] ?? "monthly";

if ($tenantId <= 0) {
    logWebhook("Missing tenant ID from description", [
        "description" => $description
    ]);
    http_response_code(400);
    echo json_encode(["error" => "Missing tenant ID"]);
    exit;
}

$paymongoPaymentId = $payment["id"] ?? null;
$amountCentavos = (int)($paymentAttributes["amount"] ?? 0);
$amount = $amountCentavos / 100;

$rawMethod = $paymentAttributes["source"]["type"]
    ?? $paymentAttributes["payment_method"]["type"]
    ?? $checkoutAttributes["payment_method_used"]
    ?? "card";

$paymentMethod = "Card";
if ($rawMethod === "gcash") $paymentMethod = "GCash";
elseif ($rawMethod === "paymaya") $paymentMethod = "PayMaya";
elseif ($rawMethod === "grab_pay") $paymentMethod = "GrabPay";
elseif ($rawMethod === "qrph") $paymentMethod = "QRPH";
elseif ($rawMethod === "dob") $paymentMethod = "Bank Transfer";

$transactionReference = $paymongoPaymentId ?: $checkoutSessionId;
$gcashReference = ($paymentMethod === "GCash") ? $transactionReference : null;

$startDate = date("Y-m-d");

if ($billingCycle === "quarterly") {
    $endDate = date("Y-m-d", strtotime("+3 months"));
} elseif ($billingCycle === "yearly") {
    $endDate = date("Y-m-d", strtotime("+1 year"));
} else {
    $endDate = date("Y-m-d", strtotime("+1 month"));
}

$nextBillingDate = $endDate;

mysqli_begin_transaction($conn);

try {
    $checkStmt = mysqli_prepare($conn, "
        SELECT payment_id
        FROM subscription_payments
        WHERE checkout_session_id = ?
           OR paymongo_payment_id = ?
           OR transaction_reference = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param(
        $checkStmt,
        "sss",
        $checkoutSessionId,
        $paymongoPaymentId,
        $transactionReference
    );

    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $existing = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
    mysqli_stmt_close($checkStmt);

    if ($existing) {
        mysqli_commit($conn);
        logWebhook("Payment already saved", $existing);
        http_response_code(200);
        echo json_encode(["status" => "already_saved"]);
        exit;
    }

    $subStmt = mysqli_prepare($conn, "
        INSERT INTO subscriptions (
            tenantID,
            plan_id,
            billing_cycle,
            start_date,
            end_date,
            next_billing_date,
            amount,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");

    if (!$subStmt) {
        throw new Exception("Subscription prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $subStmt,
        "iissssd",
        $tenantId,
        $planId,
        $billingCycle,
        $startDate,
        $endDate,
        $nextBillingDate,
        $amount
    );

    mysqli_stmt_execute($subStmt);
    $subscriptionId = mysqli_insert_id($conn);
    mysqli_stmt_close($subStmt);

    $paymentStmt = mysqli_prepare($conn, "
        INSERT INTO subscription_payments (
            tenantID,
            subscription_id,
            plan_id,
            amount,
            payment_method,
            payment_status,
            checkout_session_id,
            paymongo_payment_id,
            transaction_reference,
            gcash_reference,
            billing_period_start,
            billing_period_end,
            paid_at,
            next_billing_date,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 'Paid', ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())
    ");

    if (!$paymentStmt) {
        throw new Exception("Payment prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $paymentStmt,
        "iiidssssssss",
        $tenantId,
        $subscriptionId,
        $planId,
        $amount,
        $paymentMethod,
        $checkoutSessionId,
        $paymongoPaymentId,
        $transactionReference,
        $gcashReference,
        $startDate,
        $endDate,
        $nextBillingDate
    );

    mysqli_stmt_execute($paymentStmt);
    $paymentId = mysqli_insert_id($conn);
    mysqli_stmt_close($paymentStmt);

    mysqli_commit($conn);

    logWebhook("Payment saved as Paid", [
        "payment_id" => $paymentId,
        "subscription_id" => $subscriptionId,
        "tenant_id" => $tenantId,
        "plan_id" => $planId,
        "method" => $paymentMethod,
        "checkout_session_id" => $checkoutSessionId,
        "paymongo_payment_id" => $paymongoPaymentId
    ]);

    http_response_code(200);
    echo json_encode(["status" => "success"]);
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);

    logWebhook("DB ERROR", [
        "message" => $e->getMessage(),
        "checkout_session_id" => $checkoutSessionId,
        "paymongo_payment_id" => $paymongoPaymentId
    ]);

    http_response_code(500);
    echo json_encode(["error" => "Database save failed"]);
    exit;
}