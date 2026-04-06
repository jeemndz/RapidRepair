<?php
/**
 * Appointment CRUD API
 * Handles list, create, update, and delete operations for appointments
 * Database Tables: appointments, appointment_services, payments, repair_jobs
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

    if (!$conn->ping()) {
        errorResponse('Database connection lost.', 500);
    }

    return $conn;
}

function sanitize($value)
{
    return trim((string)$value);
}

function getRequestData()
{
    $data = [];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = $_GET;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
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

function normalizeAppointment($row)
{
    return [
        'appointment_id' => (int)$row['appointment_id'],
        'tenantID' => (int)$row['tenantID'],
        'user_id' => (int)$row['user_id'],
        'vehicle_id' => (int)$row['vehicle_id'],
        'appointment_date' => (string)$row['appointment_date'],
        'appointment_time' => (string)$row['appointment_time'],
        'status' => (string)$row['status'],
        'notes' => $row['notes'] !== null ? (string)$row['notes'] : '',
        'total_amount' => (float)$row['total_amount'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function fetchAppointmentById($conn, $appointment_id, $tenantID)
{
    $query = "SELECT * FROM appointments WHERE appointment_id = ? AND tenantID = ? LIMIT 1";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param('ii', $appointment_id, $tenantID);
    $stmt->execute();

    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    $stmt->close();

    if (!$appointment) {
        errorResponse('Appointment not found.', 404);
    }

    return normalizeAppointment($appointment);
}

function handleListAppointments($conn, $data)
{
    $tenantID = isset($data['tenantID']) ? (int)$data['tenantID'] : 0;
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $limit = max(1, min((int)($data['limit'] ?? 50), 100));
    $offset = max(0, (int)($data['offset'] ?? 0));

    if ($tenantID <= 0) {
        errorResponse('Invalid tenantID.');
    }

    if ($user_id > 0) {
        $query = "
            SELECT 
                a.*,
                p.referenceNumber,
                rj.job_order_no,
                rj.job_status
            FROM appointments a
            LEFT JOIN payments p 
                ON p.appointment_id = a.appointment_id AND p.tenantID = a.tenantID
            LEFT JOIN repair_jobs rj 
                ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
            WHERE a.tenantID = ? AND a.user_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            errorResponse('Query error: ' . $conn->error, 500);
        }

        $stmt->bind_param('iiii', $tenantID, $user_id, $limit, $offset);
    } else {
        $query = "
            SELECT 
                a.*,
                p.referenceNumber,
                rj.job_order_no,
                rj.job_status
            FROM appointments a
            LEFT JOIN payments p 
                ON p.appointment_id = a.appointment_id AND p.tenantID = a.tenantID
            LEFT JOIN repair_jobs rj 
                ON rj.appointment_id = a.appointment_id AND rj.tenantID = a.tenantID
            WHERE a.tenantID = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            errorResponse('Query error: ' . $conn->error, 500);
        }

        $stmt->bind_param('iii', $tenantID, $limit, $offset);
    }

    $stmt->execute();

    $result = $stmt->get_result();
    $appointments = [];

    while ($row = $result->fetch_assoc()) {
        $appointments[] = [
            'appointment_id' => (int)$row['appointment_id'],
            'tenantID' => (int)$row['tenantID'],
            'user_id' => (int)$row['user_id'],
            'vehicle_id' => (int)$row['vehicle_id'],
            'appointment_date' => (string)$row['appointment_date'],
            'appointment_time' => (string)$row['appointment_time'],
            'status' => (string)$row['status'],
            'notes' => $row['notes'] !== null ? (string)$row['notes'] : '',
            'total_amount' => (float)$row['total_amount'],
            'referenceNumber' => $row['referenceNumberr'] !== null ? (string)$row['referenceNumber'] : null,
            'job_order_no' => $row['job_order_no'] !== null ? (string)$row['job_order_no'] : null,
            'job_status' => $row['job_status'] !== null ? (string)$row['job_status'] : null,
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }

    $stmt->close();

    successResponse('Appointments fetched successfully.', $appointments);
}

function handleCreateAppointment($conn, $data)
{
    $required = ['tenantID', 'user_id', 'vehicle_id', 'appointment_date', 'appointment_time', 'service_ids', 'total_amount'];
    $missing = validateRequired($required, $data);

    if (!empty($missing)) {
        errorResponse('Missing required fields: ' . implode(', ', $missing));
    }

    $tenantID = (int)$data['tenantID'];
    $user_id = (int)$data['user_id'];
    $vehicle_id = (int)$data['vehicle_id'];
    $appointment_date = sanitize($data['appointment_date']);
    $appointment_time = sanitize($data['appointment_time']);
    $notes = isset($data['notes']) ? sanitize($data['notes']) : null;
    $total_amount = (float)$data['total_amount'];
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
        $services[(int)$row['service_id']] = (float)$row['price'];
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

        $referenceNumber = 'AP-' . str_pad((string)$appointment_id, 5, '0', STR_PAD_LEFT);
        $paymentMethod = 'Pending';
        $paymentStatus = 'Pending';
        $amountPaid = 0.00;
        $balance = $total_amount;

        $paymentInsertStmt = $conn->prepare("
            INSERT INTO payments (
                appointment_id, tenantID, user_id, payment_method, amount,
                amount_paid, balance, payment_status, referenceNumber,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$paymentInsertStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }

        $paymentInsertStmt->bind_param(
            'iiiisddss',
            $appointment_id,
            $tenantID,
            $user_id,
            $paymentMethod,
            $total_amount,
            $amountPaid,
            $balance,
            $paymentStatus,
            $referenceNumber
        );

        if (!$paymentInsertStmt->execute()) {
            throw new Exception('Failed to create payment record: ' . $paymentInsertStmt->error);
        }
        $paymentInsertStmt->close();

        $jobOrderNo = 'RR-' . str_pad((string)$appointment_id, 5, '0', STR_PAD_LEFT);
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

function handleUpdateAppointment($conn, $data)
{
    if (!isset($data['appointment_id']) || !isset($data['tenantID'])) {
        errorResponse('Missing appointment_id or tenantID.');
    }

    $appointment_id = (int)$data['appointment_id'];
    $tenantID = (int)$data['tenantID'];

    if ($appointment_id <= 0 || $tenantID <= 0) {
        errorResponse('Invalid appointment_id or tenantID.');
    }

    $verifyStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND tenantID = ? LIMIT 1");
    if (!$verifyStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $verifyStmt->bind_param('ii', $appointment_id, $tenantID);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        errorResponse('Appointment not found or you do not have permission to update it.', 403);
    }
    $verifyStmt->close();

    $fieldTypes = [
        'status' => 's',
        'appointment_date' => 's',
        'appointment_time' => 's',
        'notes' => 's',
        'total_amount' => 'd',
    ];

    $updateFields = [];
    $updateValues = [];
    $types = '';

    foreach ($fieldTypes as $field => $type) {
        if (array_key_exists($field, $data) && $data[$field] !== '') {
            $value = $type === 'd' ? (float)$data[$field] : sanitize($data[$field]);

            if ($field === 'status') {
                $allowedStatuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
                if (!in_array($value, $allowedStatuses, true)) {
                    errorResponse('Invalid status value.');
                }
            }

            if ($field === 'appointment_date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                errorResponse('Appointment date must be in YYYY-MM-DD format.');
            }

            if ($field === 'appointment_time' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                errorResponse('Appointment time must be in HH:MM or HH:MM:SS format.');
            }

            $updateFields[] = "{$field} = ?";
            $updateValues[] = $value;
            $types .= $type;
        }
    }

    if (empty($updateFields)) {
        errorResponse('No updateable fields provided.');
    }

    $updateValues[] = $appointment_id;
    $updateValues[] = $tenantID;
    $types .= 'ii';

    $query = "UPDATE appointments SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE appointment_id = ? AND tenantID = ?";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $stmt->bind_param($types, ...$updateValues);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        errorResponse('Failed to update appointment: ' . $error, 500);
    }

    $stmt->close();

    $appointment = fetchAppointmentById($conn, $appointment_id, $tenantID);
    successResponse('Appointment updated successfully.', $appointment);
}

function handleDeleteAppointment($conn, $data)
{
    if (!isset($data['appointment_id']) || !isset($data['tenantID'])) {
        errorResponse('Missing appointment_id or tenantID.');
    }

    $appointment_id = (int)$data['appointment_id'];
    $tenantID = (int)$data['tenantID'];

    if ($appointment_id <= 0 || $tenantID <= 0) {
        errorResponse('Invalid appointment_id or tenantID.');
    }

    $verifyStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND tenantID = ? LIMIT 1");
    if (!$verifyStmt) {
        errorResponse('Query error: ' . $conn->error, 500);
    }

    $verifyStmt->bind_param('ii', $appointment_id, $tenantID);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    if ($verifyResult->num_rows === 0) {
        $verifyStmt->close();
        errorResponse('Appointment not found or you do not have permission to delete it.', 403);
    }
    $verifyStmt->close();

    $conn->begin_transaction();

    try {
        $deleteRepairJobStmt = $conn->prepare("DELETE FROM repair_jobs WHERE appointment_id = ? AND tenantID = ?");
        if (!$deleteRepairJobStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }
        $deleteRepairJobStmt->bind_param('ii', $appointment_id, $tenantID);
        if (!$deleteRepairJobStmt->execute()) {
            throw new Exception('Failed to delete repair job: ' . $deleteRepairJobStmt->error);
        }
        $deleteRepairJobStmt->close();

        $deleteServicesStmt = $conn->prepare("DELETE FROM appointment_services WHERE appointment_id = ? AND tenantID = ?");
        if (!$deleteServicesStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }
        $deleteServicesStmt->bind_param('ii', $appointment_id, $tenantID);
        if (!$deleteServicesStmt->execute()) {
            throw new Exception('Failed to delete appointment services: ' . $deleteServicesStmt->error);
        }
        $deleteServicesStmt->close();

        $deletePaymentsStmt = $conn->prepare("DELETE FROM payments WHERE appointment_id = ? AND tenantID = ?");
        if (!$deletePaymentsStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }
        $deletePaymentsStmt->bind_param('ii', $appointment_id, $tenantID);
        if (!$deletePaymentsStmt->execute()) {
            throw new Exception('Failed to delete payments: ' . $deletePaymentsStmt->error);
        }
        $deletePaymentsStmt->close();

        $deleteStmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ? AND tenantID = ?");
        if (!$deleteStmt) {
            throw new Exception('Query error: ' . $conn->error);
        }
        $deleteStmt->bind_param('ii', $appointment_id, $tenantID);
        if (!$deleteStmt->execute()) {
            throw new Exception('Failed to delete appointment: ' . $deleteStmt->error);
        }
        $deleteStmt->close();

        $conn->commit();

        successResponse('Appointment deleted successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        errorResponse('Transaction failed: ' . $e->getMessage(), 500);
    }
}

try {
    $conn = getConnection();
    $data = getRequestData();

    $action = isset($data['action']) ? sanitize($data['action']) : '';

    if ($action === '') {
        errorResponse('Missing action parameter.');
    }

    switch ($action) {
        case 'list':
            handleListAppointments($conn, $data);
            break;

        case 'create':
            handleCreateAppointment($conn, $data);
            break;

        case 'update':
            handleUpdateAppointment($conn, $data);
            break;

        case 'delete':
            handleDeleteAppointment($conn, $data);
            break;

        default:
            errorResponse('Unknown action: ' . $action);
    }

    $conn->close();
} catch (Throwable $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
?>