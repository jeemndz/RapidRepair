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

$diagnosticId = isset($_GET['diagnostic_id']) ? (int) $_GET['diagnostic_id'] : 0;

if ($diagnosticId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid diagnostic_id',
        'received' => $_GET
    ]);
    exit;
}

$diagnosticStmt = mysqli_prepare($conn, "
    SELECT
        dr.*,
        rj.job_order_no,
        rj.user_id,
        rj.vehicle_id,
        rj.job_status
    FROM diagnostic_reports dr
    LEFT JOIN repair_jobs rj
        ON rj.repair_job_id = dr.repair_job_id
        AND rj.tenantID = dr.tenantID
    WHERE dr.diagnostic_id = ?
    LIMIT 1
");

if (!$diagnosticStmt) {
    echo json_encode(['success' => false, 'message' => 'Unable to prepare diagnostic query']);
    exit;
}

mysqli_stmt_bind_param($diagnosticStmt, 'i', $diagnosticId);
mysqli_stmt_execute($diagnosticStmt);
$diagnosticResult = mysqli_stmt_get_result($diagnosticStmt);
$diagnostic = $diagnosticResult ? mysqli_fetch_assoc($diagnosticResult) : null;
mysqli_stmt_close($diagnosticStmt);

if (!$diagnostic) {
    echo json_encode([
        'success' => false,
        'message' => 'Diagnostic report not found',
        'diagnostic_id' => $diagnosticId
    ]);
    exit;
}

$tenantID = (int) ($diagnostic['tenantID'] ?? 0);
$appointmentId = (int) ($diagnostic['appointment_id'] ?? 0);

$recommendedServices = [];

$servicesStmt = mysqli_prepare($conn, "
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

if (!$servicesStmt) {
    echo json_encode(['success' => false, 'message' => 'Unable to prepare recommended services query']);
    exit;
}

mysqli_stmt_bind_param($servicesStmt, 'ii', $diagnosticId, $tenantID);
mysqli_stmt_execute($servicesStmt);
$servicesResult = mysqli_stmt_get_result($servicesStmt);

while ($row = $servicesResult ? mysqli_fetch_assoc($servicesResult) : null) {
    $recommendedServices[] = $row;
}

mysqli_stmt_close($servicesStmt);

$bookedServices = [];

if ($appointmentId > 0 && $tenantID > 0) {
    $bookedStmt = mysqli_prepare($conn, "
        SELECT
            aps.appointment_service_id,
            aps.appointment_id,
            aps.tenantID,
            aps.service_id,
            aps.service_price,
            aps.duration_minutes,
            aps.notes,
            aps.created_at,
            s.service_name,
            s.description,
            s.category,
            s.service_type,
            s.parent_service_id,
            ps.service_name AS parent_service_name
        FROM appointment_services aps
        LEFT JOIN services s
            ON s.service_id = aps.service_id
            AND s.tenantID = aps.tenantID
        LEFT JOIN services ps
            ON ps.service_id = s.parent_service_id
            AND ps.tenantID = s.tenantID
        WHERE aps.appointment_id = ?
          AND aps.tenantID = ?
        ORDER BY aps.created_at ASC, aps.appointment_service_id ASC
    ");

    if ($bookedStmt) {
        mysqli_stmt_bind_param($bookedStmt, 'ii', $appointmentId, $tenantID);
        mysqli_stmt_execute($bookedStmt);
        $bookedResult = mysqli_stmt_get_result($bookedStmt);

        while ($row = $bookedResult ? mysqli_fetch_assoc($bookedResult) : null) {
            $bookedServices[] = $row;
        }

        mysqli_stmt_close($bookedStmt);
    }
}

echo json_encode([
    'success' => true,
    'status' => 'success',
    'diagnostic_id' => $diagnosticId,
    'diagnostic' => $diagnostic,
    'diagnostic_report' => $diagnostic,
    'services' => $recommendedServices,
    'recommended_services' => $recommendedServices,
    'booked_services' => $bookedServices,
    'appointment_services' => $bookedServices,
    'debug' => [
        'appointment_id' => $appointmentId,
        'tenantID' => $tenantID,
        'recommended_count' => count($recommendedServices),
        'booked_count' => count($bookedServices)
    ]
]);
exit;
?>