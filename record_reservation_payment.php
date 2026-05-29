<?php
/**
 * Record Reservation Fee Payment
 * No PayMongo integration.
 * This marks the ₱500 reservation fee as paid and confirms the appointment.
 */

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
$amount = (float)($data['amount'] ?? 500);
$payment_method = trim($data['payment_method'] ?? 'Reservation Fee');
$remarks = trim($data['remarks'] ?? 'Reservation fee paid');

if (!$tenantID || !$user_id || !$appointment_id) {
    responseJson('error', 'Missing required fields', null, 400);
}

if ($amount < 500) {
    responseJson('error', 'Reservation fee must be at least ₱500.', null, 400);
}

$stmt = $conn->prepare("
    SELECT appointment_id, tenantID, user_id, status, reservation_paid, reservation_payment_status
    FROM appointments
    WHERE appointment_id = ?
    AND tenantID = ?
    AND user_id = ?
    LIMIT 1
");

if (!$stmt) {
    responseJson('error', 'Failed to prepare appointment query: ' . $conn->error, null, 500);
}

$stmt->bind_param('iii', $appointment_id, $tenantID, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    responseJson('error', 'Appointment not found', null, 404);
}

if ((int)$appointment['reservation_paid'] === 1) {
    responseJson('error', 'Reservation fee is already paid.', null, 409);
}

if (in_array($appointment['status'], ['Cancelled', 'No Show', 'Completed'], true)) {
    responseJson('error', 'Reservation fee cannot be paid for this appointment status.', null, 409);
}

$referenceNumber = 'RES-' . $appointment_id . '-' . date('YmdHis');

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        UPDATE appointments
        SET
            status = 'Confirmed',
            reservation_fee = ?,
            reservation_paid = 1,
            reservation_payment_status = 'Paid',
            reservation_payment_reference = ?,
            reservation_paid_at = NOW(),
            updated_at = NOW()
        WHERE appointment_id = ?
        AND tenantID = ?
        AND user_id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare appointment update: ' . $conn->error);
    }

    $stmt->bind_param('dsiii', $amount, $referenceNumber, $appointment_id, $tenantID, $user_id);
    $stmt->execute();
    $stmt->close();

    /*
     * Optional: create a payment record for the reservation fee.
     * This only runs if your payments table exists with these columns.
     */
    $checkTable = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $paymentStatus = 'Paid';
        $paymentMethod = $payment_method;
        $paymentAmount = $amount;
        $amountPaid = $amount;
        $balance = 0.00;
        $paymentRemarks = $remarks . ' | Reservation fee for appointment #' . $appointment_id;

        $stmt = $conn->prepare("
            INSERT INTO payments (
                tenantID,
                user_id,
                appointment_id,
                paymentAmount,
                amountPaid,
                balance,
                paymentMethod,
                paymentDate,
                paymentStatus,
                referenceNumber,
                remarks,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), NOW())
        ");

        if ($stmt) {
            $stmt->bind_param(
                'iiidddssss',
                $tenantID,
                $user_id,
                $appointment_id,
                $paymentAmount,
                $amountPaid,
                $balance,
                $paymentMethod,
                $paymentStatus,
                $referenceNumber,
                $paymentRemarks
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();

    responseJson('success', 'Reservation fee recorded successfully. Appointment confirmed.', [
        'appointment_id' => $appointment_id,
        'amount' => $amount,
        'reference_number' => $referenceNumber,
        'reservation_paid' => 1,
        'reservation_payment_status' => 'Paid',
        'appointment_status' => 'Confirmed'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    responseJson('error', $e->getMessage(), null, 500);
}
