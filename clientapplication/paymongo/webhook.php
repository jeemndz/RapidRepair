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

$description = $checkoutAttributes["description"] ?? "";

preg_match('/Tenant ID:\s*(\d+)/', $description, $tenantMatch);
preg_match('/Plan ID:\s*(\d+)/', $description, $planMatch);
preg_match('/Billing Cycle:\s*(monthly|quarterly|yearly)/', $description, $cycleMatch);

$tenantId = isset($tenantMatch[1]) ? intval($tenantMatch[1]) : 0;
$planId = isset($planMatch[1]) ? intval($planMatch[1]) : 0;
$billingCycle = $cycleMatch[1] ?? "monthly";

if ($tenantId <= 0) {
    logWebhook("Missing tenant ID", ["description" => $description]);
    http_response_code(400);
    echo json_encode(["error" => "Missing tenant ID"]);
    exit;
}

$payment = $checkoutAttributes["payments"][0] ?? [];
$paymentAttributes = $payment["attributes"] ?? [];

$paymongoPaymentId = $payment["id"] ?? null;

$amountCentavos = intval($paymentAttributes["amount"] ?? ($checkoutAttributes["line_items"][0]["amount"] ?? 0));
$amount = $amountCentavos / 100;

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
}

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
    $existingPayment = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
    mysqli_stmt_close($checkStmt);

    if ($existingPayment) {
        mysqli_commit($conn);

        logWebhook("Payment already exists", [
            "checkout_session_id" => $checkoutSessionId,
            "paymongo_payment_id" => $paymongoPaymentId
        ]);

        http_response_code(200);
        echo json_encode(["status" => "already_exists"]);
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
        "payment_method" => $paymentMethod,
        "checkout_session_id" => $checkoutSessionId,
        "paymongo_payment_id" => $paymongoPaymentId
    ]);

} catch (Throwable $e) {
    mysqli_rollback($conn);

    logWebhook("DB ERROR", [
        "message" => $e->getMessage(),
        "checkout_session_id" => $checkoutSessionId,
        "paymongo_payment_id" => $paymongoPaymentId
    ]);

    http_response_code(500);
    echo json_encode(["error" => "Database update failed"]);
    exit;
}

http_response_code(200);
echo json_encode(["status" => "success"]);