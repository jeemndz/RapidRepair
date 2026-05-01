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

$diagnosticId = isset($input['diagnostic_id']) ? (int) $input['diagnostic_id'] : 0;

if ($diagnosticId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid diagnostic_id']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $reportStmt = mysqli_prepare(
        $conn,
        "SELECT diagnostic_id, appointment_id, repair_job_id, tenantID, diagnosis_status, customer_approval
         FROM diagnostic_reports
         WHERE diagnostic_id = ?
         LIMIT 1"
    );

    if (!$reportStmt) {
        throw new Exception('Unable to prepare diagnostic query');
    }

    mysqli_stmt_bind_param($reportStmt, 'i', $diagnosticId);
    mysqli_stmt_execute($reportStmt);
    $reportResult = mysqli_stmt_get_result($reportStmt);
    $report = mysqli_fetch_assoc($reportResult);
    mysqli_stmt_close($reportStmt);

    if (!$report) {
        throw new Exception('Diagnostic report not found');
    }

    $repairJobId = (int) $report['repair_job_id'];
    $appointmentId = (int) $report['appointment_id'];
    $tenantID = (int) $report['tenantID'];

    if ($repairJobId <= 0 || $tenantID <= 0) {
        throw new Exception('Diagnostic report is missing repair_job_id or tenantID');
    }

    $approvedStmt = mysqli_prepare(
        $conn,
        "SELECT 
            report_service_id,
            service_id,
            parent_service_id,
            service_name,
            service_price,
            duration_minutes
         FROM diagnostic_report_services
         WHERE diagnostic_id = ?
           AND tenantID = ?
           AND approval_status = 'Approved'"
    );

    if (!$approvedStmt) {
        throw new Exception('Unable to prepare approved services query');
    }

    mysqli_stmt_bind_param($approvedStmt, 'ii', $diagnosticId, $tenantID);
    mysqli_stmt_execute($approvedStmt);
    $approvedResult = mysqli_stmt_get_result($approvedStmt);

    $approvedServices = [];
    $approvedTotal = 0.00;

    while ($row = mysqli_fetch_assoc($approvedResult)) {
        $approvedServices[] = $row;
        $approvedTotal += (float) ($row['service_price'] ?? 0);
    }

    mysqli_stmt_close($approvedStmt);

    if (count($approvedServices) === 0) {
        throw new Exception('No approved services found');
    }

    $insertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO repair_job_services
            (
                repair_job_id,
                tenantID,
                service_id,
                service_price,
                estimated_duration_minutes,
                service_status,
                remarks
            )
         SELECT ?, ?, ?, ?, ?, 'Pending', ?
         WHERE NOT EXISTS (
            SELECT 1
            FROM repair_job_services
            WHERE repair_job_id = ?
              AND tenantID = ?
              AND service_id = ?
            LIMIT 1
         )"
    );

    if (!$insertStmt) {
        throw new Exception('Unable to prepare repair service insert');
    }

    $insertedServices = 0;

    foreach ($approvedServices as $service) {
        $serviceId = (int) ($service['service_id'] ?? 0);
        $price = (float) ($service['service_price'] ?? 0);
        $duration = (int) ($service['duration_minutes'] ?? 0);
        $serviceName = trim((string) ($service['service_name'] ?? 'Recommended Service'));
        $remarks = 'Approved from diagnostic recommendation: ' . $serviceName;

        if ($serviceId <= 0) {
            continue;
        }

        mysqli_stmt_bind_param(
            $insertStmt,
            'iiidisiii',
            $repairJobId,
            $tenantID,
            $serviceId,
            $price,
            $duration,
            $remarks,
            $repairJobId,
            $tenantID,
            $serviceId
        );

        if (!mysqli_stmt_execute($insertStmt)) {
            throw new Exception('Failed to add approved service: ' . mysqli_stmt_error($insertStmt));
        }

        if (mysqli_stmt_affected_rows($insertStmt) > 0) {
            $insertedServices++;
        }
    }

    mysqli_stmt_close($insertStmt);

    $totalStmt = mysqli_prepare(
        $conn,
        "SELECT COALESCE(SUM(service_price), 0) AS labor_total
         FROM repair_job_services
         WHERE repair_job_id = ?
           AND tenantID = ?
           AND service_status <> 'Cancelled'"
    );

    if (!$totalStmt) {
        throw new Exception('Unable to calculate repair total');
    }

    mysqli_stmt_bind_param($totalStmt, 'ii', $repairJobId, $tenantID);
    mysqli_stmt_execute($totalStmt);
    $totalResult = mysqli_stmt_get_result($totalStmt);
    $totalRow = mysqli_fetch_assoc($totalResult);
    mysqli_stmt_close($totalStmt);

    $laborTotal = (float) ($totalRow['labor_total'] ?? 0);

    $updateReportStmt = mysqli_prepare(
        $conn,
        "UPDATE diagnostic_reports
         SET 
            customer_approval = 'Approved',
            diagnosis_status = 'Approved',
            estimated_total = ?,
            approved_at = COALESCE(approved_at, NOW()),
            updated_at = NOW()
         WHERE diagnostic_id = ?
           AND tenantID = ?
         LIMIT 1"
    );

    if (!$updateReportStmt) {
        throw new Exception('Unable to prepare diagnostic update');
    }

    mysqli_stmt_bind_param($updateReportStmt, 'dii', $approvedTotal, $diagnosticId, $tenantID);
    mysqli_stmt_execute($updateReportStmt);
    mysqli_stmt_close($updateReportStmt);

    $updateJobStmt = mysqli_prepare(
        $conn,
        "UPDATE repair_jobs
         SET 
            job_status = 'In Progress',
            labor_total = ?,
            grand_total = ? + IFNULL(parts_total, 0),
            work_started_at = COALESCE(work_started_at, NOW()),
            updated_at = NOW()
         WHERE repair_job_id = ?
           AND tenantID = ?
         LIMIT 1"
    );

    if (!$updateJobStmt) {
        throw new Exception('Unable to prepare repair job update');
    }

    mysqli_stmt_bind_param(
        $updateJobStmt,
        'ddii',
        $laborTotal,
        $laborTotal,
        $repairJobId,
        $tenantID
    );

    mysqli_stmt_execute($updateJobStmt);
    mysqli_stmt_close($updateJobStmt);

    if ($appointmentId > 0) {
        $updateAppointmentStmt = mysqli_prepare(
            $conn,
            "UPDATE appointments
             SET status = 'In Progress',
                 total_amount = ?,
                 updated_at = NOW()
             WHERE appointment_id = ?
               AND tenantID = ?
             LIMIT 1"
        );

        if ($updateAppointmentStmt) {
            mysqli_stmt_bind_param($updateAppointmentStmt, 'dii', $laborTotal, $appointmentId, $tenantID);
            mysqli_stmt_execute($updateAppointmentStmt);
            mysqli_stmt_close($updateAppointmentStmt);
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Diagnostic approved and repair services added',
        'diagnostic_id' => $diagnosticId,
        'repair_job_id' => $repairJobId,
        'appointment_id' => $appointmentId,
        'inserted_services' => $insertedServices,
        'approved_services' => count($approvedServices),
        'approved_total' => $approvedTotal,
        'labor_total' => $laborTotal
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}