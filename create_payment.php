<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function respond($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$repair_job_id = (int)($data['repair_job_id'] ?? 0);
$tenantID = (int)($data['tenantID'] ?? 0);
$user_id = (int)($data['user_id'] ?? 0);

if (!$repair_job_id || !$tenantID || !$user_id) {
    respond('error', 'Missing required fields');
}

$conn->begin_transaction();

try {

    // ✅ 1. CHECK IF PAYMENT ALREADY EXISTS
    $stmt = $conn->prepare("
        SELECT payment_id 
        FROM payments 
        WHERE appointment_id = (
            SELECT appointment_id FROM repair_jobs WHERE repair_job_id = ?
        ) LIMIT 1
    ");
    $stmt->bind_param('i', $repair_job_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        throw new Exception('Payment already exists for this job');
    }

    // ✅ 2. GET JOB + APPOINTMENT
    $stmt = $conn->prepare("
        SELECT rj.appointment_id
        FROM repair_jobs rj
        WHERE rj.repair_job_id = ? AND rj.tenantID = ?
    ");
    $stmt->bind_param('ii', $repair_job_id, $tenantID);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        throw new Exception('Repair job not found');
    }

    $appointment_id = $job['appointment_id'];

    // ✅ 3. GET SERVICES TOTAL
    $stmt = $conn->prepare("
        SELECT 
            service_id,
            service_price,
            estimated_duration_minutes
        FROM repair_job_services
        WHERE repair_job_id = ?
    ");
    $stmt->bind_param('i', $repair_job_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
        $total += (float)$row['service_price'];
    }
    $stmt->close();

    if ($total <= 0) {
        throw new Exception('Invalid total amount');
    }

    // ✅ 4. GENERATE REFERENCE
    $referenceNumber = 'INV-' . time();

    // ✅ 5. INSERT PAYMENT
    $stmt = $conn->prepare("
        INSERT INTO payments (
            tenantID,
            user_id,
            appointment_id,
            paymentAmount,
            amountPaid,
            balance,
            paymentMethod,
            paymentStatus,
            referenceNumber,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, 0, ?, 'Pending', ?, NOW(), NOW())
    ");

    $stmt->bind_param(
        'iiidds',
        $tenantID,
        $user_id,
        $appointment_id,
        $total,
        $total,
        $referenceNumber
    );

    $stmt->execute();
    $payment_id = $conn->insert_id;
    $stmt->close();

    // ✅ 6. SAVE SERVICES INTO JSON (for mobile display)
    $stmt = $conn->prepare("
        UPDATE payments 
        SET remarks = ?
        WHERE payment_id = ?
    ");

    $servicesJson = json_encode($services);
    $stmt->bind_param('si', $servicesJson, $payment_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    respond('success', 'Payment created', [
        'payment_id' => $payment_id,
        'amount' => $total,
        'reference' => $referenceNumber
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}