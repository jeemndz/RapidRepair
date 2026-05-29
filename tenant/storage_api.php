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

function jsonResponse($payload) {
    echo json_encode($payload);
    exit;
}

function tableExists($conn, $tableName) {
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$safeTable'");
    return $result && mysqli_num_rows($result) > 0;
}

function columnExists($conn, $tableName, $columnName) {
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $safeColumn = mysqli_real_escape_string($conn, $columnName);

    $result = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function normalizePlanCode($value) {
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    $normalized = trim($normalized, '-');

    return $normalized;
}

function folderSize($dir) {

    $size = 0;

    if (!is_dir($dir)) {
        return 0;
    }

    try {

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $dir,
                    FilesystemIterator::SKIP_DOTS
                )
            ) as $file
        ) {

            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

    } catch (Throwable $e) {
        return 0;
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

    if (!tableExists($conn, $table)) {
        return 0;
    }

    if (!columnExists($conn, $table, 'tenantID')) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM `$table`
        WHERE CAST(tenantID AS UNSIGNED)
        = CAST(? AS UNSIGNED)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $tenantIDString = (string) $tenantID;

    $stmt->bind_param("s", $tenantIDString);
    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result
        ? $result->fetch_assoc()
        : null;

    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function parseStorageFromFeatures(
    $featuresRaw,
    $planCode = '',
    $planName = ''
) {

    $storageGB = null;
    $recordLimit = null;

    $featuresRaw = trim((string) $featuresRaw);

    if ($featuresRaw !== '') {

        $decoded = json_decode($featuresRaw, true);

        if (is_array($decoded)) {

            if (
                isset($decoded['storage_gb']) &&
                is_numeric($decoded['storage_gb'])
            ) {
                $storageGB = (float)$decoded['storage_gb'];
            }

            if (
                isset($decoded['record_limit']) &&
                is_numeric($decoded['record_limit'])
            ) {
                $recordLimit = (int)$decoded['record_limit'];
            }
        }

        if ($storageGB === null) {

            if (
                preg_match(
                    '/(\d+(?:\.\d+)?)\s*(gb|gigabyte|gigabytes)/i',
                    $featuresRaw,
                    $match
                )
            ) {

                $storageGB = (float)$match[1];
            }
        }

        if ($recordLimit === null) {

            if (
                preg_match(
                    '/(\d[\d,]*)\s*(records|record)/i',
                    $featuresRaw,
                    $match
                )
            ) {

                $recordLimit =
                    (int) str_replace(',', '', $match[1]);
            }
        }
    }

    if ($storageGB === null || $storageGB <= 0) {

        $planKey = normalizePlanCode(
            $planCode !== ''
                ? $planCode
                : $planName
        );

        $fallbackStorage = [

            'basic' => 1,
            'starter' => 1,

            'standard' => 5,
            'professional' => 5,

            'premium' => 10,
            'enterprise' => 20
        ];

        $storageGB =
            $fallbackStorage[$planKey] ?? 1;
    }

    return [
        'storage_gb' => (float)$storageGB,
        'record_limit' => $recordLimit
    ];
}

function getTenantSubscriptionData($conn, $tenantID) {

    $tenantIDString = (string)$tenantID;

    $sql = "
        SELECT 
            o.tenantID,
            o.shopName,

            s.subscription_id,
            s.status AS subscription_status,
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
            ON CAST(o.tenantID AS UNSIGNED)
            = CAST(s.tenantID AS UNSIGNED)
            AND s.status = 'active'

        LEFT JOIN subscription_plans sp
            ON s.plan_id = sp.plan_id

        WHERE CAST(o.tenantID AS UNSIGNED)
            = CAST(? AS UNSIGNED)

        ORDER BY s.subscription_id DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $tenantIDString);
    $stmt->execute();

    $result = $stmt->get_result();

    $data = $result
        ? $result->fetch_assoc()
        : null;

    $stmt->close();

    return $data;
}

$data = getTenantSubscriptionData(
    $conn,
    $tenantID
);

if (!$data) {

    jsonResponse([
        "success" => false,
        "message" => "No active subscription found."
    ]);
}

$limits = parseStorageFromFeatures(
    $data['plan_features'] ?? '',
    $data['plan_code'] ?? '',
    $data['plan_name'] ?? ''
);

$storageGB = $limits['storage_gb'];
$recordLimit = $limits['record_limit'];

$storageLimitBytes =
    $storageGB * 1024 * 1024 * 1024;

/*
|--------------------------------------------------------------------------
| TENANT FOLDER
|--------------------------------------------------------------------------
*/

$tenantFolder =
    __DIR__ . "/../uploads/tenants/" . $tenantID;

/*
|--------------------------------------------------------------------------
| FILE STORAGE
|--------------------------------------------------------------------------
*/

$fileBytesUsed =
    folderSize($tenantFolder);

/*
|--------------------------------------------------------------------------
| RECORD COUNTS
|--------------------------------------------------------------------------
*/

$recordUsage = [

    "customers" =>
        countTenantRecords(
            $conn,
            "users",
            $tenantID
        ),

    "vehicles" =>
        countTenantRecords(
            $conn,
            "vehicleinformation",
            $tenantID
        ),

    "appointments" =>
        countTenantRecords(
            $conn,
            "appointments",
            $tenantID
        ),

    "repair_jobs" =>
        countTenantRecords(
            $conn,
            "repair_jobs",
            $tenantID
        ),

    "diagnostics" =>
        countTenantRecords(
            $conn,
            "diagnostic_reports",
            $tenantID
        ),

    "inventory_items" =>
        countTenantRecords(
            $conn,
            "inventory_items",
            $tenantID
        ),

    "payments" =>
        countTenantRecords(
            $conn,
            "payments",
            $tenantID
        )
];

