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

$reportServiceId = isset($input['service_id']) ? (int) $input['service_id'] : 0;
$status = isset($input['status']) ? trim($input['status']) : '';

$allowedStatuses = ['Pending', 'Approved', 'Declined'];

if ($reportServiceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid service_id']);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid approval status']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $updateStmt = mysqli_prepare(
        $conn,
        "UPDATE diagnostic_report_services
         SET approval_status = ?, updated_at = NOW()
         WHERE report_service_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($updateStmt, 'si', $status, $reportServiceId);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    $diagnosticStmt = mysqli_prepare(
        $conn,
        "SELECT diagnostic_id 
         FROM diagnostic_report_services 
         WHERE report_service_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($diagnosticStmt, 'i', $reportServiceId);
    mysqli_stmt_execute($diagnosticStmt);
    $diagnosticResult = mysqli_stmt_get_result($diagnosticStmt);
    $diagnosticRow = mysqli_fetch_assoc($diagnosticResult);
    mysqli_stmt_close($diagnosticStmt);

    if (!$diagnosticRow) {
        throw new Exception('Diagnostic service not found');
    }

    $diagnosticId = (int) $diagnosticRow['diagnostic_id'];

    $summaryStmt = mysqli_prepare(
        $conn,
        "SELECT 
            COUNT(*) AS total_services,
            SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN approval_status = 'Declined' THEN 1 ELSE 0 END) AS declined_count,
            SUM(CASE WHEN approval_status = 'Approved' THEN service_price ELSE 0 END) AS approved_total
         FROM diagnostic_report_services
         WHERE diagnostic_id = ?"
    );

    mysqli_stmt_bind_param($summaryStmt, 'i', $diagnosticId);
    mysqli_stmt_execute($summaryStmt);
    $summaryResult = mysqli_stmt_get_result($summaryStmt);
    $summary = mysqli_fetch_assoc($summaryResult);
    mysqli_stmt_close($summaryStmt);

    $totalServices = (int) ($summary['total_services'] ?? 0);
    $approvedCount = (int) ($summary['approved_count'] ?? 0);
    $declinedCount = (int) ($summary['declined_count'] ?? 0);
    $approvedTotal = (float) ($summary['approved_total'] ?? 0);

    $customerApproval = 'Pending';
    $diagnosisStatus = 'Submitted';

    if ($totalServices > 0 && $approvedCount > 0) {
        $customerApproval = 'Approved';
        $diagnosisStatus = 'Approved';
    } elseif ($totalServices > 0 && $declinedCount === $totalServices) {
        $customerApproval = 'Declined';
        $diagnosisStatus = 'Declined';
    }

    $reportUpdateStmt = mysqli_prepare(
        $conn,
        "UPDATE diagnostic_reports
         SET 
            customer_approval = ?,
            diagnosis_status = ?,
            estimated_total = ?,
            approved_at = CASE WHEN ? = 'Approved' THEN NOW() ELSE approved_at END,
            declined_at = CASE WHEN ? = 'Declined' THEN NOW() ELSE declined_at END,
            updated_at = NOW()
         WHERE diagnostic_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $reportUpdateStmt,
        'ssdssi',
        $customerApproval,
        $diagnosisStatus,
        $approvedTotal,
        $customerApproval,
        $customerApproval,
        $diagnosticId
    );

    mysqli_stmt_execute($reportUpdateStmt);
    mysqli_stmt_close($reportUpdateStmt);

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Service status updated',
        'diagnostic_id' => $diagnosticId,
        'customer_approval' => $customerApproval,
        'diagnosis_status' => $diagnosisStatus,
        'approved_total' => $approvedTotal
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}