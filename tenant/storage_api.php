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

function hApiString($value) {
    return trim((string) $value);
}

function tableExists($conn, $tableName) {
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$safeTable'");
    return $result && mysqli_num_rows($result) > 0;
}

function columnExists($conn, $tableName, $columnName) {
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    return $result && mysqli_num_rows($result) > 0;
}

function normalizePlanCode($value) {
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    $normalized = trim((string) $normalized, '-');
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
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
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

    $sql = "SELECT COUNT(*) AS total FROM `$table` WHERE CAST(tenantID AS UNSIGNED) = CAST(? AS UNSIGNED)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $tenantIDString = (string) $tenantID;
    $stmt->bind_param("s", $tenantIDString);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function parseStorageFromFeatures($featuresRaw, $planCode = '', $planName = '') {
    $storageGB = null;
    $recordLimit = null;

    $featuresRaw = trim((string) $featuresRaw);

    if ($featuresRaw !== '') {
        $decoded = json_decode($featuresRaw, true);

        if (is_array($decoded)) {
            if (isset($decoded['storage_gb']) && is_numeric($decoded['storage_gb'])) {
                $storageGB = (float) $decoded['storage_gb'];
            } elseif (isset($decoded['storage']) && is_numeric($decoded['storage'])) {
                $storageGB = (float) $decoded['storage'];
            } elseif (isset($decoded['storage_limit_gb']) && is_numeric($decoded['storage_limit_gb'])) {
                $storageGB = (float) $decoded['storage_limit_gb'];
            }

            if (isset($decoded['record_limit']) && is_numeric($decoded['record_limit'])) {
                $recordLimit = (int) $decoded['record_limit'];
            } elseif (isset($decoded['records']) && is_numeric($decoded['records'])) {
                $recordLimit = (int) $decoded['records'];
            }
        }

        if ($storageGB === null) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*(gb|gigabyte|gigabytes)/i', $featuresRaw, $match)) {
                $storageGB = (float) $match[1];
            } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(mb|megabyte|megabytes)/i', $featuresRaw, $match)) {
                $storageGB = ((float) $match[1]) / 1024;
            }
        }

        if ($recordLimit === null) {
            if (preg_match('/(\d[\d,]*)\s*(records|record)/i', $featuresRaw, $match)) {
                $recordLimit = (int) str_replace(',', '', $match[1]);
            }
        }
    }

    if ($storageGB === null || $storageGB <= 0) {
        $planKey = normalizePlanCode($planCode !== '' ? $planCode : $planName);

        /*
            Fallback storage limits.
            Update these values if your actual plan limits are different.
            If your subscription_plans.plan_features contains JSON like {"storage_gb":8},
            that JSON value will be used instead of these defaults.
        */
        $fallbackStorage = [
            'basic' => 1,
            'basic-plan' => 1,
            'starter' => 1,

            'medium' => 5,
            'standard' => 5,
            'standard-plan' => 5,
            'professional' => 5,

            'premium' => 10,
            'premium-plan' => 10,
            'enterprise' => 20
        ];

        $storageGB = $fallbackStorage[$planKey] ?? 1;
    }

    return [
        'storage_gb' => (float) $storageGB,
        'record_limit' => $recordLimit
    ];
}

