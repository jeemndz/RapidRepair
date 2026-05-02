<?php
// api/appointment_history.php
// Put this file in: C:\xampp\htdocs\RapidRepair\api\appointment_history.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../db.php";

function jsonResponse($success, $message, $data = null, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

$tenantID = isset($_GET["tenantID"]) ? (int) $_GET["tenantID"] : 0;
$user_id = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : 0;

if ($tenantID <= 0 || $user_id <= 0) {
    jsonResponse(false, "tenantID and user_id are required.", [], 400);
}

/*
  IMPORTANT:
  This endpoint assumes you have a repair_jobs table with appointment_id and repair_job_id.

  Expected relationship:
  appointments.appointment_id = repair_jobs.appointment_id
  repair_jobs.repair_job_id = repair_job_services.repair_job_id
  repair_job_services.service_id = services.service_id
*/

$sql = "
    SELECT
        a.appointment_id,
        a.tenantID,
        a.user_id,
        a.vehicle_id,
        a.appointment_date,
        a.appointment_time,
        a.status AS appointment_status,
        a.notes,
        a.total_amount,
        a.created_at,
        a.updated_at,

        vi.brand,
        vi.model,
        vi.plate_number,
        vi.year_model,
        vi.color,

        rj.repair_job_id,

        COALESCE(
            SUM(CASE WHEN rjs.service_status <> 'Cancelled' THEN rjs.service_price ELSE 0 END),
            0
        ) AS services_total,

        GROUP_CONCAT(
            DISTINCT s.service_name
            ORDER BY s.service_type ASC, s.service_name ASC
            SEPARATOR '||'
        ) AS service_names_concat,

        GROUP_CONCAT(
            DISTINCT CONCAT_WS(
                '::',
                rjs.repair_job_service_id,
                rjs.service_id,
                REPLACE(COALESCE(s.service_name, ''), '::', ' '),
                COALESCE(rjs.service_price, s.price, 0),
                COALESCE(rjs.service_status, ''),
                COALESCE(s.category, ''),
                COALESCE(rjs.technician_name, ''),
                COALESCE(rjs.remarks, '')
            )
            ORDER BY s.service_type ASC, s.service_name ASC
            SEPARATOR '||'
        ) AS services_concat

    FROM appointments a
    LEFT JOIN vehicleinformation vi
        ON vi.vehicle_id = a.vehicle_id
        AND vi.tenantID = a.tenantID

    LEFT JOIN repair_jobs rj
        ON rj.appointment_id = a.appointment_id
        AND rj.tenantID = a.tenantID

    LEFT JOIN repair_job_services rjs
        ON rjs.repair_job_id = rj.repair_job_id
        AND rjs.tenantID = a.tenantID

    LEFT JOIN services s
        ON s.service_id = rjs.service_id
        AND s.tenantID = a.tenantID

    WHERE
        a.tenantID = ?
        AND a.user_id = ?
        AND a.status IN ('Completed', 'Cancelled')

    GROUP BY
        a.appointment_id,
        a.tenantID,
        a.user_id,
        a.vehicle_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
        a.created_at,
        a.updated_at,
        vi.brand,
        vi.model,
        vi.plate_number,
        vi.year_model,
        vi.color,
        rj.repair_job_id

    ORDER BY
        a.appointment_date DESC,
        a.appointment_time DESC,
        a.appointment_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    jsonResponse(false, "Prepare failed: " . mysqli_error($conn), [], 500);
}

mysqli_stmt_bind_param($stmt, "ii", $tenantID, $user_id);

if (!mysqli_stmt_execute($stmt)) {
    jsonResponse(false, "Execute failed: " . mysqli_stmt_error($stmt), [], 500);
}

$result = mysqli_stmt_get_result($stmt);
$appointments = [];

while ($row = mysqli_fetch_assoc($result)) {
    $serviceNames = [];
    $services = [];

    if (!empty($row["service_names_concat"])) {
        $serviceNames = array_values(array_filter(explode("||", $row["service_names_concat"])));
    }

    if (!empty($row["services_concat"])) {
        $serviceRows = array_values(array_filter(explode("||", $row["services_concat"])));

        foreach ($serviceRows as $serviceRow) {
            $parts = explode("::", $serviceRow);

            $services[] = [
                "repair_job_service_id" => isset($parts[0]) ? (int) $parts[0] : null,
                "service_id" => isset($parts[1]) ? (int) $parts[1] : null,
                "service_name" => $parts[2] ?? "",
                "service_price" => isset($parts[3]) ? (float) $parts[3] : 0,
                "service_status" => $parts[4] ?? "",
                "category" => $parts[5] ?? "",
                "technician_name" => $parts[6] ?? "",
                "remarks" => $parts[7] ?? "",
            ];
        }
    }

    $vehicleParts = array_filter([
        $row["year_model"] ?? "",
        $row["brand"] ?? "",
        $row["model"] ?? "",
        !empty($row["plate_number"]) ? "(" . $row["plate_number"] . ")" : ""
    ]);

    $appointments[] = [
        "appointment_id" => (int) $row["appointment_id"],
        "tenantID" => (int) $row["tenantID"],
        "user_id" => (int) $row["user_id"],
        "vehicle_id" => (int) $row["vehicle_id"],
        "repair_job_id" => $row["repair_job_id"] !== null ? (int) $row["repair_job_id"] : null,
        "appointment_date" => $row["appointment_date"],
        "appointment_time" => $row["appointment_time"],
        "appointment_status" => $row["appointment_status"],
        "notes" => $row["notes"],
        "total_amount" => (float) $row["total_amount"],
        "services_total" => (float) $row["services_total"],
        "brand" => $row["brand"],
        "model" => $row["model"],
        "plate_number" => $row["plate_number"],
        "year_model" => $row["year_model"],
        "color" => $row["color"],
        "vehicle" => trim(implode(" ", $vehicleParts)),
        "service_names" => $serviceNames,
        "services" => $services,
        "created_at" => $row["created_at"],
        "updated_at" => $row["updated_at"],
    ];
}

mysqli_stmt_close($stmt);

jsonResponse(true, "Appointment history loaded.", $appointments);
