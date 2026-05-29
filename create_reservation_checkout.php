<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

function responseJson($status, $message = '', $data = null, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function getRequestData()
{
    $data = $_POST;

    if (empty($data)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (is_array($json)) {
            $data = $json;
        }
    }

    return $data;
}

$data = getRequestData();

$tenantID = (int)($data['tenantID'] ?? 0);
$user_id = (int)($data['user_id'] ?? 0);
$appointment_id = (int)($data['appointment_id'] ?? 0);
$amount = 500.00;

if (!$tenantID || !$user_id || !$appointment_id) {
    responseJson('error', 'Missing required fields', null, 400);
}

$stmt = $conn->prepare("
    SELECT 
        appointment_id,
        reservation_fee,
        reservation_paid,
        reservation_payment_status
    FROM appointments
    WHERE appointment_id = ?
    AND tenantID = ?
    AND user_id = ?
    LIMIT 1
");

$stmt->bind_param('iii', $appointment_id, $tenantID, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    responseJson('error', 'Appointment not found', null, 404);
}

if ((int)$appointment['reservation_paid'] === 1) {
    responseJson('error', 'Reservation fee is already paid', null, 409);
}

$referenceNumber = 'RES-' . $appointment_id . '-' . date('YmdHis');

$stmt = $conn->prepare("
    UPDATE appointments
    SET 
        reservation_fee = ?,
        reservation_paid = 0,
        reservation_payment_status = 'Unpaid',
        reservation_payment_reference = ?,
        updated_at = NOW()
    WHERE appointment_id = ?
    AND tenantID = ?
    AND user_id = ?
");

$stmt->bind_param(
    'dsiii',
    $amount,
    $referenceNumber,
    $appointment_id,
    $tenantID,
    $user_id
);

$stmt->execute();
$stmt->close();

responseJson('success', 'Reservation fee recorded. Please pay ₱500 to confirm your appointment.', [
    'appointment_id' => $appointment_id,
    'reference_number' => $referenceNumber,
    'amount' => $amount,
    'reservation_payment_status' => 'Unpaid',
    'message' => 'Please pay ₱500 reservation fee at the shop or through the shop payment method. If you do not show up, the reservation fee may be forfeited.'
]);