<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';

function json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function format_money($value)
{
    return number_format((float) ($value ?? 0), 2);
}

function format_datetime_label($value)
{
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return 'No schedule yet';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return 'No schedule yet';
    }

    $today = date('Y-m-d');
    $date = date('Y-m-d', $timestamp);
    $prefix = date('M d, Y', $timestamp);

    if ($date === $today) {
        $prefix = 'Today, ' . date('M d', $timestamp);
    } elseif ($date === date('Y-m-d', strtotime('+1 day'))) {
        $prefix = 'Tomorrow, ' . date('M d', $timestamp);
    }

    return $prefix . ' • ' . date('h:i A', $timestamp);
}

function estimated_ready_label($value, $status)
{
    if ($status === 'Ready for Pickup') {
        return 'Vehicle is ready for pickup';
    }

    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return 'Estimated finish not set';
    }

    $finish = strtotime($value);
    if (!$finish) {
        return 'Estimated finish not set';
    }

    $diff = $finish - time();
    if ($diff <= 0) {
        return 'Estimated finish time reached';
    }

    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($hours > 0) {
        return 'Estimated Ready in ' . $hours . ' hr' . ($hours > 1 ? 's' : '') . ($minutes > 0 ? ' ' . $minutes . ' min' : '');
    }

    return 'Estimated Ready in ' . max(1, $minutes) . ' min';
}

function normalize_image_url($path)
{
    if (empty($path)) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $base = $host ? ($scheme . '://' . $host) : '';

    return $base . '/' . ltrim($path, '/');
}

$tenantID = isset($_GET['tenantID']) ? (int) $_GET['tenantID'] : 0;
$userID = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($tenantID <= 0 || $userID <= 0) {
    json_response(400, [
        'status' => 'error',
        'message' => 'tenantID and user_id are required.',
    ]);
}

$defaultStatuses = ['Queued', 'In Progress', 'Diagnostics', 'Waiting for Parts', 'Quality Check', 'Ready for Pickup'];
$requestedStatuses = isset($_GET['statuses']) ? explode(',', (string) $_GET['statuses']) : $defaultStatuses;
$allowedStatuses = array_values(array_intersect($defaultStatuses, array_map('trim', $requestedStatuses)));

if (empty($allowedStatuses)) {
    $allowedStatuses = $defaultStatuses;
}

$placeholders = implode(',', array_fill(0, count($allowedStatuses), '?'));
$typeString = 'ii' . str_repeat('s', count($allowedStatuses));
$params = array_merge([$tenantID, $userID], $allowedStatuses);