function getTenantSubscriptionData($conn, $tenantID) {
    $tenantIDString = (string) $tenantID;

    /*
        1. First source: latest active row from subscriptions table.
        This is the main source after plan update.
    */
    if (tableExists($conn, 'subscriptions') && tableExists($conn, 'subscription_plans')) {
        $sql = "
            SELECT 
                o.tenantID,
                o.shopName,
                o.subscription_plan AS owner_subscription_plan,
                o.billing_cycle AS owner_billing_cycle,
                o.next_billing_date AS owner_next_billing_date,
                s.subscription_id,
                s.status AS subscription_status,
                s.billing_cycle,
                s.start_date,
                s.end_date,
                s.next_billing_date,
                s.amount,
                sp.plan_id,
                sp.plan_name,
                sp.plan_code,
                sp.monthly_price,
                sp.plan_features
            FROM owners o
            LEFT JOIN subscriptions s 
                ON CAST(o.tenantID AS UNSIGNED) = CAST(s.tenantID AS UNSIGNED)
                AND s.status = 'active'
            LEFT JOIN subscription_plans sp 
                ON s.plan_id = sp.plan_id
            WHERE CAST(o.tenantID AS UNSIGNED) = CAST(? AS UNSIGNED)
            ORDER BY s.subscription_id DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $tenantIDString);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($data && !empty($data['subscription_id']) && !empty($data['plan_id'])) {
                $data['source'] = 'subscriptions';
                return $data;
            }
        }
    }

    /*
        2. Fallback source: owners.subscription_plan.
        This prevents storage from breaking if the subscriptions table has not been synced yet.
    */
    if (tableExists($conn, 'subscription_plans')) {
        $sql = "
            SELECT 
                o.tenantID,
                o.shopName,
                o.subscription_plan AS owner_subscription_plan,
                o.billing_cycle AS owner_billing_cycle,
                o.subscription_start AS owner_subscription_start,
                o.subscription_end AS owner_subscription_end,
                o.next_billing_date AS owner_next_billing_date,
                o.plan_price AS owner_plan_price,
                sp.plan_id,
                sp.plan_name,
                sp.plan_code,
                sp.monthly_price,
                sp.plan_features
            FROM owners o
            LEFT JOIN subscription_plans sp 
                ON LOWER(sp.plan_code) = LOWER(o.subscription_plan)
            WHERE CAST(o.tenantID AS UNSIGNED) = CAST(? AS UNSIGNED)
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $tenantIDString);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($data && !empty($data['plan_id'])) {
                return [
                    'tenantID' => $data['tenantID'],
                    'shopName' => $data['shopName'],
                    'subscription_id' => null,
                    'subscription_status' => 'active',
                    'billing_cycle' => $data['owner_billing_cycle'] ?: 'monthly',
                    'start_date' => $data['owner_subscription_start'] ?? null,
                    'end_date' => $data['owner_subscription_end'] ?? null,
                    'next_billing_date' => $data['owner_next_billing_date'] ?? null,
                    'amount' => $data['owner_plan_price'] ?? null,
                    'plan_id' => $data['plan_id'],
                    'plan_name' => $data['plan_name'],
                    'plan_code' => $data['plan_code'],
                    'monthly_price' => $data['monthly_price'],
                    'plan_features' => $data['plan_features'],
                    'source' => 'owners'
                ];
            }
        }
    }

    /*
        3. Last fallback: owner exists but no matching plan found.
    */
    $stmt = $conn->prepare("SELECT tenantID, shopName, subscription_plan, billing_cycle, next_billing_date FROM owners WHERE CAST(tenantID AS UNSIGNED) = CAST(? AS UNSIGNED) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $tenantIDString);
        $stmt->execute();
        $result = $stmt->get_result();
        $owner = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($owner) {
            $planCode = $owner['subscription_plan'] ?: 'basic';
            return [
                'tenantID' => $owner['tenantID'],
                'shopName' => $owner['shopName'],
                'subscription_id' => null,
                'subscription_status' => 'active',
                'billing_cycle' => $owner['billing_cycle'] ?: 'monthly',
                'start_date' => null,
                'end_date' => null,
                'next_billing_date' => $owner['next_billing_date'] ?? null,
                'amount' => null,
                'plan_id' => null,
                'plan_name' => ucwords(str_replace('-', ' ', $planCode)),
                'plan_code' => $planCode,
                'monthly_price' => 0,
                'plan_features' => '',
                'source' => 'owners_default'
            ];
        }
    }

    return null;
}

$data = getTenantSubscriptionData($conn, $tenantID);

if (!$data) {
    jsonResponse([
        "success" => false,
        "message" => "Tenant subscription information was not found."
    ]);
}

$limits = parseStorageFromFeatures(
    $data['plan_features'] ?? '',
    $data['plan_code'] ?? '',
    $data['plan_name'] ?? ''
);

$storageGB = $limits['storage_gb'];
$recordLimit = $limits['record_limit'];

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
    Services table is not included.
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

jsonResponse([
    "success" => true,

    "tenantID" => $tenantID,
    "shopName" => $data['shopName'] ?? '',

    "subscription_source" => $data['source'] ?? 'unknown',
    "subscription_id" => $data['subscription_id'] ?? null,
    "subscription_status" => $data['subscription_status'] ?? 'active',

    "plan_id" => $data['plan_id'] ?? null,
    "plan_name" => $data['plan_name'] ?? 'Subscription',
    "plan_code" => $data['plan_code'] ?? '',
    "billing_cycle" => $data['billing_cycle'] ?? 'monthly',
    "start_date" => $data['start_date'] ?? null,
    "end_date" => $data['end_date'] ?? null,
    "next_billing_date" => $data['next_billing_date'] ?? null,

    "storage_limit_gb" => $storageGB,
    "storage_limit_bytes" => $storageLimitBytes,

    "used_bytes" => $usedBytes,
    "used_mb" => round($usedBytes / 1024 / 1024, 2),
    "used_gb" => round($usedBytes / 1024 / 1024 / 1024, 2),

    "percentage" => $percentage,
    "is_warning" => $percentage >= 80,
    "is_full" => $storageLimitBytes > 0 ? ($usedBytes >= $storageLimitBytes) : false,

    "record_usage" => $recordUsage,
    "total_records" => $totalRecords,
    "record_limit" => $recordLimit,
    "record_percentage" => $recordPercentage,
    "record_is_warning" => $recordIsWarning,
    "record_is_full" => $recordIsFull
]);
