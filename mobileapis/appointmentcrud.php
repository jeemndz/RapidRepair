<?php
/**
 * Customer Appointment API
 * Booking only (NO PAYMENT CREATION)
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
        if (is_array($json)) $data = $json;
    }

    return $data;
}

function normalizeServiceIds($ids)
{
    if (!is_array($ids)) return [];

    return array_values(array_unique(array_filter(array_map('intval', $ids))));
}

function handleCreateAppointment($conn, $data)
{
    $tenantID = (int)$data['tenantID'];
    $user_id = (int)$data['user_id'];
    $vehicle_id = (int)$data['vehicle_id'];
    $appointment_date = $data['appointment_date'];
    $appointment_time = $data['appointment_time'];
    $notes = $data['notes'] ?? '';
    $service_ids = normalizeServiceIds($data['service_ids']);

    if (!$tenantID || !$user_id || !$vehicle_id || empty($service_ids)) {
        errorResponse('Missing required fields');
    }

    // GET SERVICE PRICES
    $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
    $types = str_repeat('i', count($service_ids)) . 'i';

    $stmt = $conn->prepare("
        SELECT service_id, price, duration_minutes
        FROM services
        WHERE service_id IN ($placeholders) AND tenantID = ?
    ");

    $params = array_merge($service_ids, [$tenantID]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[$row['service_id']] = $row;
    }
    $stmt->close();

    if (count($services) !== count($service_ids)) {
        errorResponse('Invalid services selected');
    }

    // COMPUTE TOTAL
    $total = 0;
    foreach ($services as $s) {
        $total += $s['price'];
    }

    $conn->begin_transaction();

    try {
        // ✅ INSERT APPOINTMENT
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                tenantID, user_id, vehicle_id,
                appointment_date, appointment_time,
                status, notes, total_amount,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, NOW(), NOW())
        ");

        $stmt->bind_param(
            'iiisssd',
            $tenantID,
            $user_id,
            $vehicle_id,
            $appointment_date,
            $appointment_time,
            $notes,
            $total
        );

        $stmt->execute();
        $appointment_id = $conn->insert_id;
        $stmt->close();

        // ✅ INSERT APPOINTMENT SERVICES
        $stmt = $conn->prepare("
            INSERT INTO appointment_services (
                appointment_id, tenantID,
                service_id, service_price,
                duration_minutes, notes, created_at
            )
            VALUES (?, ?, ?, ?, ?, '', NOW())
        ");

        foreach ($services as $id => $s) {
            $stmt->bind_param(
                'iiidi',
                $appointment_id,
                $tenantID,
                $id,
                $s['price'],
                $s['duration_minutes']
            );
            $stmt->execute();
        }
        $stmt->close();

        // ✅ CREATE REPAIR JOB
        $jobOrderNo = 'RR-' . str_pad($appointment_id, 5, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("
            INSERT INTO repair_jobs (
                tenantID, appointment_id, user_id, vehicle_id,
                job_order_no, job_status, priority, concern,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'Queued', 'Normal', ?, NOW(), NOW())
        ");

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

        // ✅ INSERT REPAIR JOB SERVICES
        $stmt = $conn->prepare("
            INSERT INTO repair_job_services (
                repair_job_id, tenantID,
                service_id, service_price,
                estimated_duration_minutes,
                service_status,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
        ");

        foreach ($services as $id => $s) {
            $stmt->bind_param(
                'iiidi',
                $repair_job_id,
                $tenantID,
                $id,
                $s['price'],
                $s['duration_minutes']
            );
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();

        successResponse('Booking created successfully', [
            'appointment_id' => $appointment_id,
            'job_order_no' => $jobOrderNo,
            'total_amount' => $total
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