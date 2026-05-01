<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    echo json_encode(['success' => false, 'message' => 'db.php not found']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$diagnosticId = isset($input['diagnostic_id']) ? (int) $input['diagnostic_id'] : 0;

if ($diagnosticId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid diagnostic_id']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $reportStmt = mysqli_prepare(
        $conn,
        "SELECT repair_job_id, tenantID
         FROM diagnostic_reports
         WHERE diagnostic_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($reportStmt, 'i', $diagnosticId);
    mysqli_stmt_execute($reportStmt);
    $reportResult = mysqli_stmt_get_result($reportStmt);
    $report = mysqli_fetch_assoc($reportResult);
    mysqli_stmt_close($reportStmt);

    if (!$report) {
        throw new Exception('Diagnostic report not found');
    }

    $repairJobId = (int) $report['repair_job_id'];
    $tenantID = (int) $report['tenantID'];

    $approvedStmt = mysqli_prepare(
        $conn,
        "SELECT service_id, service_price, duration_minutes, service_name
         FROM diagnostic_report_services
         WHERE diagnostic_id = ?
           AND approval_status = 'Approved'"
    );

    mysqli_stmt_bind_param($approvedStmt, 'i', $diagnosticId);
    mysqli_stmt_execute($approvedStmt);
    $approvedResult = mysqli_stmt_get_result($approvedStmt);

    $approvedServices = [];
    $approvedTotal = 0;

    while ($row = mysqli_fetch_assoc($approvedResult)) {
        $approvedServices[] = $row;
        $approvedTotal += (float) $row['service_price'];
    }

    mysqli_stmt_close($approvedStmt);

    if (count($approvedServices) === 0) {
        throw new Exception('No approved services found');
    }

    $insertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO repair_job_services
            (repair_job_id, tenantID, service_id, service_price, estimated_duration_minutes, service_status, remarks)
         VALUES (?, ?, ?, ?, ?, 'Pending', ?)"
    );

    foreach ($approvedServices as $service) {
        $serviceId = (int) $service['service_id'];
        $price = (float) $service['service_price'];
        $duration = (int) $service['duration_minutes'];
        $remarks = 'Approved from diagnostic recommendation: ' . $service['service_name'];

        mysqli_stmt_bind_param(
            $insertStmt,
            'iiidis',
            $repairJobId,
            $tenantID,
            $serviceId,
            $price,
            $duration,
            $remarks
        );

        mysqli_stmt_execute($insertStmt);
    }

    mysqli_stmt_close($insertStmt);

    $updateReportStmt = mysqli_prepare(
        $conn,
        "UPDATE diagnostic_reports
         SET 
            customer_approval = 'Approved',
            diagnosis_status = 'Approved',
            estimated_total = ?,
            approved_at = NOW(),
            updated_at = NOW()
         WHERE diagnostic_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($updateReportStmt, 'di', $approvedTotal, $diagnosticId);
    mysqli_stmt_execute($updateReportStmt);
    mysqli_stmt_close($updateReportStmt);

    $updateJobStmt = mysqli_prepare(
        $conn,
        "UPDATE repair_jobs
         SET 
            job_status = 'In Progress',
            labor_total = ?,
            grand_total = ? + IFNULL(parts_total, 0),
            updated_at = NOW()
         WHERE repair_job_id = ?
           AND tenantID = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $updateJobStmt,
        'ddii',
        $approvedTotal,
        $approvedTotal,
        $repairJobId,
        $tenantID
    );

    mysqli_stmt_execute($updateJobStmt);
    mysqli_stmt_close($updateJobStmt);

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Diagnostic approved and repair services added',
        'repair_job_id' => $repairJobId,
        'approved_total' => $approvedTotal
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}