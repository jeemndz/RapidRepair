<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

function json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_input()
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['status' => 'error', 'message' => 'POST method is required.']);
}

$input = read_input();
$tenantID = isset($input['tenantID']) ? (int) $input['tenantID'] : 0;
$userID = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$repairJobID = isset($input['repair_job_id']) ? (int) $input['repair_job_id'] : 0;
$appointmentID = isset($input['appointment_id']) ? (int) $input['appointment_id'] : 0;

if ($tenantID <= 0 || $userID <= 0 || $repairJobID <= 0) {
    json_response(400, [
        'status' => 'error',
        'message' => 'tenantID, user_id, and repair_job_id are required.',
    ]);
}

mysqli_begin_transaction($conn);

try {
    $findSql = "
        SELECT repair_job_id, appointment_id, job_status
        FROM repair_jobs
        WHERE repair_job_id = ?
          AND tenantID = ?
          AND user_id = ?
        LIMIT 1
    ";

    $findStmt = mysqli_prepare($conn, $findSql);
    if (!$findStmt) {
        throw new Exception('Failed to prepare repair lookup: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($findStmt, 'iii', $repairJobID, $tenantID, $userID);
    mysqli_stmt_execute($findStmt);
    $result = mysqli_stmt_get_result($findStmt);
    $repairJob = mysqli_fetch_assoc($result);
    mysqli_stmt_close($findStmt);

    if (!$repairJob) {
        throw new Exception('Repair job not found for this account.');
    }

    if (in_array($repairJob['job_status'], ['Completed', 'Cancelled'], true)) {
        throw new Exception('This repair job can no longer be cancelled.');
    }

    $realAppointmentID = $appointmentID > 0 ? $appointmentID : (int) $repairJob['appointment_id'];

    $repairSql = "
        UPDATE repair_jobs
        SET job_status = 'Cancelled', updated_at = NOW()
        WHERE repair_job_id = ? AND tenantID = ? AND user_id = ?
    ";
    $repairStmt = mysqli_prepare($conn, $repairSql);
    if (!$repairStmt) {
        throw new Exception('Failed to prepare repair cancellation: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($repairStmt, 'iii', $repairJobID, $tenantID, $userID);
    mysqli_stmt_execute($repairStmt);
    mysqli_stmt_close($repairStmt);

    $servicesSql = "
        UPDATE repair_job_services
        SET service_status = 'Cancelled', updated_at = NOW()
        WHERE repair_job_id = ? AND tenantID = ? AND service_status <> 'Completed'
    ";
    $servicesStmt = mysqli_prepare($conn, $servicesSql);
    if (!$servicesStmt) {
        throw new Exception('Failed to prepare service cancellation: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($servicesStmt, 'ii', $repairJobID, $tenantID);
    mysqli_stmt_execute($servicesStmt);
    mysqli_stmt_close($servicesStmt);

    if ($realAppointmentID > 0) {
        $appointmentSql = "
            UPDATE appointments
            SET status = 'Cancelled', updated_at = NOW()
            WHERE appointment_id = ? AND tenantID = ? AND user_id = ?
        ";
        $appointmentStmt = mysqli_prepare($conn, $appointmentSql);
        if (!$appointmentStmt) {
            throw new Exception('Failed to prepare appointment cancellation: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($appointmentStmt, 'iii', $realAppointmentID, $tenantID, $userID);
        mysqli_stmt_execute($appointmentStmt);
        mysqli_stmt_close($appointmentStmt);
    }

    mysqli_commit($conn);

    json_response(200, [
        'status' => 'success',
        'message' => 'Repair job cancelled successfully.',
    ]);
} catch (Exception $error) {
    mysqli_rollback($conn);
    json_response(400, ['status' => 'error', 'message' => $error->getMessage()]);
}
