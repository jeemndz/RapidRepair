<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$userID = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($tenantID <= 0 || $userID <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'tenantID and user_id are required'
    ]);
    exit;
}

$stmt = mysqli_prepare($conn, "
    SELECT
        dr.diagnostic_id,
        dr.appointment_id,
        dr.repair_job_id,
        dr.tenantID,
        dr.mechanic_name,
        dr.problem_description,
        dr.findings,
        dr.recommended_action,
        dr.estimated_total,
        dr.customer_approval,
        dr.diagnosis_status,
        dr.created_at,
        dr.updated_at,
        rj.job_order_no,
        rj.user_id,
        rj.vehicle_id
    FROM diagnostic_reports dr
    INNER JOIN repair_jobs rj
        ON rj.repair_job_id = dr.repair_job_id
        AND rj.tenantID = dr.tenantID
    WHERE dr.tenantID = ?
      AND rj.user_id = ?
      AND dr.diagnosis_status IN ('Submitted', 'Approved')
      AND EXISTS (
          SELECT 1
          FROM diagnostic_report_services drs
          WHERE drs.diagnostic_id = dr.diagnostic_id
            AND drs.tenantID = dr.tenantID
      )
    ORDER BY
      CASE WHEN dr.customer_approval = 'Pending' THEN 0 ELSE 1 END,
      dr.updated_at DESC,
      dr.created_at DESC
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare latest diagnostic query'
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $tenantID, $userID);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$diagnostic = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$diagnostic) {
    echo json_encode([
        'success' => true,
        'has_estimate' => false,
        'message' => 'No diagnostic estimate found',
        'diagnostic_id' => null,
        'diagnostic' => null,
        'services' => []
    ]);
    exit;
}

$diagnosticID = (int) $diagnostic['diagnostic_id'];

$services = [];
$serviceStmt = mysqli_prepare($conn, "
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
        drs.created_at,
        drs.updated_at,
        s.description,
        s.category,
        s.service_type,
        ps.service_name AS parent_service_name
    FROM diagnostic_report_services drs
    LEFT JOIN services s
        ON s.service_id = drs.service_id
        AND s.tenantID = drs.tenantID
    LEFT JOIN services ps
        ON ps.service_id = drs.parent_service_id
        AND ps.tenantID = drs.tenantID
    WHERE drs.diagnostic_id = ?
      AND drs.tenantID = ?
    ORDER BY drs.created_at ASC, drs.report_service_id ASC
");

if ($serviceStmt) {
    mysqli_stmt_bind_param($serviceStmt, 'ii', $diagnosticID, $tenantID);
    mysqli_stmt_execute($serviceStmt);
    $serviceResult = mysqli_stmt_get_result($serviceStmt);

    while ($row = $serviceResult ? mysqli_fetch_assoc($serviceResult) : null) {
        $services[] = $row;
    }

    mysqli_stmt_close($serviceStmt);
}

echo json_encode([
    'success' => true,
    'status' => 'success',
    'has_estimate' => true,
    'diagnostic_id' => $diagnosticID,
    'diagnostic' => $diagnostic,
    'diagnostic_report' => $diagnostic,
    'services' => $services,
    'recommended_services' => $services,
    'debug' => [
        'tenantID' => $tenantID,
        'user_id' => $userID,
        'recommended_count' => count($services)
    ]
]);
exit;
?>