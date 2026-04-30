<?php
header("Content-Type: application/json");

require_once "config.php";
include __DIR__ . "/../../db.php";

$payload = file_get_contents("php://input");

function webhookLog($message, $data = null)
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message;

    if ($data !== null) {
        $line .= " | " . json_encode($data);
    }

    file_put_contents(__DIR__ . "/webhook.log", $line . PHP_EOL, FILE_APPEND);
}

webhookLog("Webhook received", json_decode($payload, true));

try {
    $event = json_decode($payload, true);

    if (!$event) {
        webhookLog("Invalid JSON");
        http_response_code(200);
        echo json_encode(["status" => "invalid_json"]);
        exit;
    }

    $eventType = $event["data"]["attributes"]["type"] ?? "";

    if ($eventType !== "checkout_session.payment.paid") {
        webhookLog("Ignored event", ["event_type" => $eventType]);
        http_response_code(200);
        echo json_encode(["status" => "ignored"]);
        exit;
    }

    $checkoutData = $event["data"]["attributes"]["data"] ?? [];
    $checkoutSessionId = $checkoutData["id"] ?? "";
    $checkoutAttributes = $checkoutData["attributes"] ?? [];

    $description = $checkoutAttributes["description"] ?? "";

    preg_match('/Tenant ID:\s*(\d+)/', $description, $tenantMatch);
    preg_match('/Plan ID:\s*(\d+)/', $description, $planMatch);
    preg_match('/Billing Cycle:\s*(monthly|quarterly|yearly)/', $description, $cycleMatch);

    $tenantId = (int)($tenantMatch[1] ?? 0);
    $tenantIdString = (string)$tenantId;
    $planId = (int)($planMatch[1] ?? 0);
    $billingCycle = $cycleMatch[1] ?? "monthly";

    if ($tenantId <= 0) {
        webhookLog("Missing tenant ID", ["description" => $description]);
        http_response_code(200);
        echo json_encode(["status" => "missing_tenant_id"]);
        exit;
    }

    $payment = $checkoutAttributes["payments"][0] ?? [];
    $paymentAttributes = $payment["attributes"] ?? [];

    $paymongoPaymentId = $payment["id"] ?? "";
    $paymentStatus = $paymentAttributes["status"] ?? "";
    $amountCentavos = (int)($paymentAttributes["amount"] ?? 0);
    $amount = $amountCentavos / 100;

    if ($paymentStatus !== "paid") {
        webhookLog("Payment not paid", ["status" => $paymentStatus]);
        http_response_code(200);
        echo json_encode(["status" => "not_paid"]);
        exit;
    }

    $rawMethod = $paymentAttributes["source"]["type"]
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

    $paymentProvider = "PayMongo";
    $transactionReference = $paymongoPaymentId ?: $checkoutSessionId;
    $gcashReference = ($paymentMethod === "GCash") ? $transactionReference : null;
    $rawPayload = $payload;

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

    $checkStmt = mysqli_prepare($conn, "
        SELECT payment_id
        FROM subscription_payments
        WHERE checkout_session_id = ?
           OR paymongo_payment_id = ?
           OR transaction_reference = ?
        LIMIT 1
    ");

    if (!$checkStmt) {
        throw new Exception("Check prepare failed: " . mysqli_error($conn));
    }

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

        webhookLog("Payment already exists", $existing);

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
        "sissssd",
        $tenantIdString,
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
            payment_provider,
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
            raw_payload,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'Paid', ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW())
    ");

    if (!$paymentStmt) {
        throw new Exception("Payment prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $paymentStmt,
        "iiidssssssssss",
        $tenantId,
        $subscriptionId,
        $planId,
        $amount,
        $paymentProvider,
        $paymentMethod,
        $checkoutSessionId,
        $paymongoPaymentId,
        $transactionReference,
        $gcashReference,
        $startDate,
        $endDate,
        $nextBillingDate,
        $rawPayload
    );

    mysqli_stmt_execute($paymentStmt);
    $paymentId = mysqli_insert_id($conn);
    mysqli_stmt_close($paymentStmt);

    mysqli_commit($conn);

    webhookLog("Payment inserted as Paid", [
        "payment_id" => $paymentId,
        "subscription_id" => $subscriptionId,
        "tenant_id" => $tenantId,
        "plan_id" => $planId,
        "amount" => $amount,
        "payment_method" => $paymentMethod,
        "checkout_session_id" => $checkoutSessionId,
        "paymongo_payment_id" => $paymongoPaymentId
    ]);

    http_response_code(200);
    echo json_encode(["status" => "success"]);
    exit;

} catch (Throwable $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }

    webhookLog("DB ERROR", [
        "message" => $e->getMessage(),
        "mysql_error" => isset($conn) ? mysqli_error($conn) : "No connection"
    ]);

    http_response_code(200);
    echo json_encode([
        "status" => "db_error",
        "message" => $e->getMessage()
    ]);
    exit;
}