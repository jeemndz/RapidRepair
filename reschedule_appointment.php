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
$appointmentID = isset($input['appointment_id']) ? (int) $input['appointment_id'] : 0;
$repairJobID = isset($input['repair_job_id']) ? (int) $input['repair_job_id'] : 0;
$appointmentDate = isset($input['appointment_date']) ? trim((string) $input['appointment_date']) : '';
$appointmentTime = isset($input['appointment_time']) ? trim((string) $input['appointment_time']) : '';

if ($tenantID <= 0 || $userID <= 0 || $appointmentID <= 0 || $appointmentDate === '' || $appointmentTime === '') {
    json_response(400, [
        'status' => 'error',
        'message' => 'tenantID, user_id, appointment_id, appointment_date, and appointment_time are required.',
    ]);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
    json_response(400, ['status' => 'error', 'message' => 'appointment_date must use YYYY-MM-DD format.']);
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointmentTime)) {
    json_response(400, ['status' => 'error', 'message' => 'appointment_time must use HH:MM or HH:MM:SS format.']);
}

if (strlen($appointmentTime) === 5) {
    $appointmentTime .= ':00';
}

$newDateTime = $appointmentDate . ' ' . $appointmentTime;

mysqli_begin_transaction($conn);

try {
    $findAppointmentSql = "
        SELECT appointment_id, status
        FROM appointments
        WHERE appointment_id = ? AND tenantID = ? AND user_id = ?
        LIMIT 1
    ";
    $appointmentFindStmt = mysqli_prepare($conn, $findAppointmentSql);
    if (!$appointmentFindStmt) {
        throw new Exception('Failed to prepare appointment lookup: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($appointmentFindStmt, 'iii', $appointmentID, $tenantID, $userID);
    mysqli_stmt_execute($appointmentFindStmt);
    $appointmentResult = mysqli_stmt_get_result($appointmentFindStmt);
    $appointment = mysqli_fetch_assoc($appointmentResult);
    mysqli_stmt_close($appointmentFindStmt);

    if (!$appointment) {
        throw new Exception('Appointment not found for this account.');
    }

    if (in_array($appointment['status'], ['Completed', 'Cancelled'], true)) {
        throw new Exception('This appointment can no longer be rescheduled.');
    }

    if ($repairJobID > 0) {
        $findRepairSql = "
            SELECT repair_job_id, job_status
            FROM repair_jobs
            WHERE repair_job_id = ? AND tenantID = ? AND user_id = ?
            LIMIT 1
        ";
        $repairFindStmt = mysqli_prepare($conn, $findRepairSql);
        if (!$repairFindStmt) {
            throw new Exception('Failed to prepare repair lookup: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($repairFindStmt, 'iii', $repairJobID, $tenantID, $userID);
        mysqli_stmt_execute($repairFindStmt);
        $repairResult = mysqli_stmt_get_result($repairFindStmt);
        $repair = mysqli_fetch_assoc($repairResult);
        mysqli_stmt_close($repairFindStmt);

        if (!$repair) {
            throw new Exception('Repair job not found for this account.');
        }

        if ($repair['job_status'] !== 'Queued') {
            throw new Exception('Only queued repair jobs can be rescheduled.');
        }
    }

    $appointmentSql = "
        UPDATE appointments
        SET appointment_date = ?, appointment_time = ?, updated_at = NOW()
        WHERE appointment_id = ? AND tenantID = ? AND user_id = ?
    ";
    $appointmentStmt = mysqli_prepare($conn, $appointmentSql);
    if (!$appointmentStmt) {
        throw new Exception('Failed to prepare appointment update: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($appointmentStmt, 'ssiii', $appointmentDate, $appointmentTime, $appointmentID, $tenantID, $userID);
    mysqli_stmt_execute($appointmentStmt);
    mysqli_stmt_close($appointmentStmt);

    if ($repairJobID > 0) {
        $repairSql = "
            UPDATE repair_jobs
            SET check_in_time = ?, updated_at = NOW()
            WHERE repair_job_id = ? AND tenantID = ? AND user_id = ? AND job_status = 'Queued'
        ";
        $repairStmt = mysqli_prepare($conn, $repairSql);
        if (!$repairStmt) {
            throw new Exception('Failed to prepare repair update: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($repairStmt, 'siii', $newDateTime, $repairJobID, $tenantID, $userID);
        mysqli_stmt_execute($repairStmt);
        mysqli_stmt_close($repairStmt);
    }

    mysqli_commit($conn);

    json_response(200, [
        'status' => 'success',
        'message' => 'Appointment rescheduled successfully.',
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
    ]);
} catch (Exception $error) {
    mysqli_rollback($conn);
    json_response(400, ['status' => 'error', 'message' => $error->getMessage()]);
}