$sql = "
    SELECT
        rj.repair_job_id,
        rj.tenantID,
        rj.appointment_id,
        rj.user_id,
        rj.vehicle_id,
        rj.job_order_no,
        rj.bay_no,
        rj.assigned_technician,
        rj.job_status,
        rj.priority,
        rj.concern,
        rj.diagnosis_notes,
        rj.progress_notes,
        rj.check_in_time,
        rj.work_started_at,
        rj.estimated_finish_at,
        rj.labor_total,
        rj.parts_total,
        rj.grand_total,
        rj.created_at,
        rj.updated_at,
        vi.brand,
        vi.model,
        vi.year_model,
        vi.fuel_type,
        vi.transmission_type,
        vi.engine_number,
        vi.mileage_km,
        vi.vin_number,
        vi.plate_number,
        vi.color,
        vi.vehicle_image,
        COALESCE(COUNT(rjs.repair_job_service_id), 0) AS service_count,
        COALESCE(SUM(CASE WHEN rjs.service_status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_service_count,
        COALESCE(SUM(rjs.service_price), 0) AS services_total,
        COALESCE(SUM(rjs.estimated_duration_minutes), 0) AS estimated_minutes,
        GROUP_CONCAT(
            DISTINCT CONCAT('Service #', rjs.service_id, ' - ', rjs.service_status)
            ORDER BY rjs.repair_job_service_id
            SEPARATOR ', '
        ) AS service_summary
    FROM repair_jobs rj
    INNER JOIN vehicleinformation vi
        ON vi.vehicle_id = rj.vehicle_id
        AND vi.tenantID = rj.tenantID
        AND vi.user_id = rj.user_id
    LEFT JOIN repair_job_services rjs
        ON rjs.repair_job_id = rj.repair_job_id
        AND rjs.tenantID = rj.tenantID
    WHERE rj.tenantID = ?
      AND rj.user_id = ?
      AND rj.job_status IN ($placeholders)
    GROUP BY rj.repair_job_id
    ORDER BY
        CASE rj.job_status
            WHEN 'In Progress' THEN 1
            WHEN 'Diagnostics' THEN 2
            WHEN 'Waiting for Parts' THEN 3
            WHEN 'Quality Check' THEN 4
            WHEN 'Ready for Pickup' THEN 5
            WHEN 'Queued' THEN 6
            ELSE 7
        END,
        COALESCE(rj.work_started_at, rj.check_in_time, rj.created_at) DESC
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    json_response(500, [
        'status' => 'error',
        'message' => 'Failed to prepare active appointments query: ' . mysqli_error($conn),
    ]);
}

mysqli_stmt_bind_param($stmt, $typeString, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$appointments = [];
$activeCount = 0;
$waitingCount = 0;
$todayCount = 0;
$nextItem = null;
$latestEstimate = null;

while ($row = mysqli_fetch_assoc($result)) {
    $status = $row['job_status'];

    if (!in_array($status, ['Completed', 'Cancelled'], true)) {
        $activeCount++;
    }

    if (in_array($status, ['Queued', 'Waiting for Parts'], true)) {
        $waitingCount++;
    }

    $scheduleSource = $row['work_started_at'] ?: ($row['check_in_time'] ?: $row['created_at']);
    if (!empty($scheduleSource) && date('Y-m-d', strtotime($scheduleSource)) === date('Y-m-d')) {
        $todayCount++;
    }

    if (!empty($row['estimated_finish_at']) && $row['estimated_finish_at'] !== '0000-00-00 00:00:00') {
        $finishTime = strtotime($row['estimated_finish_at']);
        if ($finishTime && ($latestEstimate === null || $finishTime > $latestEstimate)) {
            $latestEstimate = $finishTime;
        }
    }

    $vehicleTitle = trim(($row['year_model'] ?: '') . ' ' . ($row['brand'] ?: '') . ' ' . ($row['model'] ?: ''));
    if ($vehicleTitle === '') {
        $vehicleTitle = 'Vehicle #' . $row['vehicle_id'];
    }

    $serviceCount = (int) $row['service_count'];
    $completedServices = (int) $row['completed_service_count'];
    $estimatedMinutes = (int) $row['estimated_minutes'];

    $serviceTitle = $serviceCount > 0
        ? $serviceCount . ' Repair Service' . ($serviceCount > 1 ? 's' : '')
        : 'Repair Job';

    $serviceDetailParts = [];
    if (!empty($row['concern'])) {
        $serviceDetailParts[] = 'Concern: ' . $row['concern'];
    }
    if (!empty($row['assigned_technician'])) {
        $serviceDetailParts[] = 'Technician: ' . $row['assigned_technician'];
    }
    if (!empty($row['bay_no'])) {
        $serviceDetailParts[] = 'Bay: ' . $row['bay_no'];
    }

    $serviceDetail = !empty($serviceDetailParts)
        ? implode(' • ', $serviceDetailParts)
        : ($row['service_summary'] ?: 'No service details added yet');

    $appointments[] = [
        'id' => 'repair-job-' . $row['repair_job_id'],
        'repairJobId' => (int) $row['repair_job_id'],
        'appointmentId' => (int) $row['appointment_id'],
        'vehicleId' => (int) $row['vehicle_id'],
        'jobOrderNo' => $row['job_order_no'],
        'vehicle' => $vehicleTitle,
        'brand' => $row['brand'],
        'model' => $row['model'],
        'yearModel' => $row['year_model'],
        'status' => strtoupper($status),
        'rawStatus' => $status,
        'priority' => $row['priority'],
        'color' => $row['color'] ?: 'No color',
        'plateNumber' => $row['plate_number'] ?: 'No plate',
        'fuelType' => $row['fuel_type'],
        'transmissionType' => $row['transmission_type'],
        'mileageKm' => $row['mileage_km'] !== null ? (int) $row['mileage_km'] : null,
        'vinNumber' => $row['vin_number'],
        'engineNumber' => $row['engine_number'],
        'vehicleImage' => normalize_image_url($row['vehicle_image']),
        'service' => $serviceTitle,
        'serviceDetail' => $serviceDetail,
        'serviceSummary' => $row['service_summary'],
        'serviceCount' => $serviceCount,
        'completedServiceCount' => $completedServices,
        'estimatedDurationMinutes' => $estimatedMinutes,
        'schedule' => format_datetime_label($scheduleSource),
        'readyIn' => estimated_ready_label($row['estimated_finish_at'], $status),
        'checkInTime' => $row['check_in_time'],
        'workStartedAt' => $row['work_started_at'],
        'estimatedFinishAt' => $row['estimated_finish_at'],
        'laborTotal' => (float) $row['labor_total'],
        'partsTotal' => (float) $row['parts_total'],
        'grandTotal' => (float) $row['grand_total'],
        'totalLabel' => '₱' . format_money($row['grand_total']),
        'progressNotes' => $row['progress_notes'],
        'diagnosisNotes' => $row['diagnosis_notes'],
    ];

    if ($nextItem === null) {
        $nextItem = end($appointments);
    }
}

mysqli_stmt_close($stmt);

$next = [
    'title' => $nextItem ? ($nextItem['rawStatus'] ?: 'Next') : 'No Active Repairs',
    'subtitle' => $nextItem ? ($nextItem['vehicle'] . ' • ' . $nextItem['schedule']) : 'Your repair queue is empty',
];

$heroCaption = $latestEstimate
    ? 'Latest estimated completion: ' . date('h:i A, M d', $latestEstimate)
    : 'Estimated completion will appear once set by the shop.';

json_response(200, [
    'status' => 'success',
    'summary' => [
        ['label' => 'Active', 'value' => (string) $activeCount],
        ['label' => 'Today', 'value' => (string) $todayCount],
        ['label' => 'Waiting', 'value' => (string) $waitingCount],
    ],
    'next' => $next,
    'hero' => [
        'title' => $activeCount . ' Service' . ($activeCount === 1 ? '' : 's') . ' Active',
        'caption' => $heroCaption,
    ],
    'appointments' => $appointments,
    'promo' => [
        'title' => 'Repair Progress',
        'text' => 'Track your vehicle, repair status, services, and estimated completion in real time.',
        'imageUrl' => 'https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=800&q=80',
    ],
]);
