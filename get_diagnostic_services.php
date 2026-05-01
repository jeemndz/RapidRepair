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

$diagnosticId = isset($_GET['diagnostic_id']) ? (int) $_GET['diagnostic_id'] : 0;

if ($diagnosticId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid diagnostic_id']);
    exit;
}

$sql = "
    SELECT 
        drs.report_service_id,
        drs.diagnostic_id,
        drs.tenantID,
        drs.service_id,
        drs.parent_service_id,
        drs.service_name,
        drs.service_price,
        drs.duration_minutes,
        drs.approval_status,
        dr.problem_description,
        dr.findings,
        dr.recommended_action,
        dr.estimated_total,
        dr.customer_approval,
        dr.diagnosis_status
    FROM diagnostic_report_services drs
    INNER JOIN diagnostic_reports dr 
        ON dr.diagnostic_id = drs.diagnostic_id
    WHERE drs.diagnostic_id = ?
    ORDER BY drs.created_at ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $diagnosticId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$services = [];

while ($row = mysqli_fetch_assoc($result)) {
    $services[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'diagnostic_id' => $diagnosticId,
    'services' => $services
]);