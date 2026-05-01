<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
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

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$reportServiceId = 0;

if (isset($input['report_service_id'])) {
    $reportServiceId = (int) $input['report_service_id'];
} elseif (isset($input['service_id'])) {
    $reportServiceId = (int) $input['service_id'];
}

$status = isset($input['status']) ? trim((string) $input['status']) : '';

$allowedStatuses = ['Pending', 'Approved', 'Declined'];

if ($reportServiceId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid report_service_id',
        'received' => $input
    ]);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid approval status'
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $diagnosticStmt = mysqli_prepare(
        $conn,
        "SELECT diagnostic_id, tenantID
         FROM diagnostic_report_services
         WHERE report_service_id = ?
         LIMIT 1"
    );

    if (!$diagnosticStmt) {
        throw new Exception('Unable to prepare diagnostic service lookup.');
    }

    mysqli_stmt_bind_param($diagnosticStmt, 'i', $reportServiceId);
    mysqli_stmt_execute($diagnosticStmt);
    $diagnosticResult = mysqli_stmt_get_result($diagnosticStmt);
    $diagnosticRow = $diagnosticResult ? mysqli_fetch_assoc($diagnosticResult) : null;
    mysqli_stmt_close($diagnosticStmt);

    if (!$diagnosticRow) {
        throw new Exception('Diagnostic recommended service not found.');
    }

    $diagnosticId = (int) $diagnosticRow['diagnostic_id'];

    $updateStmt = mysqli_prepare(
        $conn,
        "UPDATE diagnostic_report_services
         SET approval_status = ?, updated_at = NOW()
         WHERE report_service_id = ?
         LIMIT 1"
    );

    if (!$updateStmt) {
        throw new Exception('Unable to prepare service status update.');
    }

    mysqli_stmt_bind_param($updateStmt, 'si', $status, $reportServiceId);

    if (!mysqli_stmt_execute($updateStmt)) {
        throw new Exception('Failed to update service status.');
    }

    mysqli_stmt_close($updateStmt);

    $summaryStmt = mysqli_prepare(
        $conn,
        "SELECT
            COUNT(*) AS total_services,
            SUM(CASE WHEN approval_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN approval_status = 'Declined' THEN 1 ELSE 0 END) AS declined_count,
            COALESCE(SUM(CASE WHEN approval_status = 'Approved' THEN service_price ELSE 0 END), 0) AS approved_total
         FROM diagnostic_report_services
         WHERE diagnostic_id = ?"
    );

    if (!$summaryStmt) {
        throw new Exception('Unable to prepare diagnostic summary.');
    }

    mysqli_stmt_bind_param($summaryStmt, 'i', $diagnosticId);
    mysqli_stmt_execute($summaryStmt);
    $summaryResult = mysqli_stmt_get_result($summaryStmt);
    $summary = $summaryResult ? mysqli_fetch_assoc($summaryResult) : [];
    mysqli_stmt_close($summaryStmt);

    $totalServices = (int) ($summary['total_services'] ?? 0);
    $pendingCount = (int) ($summary['pending_count'] ?? 0);
    $approvedCount = (int) ($summary['approved_count'] ?? 0);
    $declinedCount = (int) ($summary['declined_count'] ?? 0);
    $approvedTotal = (float) ($summary['approved_total'] ?? 0);

    $customerApproval = 'Pending';
    $diagnosisStatus = 'Submitted';

    if ($totalServices > 0 && $pendingCount > 0) {
        $customerApproval = 'Pending';
        $diagnosisStatus = 'Submitted';
    } elseif ($approvedCount > 0) {
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
            approved_at = CASE WHEN ? = 'Approved' AND approved_at IS NULL THEN NOW() ELSE approved_at END,
            declined_at = CASE WHEN ? = 'Declined' AND declined_at IS NULL THEN NOW() ELSE declined_at END,
            updated_at = NOW()
         WHERE diagnostic_id = ?
         LIMIT 1"
    );

    if (!$reportUpdateStmt) {
        throw new Exception('Unable to prepare diagnostic report update.');
    }

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

    if (!mysqli_stmt_execute($reportUpdateStmt)) {
        throw new Exception('Failed to update diagnostic report.');
    }

    mysqli_stmt_close($reportUpdateStmt);

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Service status updated.',
        'diagnostic_id' => $diagnosticId,
        'report_service_id' => $reportServiceId,
        'approval_status' => $status,
        'customer_approval' => $customerApproval,
        'diagnosis_status' => $diagnosisStatus,
        'approved_total' => $approvedTotal,
        'summary' => [
            'total_services' => $totalServices,
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'declined_count' => $declinedCount
        ]
    ]);
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>