<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

require_once __DIR__ . '/db.php';

function respond($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function createPaymentFromRepairJob($conn, $repair_job_id, $tenantID, $user_id) {
    // Get repair job with grand total
    $stmt = $conn->prepare("
        SELECT appointment_id, grand_total
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
        throw new Exception('Repair job not found.');
    }

    $appointment_id = (int)$job['appointment_id'];
    $total = round((float)$job['grand_total'], 2);

    if ($total <= 0) {
        throw new Exception('Invalid grand total amount.');
    }

    // Prevent duplicate payment
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
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        return [
            'created' => false,
            'payment_id' => (int)$existing['payment_id'],
            'message' => 'Payment already exists.'
        ];
    }

    // Get services for invoice display
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

    $referenceNumber = 'INV-' . date('YmdHis') . '-' . $repair_job_id;
    $servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Create payment
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
        $total,
        $total,
        $referenceNumber,
        $servicesJson
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to create payment: ' . $stmt->error);
    }

    $payment_id = $conn->insert_id;
    $stmt->close();

    return [
        'created' => true,
        'payment_id' => $payment_id,
        'amount' => $total,
        'referenceNumber' => $referenceNumber,
        'services' => $services
    ];
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    $data = $_POST;
}

$repair_job_id = (int)($data['repair_job_id'] ?? 0);
$tenantID = (int)($data['tenantID'] ?? 0);
$user_id = (int)($data['user_id'] ?? 0);
$new_status = trim((string)($data['job_status'] ?? ''));

$allowedStatuses = [
    'Queued',
    'In Progress',
    'Diagnostics',
    'Waiting for Parts',
    'Quality Check',
    'Ready for Pickup',
    'Completed',
    'Cancelled'
];

if ($repair_job_id <= 0 || $tenantID <= 0 || $user_id <= 0) {
    respond('error', 'Missing required fields.');
}

if (!in_array($new_status, $allowedStatuses, true)) {
    respond('error', 'Invalid job status.');
}

$conn->begin_transaction();

try {
    // Get old status
    $stmt = $conn->prepare("
        SELECT job_status
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
        throw new Exception('Repair job not found.');
    }

    $old_status = $job['job_status'];

    // Update repair job status
    $stmt = $conn->prepare("
        UPDATE repair_jobs
        SET 
            job_status = ?,
            completed_at = CASE 
                WHEN ? = 'Completed' AND completed_at IS NULL THEN NOW()
                ELSE completed_at
            END,
            updated_at = NOW()
        WHERE repair_job_id = ?
        AND tenantID = ?
        AND user_id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'ssiii',
        $new_status,
        $new_status,
        $repair_job_id,
        $tenantID,
        $user_id
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to update repair job status.');
    }

    $stmt->close();

    $paymentResult = null;

    // ✅ Auto-create payment ONLY when status changes to Completed
    if ($old_status !== 'Completed' && $new_status === 'Completed') {
        $paymentResult = createPaymentFromRepairJob(
            $conn,
            $repair_job_id,
            $tenantID,
            $user_id
        );
    }

    $conn->commit();

    respond('success', 'Repair job status updated.', [
        'repair_job_id' => $repair_job_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'payment' => $paymentResult
    ]);

} catch (Exception $e) {
    $conn->rollback();
    respond('error', $e->getMessage());
}
?>