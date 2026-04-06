<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'https://raw.githubusercontent.com/jeemnndz/RapidRepair/main/db.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function getInput() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?? [];
}

function normalizeTenantID($value) {
    $tenantID = (int)($value ?? 0);
    return $tenantID > 0 ? $tenantID : 1;
}

function normalizeServiceIds($value) {
    if (is_array($value)) {
        $ids = $value;
    } elseif (is_string($value) && trim($value) !== '') {
        $ids = explode(',', $value);
    } else {
        $ids = [];
    }

    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

    return $ids;
}

function validateDateValue($date) {
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

function validateTimeValue($time) {
    return is_string($time) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time);
}

function rowExists(mysqli $conn, string $sql, string $types, ...$params): bool {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception('Execute failed: ' . $err);
    }

    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = trim((string)($_GET['action'] ?? 'list'));
        $tenantID = normalizeTenantID($_GET['tenantID'] ?? null);

        if ($action === 'list') {
            $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            $limit = max(1, min((int)($_GET['limit'] ?? 50), 100));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            if ($user_id > 0) {
                $stmt = $conn->prepare("
                    SELECT *
                    FROM appointments
                    WHERE tenantID = ? AND user_id = ?
                    ORDER BY appointment_date DESC, appointment_time DESC
                    LIMIT ? OFFSET ?
                ");
                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('iiii', $tenantID, $user_id, $limit, $offset);
            } else {
                $stmt = $conn->prepare("
                    SELECT *
                    FROM appointments
                    WHERE tenantID = ?
                    ORDER BY appointment_date DESC, appointment_time DESC
                    LIMIT ? OFFSET ?
                ");
                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('iii', $tenantID, $limit, $offset);
            }

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Query failed: ' . $err);
            }

            $result = $stmt->get_result();
            $rows = [];

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $stmt->close();

            respond(200, ['status' => 'success', 'data' => $rows]);
        }

        if ($action === 'delete') {
            $appointment_id = (int)($_GET['appointment_id'] ?? 0);

            if ($appointment_id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Missing appointment_id']);
            }

            $conn->begin_transaction();

            $stmt = $conn->prepare("DELETE FROM appointment_services WHERE appointment_id = ? AND tenantID = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $appointment_id, $tenantID);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Delete appointment services failed: ' . $err);
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM payments WHERE appointment_id = ? AND tenantID = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $appointment_id, $tenantID);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Delete payments failed: ' . $err);
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ? AND tenantID = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $appointment_id, $tenantID);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Delete appointment failed: ' . $err);
            }

            if ($stmt->affected_rows < 1) {
                $stmt->close();
                $conn->rollback();
                respond(404, ['status' => 'error', 'message' => 'Appointment not found for this tenant']);
            }

            $stmt->close();
            $conn->commit();

            respond(200, [
                'status' => 'success',
                'message' => 'Appointment deleted successfully',
                'data' => ['appointment_id' => $appointment_id]
            ]);
        }

        if ($action === 'create') {
            // Handle GET-based appointment creation (Azure POST blocking workaround)
            $input = $_GET;

            $tenantID = normalizeTenantID($input['tenantID'] ?? null);
            $user_id = (int)($input['user_id'] ?? 0);
            $vehicle_id = (int)($input['vehicle_id'] ?? 0);
            $appointment_date = trim((string)($input['appointment_date'] ?? ''));
            $appointment_time = trim((string)($input['appointment_time'] ?? ''));
            $notes = trim((string)($input['notes'] ?? ''));

            // Parse service_ids from GET (might be JSON string)
            $service_ids_raw = $input['service_ids'] ?? '[]';
            if (is_string($service_ids_raw)) {
                $parsed = json_decode($service_ids_raw, true);
                $service_ids = is_array($parsed) ? $parsed : [];
            } else {
                $service_ids = is_array($service_ids_raw) ? $service_ids_raw : [];
            }
            $service_ids = normalizeServiceIds($service_ids);

            $total_amount = (float)($input['total_amount'] ?? 0);

            if ($user_id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Invalid or missing user_id']);
            }

            if ($vehicle_id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Invalid or missing vehicle_id']);
            }

            if (!validateDateValue($appointment_date)) {
                respond(400, ['status' => 'error', 'message' => 'Invalid appointment_date format. Use YYYY-MM-DD']);
            }

            if (!validateTimeValue($appointment_time)) {
                respond(400, ['status' => 'error', 'message' => 'Invalid appointment_time format. Use HH:MM or HH:MM:SS']);
            }

            if (empty($service_ids)) {
                respond(400, ['status' => 'error', 'message' => 'At least one service must be selected']);
            }

            if ($total_amount <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Invalid total_amount']);
            }

            // Multi-tenant validation
            $userExists = rowExists(
                $conn,
                "SELECT id FROM users WHERE id = ? AND tenantID = ? LIMIT 1",
                'ii',
                $user_id,
                $tenantID
            );

            if (!$userExists) {
                respond(400, ['status' => 'error', 'message' => 'User does not belong to this tenant']);
            }

            $vehicleExists = rowExists(
                $conn,
                "SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND tenantID = ? AND user_id = ? LIMIT 1",
                'iii',
                $vehicle_id,
                $tenantID,
                $user_id
            );

            if (!$vehicleExists) {
                respond(400, ['status' => 'error', 'message' => 'Vehicle does not belong to this tenant/user']);
            }

            $conn->begin_transaction();

            // Load services for this tenant
            $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
            $types = str_repeat('i', count($service_ids)) . 'i';

            $sql = "SELECT id, price
                    FROM services
                    WHERE id IN ($placeholders) AND tenantID = ?";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Service prepare failed: ' . $conn->error);
            }

            $params = array_merge($service_ids, [$tenantID]);
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Service query failed: ' . $err);
            }

            $result = $stmt->get_result();
            $services = [];
            $computedTotal = 0;

            while ($row = $result->fetch_assoc()) {
                $serviceId = (int)$row['id'];
                $price = (float)$row['price'];
                $services[$serviceId] = $price;
                $computedTotal += $price;
            }
            $stmt->close();

            if (count($services) !== count($service_ids)) {
                throw new Exception('One or more selected services are invalid for this tenant');
            }

            $finalTotal = $computedTotal;
            $status = 'Pending';

            $stmt = $conn->prepare("
                INSERT INTO appointments
                (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
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
                $finalTotal
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Appointment insert failed: ' . $err);
            }

            $appointment_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO appointment_services
                (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            if (!$stmt) {
                throw new Exception('Appointment services prepare failed: ' . $conn->error);
            }

            $duration_minutes = 0;
            $service_notes = '';

            foreach ($service_ids as $service_id) {
                $service_price = (float)$services[$service_id];
                $stmt->bind_param(
                    'iiidss',
                    $appointment_id,
                    $tenantID,
                    $service_id,
                    $service_price,
                    $duration_minutes,
                    $service_notes
                );

                if (!$stmt->execute()) {
                    $err = $stmt->error;
                    $stmt->close();
                    throw new Exception('Appointment service insert failed: ' . $err);
                }
            }
            $stmt->close();

            $paymentMethod = 'Pending';
            $paymentStatus = 'Pending';
            $referenceNumber = 'RR-' . str_pad((string)$appointment_id, 5, '0', STR_PAD_LEFT);
            $amountPaid = 0.00;
            $balance = $finalTotal;

            $stmt = $conn->prepare("
                INSERT INTO payments
                (tenantID, user_id, appointment_id, paymentAmount, amountPaid, balance, paymentMethod, paymentStatus, referenceNumber, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if (!$stmt) {
                throw new Exception('Payment prepare failed: ' . $conn->error);
            }

            $stmt->bind_param(
                'iiidddsss',
                $tenantID,
                $user_id,
                $appointment_id,
                $finalTotal,
                $amountPaid,
                $balance,
                $paymentMethod,
                $paymentStatus,
                $referenceNumber
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Payment insert failed: ' . $err);
            }
            $stmt->close();

            $conn->commit();

            respond(201, [
                'status' => 'success',
                'message' => 'Appointment created successfully',
                'data' => [
                    'appointment_id' => $appointment_id,
                    'reference_number' => $referenceNumber,
                    'status' => $status,
                    'total_amount' => $finalTotal
                ]
            ]);
        }

        respond(400, ['status' => 'error', 'message' => 'Invalid action']);
    }

    if ($method === 'POST') {
        $input = getInput();
        $action = trim((string)($input['action'] ?? 'create'));

        if ($action === 'update') {
            $tenantID = normalizeTenantID($input['tenantID'] ?? null);
            $appointment_id = (int)($input['appointment_id'] ?? 0);
            $status = trim((string)($input['status'] ?? ''));

            $allowedStatuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];

            if ($appointment_id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Invalid appointment_id']);
            }

            if (!in_array($status, $allowedStatuses, true)) {
                respond(400, ['status' => 'error', 'message' => 'Invalid status']);
            }

            $stmt = $conn->prepare("
                UPDATE appointments
                SET status = ?, updated_at = NOW()
                WHERE appointment_id = ? AND tenantID = ?
            ");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param('sii', $status, $appointment_id, $tenantID);

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Update failed: ' . $err);
            }

            if ($stmt->affected_rows < 1) {
                $stmt->close();
                respond(404, ['status' => 'error', 'message' => 'Appointment not found for this tenant']);
            }

            $stmt->close();

            respond(200, [
                'status' => 'success',
                'message' => 'Appointment updated successfully',
                'data' => [
                    'appointment_id' => $appointment_id,
                    'tenantID' => $tenantID,
                    'status' => $status
                ]
            ]);
        }

        // Default POST action = create
        $tenantID = normalizeTenantID($input['tenantID'] ?? null);
        $user_id = (int)($input['user_id'] ?? 0);
        $vehicle_id = (int)($input['vehicle_id'] ?? 0);
        $appointment_date = trim((string)($input['appointment_date'] ?? ''));
        $appointment_time = trim((string)($input['appointment_time'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));
        $service_ids = normalizeServiceIds($input['service_ids'] ?? []);
        $total_amount = (float)($input['total_amount'] ?? 0);

        if ($user_id <= 0) {
            respond(400, ['status' => 'error', 'message' => 'Invalid or missing user_id']);
        }

        if ($vehicle_id <= 0) {
            respond(400, ['status' => 'error', 'message' => 'Invalid or missing vehicle_id']);
        }

        if (!validateDateValue($appointment_date)) {
            respond(400, ['status' => 'error', 'message' => 'Invalid appointment_date format. Use YYYY-MM-DD']);
        }

        if (!validateTimeValue($appointment_time)) {
            respond(400, ['status' => 'error', 'message' => 'Invalid appointment_time format. Use HH:MM or HH:MM:SS']);
        }

        if (empty($service_ids)) {
            respond(400, ['status' => 'error', 'message' => 'At least one service must be selected']);
        }

        if ($total_amount <= 0) {
            respond(400, ['status' => 'error', 'message' => 'Invalid total_amount']);
        }

        // Multi-tenant validation
        $userExists = rowExists(
            $conn,
            "SELECT id FROM users WHERE id = ? AND tenantID = ? LIMIT 1",
            'ii',
            $user_id,
            $tenantID
        );

        if (!$userExists) {
            respond(400, ['status' => 'error', 'message' => 'User does not belong to this tenant']);
        }

        $vehicleExists = rowExists(
            $conn,
            "SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND tenantID = ? AND user_id = ? LIMIT 1",
            'iii',
            $vehicle_id,
            $tenantID,
            $user_id
        );

        if (!$vehicleExists) {
            respond(400, ['status' => 'error', 'message' => 'Vehicle does not belong to this tenant/user']);
        }

        $conn->begin_transaction();

        // Load services for this tenant
        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
        $types = str_repeat('i', count($service_ids)) . 'i';

        $sql = "SELECT id, price
                FROM services
                WHERE id IN ($placeholders) AND tenantID = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Service prepare failed: ' . $conn->error);
        }

        $params = array_merge($service_ids, [$tenantID]);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Service query failed: ' . $err);
        }

        $result = $stmt->get_result();
        $services = [];
        $computedTotal = 0;

        while ($row = $result->fetch_assoc()) {
            $serviceId = (int)$row['id'];
            $price = (float)$row['price'];
            $services[$serviceId] = $price;
            $computedTotal += $price;
        }
        $stmt->close();

        if (count($services) !== count($service_ids)) {
            throw new Exception('One or more selected services are invalid for this tenant');
        }

        // Optional: trust backend total instead of frontend total
        $finalTotal = $computedTotal;

        $status = 'Pending';

        $stmt = $conn->prepare("
            INSERT INTO appointments
            (tenantID, user_id, vehicle_id, appointment_date, appointment_time, status, notes, total_amount, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmt) {
            throw new Exception('Appointment prepare failed: ' . $conn->error);
        }

        // 8 params = 8 type letters
        $stmt->bind_param(
            'iiissssd',
            $tenantID,
            $user_id,
            $vehicle_id,
            $appointment_date,
            $appointment_time,
            $status,
            $notes,
            $finalTotal
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Appointment insert failed: ' . $err);
        }

        $appointment_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO appointment_services
            (appointment_id, tenantID, service_id, service_price, duration_minutes, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception('Appointment services prepare failed: ' . $conn->error);
        }

        $duration_minutes = 0;
        $service_notes = '';

        foreach ($service_ids as $service_id) {
            $service_price = (float)$services[$service_id];

            $stmt->bind_param(
                'iiidss',
                $appointment_id,
                $tenantID,
                $service_id,
                $service_price,
                $duration_minutes,
                $service_notes
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Appointment service insert failed: ' . $err);
            }
        }
        $stmt->close();

        $paymentMethod = 'Pending';
        $paymentStatus = 'Pending';
        $referenceNumber = 'RR-' . str_pad((string)$appointment_id, 5, '0', STR_PAD_LEFT);
        $amountPaid = 0.00;
        $balance = $finalTotal;

        $stmt = $conn->prepare("
            INSERT INTO payments
            (tenantID, user_id, appointment_id, paymentAmount, amountPaid, balance, paymentMethod, paymentStatus, referenceNumber, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmt) {
            throw new Exception('Payment prepare failed: ' . $conn->error);
        }

        // 9 params = 9 type letters
        $stmt->bind_param(
            'iiidddsss',
            $tenantID,
            $user_id,
            $appointment_id,
            $finalTotal,
            $amountPaid,
            $balance,
            $paymentMethod,
            $paymentStatus,
            $referenceNumber
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Payment insert failed: ' . $err);
        }
        $stmt->close();

        $conn->commit();

        respond(201, [
            'status' => 'success',
            'message' => 'Appointment created successfully',
            'data' => [
                'appointment_id' => $appointment_id,
                'reference_number' => $referenceNumber,
                'status' => $status,
                'total_amount' => $finalTotal
            ]
        ]);
    }

    if ($method === 'GET') {
        $action = trim((string)($_GET['action'] ?? 'list'));
        $tenantID = normalizeTenantID($_GET['tenantID'] ?? null);

        if ($action === 'list') {
            $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            $limit = max(1, min((int)($_GET['limit'] ?? 50), 100));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            if ($user_id > 0) {
                $stmt = $conn->prepare("
                    SELECT *
                    FROM appointments
                    WHERE tenantID = ? AND user_id = ?
                    ORDER BY appointment_date DESC, appointment_time DESC
                    LIMIT ? OFFSET ?
                ");
                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('iiii', $tenantID, $user_id, $limit, $offset);
            } else {
                $stmt = $conn->prepare("
                    SELECT *
                    FROM appointments
                    WHERE tenantID = ?
                    ORDER BY appointment_date DESC, appointment_time DESC
                    LIMIT ? OFFSET ?
                ");
                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('iii', $tenantID, $limit, $offset);
            }

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Query failed: ' . $err);
            }

            $result = $stmt->get_result();
            $rows = [];

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $stmt->close();

            respond(200, ['status' => 'success', 'data' => $rows]);
        }

        if ($action === 'delete') {
            $appointment_id = (int)($_GET['appointment_id'] ?? 0);

            if ($appointment_id <= 0) {
                respond(400, ['status' => 'error', 'message' => 'Missing appointment_id']);
            }

            $conn->begin_transaction();

            $stmt = $conn->prepare("DELETE FROM appointment_services WHERE appointment_id = ? AND tenantID = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $appointment_id, $tenantID);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Delete appointment services failed: ' . $err);
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM payments WHERE appointment_id = ? AND tenantID = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $appointment_id, $tenantID);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Delete payments failed: ' . $err);
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ? AND tenantID = ?");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $appointment_id, $tenantID);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Delete appointment failed: ' . $err);
            }

            if ($stmt->affected_rows < 1) {
                $stmt->close();
                $conn->rollback();
                respond(404, ['status' => 'error', 'message' => 'Appointment not found for this tenant']);
            }

            $stmt->close();
            $conn->commit();

            respond(200, [
                'status' => 'success',
                'message' => 'Appointment deleted successfully',
                'data' => ['appointment_id' => $appointment_id]
            ]);
        }

        respond(400, ['status' => 'error', 'message' => 'Invalid action']);
    }

    respond(405, ['status' => 'error', 'message' => 'Method not allowed']);

} catch (Throwable $e) {
    if ($conn && $conn->errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {}
    }

    respond(500, [
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}