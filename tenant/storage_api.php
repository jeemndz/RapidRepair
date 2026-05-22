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

$storageGB = isset($features['storage_gb']) ? (float)$features['storage_gb'] : 1;

$storageLimitBytes = $storageGB * 1024 * 1024 * 1024;

/*
Recommended tenant upload path:
uploads/tenants/{tenantID}/
*/
$tenantFolder = __DIR__ . "/uploads/tenants/" . $tenantID;

$usedBytes = folderSize($tenantFolder);

$percentage = $storageLimitBytes > 0
    ? round(($usedBytes / $storageLimitBytes) * 100, 2)
    : 0;

if ($percentage > 100) {
    $percentage = 100;
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
    "is_full" => $usedBytes >= $storageLimitBytes
]);