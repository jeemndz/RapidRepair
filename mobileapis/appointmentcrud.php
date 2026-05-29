<?php
/**
 * Customer Appointment API
 * Booking only + mechanic slot capacity + reservation fee fields
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

function errorResponse($message, $httpCode = 400)
{
    responseJson('error', $message, null, $httpCode);
}

function successResponse($message = '', $data = null, $httpCode = 200)
{
    responseJson('success', $message, $data, $httpCode);
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

function normalizeServiceIds($ids)
{
    if (!is_array($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', $ids))));
}

function getActiveMechanicCount($conn, $tenantID)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total_mechanics
        FROM roles
        WHERE tenantID = ?
        AND is_active = 1
        AND status = 'Active'
        AND (
            role_name LIKE '%Mechanic%'
            OR role_name LIKE '%Technician%'
        )
    ");

    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)($row['total_mechanics'] ?? 0);
}

function getBookedSlotCount($conn, $tenantID, $appointmentDate, $appointmentTime)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS booked_count
        FROM appointments
        WHERE tenantID = ?
        AND appointment_date = ?
        AND appointment_time = ?
        AND status NOT IN ('Cancelled', 'No Show')
    ");

    $stmt->bind_param('iss', $tenantID, $appointmentDate, $appointmentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)($row['booked_count'] ?? 0);
}

function validateSlotAvailability($conn, $tenantID, $appointmentDate, $appointmentTime)
{
    $activeMechanics = getActiveMechanicCount($conn, $tenantID);

    if ($activeMechanics <= 0) {
        errorResponse('No active mechanic available for this shop.', 409);
    }

    $bookedCount = getBookedSlotCount($conn, $tenantID, $appointmentDate, $appointmentTime);

    if ($bookedCount >= $activeMechanics) {
        errorResponse('This time slot is already full. Please choose another time.', 409);
    }

    return [
        'active_mechanics' => $activeMechanics,
        'booked_count' => $bookedCount,
        'remaining_slots' => $activeMechanics - $bookedCount
    ];
}

function handleCreateAppointment($conn, $data)
{
    $tenantID = (int)($data['tenantID'] ?? 0);
    $user_id = (int)($data['user_id'] ?? 0);
    $vehicle_id = (int)($data['vehicle_id'] ?? 0);
    $appointment_date = trim($data['appointment_date'] ?? '');
    $appointment_time = trim($data['appointment_time'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $service_ids = normalizeServiceIds($data['service_ids'] ?? []);

    $reservation_fee = 500.00;
    $reservation_paid = 0;
    $reservation_payment_status = 'Unpaid';

    if (!$tenantID || !$user_id || !$vehicle_id || !$appointment_date || !$appointment_time || empty($service_ids)) {
        errorResponse('Missing required fields');
    }

    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        errorResponse('Past dates are not allowed.');
    }

    $slotInfo = validateSlotAvailability($conn, $tenantID, $appointment_date, $appointment_time);

    $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
    $types = str_repeat('i', count($service_ids)) . 'i';

    $stmt = $conn->prepare("
        SELECT service_id, price, duration_minutes
        FROM services
        WHERE service_id IN ($placeholders)
        AND tenantID = ?
        AND status = 'Active'
    ");

    if (!$stmt) {
        errorResponse('Failed to prepare service query: ' . $conn->error, 500);
    }

    $params = array_merge($service_ids, [$tenantID]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[(int)$row['service_id']] = $row;
    }
    $stmt->close();

    if (count($services) !== count($service_ids)) {
        errorResponse('Invalid services selected');
    }

    $total = 0;
    foreach ($services as $s) {
        $total += (float)$s['price'];
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                tenantID,
                user_id,
                vehicle_id,
                appointment_date,
                appointment_time,
                status,
                notes,
                total_amount,
                reservation_fee,
                reservation_paid,
                reservation_payment_status,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare appointment insert: ' . $conn->error);
        }

        $stmt->bind_param(
            'iiisssddis',
            $tenantID,
            $user_id,
            $vehicle_id,
            $appointment_date,
            $appointment_time,
            $notes,
            $total,
            $reservation_fee,
            $reservation_paid,
            $reservation_payment_status
        );

        $stmt->execute();
        $appointment_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO appointment_services (
                appointment_id,
                tenantID,
                service_id,
                service_price,
                duration_minutes,
                notes,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, '', NOW())
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare appointment services insert: ' . $conn->error);
        }

        foreach ($services as $id => $s) {
            $price = (float)$s['price'];
            $duration = (int)$s['duration_minutes'];

            $stmt->bind_param(
                'iiidi',
                $appointment_id,
                $tenantID,
                $id,
                $price,
                $duration
            );
            $stmt->execute();
        }
        $stmt->close();

        $jobOrderNo = 'RR-' . str_pad($appointment_id, 5, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("
            INSERT INTO repair_jobs (
                tenantID,
                appointment_id,
                user_id,
                vehicle_id,
                job_order_no,
                job_status,
                priority,
                concern,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'Queued', 'Normal', ?, NOW(), NOW())
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare repair job insert: ' . $conn->error);
        }

        $stmt->bind_param(
            'iiiiss',
            $tenantID,
            $appointment_id,
            $user_id,
            $vehicle_id,
            $jobOrderNo,
            $notes
        );

        $stmt->execute();
        $repair_job_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO repair_job_services (
                repair_job_id,
                tenantID,
                service_id,
                service_price,
                estimated_duration_minutes,
                service_status,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare repair job services insert: ' . $conn->error);
        }

        foreach ($services as $id => $s) {
            $price = (float)$s['price'];
            $duration = (int)$s['duration_minutes'];

            $stmt->bind_param(
                'iiidi',
                $repair_job_id,
                $tenantID,
                $id,
                $price,
                $duration
            );
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();

        successResponse('Booking created. Please pay the ₱500 reservation fee to confirm your appointment.', [
            'appointment_id' => $appointment_id,
            'repair_job_id' => $repair_job_id,
            'job_order_no' => $jobOrderNo,
            'referenceNumber' => $jobOrderNo,
            'status' => 'Pending',
            'job_status' => 'Queued',
            'total_amount' => $total,
            'reservation_fee' => $reservation_fee,
            'reservation_paid' => $reservation_paid,
            'reservation_payment_status' => $reservation_payment_status,
            'slot_info' => $slotInfo
        ], 201);

    } catch (Exception $e) {
        $conn->rollback();
        errorResponse($e->getMessage(), 500);
    }
}

$data = getRequestData();

if (($data['action'] ?? '') !== 'create') {
    errorResponse('Invalid action');
}

handleCreateAppointment($conn, $data);