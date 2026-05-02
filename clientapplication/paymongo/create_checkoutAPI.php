<?php
/**
 * create_checkout.php
 * Mobile app endpoint for creating a PayMongo Checkout Session.
 *
 * Expected JSON body:
 * {
 *   "payment_id": 1,
 *   "tenantID": 1,
 *   "user_id": 1
 * }
 *
 * Optional JSON body:
 * {
 *   "customer_name": "Juan Dela Cruz",
 *   "customer_email": "juan@example.com",
 *   "customer_phone": "09171234567"
 * }
 */

include __DIR__ . "/../db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed"
    ]);
    exit;
}

$PAYMONGO_SECRET_KEY = "sk_test_Z6ynXiE7Ywm2KmTCoKsHZnz2";

function jsonResponse($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function readJsonBody()
{
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return [];
    }

    return $data;
}

function normalizePhone($phone)
{
    $phone = trim((string)$phone);
    if ($phone === '') return null;

    // PayMongo accepts PH phone numbers better in +63 format.
    if (preg_match('/^09\d{9}$/', $phone)) {
        return '+63' . substr($phone, 1);
    }

    return $phone;
}

$input = readJsonBody();

$payment_id = isset($input['payment_id']) ? (int)$input['payment_id'] : 0;
$tenantID = isset($input['tenantID']) ? (int)$input['tenantID'] : 0;
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if ($payment_id <= 0 || $tenantID <= 0 || $user_id <= 0) {
    jsonResponse(400, [
        "status" => "error",
        "message" => "Missing or invalid payment_id, tenantID, or user_id"
    ]);
}

$stmt = $conn->prepare("\n    SELECT \n        payment_id,\n        tenantID,\n        user_id,\n        appointment_id,\n        repair_job_id,\n        grand_total,\n        paymentAmount,\n        amountPaid,\n        balance,\n        paymentStatus,\n        referenceNumber,\n        paymongo_checkout_id,\n        paymongo_status\n    FROM payments\n    WHERE payment_id = ? AND tenantID = ? AND user_id = ?\n    LIMIT 1\n");

if (!$stmt) {
    jsonResponse(500, [
        "status" => "error",
        "message" => "Database prepare failed: " . $conn->error
    ]);
}

$stmt->bind_param("iii", $payment_id, $tenantID, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    jsonResponse(404, [
        "status" => "error",
        "message" => "Payment record not found"
    ]);
}

if (strtolower((string)$payment['paymentStatus']) === 'paid') {
    jsonResponse(400, [
        "status" => "error",
        "message" => "This payment is already paid"
    ]);
}

$grandTotal = (float)($payment['grand_total'] ?? 0);
if ($grandTotal <= 0) {
    $grandTotal = (float)($payment['paymentAmount'] ?? 0);
}

$amountPaid = (float)($payment['amountPaid'] ?? 0);
$balance = $grandTotal - $amountPaid;

if (isset($payment['balance']) && (float)$payment['balance'] > 0) {
    $balance = (float)$payment['balance'];
}

$balance = max(0, $balance);

if ($balance <= 0) {
    jsonResponse(400, [
        "status" => "error",
        "message" => "This payment has no remaining balance"
    ]);
}

// PayMongo amount must be in centavos.
$amountCentavos = (int)round($balance * 100);

$customerName = trim((string)($input['customer_name'] ?? 'Rapid Repair Customer'));
$customerEmail = trim((string)($input['customer_email'] ?? 'customer@example.com'));
$customerPhone = normalizePhone($input['customer_phone'] ?? '');

$requestedMethod = strtolower(trim((string)($input['payment_method'] ?? '')));
$allowedMethods = ['gcash', 'card'];
if ($requestedMethod === 'paymaya' || $requestedMethod === 'maya') {
    $allowedMethods = ['paymaya'];
} elseif ($requestedMethod === 'card') {
    $allowedMethods = ['card'];
} elseif ($requestedMethod === 'dob' || $requestedMethod === 'bank_transfer' || $requestedMethod === 'online_banking') {
    $allowedMethods = ['dob'];
} elseif ($requestedMethod === 'gcash') {
    $allowedMethods = ['gcash'];
}

// Change these to your real hosted pages.
// For mobile app, these can be simple web pages that tell the user to return to the app.
$successUrl = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net/payment-success.php?payment_id=" . urlencode((string)$payment_id);
$cancelUrl = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net/payment-cancel.php?payment_id=" . urlencode((string)$payment_id);

$billing = [
    "name" => $customerName,
    "email" => $customerEmail
];

if ($customerPhone !== null) {
    $billing["phone"] = $customerPhone;
}

$payload = [
    "data" => [
        "attributes" => [
            "billing" => $billing,
            "send_email_receipt" => false,
            "show_description" => true,
            "show_line_items" => true,
            "description" => "Rapid Repair payment for invoice #" . $payment_id,
            "line_items" => [
                [
                    "currency" => "PHP",
                    "amount" => $amountCentavos,
                    "description" => "Repair service payment balance",
                    "name" => "Rapid Repair Payment",
                    "quantity" => 1
                ]
            ],
            "payment_method_types" => $allowedMethods,
            "success_url" => $successUrl,
            "cancel_url" => $cancelUrl,
            "metadata" => [
                "payment_id" => (string)$payment_id,
                "tenantID" => (string)$tenantID,
                "user_id" => (string)$user_id,
                "appointment_id" => (string)($payment['appointment_id'] ?? ''),
                "repair_job_id" => (string)($payment['repair_job_id'] ?? '')
            ]
        ]
    ]
];

$ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode($PAYMONGO_SECRET_KEY . ":"),
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError) {
    jsonResponse(500, [
        "status" => "error",
        "message" => "PayMongo request failed",
        "details" => $curlError
    ]);
}

$data = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    jsonResponse($httpCode, [
        "status" => "error",
        "message" => "PayMongo returned an error",
        "paymongo_response" => $data
    ]);
}

$checkoutId = $data['data']['id'] ?? null;
$checkoutUrl = $data['data']['attributes']['checkout_url'] ?? null;

if (!$checkoutId || !$checkoutUrl) {
    jsonResponse(500, [
        "status" => "error",
        "message" => "Invalid PayMongo checkout response",
        "paymongo_response" => $data
    ]);
}

$referenceNumber = $payment['referenceNumber'];
if (!$referenceNumber) {
    $referenceNumber = "PMCO-" . strtoupper(substr($checkoutId, -10));
}

$update = $conn->prepare("\n    UPDATE payments\n    SET \n        paymongo_checkout_id = ?,\n        paymongo_status = 'pending',\n        paymongo_method = NULL,\n        referenceNumber = ?,\n        paymentStatus = 'Pending',\n        updated_at = NOW()\n    WHERE payment_id = ? AND tenantID = ? AND user_id = ?\n");

if (!$update) {
    jsonResponse(500, [
        "status" => "error",
        "message" => "Database update prepare failed: " . $conn->error
    ]);
}

$update->bind_param("ssiii", $checkoutId, $referenceNumber, $payment_id, $tenantID, $user_id);
$update->execute();
$update->close();

jsonResponse(200, [
    "status" => "success",
    "message" => "Checkout session created",
    "payment_id" => $payment_id,
    "paymongo_checkout_id" => $checkoutId,
    "checkout_url" => $checkoutUrl,
    "amount" => $balance,
    "amount_centavos" => $amountCentavos
]);
