<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['tenantID'])) {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please login first."
    ]);
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

function folderSize($dir) {
    $size = 0;

    if (!is_dir($dir)) {
        return 0;
    }

    foreach (
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        ) as $file
    ) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }

    return $size;
}

function countTenantRecords($conn, $table, $tenantID) {
    $allowedTables = [
        'users',
        'vehicleinformation',
        'appointments',
        'repair_jobs',
        'diagnostic_reports',
        'inventory_items',
        'payments'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS total FROM `$table` WHERE tenantID = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $tenantID);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return (int)($row['total'] ?? 0);
}

$sql = "
    SELECT 
        o.tenantID,
        o.shopName,
        s.subscription_id,
        s.status,
        s.billing_cycle,
        s.start_date,
        s.end_date,
        s.next_billing_date,
        sp.plan_id,
        sp.plan_name,
        sp.plan_code,
        sp.monthly_price,
        sp.plan_features
    FROM owners o
    LEFT JOIN subscriptions s 
        ON o.tenantID = s.tenantID 
        AND s.status = 'active'
    LEFT JOIN subscription_plans sp 
        ON s.plan_id = sp.plan_id
    WHERE o.tenantID = ?
    ORDER BY s.subscription_id DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tenantID);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data || empty($data['subscription_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "No active subscription found."
    ]);
    exit;
}

$features = json_decode($data['plan_features'], true);

if (!is_array($features)) {
    $features = [];
}

/*
Supports this format:
{
  "storage_gb": 8,
  "record_limit": 10000
}
*/
$storageGB = isset($features['storage_gb']) ? (float)$features['storage_gb'] : 1;
$recordLimit = isset($features['record_limit']) ? $features['record_limit'] : null;

$storageLimitBytes = $storageGB * 1024 * 1024 * 1024;

/*
Folder path if storage_api.php is inside /tenant folder:
RapidRepair/uploads/tenants/{tenantID}/
*/
$tenantFolder = __DIR__ . "/../uploads/tenants/" . $tenantID;

$usedBytes = folderSize($tenantFolder);

$percentage = $storageLimitBytes > 0
    ? round(($usedBytes / $storageLimitBytes) * 100, 2)
    : 0;

if ($percentage > 100) {
    $percentage = 100;
}

/*
Database record usage per tenant.
Services table is removed.
*/
$recordUsage = [
    "customers" => countTenantRecords($conn, "users", $tenantID),
    "vehicles" => countTenantRecords($conn, "vehicleinformation", $tenantID),
    "appointments" => countTenantRecords($conn, "appointments", $tenantID),
    "repair_jobs" => countTenantRecords($conn, "repair_jobs", $tenantID),
    "diagnostics" => countTenantRecords($conn, "diagnostic_reports", $tenantID),
    "inventory_items" => countTenantRecords($conn, "inventory_items", $tenantID),
    "payments" => countTenantRecords($conn, "payments", $tenantID)
];

$totalRecords = array_sum($recordUsage);

$recordPercentage = null;
$recordIsWarning = false;
$recordIsFull = false;

if (is_numeric($recordLimit) && (int)$recordLimit > 0) {
    $recordLimit = (int)$recordLimit;
    $recordPercentage = round(($totalRecords / $recordLimit) * 100, 2);

    if ($recordPercentage > 100) {
        $recordPercentage = 100;
    }

    $recordIsWarning = $recordPercentage >= 80;
    $recordIsFull = $totalRecords >= $recordLimit;
}

echo json_encode([
    "success" => true,

    "tenantID" => $tenantID,
    "shopName" => $data['shopName'],

    "plan_name" => $data['plan_name'],
    "plan_code" => $data['plan_code'],
    "billing_cycle" => $data['billing_cycle'],
    "start_date" => $data['start_date'],
    "end_date" => $data['end_date'],
    "next_billing_date" => $data['next_billing_date'],

    "storage_limit_gb" => $storageGB,
    "storage_limit_bytes" => $storageLimitBytes,

    "used_bytes" => $usedBytes,
    "used_mb" => round($usedBytes / 1024 / 1024, 2),
    "used_gb" => round($usedBytes / 1024 / 1024 / 1024, 2),

    "percentage" => $percentage,
    "is_warning" => $percentage >= 80,
    "is_full" => $usedBytes >= $storageLimitBytes,

    "record_usage" => $recordUsage,
    "total_records" => $totalRecords,
    "record_limit" => $recordLimit,
    "record_percentage" => $recordPercentage,
    "record_is_warning" => $recordIsWarning,
    "record_is_full" => $recordIsFull
]);