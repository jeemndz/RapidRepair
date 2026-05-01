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
    $stmt = $conn->prepare("
        SELECT 
            repair_job_id,
            appointment_id,
            labor_total,
            parts_total,
            grand_total
        FROM repair_jobs
        WHERE repair_job_id = ?
        AND tenantID = ?
        AND user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('iii', $repair_job_id, $tenantID, $user_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        throw new Exception('Repair job not found');
    }

    $appointment_id = (int)$job['appointment_id'];
    $labor_total = round((float)$job['labor_total'], 2);
    $parts_total = round((float)$job['parts_total'], 2);
    $grand_total = round((float)$job['grand_total'], 2);

    if ($grand_total <= 0) {
        throw new Exception('Invalid grand total amount');
    }

    $stmt = $conn->prepare("
        SELECT payment_id
        FROM payments
        WHERE appointment_id = ?
        AND tenantID = ?
        AND user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('iii', $appointment_id, $tenantID, $user_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        throw new Exception('Payment already exists for this job');
    }

    $stmt = $conn->prepare("
        SELECT 
            rjs.service_id,
            COALESCE(s.service_name, CONCAT('Service #', rjs.service_id)) AS service_name,
            rjs.service_price,
            rjs.estimated_duration_minutes
        FROM repair_job_services rjs
        LEFT JOIN services s 
            ON s.service_id = rjs.service_id
            AND s.tenantID = rjs.tenantID
        WHERE rjs.repair_job_id = ?
        AND rjs.tenantID = ?
    ");
    $stmt->bind_param('ii', $repair_job_id, $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];

    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'service_id' => (int)$row['service_id'],
            'service_name' => $row['service_name'],
            'service_price' => (float)$row['service_price'],
            'estimated_duration_minutes' => (int)$row['estimated_duration_minutes'],
        ];
    }

    $stmt->close();

    $invoiceData = [
        'repair_job_id' => $repair_job_id,
        'appointment_id' => $appointment_id,
        'labor_total' => $labor_total,
        'parts_total' => $parts_total,
        'grand_total' => $grand_total,
        'services' => $services,
    ];

    $servicesJson = json_encode(
        $services,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $referenceNumber = 'INV-' . date('YmdHis') . '-' . $repair_job_id;

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
            remarks,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, 0.00, ?, 'Cash', 'Pending', ?, ?, NOW(), NOW())
    ");

    $stmt->bind_param(
        'iiiddss',
        $tenantID,
        $user_id,
        $appointment_id,
        $grand_total,
        $grand_total,
        $referenceNumber,
        $servicesJson
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to create payment: ' . $stmt->error);
    }

    $payment_id = $conn->insert_id;
    $stmt->close();

    $conn->commit();

    respond('success', 'Payment created', [
        'payment_id' => $payment_id,
        'repair_job_id' => $repair_job_id,
        'appointment_id' => $appointment_id,
        'amount' => $grand_total,
        'balance' => $grand_total,
        'reference' => $referenceNumber,
        'invoice' => $invoiceData
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
?>