/*
|--------------------------------------------------------------------------
| AVERAGE KB PER RECORD
|--------------------------------------------------------------------------
*/

$averageRecordSizesKB = [

    "customers" => 2.0,
    "vehicles" => 3.5,
    "appointments" => 2.5,
    "repair_jobs" => 5.0,
    "diagnostics" => 4.0,
    "inventory_items" => 2.5,
    "payments" => 2.0
];

/*
|--------------------------------------------------------------------------
| TOTAL RECORDS
|--------------------------------------------------------------------------
*/

$totalRecords =
    array_sum($recordUsage);

/*
|--------------------------------------------------------------------------
| DATABASE STORAGE ESTIMATION
|--------------------------------------------------------------------------
*/

$databaseKBUsed = 0;

foreach ($recordUsage as $key => $count) {

    $averageKB =
        $averageRecordSizesKB[$key] ?? 1;

    $databaseKBUsed += (
        $count * $averageKB
    );
}

$databaseBytesUsed =
    $databaseKBUsed * 1024;

/*
|--------------------------------------------------------------------------
| TOTAL STORAGE USED
|--------------------------------------------------------------------------
*/

$totalUsedBytes =
    $fileBytesUsed + $databaseBytesUsed;

/*
|--------------------------------------------------------------------------
| STORAGE PERCENTAGE
|--------------------------------------------------------------------------
*/

$percentage = $storageLimitBytes > 0
    ? round(
        ($totalUsedBytes / $storageLimitBytes) * 100,
        2
    )
    : 0;

if ($percentage > 100) {
    $percentage = 100;
}

/*
|--------------------------------------------------------------------------
| RECORD LIMITS
|--------------------------------------------------------------------------
*/

$recordPercentage = null;
$recordIsWarning = false;
$recordIsFull = false;

if (
    is_numeric($recordLimit) &&
    (int)$recordLimit > 0
) {

    $recordLimit = (int)$recordLimit;

    $recordPercentage = round(
        ($totalRecords / $recordLimit) * 100,
        2
    );

    if ($recordPercentage > 100) {
        $recordPercentage = 100;
    }

    $recordIsWarning =
        $recordPercentage >= 80;

    $recordIsFull =
        $totalRecords >= $recordLimit;
}

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

jsonResponse([

    "success" => true,

    "tenantID" => $tenantID,

    "shopName" =>
        $data['shopName'] ?? '',

    "subscription_id" =>
        $data['subscription_id'] ?? null,

    "subscription_status" =>
        $data['subscription_status'] ?? 'active',

    "plan_id" =>
        $data['plan_id'] ?? null,

    "plan_name" =>
        $data['plan_name'] ?? 'Subscription',

    "plan_code" =>
        $data['plan_code'] ?? '',

    "billing_cycle" =>
        $data['billing_cycle'] ?? 'monthly',

    "start_date" =>
        $data['start_date'] ?? null,

    "end_date" =>
        $data['end_date'] ?? null,

    "next_billing_date" =>
        $data['next_billing_date'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | STORAGE LIMITS
    |--------------------------------------------------------------------------
    */

    "storage_limit_gb" =>
        $storageGB,

    "storage_limit_bytes" =>
        $storageLimitBytes,

    /*
    |--------------------------------------------------------------------------
    | FILE STORAGE
    |--------------------------------------------------------------------------
    */

    "file_used_bytes" =>
        $fileBytesUsed,

    "file_used_kb" =>
        round($fileBytesUsed / 1024, 2),

    "file_used_mb" =>
        round($fileBytesUsed / 1024 / 1024, 2),

    "file_used_gb" =>
        round($fileBytesUsed / 1024 / 1024 / 1024, 2),

    /*
    |--------------------------------------------------------------------------
    | DATABASE STORAGE
    |--------------------------------------------------------------------------
    */

    "database_used_kb" =>
        round($databaseKBUsed, 2),

    "database_used_mb" =>
        round($databaseKBUsed / 1024, 2),

    /*
    |--------------------------------------------------------------------------
    | TOTAL STORAGE
    |--------------------------------------------------------------------------
    */

    "used_bytes" =>
        $totalUsedBytes,

    "used_kb" =>
        round($totalUsedBytes / 1024, 2),

    "used_mb" =>
        round($totalUsedBytes / 1024 / 1024, 2),

    "used_gb" =>
        round($totalUsedBytes / 1024 / 1024 / 1024, 2),

    /*
    |--------------------------------------------------------------------------
    | STORAGE STATUS
    |--------------------------------------------------------------------------
    */

    "percentage" =>
        $percentage,

    "is_warning" =>
        $percentage >= 80,

    "is_full" =>
        $storageLimitBytes > 0
            ? ($totalUsedBytes >= $storageLimitBytes)
            : false,

    /*
    |--------------------------------------------------------------------------
    | RECORDS
    |--------------------------------------------------------------------------
    */

    "record_usage" =>
        $recordUsage,

    "total_records" =>
        $totalRecords,

    "record_limit" =>
        $recordLimit,

    "record_percentage" =>
        $recordPercentage,

    "record_is_warning" =>
        $recordIsWarning,

    "record_is_full" =>
        $recordIsFull
]);