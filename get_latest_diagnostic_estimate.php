<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$userID = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($tenantID <= 0 || $userID <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'tenantID and user_id are required'
    ]);
    exit;
}

$reportStmt = mysqli_prepare($conn, "
    SELECT
        dr.*
    FROM diagnostic_reports dr
    INNER JOIN repair_jobs rj 
        ON rj.repair_job_id = dr.repair_job_id
        AND rj.tenantID = dr.tenantID
    WHERE dr.tenantID = ?
      AND rj.user_id = ?
      AND dr.diagnosis_status IN ('Submitted', 'Approved')
      AND dr.customer_approval = 'Pending'
    ORDER BY dr.updated_at DESC, dr.created_at DESC
    LIMIT 1
");

mysqli_stmt_bind_param($reportStmt, 'ii', $tenantID, $userID);
mysqli_stmt_execute($reportStmt);
$reportResult = mysqli_stmt_get_result($reportStmt);
$report = mysqli_fetch_assoc($reportResult);
mysqli_stmt_close($reportStmt);

if (!$report) {
    echo json_encode([
        'success' => true,
        'has_estimate' => false,
        'message' => 'No pending diagnostic estimate found',
        'diagnostic' => null,
        'services' => []
    ]);
    exit;
}

$diagnosticID = (int) $report['diagnostic_id'];

$services = [];
$serviceStmt = mysqli_prepare($conn, "
    SELECT
        report_service_id,
        diagnostic_id,
        tenantID,
        service_id,
        parent_service_id,
        service_name,
        service_price,
        duration_minutes,
        approval_status,
        created_at,
        updated_at
    FROM diagnostic_report_services
    WHERE diagnostic_id = ?
      AND tenantID = ?
    ORDER BY created_at ASC
");

mysqli_stmt_bind_param($serviceStmt, 'ii', $diagnosticID, $tenantID);
mysqli_stmt_execute($serviceResult = mysqli_stmt_get_result($serviceStmt));

$result = mysqli_stmt_get_result($serviceStmt);
while ($row = mysqli_fetch_assoc($result)) {
    $services[] = $row;
}
mysqli_stmt_close($serviceStmt);

echo json_encode([
    'success' => true,
    'has_estimate' => true,
    'diagnostic_id' => $diagnosticID,
    'diagnostic' => $report,
    'services' => $services
]);