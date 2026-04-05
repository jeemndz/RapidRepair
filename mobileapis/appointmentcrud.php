<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

function jsonResponse($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

function validateDateFormat($date) {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

function validateTimeFormat($time) {
    return (bool) preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time);
}

function normalizeServiceIds($service_ids) {
    if (is_array($service_ids)) {
        return array_values(array_filter(array_map('intval', $service_ids), fn($id) => $id > 0));
    }

    if (is_string($service_ids) && trim($service_ids) !== '') {
        return array_values(array_filter(array_map('intval', explode(',', $service_ids)), fn($id) => $id > 0));
    }

    return [];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = getJsonInput();

    $action = isset($input['action']) ? trim($input['action']) : 'create';

    if ($action === 'update') {
        $appointment_id = isset($input['appointment_id']) ? (int) $input['appointment_id'] : 0;
        $tenantID = isset($input['tenantID']) ? (int) $input['tenantID'] : 0;
        $status = isset($input['status']) ? trim($input['status']) : '';

        if ($appointment_id <= 0 || $tenantID <= 0 || $status === '') {
            jsonResponse(400, [
                'status' => 'error',
                'message' => 'Missing required update fields'
            ]);
        }

        $query = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ? AND tenantID = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            jsonResponse(500, [
                'status' => 'error',
                'message' => 'Prepare failed: ' . $conn->error
            ]);
        }

        $stmt->bind_param('sii', $status, $appointment_id, $tenantID);

        if (!$stmt->execute()) {
            $stmt->close();
            jsonResponse(500, [
                'status' => 'error',
                'message' => 'Update failed: ' . $stmt->error
            ]);
        }

        $stmt->close();

        jsonResponse(200, [
            'status' => 'success',
            'message' => 'Appointment updated successfully',
            'data' => [
                'appointment_id' => $appointment_id,
                'status' => $status
            ]
        ]);
    }

    $tenantID = isset($input['tenantID']) ? (int) $input['tenantID'] : 0;
    $user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    $vehicle_id = isset($input['vehicle_id']) ? (int) $input['vehicle_id'] : 0;
    $appointment_date = isset($input['appointment_date']) ? trim($input['appointment_date']) : '';
    $appointment_time = isset($input['appointment_time']) ? trim($input['appointment_time']) : '';
    $notes = isset($input['notes']) ? trim($input['notes']) : '';
    $service_ids = normalizeServiceIds($input['service_ids'] ?? []);
    $total_amount = isset($input['total_amount']) ? (float) $input['total_amount'] : 0;

    if ($tenantID <= 0) {
        jsonResponse(400, ['status' => 'error', 'message' => 'Invalid or missing tenantID']);
    }

    if ($user_id <= 0) {
        jsonResponse(400, ['status' => 'error', 'message' => 'Invalid or missing user_id']);
    }

    if ($vehicle_id <= 0) {
        jsonResponse(400, ['status' => 'error', 'message' => 'Invalid or missing vehicle_id']);
    }

    if (!$appointment_date || !validateDateFormat($appointment_date)) {
        jsonResponse(400, ['status' => 'error', 'message' => 'Invalid appointment_date format. Use YYYY-MM-DD']);
    }

    if (!$appointment_time || !validateTimeFormat($appointment_time)) {
        jsonResponse(400, ['status' => 'error', 'message' => 'Invalid appointment_time format. Use HH:MM:SS or HH:MM']);
    }

    if (empty($service_ids)) {
        jsonResponse(400, ['status' => 'error', 'message' => 'At least one service must be selected']);
    }

    if ($total_amount <= 0) {
        jsonResponse(400, ['status' => 'error', 'message' => 'Invalid total_amount']);
    }

    $conn->begin_transaction();

    try {
        $status = 'Pending';

        $appointmentQuery = "
            INSERT INTO appointments
            (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = $conn->prepare($appointmentQuery);
        if (!$stmt) {
            throw new Exception('Appointment prepare failed: ' . $conn->error);
        }

        $stmt->bind_param(
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

        if (!$stmt->execute()) {
            throw new Exception('Appointment execute failed: ' . $stmt->error);
        }

        $appointment_id = $conn->insert_id;
        $stmt->close();

        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
        $types = str_repeat('i', count($service_ids)) . 'i';

        $serviceQuery = "
            SELECT service_id, price
            FROM services
            WHERE service_id IN ($placeholders) AND tenantID = ?
        ";

        $stmt = $conn->prepare($serviceQuery);
        if (!$stmt) {
            throw new Exception('Service lookup prepare failed: ' . $conn->error);
        }

        $params = array_merge($service_ids, [$tenantID]);
        $bindParams = [];
        $bindParams[] = $types;

        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        if (!$stmt->execute()) {
            throw new Exception('Service lookup failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $servicePrices = [];

        while ($row = $result->fetch_assoc()) {
            $servicePrices[(int) $row['service_id']] = (float) $row['price'];
        }

        $stmt->close();

        $serviceInsertQuery = "
            INSERT INTO appointment_services
            (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $conn->prepare($serviceInsertQuery);
        if (!$stmt) {
            throw new Exception('Service insert prepare failed: ' . $conn->error);
        }

        $duration_minutes = 0;
        $service_notes = '';

        foreach ($service_ids as $service_id) {
            $service_price = $servicePrices[$service_id] ?? 0;

            $stmt->bind_param(
                'iiidis',
                $appointment_id,
                $tenantID,
                $service_id,
                $service_price,
                $duration_minutes,
                $service_notes
            );

            if (!$stmt->execute()) {
                throw new Exception('Service insert failed: ' . $stmt->error);
            }
        }

        $stmt->close();

        $paymentMethod = 'Pending';
        $paymentStatus = 'Pending';
        $referenceNumber = 'RR-' . str_pad((string) $appointment_id, 5, '0', STR_PAD_LEFT);
        $amountPaid = 0;
        $balance = $total_amount;

        $paymentQuery = "
            INSERT INTO payments
            (tenantID, user_id, appointment_id, paymentAmount, amountPaid, balance, paymentMethod, paymentStatus, referenceNumber, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = $conn->prepare($paymentQuery);
        if (!$stmt) {
            throw new Exception('Payment prepare failed: ' . $conn->error);
        }

        $stmt->bind_param(
            'iiidddsss',
            $tenantID,
            $user_id,
            $appointment_id,
            $total_amount,
            $amountPaid,
            $balance,
            $paymentMethod,
            $paymentStatus,
            $referenceNumber
        );

        if (!$stmt->execute()) {
            throw new Exception('Payment insert failed: ' . $stmt->error);
        }

        $stmt->close();

        $conn->commit();

        jsonResponse(201, [
            'status' => 'success',
            'message' => 'Appointment created successfully',
            'data' => [
                'appointment_id' => $appointment_id,
                'reference_number' => $referenceNumber,
                'status' => $status,
                'total_amount' => $total_amount
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();

        jsonResponse(500, [
            'status' => 'error',
            'message' => 'Failed to create appointment: ' . $e->getMessage()
        ]);
    }
}

if ($method === 'GET') {
    $action = isset($_GET['action']) ? trim($_GET['action']) : 'list';

    if ($action === 'list') {
        $tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

        if ($tenantID <= 0) {
            jsonResponse(400, ['status' => 'error', 'message' => 'Invalid tenantID']);
        }

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        if ($user_id > 0) {
            $query = "
                SELECT *
                FROM appointments
                WHERE tenantID = ? AND user_id = ?
                ORDER BY appointment_date DESC, appointment_time DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                jsonResponse(500, ['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
            }

            $stmt->bind_param('iiii', $tenantID, $user_id, $limit, $offset);
        } else {
            $query = "
                SELECT *
                FROM appointments
                WHERE tenantID = ?
                ORDER BY appointment_date DESC, appointment_time DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                jsonResponse(500, ['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
            }

            $stmt->bind_param('iii', $tenantID, $limit, $offset);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            jsonResponse(500, ['status' => 'error', 'message' => 'Query failed: ' . $stmt->error]);
        }

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

        jsonResponse(200, [
            'status' => 'success',
            'data' => $rows
        ]);
    }

    if ($action === 'delete') {
        $appointment_id = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;
        $tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;

        if ($appointment_id <= 0 || $tenantID <= 0) {
            jsonResponse(400, [
                'status' => 'error',
                'message' => 'Missing appointment_id or tenantID'
            ]);
        }

        $query = "DELETE FROM appointments WHERE appointment_id = ? AND tenantID = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            jsonResponse(500, [
                'status' => 'error',
                'message' => 'Prepare failed: ' . $conn->error
            ]);
        }

        $stmt->bind_param('ii', $appointment_id, $tenantID);

        if (!$stmt->execute()) {
            $stmt->close();
            jsonResponse(500, [
                'status' => 'error',
                'message' => 'Delete failed: ' . $stmt->error
            ]);
        }

        $stmt->close();

        jsonResponse(200, [
            'status' => 'success',
            'message' => 'Appointment deleted successfully',
            'data' => [
                'appointment_id' => $appointment_id
            ]
        ]);
    }

    jsonResponse(400, [
        'status' => 'error',
        'message' => 'Invalid action'
    ]);
}

jsonResponse(405, [
    'status' => 'error',
    'message' => 'Method not allowed'
]);
?>