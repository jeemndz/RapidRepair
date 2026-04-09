<?php
/**
 * Customer Appointment API
 * Allows customer mobile app to create bookings only
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

    $response = [
        'status' => $status,
        'message' => $message,
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

function successResponse($message = '', $data = null, $httpCode = 200)
{
    responseJson('success', $message, $data, $httpCode);
}

function errorResponse($message, $httpCode = 400)
{
    responseJson('error', $message, null, $httpCode);
}

function getConnection()
{
    global $conn;

    if (!isset($conn) || !$conn) {
        errorResponse('Database connection is not available.', 500);
    }

    if (!$conn->query('SELECT 1')) {
        errorResponse('Database connection lost.', 500);
    }

    return $conn;
}

function sanitize($value)
{
    return trim((string) $value);
}

function getRequestData()
{
    $data = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;

        if (empty($data)) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $data = $json;
            }
        }
    }

    return is_array($data) ? $data : [];
}

function validateRequired($requiredFields, $data)
{
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return $missing;
}

function normalizeServiceIds($value)
{
    if (is_array($value)) {
        $ids = $value;
    } elseif (is_string($value) && trim($value) !== '') {
        $trimmed = trim($value);

        if ($trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            $ids = is_array($decoded) ? $decoded : [];
        } else {
            $ids = explode(',', $trimmed);
        }
    } else {
        $ids = [];
    }

    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

    return $ids;
}

function handleCreateAppointment($conn, $data)
{
    $required = ['tenantID', 'user_id', 'vehicle_id', 'appointment_date', 'appointment_time', 'service_ids', 'total_amount'];
    $missing = validateRequired($required, $data);

    if (!empty($missing)) {
        errorResponse('Missing required fields: ' . implode(', ', $missing));
    }

    $tenantID = (int) $data['tenantID'];
    $user_id = (int) $data['user_id'];
    $vehicle_id = (int) $data['vehicle_id'];
    $appointment_date = sanitize($data['appointment_date']);
    $appointment_time = sanitize($data['appointment_time']);
    $notes = isset($data['notes']) ? sanitize($data['notes']) : null;
    $total_amount = (float) $data['total_amount'];
    $service_ids = normalizeServiceIds($data['service_ids']);

    if ($tenantID <= 0 || $user_id <= 0 || $vehicle_id <= 0) {
        errorResponse('Invalid tenantID, user_id, or vehicle_id.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        errorResponse('Appointment date must be in YYYY-MM-DD format.');
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
        errorResponse('Appointment time must be in HH:MM or HH:MM:SS format.');
    }

    if (empty($service_ids)) {
        errorResponse('At least one service must be selected.');
    }

    if ($total_amount <= 0) {
        errorResponse('Total amount must be greater than 0.');
    }

    $userStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND tenantID = ? LIMIT 1");
    if (!$userStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $userStmt->bind_param('ii', $user_id, $tenantID);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($userResult->num_rows === 0) {
        $userStmt->close();
        errorResponse('User does not belong to this tenant.', 403);
    }
    $userStmt->close();

    $vehicleStmt = $conn->prepare("SELECT vehicle_id FROM vehicleinformation WHERE vehicle_id = ? AND tenantID = ? AND user_id = ? LIMIT 1");
    if (!$vehicleStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $vehicleStmt->bind_param('iii', $vehicle_id, $tenantID, $user_id);
    $vehicleStmt->execute();
    $vehicleResult = $vehicleStmt->get_result();
    if ($vehicleResult->num_rows === 0) {
        $vehicleStmt->close();
        errorResponse('Vehicle does not belong to this tenant/user.', 403);
    }
    $vehicleStmt->close();

    $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
    $types = str_repeat('i', count($service_ids)) . 'i';

    $serviceQuery = "SELECT service_id, price FROM services WHERE service_id IN ($placeholders) AND tenantID = ?";
    $serviceStmt = $conn->prepare($serviceQuery);
    if (!$serviceStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $params = array_merge($service_ids, [$tenantID]);
    $serviceStmt->bind_param($types, ...$params);
    $serviceStmt->execute();
    $serviceResult = $serviceStmt->get_result();
    $services = [];

    while ($row = $serviceResult->fetch_assoc()) {
        $services[(int) $row['service_id']] = (float) $row['price'];
    }
    $serviceStmt->close();

    if (count($services) !== count($service_ids)) {
        errorResponse('One or more selected services are invalid for this tenant.');
    }

    $conn->begin_transaction();

    try {
        $insertStmt = $conn->prepare("
            INSERT INTO appointments (
                tenantID, user_id, vehicle_id, appointment_date, appointment_time,
                status, notes, total_amount, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$insertStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }

        $status = 'Pending';
        $insertStmt->bind_param(
            'iiissssd',
            $tenantID,
            $user_id,
            $vehicle_id,
            $appointment_date,
            $appointment_time,
            $status,
            $notes,
            $total_amount
        );

        if (!$insertStmt->execute()) {
            throw new Exception('Failed to create appointment: ' . $insertStmt->error);
        }

        $appointment_id = $conn->insert_id;
        $insertStmt->close();

        $serviceInsertStmt = $conn->prepare("
            INSERT INTO appointment_services (
                appointment_id, tenantID, service_id, service_price,
                duration_minutes, notes, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$serviceInsertStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }

        foreach ($service_ids as $service_id) {
            $price = $services[$service_id] ?? 0;
            $duration = 60;
            $serviceNotes = '';

            $serviceInsertStmt->bind_param(
                'iiiidis',
                $appointment_id,
                $tenantID,
                $service_id,
                $price,
                $duration,
                $serviceNotes
            );

            if (!$serviceInsertStmt->execute()) {
                throw new Exception('Failed to link service: ' . $serviceInsertStmt->error);
            }
        }
        $serviceInsertStmt->close();

        $referenceNumber = 'AP-' . str_pad((string) $appointment_id, 5, '0', STR_PAD_LEFT);
        $paymentMethod = 'Cash';
        $paymentStatus = 'Pending';
        $amountPaid = 0.00;
        $balance = $total_amount;
        $remarks = '';

        $paymentInsertStmt = $conn->prepare("
            INSERT INTO payments (
                appointment_id,
                tenantID,
                user_id,
                paymentMethod,
                paymentAmount,
                amountPaid,
                balance,
                paymentStatus,
                referenceNumber,
                remarks,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$paymentInsertStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }

        $paymentInsertStmt->bind_param(
            'iiiisddsss',
            $appointment_id,
            $tenantID,
            $user_id,
            $paymentMethod,
            $total_amount,
            $amountPaid,
            $balance,
            $paymentStatus,
            $referenceNumber,
            $remarks
        );

        if (!$paymentInsertStmt->execute()) {
            throw new Exception('Failed to create payment record: ' . $paymentInsertStmt->error);
        }
        $paymentInsertStmt->close();

        $jobOrderNo = 'RR-' . str_pad((string) $appointment_id, 5, '0', STR_PAD_LEFT);
        $jobStatus = 'Queued';
        $priority = 'Normal';

        $repairStmt = $conn->prepare("
            INSERT INTO repair_jobs (
                tenantID, appointment_id, user_id, vehicle_id,
                job_order_no, job_status, priority, concern,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$repairStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }

        $concern = $notes ?? '';

        $repairStmt->bind_param(
            'iiiissss',
            $tenantID,
            $appointment_id,
            $user_id,
            $vehicle_id,
            $jobOrderNo,
            $jobStatus,
            $priority,
            $concern
        );

        if (!$repairStmt->execute()) {
            throw new Exception('Failed to create repair job: ' . $repairStmt->error);
        }
        $repairStmt->close();

        $conn->commit();

        successResponse('Appointment created successfully.', [
            'appointment_id' => $appointment_id,
            'referenceNumber' => $referenceNumber,
            'job_order_no' => $jobOrderNo,
            'status' => $status,
            'job_status' => $jobStatus,
            'total_amount' => $total_amount,
        ], 201);
    } catch (Exception $e) {
        $conn->rollback();
        errorResponse('Transaction failed: ' . $e->getMessage(), 500);
    }
}

try {
    $conn = getConnection();
    $data = getRequestData();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Only POST requests are allowed.', 405);
    }

    $action = isset($data['action']) ? sanitize($data['action']) : '';

    if ($action === '') {
        errorResponse('Missing action parameter.');
    }

    if ($action !== 'create') {
        errorResponse('Only create action is allowed for this endpoint.', 403);
    }

    handleCreateAppointment($conn, $data);

    $conn->close();
} catch (Throwable $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
?>