<?php
header("Content-Type: application/json");

require_once "config.php";
include __DIR__ . "/../../db.php";

// ============================================
// 📥 GET PAYLOAD
// ============================================
$payload = file_get_contents("php://input");

// ============================================
// 📝 LOGGER
// ============================================
function webhookLog($message, $data = null)
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message;

    if ($data !== null) {
        $line .= " | " . json_encode($data);
    }

    file_put_contents(__DIR__ . "/webhook.log", $line . PHP_EOL, FILE_APPEND);
}

webhookLog("Webhook received", $payload);

// ============================================
// 📦 PARSE JSON
// ============================================
$data = json_decode($payload, true);

if (!$data) {
    webhookLog("Invalid JSON");
    http_response_code(200);
    echo json_encode(["status" => "invalid_json"]);
    exit;
}

// ============================================
// 📌 CHECK EVENT TYPE
// ============================================
$eventType = $data["data"]["attributes"]["type"] ?? "";

if ($eventType !== "checkout_session.payment.paid") {
    webhookLog("Ignored event", ["event_type" => $eventType]);
    http_response_code(200);
    echo json_encode(["status" => "ignored"]);
    exit;
}

// ============================================
// 📌 EXTRACT CHECKOUT DATA
// ============================================
$checkout = $data["data"]["attributes"]["data"] ?? [];
$attributes = $checkout["attributes"] ?? [];

$description = $attributes["description"] ?? "";

// Extract Payment ID
preg_match('/Payment ID:\s*(\d+)/', $description, $matches);
$paymentId = isset($matches[1]) ? (int)$matches[1] : 0;

if ($paymentId <= 0) {
    webhookLog("❌ No Payment ID found", ["description" => $description]);
    http_response_code(200);
    echo json_encode(["status" => "no_payment_id"]);
    exit;
}

// ============================================
// 💳 PAYMENT DETAILS
// ============================================
$payment = $attributes["payments"][0] ?? [];
$paymentAttr = $payment["attributes"] ?? [];

$paymongoPaymentId = $payment["id"] ?? null;
$paymentStatus = $paymentAttr["status"] ?? "";

if ($paymentStatus !== "paid") {
    webhookLog("Payment not paid", ["status" => $paymentStatus]);
    http_response_code(200);
    echo json_encode(["status" => "not_paid"]);
    exit;
}

// Detect payment method
$method = $paymentAttr["source"]["type"]
    ?? $attributes["payment_method_used"]
    ?? "card";

$paymentMethod = "Card";

if ($method === "gcash") $paymentMethod = "GCash";
elseif ($method === "paymaya") $paymentMethod = "PayMaya";
elseif ($method === "grab_pay") $paymentMethod = "GrabPay";
elseif ($method === "qrph") $paymentMethod = "QRPH";
elseif ($method === "dob") $paymentMethod = "Bank Transfer";

// ============================================
// 💾 UPDATE DATABASE (KEY PART)
// ============================================
$stmt = mysqli_prepare($conn, "
    UPDATE subscription_payments
    SET 
        payment_status = 'Paid',
        payment_method = ?,
        paymongo_payment_id = ?,
        transaction_reference = ?,
        paid_at = NOW(),
        updated_at = NOW()
    WHERE payment_id = ?
");

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $paymentMethod,
        $paymongoPaymentId,
        $paymongoPaymentId,
        $paymentId
    );

    mysqli_stmt_execute($stmt);

    webhookLog("DB updated", [
        "payment_id" => $paymentId,
        "affected_rows" => mysqli_stmt_affected_rows($stmt)
    ]);

    mysqli_stmt_close($stmt);
} else {
    webhookLog("DB prepare failed", mysqli_error($conn));
}

// ============================================
// 🔁 ACTIVATE SUBSCRIPTION
// ============================================
$stmt2 = mysqli_prepare($conn, "
    UPDATE subscriptions s
    INNER JOIN subscription_payments p 
        ON s.subscription_id = p.subscription_id
    SET 
        s.status = 'active',
        s.updated_at = NOW()
    WHERE p.payment_id = ?
");

if ($stmt2) {
    mysqli_stmt_bind_param($stmt2, "i", $paymentId);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);
}

// ============================================
// ✅ IMPORTANT: ALWAYS RETURN 200
// ============================================
http_response_code(200);
echo json_encode(["status" => "success"]);
exit;