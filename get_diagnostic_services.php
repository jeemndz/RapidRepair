<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true]);
    exit;
}

if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'db.php not found.'
    ]);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available.'
    ]);
    exit;
}

$diagnosticId = isset($_GET['diagnostic_id']) ? (int) $_GET['diagnostic_id'] : 0;

if ($diagnosticId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid diagnostic_id.',
        'services' => [],
        'booked_services' => []
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get diagnostic report
|--------------------------------------------------------------------------
*/
$reportSql = "
    SELECT
        diagnostic_id,
        appointment_id,
        repair_job_id,
        tenantID,
        mechanic_name,
        problem_description,
        findings,
        recommended_action,
        estimated_total,
        customer_approval,
        diagnosis_status,
        customer_notes,
        created_at,
        updated_at
    FROM diagnostic_reports
    WHERE diagnostic_id = ?
    LIMIT 1
";

$reportStmt = $conn->prepare($reportSql);

if (!$reportStmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare diagnostic report query.'
    ]);
    exit;
}

$reportStmt->bind_param('i', $diagnosticId);
$reportStmt->execute();
$reportResult = $reportStmt->get_result();
$report = $reportResult ? $reportResult->fetch_assoc() : null;
$reportStmt->close();

if (!$report) {
    echo json_encode([
        'success' => false,
        'message' => 'Diagnostic report not found.',
        'services' => [],
        'booked_services' => []
    ]);
    exit;
}

$tenantID = (int) $report['tenantID'];
$appointmentId = (int) $report['appointment_id'];

/*
|--------------------------------------------------------------------------
| Get recommended diagnostic services
|--------------------------------------------------------------------------
*/
$servicesSql = "
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
";

$servicesStmt = $conn->prepare($servicesSql);

if (!$servicesStmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare recommended services query.'
    ]);
    exit;
}

$servicesStmt->bind_param('ii', $diagnosticId, $tenantID);
$servicesStmt->execute();
$servicesResult = $servicesStmt->get_result();

$recommendedServices = [];

while ($servicesResult && $row = $servicesResult->fetch_assoc()) {
    $recommendedServices[] = [
        'report_service_id' => (int) $row['report_service_id'],
        'diagnostic_id' => (int) $row['diagnostic_id'],
        'tenantID' => (int) $row['tenantID'],
        'service_id' => (int) $row['service_id'],
        'parent_service_id' => $row['parent_service_id'] !== null ? (int) $row['parent_service_id'] : null,
        'parent_service_name' => $row['parent_service_name'] ?? '',
        'service_name' => $row['service_name'] ?? 'Recommended Service',
        'description' => $row['description'] ?? '',
        'category' => $row['category'] ?? '',
        'service_price' => (float) $row['service_price'],
        'duration_minutes' => (int) $row['duration_minutes'],
        'approval_status' => $row['approval_status'] ?? 'Pending',
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}

$servicesStmt->close();

/*
|--------------------------------------------------------------------------
| Get original booked services
|--------------------------------------------------------------------------
*/
$bookedServices = [];

if ($appointmentId > 0) {
    $bookedSql = "
        SELECT
            aps.appointment_service_id,
            aps.appointment_id,
            aps.tenantID,
            aps.service_id,
            aps.service_price,
            aps.duration_minutes,
            aps.notes,
            s.service_name,
            s.description,
            s.service_type,
            s.parent_service_id,
            s.category
        FROM appointment_services aps
        LEFT JOIN services s
            ON s.service_id = aps.service_id
            AND s.tenantID = aps.tenantID
        WHERE aps.appointment_id = ?
          AND aps.tenantID = ?
        ORDER BY aps.appointment_service_id ASC
    ";

    $bookedStmt = $conn->prepare($bookedSql);

    if ($bookedStmt) {
        $bookedStmt->bind_param('ii', $appointmentId, $tenantID);
        $bookedStmt->execute();
        $bookedResult = $bookedStmt->get_result();

        while ($bookedResult && $row = $bookedResult->fetch_assoc()) {
            $bookedServices[] = [
                'appointment_service_id' => (int) $row['appointment_service_id'],
                'appointment_id' => (int) $row['appointment_id'],
                'tenantID' => (int) $row['tenantID'],
                'service_id' => (int) $row['service_id'],
                'service_name' => $row['service_name'] ?? 'Booked Service',
                'description' => $row['description'] ?? '',
                'service_type' => $row['service_type'] ?? '',
                'parent_service_id' => $row['parent_service_id'] !== null ? (int) $row['parent_service_id'] : null,
                'category' => $row['category'] ?? '',
                'service_price' => (float) $row['service_price'],
                'duration_minutes' => (int) $row['duration_minutes'],
                'notes' => $row['notes'] ?? '',
            ];
        }

        $bookedStmt->close();
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Diagnostic services loaded successfully.',
    'diagnostic_report' => $report,
    'services' => $recommendedServices,
    'booked_services' => $bookedServices,
]);