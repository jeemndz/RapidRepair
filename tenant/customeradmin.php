<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];



$loginSlug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$ownerStmt = mysqli_prepare($conn, 'SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1');
if (!$ownerStmt) {
    die('Unable to validate tenant.');
}
mysqli_stmt_bind_param($ownerStmt, 'is', $tenantID, $loginSlug);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$owner = $ownerResult ? mysqli_fetch_assoc($ownerResult) : null;
mysqli_stmt_close($ownerStmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$_SESSION['login_slug'] = $loginSlug;
$shopName = !empty($owner['shopName']) ? $owner['shopName'] : 'AutoFix Pro';
$shopQuery = urlencode($loginSlug);
$currentScript = basename($_SERVER['PHP_SELF']);
if (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug) {
    header('Location: ' . $currentScript . '?shop=' . $shopQuery);
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bindValues(mysqli_stmt $stmt, string $types, array &$values): bool
{
    $bindArgs = [$stmt, $types];
    foreach ($values as $index => &$value) {
        $bindArgs[] = &$value;
    }

    return call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
}

function buildCustomerFilterSql(int $tenantID, string $searchTerm): array
{
    $whereParts = ['u.tenantID = ?', "u.role = 'client'"];
    $types = 'i';
    $params = [$tenantID];

    if ($searchTerm !== '') {
        $whereParts[] = "(
            u.fullName LIKE CONCAT('%', ?, '%')
            OR u.email LIKE CONCAT('%', ?, '%')
            OR u.contactNumber LIKE CONCAT('%', ?, '%')
            OR u.address LIKE CONCAT('%', ?, '%')
            OR EXISTS (
                SELECT 1
                FROM vehicleinformation v
                WHERE v.tenantID = u.tenantID
                  AND v.user_id = u.user_id
                  AND (
                        v.brand LIKE CONCAT('%', ?, '%')
                        OR v.model LIKE CONCAT('%', ?, '%')
                        OR v.plate_number LIKE CONCAT('%', ?, '%')
                  )
            )
        )";

        for ($index = 0; $index < 7; $index++) {
            $types .= 's';
            $params[] = $searchTerm;
        }
    }

    return [
        'sql' => implode(' AND ', $whereParts),
        'types' => $types,
        'params' => $params,
    ];
}

$actionMessage = '';
$actionError = '';

$formData = [
    'fullName' => '',
    'email' => '',
    'contactNumber' => '',
    'address' => '',
    'password' => '',
    'confirm_password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_customer'])) {
    foreach ($formData as $key => $value) {
        $formData[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : $value;
    }

    if ($formData['fullName'] === '' || $formData['email'] === '' || $formData['contactNumber'] === '' || $formData['password'] === '') {
        $actionError = 'Full name, email, contact number, and password are required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $actionError = 'Enter a valid email address.';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $actionError = 'Passwords do not match.';
    } elseif (strlen($formData['password']) < 6) {
        $actionError = 'Password must be at least 6 characters long.';
    } else {
        $duplicateStmt = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE email = ? LIMIT 1');
        if ($duplicateStmt) {
            mysqli_stmt_bind_param($duplicateStmt, 's', $formData['email']);
            mysqli_stmt_execute($duplicateStmt);
            $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
            if ($duplicateResult && mysqli_fetch_assoc($duplicateResult)) {
                $actionError = 'That email address is already registered.';
            }
            mysqli_stmt_close($duplicateStmt);
        } else {
            $actionError = 'Unable to validate the new customer email.';
        }

        if ($actionError === '') {
            $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
            $address = $formData['address'] !== '' ? $formData['address'] : null;

            $insertStmt = mysqli_prepare(
                $conn,
                'INSERT INTO users (tenantID, fullName, address, email, password, contactNumber, role) VALUES (?, ?, ?, ?, ?, ?, "client")'
            );

            if ($insertStmt) {
                mysqli_stmt_bind_param(
                    $insertStmt,
                    'isssss',
                    $tenantID,
                    $formData['fullName'],
                    $address,
                    $formData['email'],
                    $passwordHash,
                    $formData['contactNumber']
                );

                if (mysqli_stmt_execute($insertStmt)) {
                    mysqli_stmt_close($insertStmt);
                    header('Location: customeradmin.php?shop=' . $shopQuery . '&customer_saved=1');
                    exit;
                }

                $actionError = 'Unable to save customer right now.';
                mysqli_stmt_close($insertStmt);
            } else {
                $actionError = 'Unable to prepare the customer insert query.';
            }
        }
    }
}

$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$selectedCustomerId = isset($_GET['customer_id']) ? max(0, (int) $_GET['customer_id']) : 0;

$customerStats = [
    'total_customers' => 0,
    'customers_with_vehicles' => 0,
    'total_vehicles' => 0,
    'active_vehicles' => 0,
];

$statsStmt = mysqli_prepare(
    $conn,
    'SELECT
        COUNT(*) AS total_customers,
        COALESCE(SUM(CASE WHEN EXISTS (
            SELECT 1
            FROM vehicleinformation v
            WHERE v.tenantID = u.tenantID
              AND v.user_id = u.user_id
        ) THEN 1 ELSE 0 END), 0) AS customers_with_vehicles
     FROM users u
     WHERE u.tenantID = ?
       AND u.role = "client"'
);
if ($statsStmt) {
    mysqli_stmt_bind_param($statsStmt, 'i', $tenantID);
    mysqli_stmt_execute($statsStmt);
    $statsResult = mysqli_stmt_get_result($statsStmt);
    if ($statsResult && $row = mysqli_fetch_assoc($statsResult)) {
        $customerStats['total_customers'] = (int) ($row['total_customers'] ?? 0);
        $customerStats['customers_with_vehicles'] = (int) ($row['customers_with_vehicles'] ?? 0);
    }
    mysqli_stmt_close($statsStmt);
}

$vehicleStatsStmt = mysqli_prepare(
    $conn,
    'SELECT
        COUNT(*) AS total_vehicles,
        COALESCE(SUM(CASE WHEN status = "Active" THEN 1 ELSE 0 END), 0) AS active_vehicles
     FROM vehicleinformation
     WHERE tenantID = ?'
);
if ($vehicleStatsStmt) {
    mysqli_stmt_bind_param($vehicleStatsStmt, 'i', $tenantID);
    mysqli_stmt_execute($vehicleStatsStmt);
    $vehicleStatsResult = mysqli_stmt_get_result($vehicleStatsStmt);
    if ($vehicleStatsResult && $row = mysqli_fetch_assoc($vehicleStatsResult)) {
        $customerStats['total_vehicles'] = (int) ($row['total_vehicles'] ?? 0);
        $customerStats['active_vehicles'] = (int) ($row['active_vehicles'] ?? 0);
    }
    mysqli_stmt_close($vehicleStatsStmt);
}

$filter = buildCustomerFilterSql($tenantID, $searchTerm);
$filteredTotal = 0;
$countSql = 'SELECT COUNT(*) AS total FROM users u WHERE ' . $filter['sql'];
$countStmt = mysqli_prepare($conn, $countSql);
if ($countStmt) {
    $countParams = $filter['params'];
    if (bindValues($countStmt, $filter['types'], $countParams)) {
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        if ($countResult && $countRow = mysqli_fetch_assoc($countResult)) {
            $filteredTotal = (int) ($countRow['total'] ?? 0);
        }
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int) ceil(max(1, $filteredTotal) / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

if (isset($_GET['export']) && $_GET['export'] === '1') {
    $exportSql = '
        SELECT
            u.user_id,
            u.fullName,
            u.email,
            u.contactNumber,
            u.address,
            COALESCE((SELECT COUNT(*) FROM vehicleinformation v WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id), 0) AS vehicle_count,
            COALESCE((SELECT SUM(CASE WHEN v.status = "Active" THEN 1 ELSE 0 END) FROM vehicleinformation v WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id), 0) AS active_vehicle_count,
            (SELECT TRIM(CONCAT(COALESCE(v.brand, ""), " ", COALESCE(v.model, "")))
             FROM vehicleinformation v
             WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
             ORDER BY v.date_added DESC, v.vehicle_id DESC
             LIMIT 1) AS latest_vehicle_label,
            (SELECT v.plate_number
             FROM vehicleinformation v
             WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
             ORDER BY v.date_added DESC, v.vehicle_id DESC
             LIMIT 1) AS latest_plate_number,
            (SELECT v.date_added
             FROM vehicleinformation v
             WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
             ORDER BY v.date_added DESC, v.vehicle_id DESC
             LIMIT 1) AS latest_vehicle_date
        FROM users u
        WHERE ' . $filter['sql'] . '
        ORDER BY u.fullName ASC
    ';

    $exportStmt = mysqli_prepare($conn, $exportSql);
    if ($exportStmt) {
        $exportParams = $filter['params'];
        if (bindValues($exportStmt, $filter['types'], $exportParams)) {
            mysqli_stmt_execute($exportStmt);
            $exportResult = mysqli_stmt_get_result($exportStmt);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=customers_export_' . date('Ymd_His') . '.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Customer ID', 'Full Name', 'Email', 'Contact Number', 'Address', 'Vehicles', 'Active Vehicles', 'Latest Vehicle', 'Latest Plate', 'Latest Vehicle Date']);

            while ($exportResult && $row = mysqli_fetch_assoc($exportResult)) {
                fputcsv($output, [
                    $row['user_id'],
                    $row['fullName'],
                    $row['email'],
                    $row['contactNumber'],
                    $row['address'],
                    $row['vehicle_count'],
                    $row['active_vehicle_count'],
                    trim((string) ($row['latest_vehicle_label'] ?? '')),
                    $row['latest_plate_number'],
                    $row['latest_vehicle_date'],
                ]);
            }

            fclose($output);
            mysqli_stmt_close($exportStmt);
            exit;
        }
        mysqli_stmt_close($exportStmt);
    }
}

$listSql = '
    SELECT
        u.user_id,
        u.fullName,
        u.email,
        u.contactNumber,
        u.address,
        COALESCE((SELECT COUNT(*) FROM vehicleinformation v WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id), 0) AS vehicle_count,
        COALESCE((SELECT SUM(CASE WHEN v.status = "Active" THEN 1 ELSE 0 END) FROM vehicleinformation v WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id), 0) AS active_vehicle_count,
        (SELECT TRIM(CONCAT(COALESCE(v.brand, ""), " ", COALESCE(v.model, "")))
         FROM vehicleinformation v
         WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
         ORDER BY v.date_added DESC, v.vehicle_id DESC
         LIMIT 1) AS latest_vehicle_label,
        (SELECT v.plate_number
         FROM vehicleinformation v
         WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
         ORDER BY v.date_added DESC, v.vehicle_id DESC
         LIMIT 1) AS latest_plate_number,
        (SELECT v.status
         FROM vehicleinformation v
         WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
         ORDER BY v.date_added DESC, v.vehicle_id DESC
         LIMIT 1) AS latest_vehicle_status,
        (SELECT v.date_added
         FROM vehicleinformation v
         WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
         ORDER BY v.date_added DESC, v.vehicle_id DESC
         LIMIT 1) AS latest_vehicle_date,
        (SELECT v.vehicle_id
         FROM vehicleinformation v
         WHERE v.tenantID = u.tenantID AND v.user_id = u.user_id
         ORDER BY v.date_added DESC, v.vehicle_id DESC
         LIMIT 1) AS latest_vehicle_id
    FROM users u
    WHERE ' . $filter['sql'] . '
    ORDER BY u.fullName ASC
    LIMIT ?, ?
';

$customers = [];
$listStmt = mysqli_prepare($conn, $listSql);
if ($listStmt) {
    $listParams = $filter['params'];
    $listParams[] = $offset;
    $listParams[] = $perPage;
    $listTypes = $filter['types'] . 'ii';

    if (bindValues($listStmt, $listTypes, $listParams)) {
        mysqli_stmt_execute($listStmt);
        $listResult = mysqli_stmt_get_result($listStmt);
        while ($listResult && $row = mysqli_fetch_assoc($listResult)) {
            $customers[] = $row;
        }
    }
    mysqli_stmt_close($listStmt);
}

if ($selectedCustomerId <= 0 && !empty($customers)) {
    $selectedCustomerId = (int) $customers[0]['user_id'];
}

$selectedCustomer = null;
$selectedVehicles = [];
$selectedCustomerVehiclesCount = 0;
$selectedCustomerActiveVehicles = 0;
if ($selectedCustomerId > 0) {
    $selectedStmt = mysqli_prepare(
        $conn,
        'SELECT user_id, fullName, email, contactNumber, address FROM users WHERE user_id = ? AND tenantID = ? AND role = "client" LIMIT 1'
    );
    if ($selectedStmt) {
        mysqli_stmt_bind_param($selectedStmt, 'ii', $selectedCustomerId, $tenantID);
        mysqli_stmt_execute($selectedStmt);
        $selectedResult = mysqli_stmt_get_result($selectedStmt);
        $selectedCustomer = $selectedResult ? mysqli_fetch_assoc($selectedResult) : null;
        mysqli_stmt_close($selectedStmt);
    }

    if ($selectedCustomer) {
        $vehiclesStmt = mysqli_prepare(
            $conn,
            'SELECT vehicle_id, brand, model, year_model, plate_number, color, status, date_added
             FROM vehicleinformation
             WHERE tenantID = ? AND user_id = ?
             ORDER BY date_added DESC, vehicle_id DESC
             LIMIT 8'
        );
        if ($vehiclesStmt) {
            mysqli_stmt_bind_param($vehiclesStmt, 'ii', $tenantID, $selectedCustomerId);
            mysqli_stmt_execute($vehiclesStmt);
            $vehiclesResult = mysqli_stmt_get_result($vehiclesStmt);
            while ($vehiclesResult && $vehicleRow = mysqli_fetch_assoc($vehiclesResult)) {
                $selectedVehicles[] = $vehicleRow;
            }
            mysqli_stmt_close($vehiclesStmt);
        }

        $selectedCountsStmt = mysqli_prepare(
            $conn,
            'SELECT
                COUNT(*) AS total_vehicles,
                COALESCE(SUM(CASE WHEN status = "Active" THEN 1 ELSE 0 END), 0) AS active_vehicles
             FROM vehicleinformation
             WHERE tenantID = ? AND user_id = ?'
        );
        if ($selectedCountsStmt) {
            mysqli_stmt_bind_param($selectedCountsStmt, 'ii', $tenantID, $selectedCustomerId);
            mysqli_stmt_execute($selectedCountsStmt);
            $selectedCountsResult = mysqli_stmt_get_result($selectedCountsStmt);
            if ($selectedCountsResult && $selectedCountsRow = mysqli_fetch_assoc($selectedCountsResult)) {
                $selectedCustomerVehiclesCount = (int) ($selectedCountsRow['total_vehicles'] ?? 0);
                $selectedCustomerActiveVehicles = (int) ($selectedCountsRow['active_vehicles'] ?? 0);
            }
            mysqli_stmt_close($selectedCountsStmt);
        }
    } else {
        $selectedCustomerId = 0;
    }
}

$recentActivity = [];
$recentStmt = mysqli_prepare(
    $conn,
    'SELECT
        v.vehicle_id,
        v.brand,
        v.model,
        v.plate_number,
        v.status,
        v.date_added,
        COALESCE(u.fullName, CONCAT("User #", v.user_id)) AS customer_name
     FROM vehicleinformation v
     LEFT JOIN users u ON u.user_id = v.user_id AND u.tenantID = v.tenantID
     WHERE v.tenantID = ?
     ORDER BY v.date_added DESC, v.vehicle_id DESC
     LIMIT 5'
);
if ($recentStmt) {
    mysqli_stmt_bind_param($recentStmt, 'i', $tenantID);
    mysqli_stmt_execute($recentStmt);
    $recentResult = mysqli_stmt_get_result($recentStmt);
    while ($recentResult && $recentRow = mysqli_fetch_assoc($recentResult)) {
        $recentActivity[] = $recentRow;
    }
    mysqli_stmt_close($recentStmt);
}

$startEntry = $filteredTotal > 0 ? $offset + 1 : 0;
$endEntry = min($offset + count($customers), $filteredTotal);
$selectedCustomerName = $selectedCustomer['fullName'] ?? 'Select a customer';

function customerStatusClass(?string $status): string
{
    if ($status === 'Active') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if ($status === 'Inactive') {
        return 'bg-slate-100 text-slate-600';
    }

    return 'bg-blue-100 text-blue-700';
}

function formatDateValue($value, string $fallback = 'No vehicle yet'): string
{
    if (empty($value)) {
        return $fallback;
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('M d, Y', $timestamp);
}

$exportParams = ['shop' => $loginSlug];
if ($searchTerm !== '') {
    $exportParams['q'] = $searchTerm;
}
if ($selectedCustomerId > 0) {
    $exportParams['customer_id'] = $selectedCustomerId;
}
$exportUrl = 'customeradmin.php?' . http_build_query($exportParams + ['export' => 1]);
$searchUrl = 'customeradmin.php?shop=' . $shopQuery;
if ($selectedCustomerId > 0) {
    $searchUrl .= '&customer_id=' . $selectedCustomerId;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title><?php echo h($shopName); ?> | Customer Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#1152d4',
                        'primary-container': '#eef2ff',
                        background: '#f6f6f8',
                        surface: '#ffffff',
                        outline: '#e2e8f0',
                        secondary: '#475569',
                        'on-surface': '#0f172a',
                        'on-background': '#0f172a',
                        tertiary: '#f59e0b',
                        error: '#ef4444',
                    },
                    fontFamily: {
                        headline: ['Inter'],
                        body: ['Inter'],
                        label: ['Inter'],
                    },
                    borderRadius: { DEFAULT: '0.125rem', lg: '0.5rem', xl: '0.75rem', full: '9999px' },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased flex min-h-screen overflow-hidden">
    <aside class="w-64 flex-shrink-0 border-r border-slate-200 bg-white overflow-y-auto flex flex-col">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none">AutoFix Pro</h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="dashboardadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="repairjobsadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="vehicleadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                    href="customeradmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="settingsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
                        Settings
                    </a>
                </div>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($_SESSION['fullName'] ?? 'Shop Manager'); ?>
                    </p>
                    <p class="text-xs text-slate-500 truncate">Shop Manager</p>
                </div>
                <form method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                    <button type="submit" class="text-slate-400 hover:text-error transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/85 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6 min-w-0">
                <div>
                    <h2 class="text-lg font-black text-slate-900 tracking-tight">Customer Management</h2>
                </div>
                <form method="get" action="customeradmin.php" class="relative hidden lg:block">
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                    <?php if ($selectedCustomerId > 0): ?>
                        <input type="hidden" name="customer_id" value="<?php echo (int) $selectedCustomerId; ?>" />
                    <?php endif; ?>
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-slate-100 border-none rounded-lg pl-10 pr-4 py-2 text-sm w-80 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search customers or vehicles..." type="text" name="q"
                        value="<?php echo h($searchTerm); ?>" />
                </form>
                <span class="hidden xl:inline-flex items-center px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-[11px] font-bold uppercase tracking-wide"><?php echo h($loginSlug); ?></span>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
                <div class="h-8 w-px bg-slate-200 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-bold text-on-background"><?php echo h($shopName); ?></p>
                        <p class="text-[10px] text-slate-500 uppercase font-semibold">Admin</p>
                    </div>
                    <img alt="Manager Avatar" class="h-10 w-10 rounded-full border-2 border-primary/20 object-cover"
                        data-alt="professional male service manager portrait in modern automotive office environment"
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuC_w4TZYv-DoCr0hxBNhV2Z-nUsRKiJWSSjgi__Y4oVCvZsEnAXH-GvsZk4qUV8VfyOd_rN5mqWnBeNlMb7An_00pBDPbF7FGZDqw2HhZ4MbeNkgRRsmuE6r3t2yOO4P5sHcWAMkVgXaheA3Z2LKA0Fo_mIUP0qh9KRyragtZ_zvLR-U7pm-kWc645Yi3rN0Mm0P9km9Kt3Fp4fKCU5i33aRJsonLoG5k45EuFpDDTP2CbZiarn81pTDjiPcRHLtpdJg1O47dGsJUD2" />
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-[30px] font-black tracking-tight text-on-surface"><?php echo h($shopName); ?>
                        Customers</h2>
                    <p class="text-secondary mt-1 font-medium">Manage customer accounts and see every vehicle linked to
                        each account.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Showing</span>
                    <span
                        class="text-sm font-bold text-on-surface"><?php echo $startEntry; ?>-<?php echo $endEntry; ?></span>
                    <span class="text-xs text-slate-500">of <?php echo number_format($filteredTotal); ?></span>
                </div>
            </div>

            <?php if (isset($_GET['customer_saved']) && $_GET['customer_saved'] === '1'): ?>
                <div
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm font-medium">
                    Customer created successfully.
                </div>
            <?php endif; ?>
            <?php if ($actionError !== ''): ?>
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm font-medium">
                    <?php echo h($actionError); ?>
                </div>
            <?php endif; ?>
            <?php if ($actionMessage !== ''): ?>
                <div
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm font-medium">
                    <?php echo h($actionMessage); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Customers</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Customers</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">
                        <?php echo number_format($customerStats['total_customers']); ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined">directions_car</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Linked</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Customers With Vehicles</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">
                        <?php echo number_format($customerStats['customers_with_vehicles']); ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined">garage</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-full">Fleet</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Vehicles</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">
                        <?php echo number_format($customerStats['total_vehicles']); ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-primary-container rounded-lg text-primary">
                            <span class="material-symbols-outlined">engineering</span>
                        </div>
                        <span
                            class="text-[10px] font-bold text-primary bg-primary-container px-2 py-1 rounded-full">Live</span>
                    </div>
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Active Vehicles</p>
                    <h3 class="text-2xl font-black text-on-surface mt-1">
                        <?php echo number_format($customerStats['active_vehicles']); ?></h3>
                </div>
            </div>

            <div id="add-customer" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-8">
                <div class="flex items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="font-bold text-on-surface text-lg">Add Customer</h3>
                        <p class="text-sm text-slate-500">Create a new client account in this tenant.</p>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-widest text-slate-400">Role: client</span>
                </div>
                <form method="post" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                    <div class="xl:col-span-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Full
                            Name</label>
                        <input name="fullName" value="<?php echo h($formData['fullName']); ?>"
                            class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary/20"
                            type="text" placeholder="Jane Doe" required />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Email</label>
                        <input name="email" value="<?php echo h($formData['email']); ?>"
                            class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary/20"
                            type="email" placeholder="jane@example.com" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Contact
                            Number</label>
                        <input name="contactNumber" value="<?php echo h($formData['contactNumber']); ?>"
                            class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary/20"
                            type="text" placeholder="09XXXXXXXXX" required />
                    </div>
                    <div class="xl:col-span-2">
                        <label
                            class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Address</label>
                        <input name="address" value="<?php echo h($formData['address']); ?>"
                            class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary/20"
                            type="text" placeholder="Street, city, province" />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Password</label>
                        <input name="password"
                            class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary/20"
                            type="password" placeholder="Set login password" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Confirm
                            Password</label>
                        <input name="confirm_password"
                            class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary/20"
                            type="password" placeholder="Repeat password" required />
                    </div>
                    <div class="xl:col-span-6 flex items-center justify-between gap-4 pt-2">
                        <p class="text-xs text-slate-500">The new customer will be created under this tenant and can be
                            linked to vehicles immediately.</p>
                        <button name="create_customer" value="1" type="submit"
                            class="px-5 py-2.5 bg-primary text-white font-bold text-sm rounded-lg hover:brightness-110 transition-all shadow-sm">
                            Save Customer
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <div
                    class="col-span-12 xl:col-span-8 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-on-surface">Customer Directory</h3>
                            <p class="text-xs text-slate-500">Each row includes the latest linked vehicle summary.</p>
                        </div>
                        <form method="get" action="customeradmin.php" class="flex items-center gap-2">
                            <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                            <?php if ($selectedCustomerId > 0): ?>
                                <input type="hidden" name="customer_id" value="<?php echo (int) $selectedCustomerId; ?>" />
                            <?php endif; ?>
                            <input name="q" value="<?php echo h($searchTerm); ?>"
                                class="rounded-lg border-slate-200 text-sm px-3 py-2 w-64" type="text"
                                placeholder="Search customers or vehicles" />
                            <button class="px-4 py-2 bg-slate-900 text-white text-sm font-bold rounded-lg"
                                type="submit">Search</button>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr
                                    class="bg-slate-50/70 text-slate-500 text-[10px] uppercase font-bold tracking-widest">
                                    <th class="px-6 py-4">Customer</th>
                                    <th class="px-6 py-4">Contact</th>
                                    <th class="px-6 py-4">Vehicles</th>
                                    <th class="px-6 py-4">Latest Vehicle</th>
                                    <th class="px-6 py-4">Latest Visit</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-16 text-center text-slate-500">
                                            No customers found for the current filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $customer): ?>
                                        <?php $isSelected = $selectedCustomerId === (int) $customer['user_id']; ?>
                                        <tr
                                            class="hover:bg-slate-50/60 transition-colors <?php echo $isSelected ? 'bg-primary-container/30' : ''; ?>">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 font-bold">
                                                        <?php echo h(strtoupper(substr((string) $customer['fullName'], 0, 1) ?: '?')); ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-bold text-on-surface">
                                                            <?php echo h($customer['fullName']); ?></p>
                                                        <p class="text-[11px] text-slate-500 font-medium">ID:
                                                            #<?php echo (int) $customer['user_id']; ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="text-sm text-on-surface"><?php echo h($customer['email']); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo h($customer['contactNumber']); ?>
                                                </p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[11px] font-bold rounded"><?php echo (int) $customer['vehicle_count']; ?></span>
                                                    <span class="text-sm text-on-surface">Vehicles linked</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="text-sm text-on-surface">
                                                    <?php echo h(trim((string) ($customer['latest_vehicle_label'] ?: 'No vehicle yet'))); ?>
                                                </p>
                                                <p class="text-[11px] text-slate-500">
                                                    <?php echo h($customer['latest_plate_number'] ?: 'No plate number'); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="text-sm text-on-surface">
                                                    <?php echo h(formatDateValue($customer['latest_vehicle_date'])); ?></p>
                                                <p class="text-[11px] text-slate-500">
                                                    <?php echo h($customer['latest_vehicle_status'] ?: 'No vehicle yet'); ?></p>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <a class="p-1.5 hover:bg-primary-container text-primary rounded transition-colors"
                                                        title="View linked vehicles"
                                                        href="customeradmin.php?<?php echo h(http_build_query(['shop' => $loginSlug, 'customer_id' => (int) $customer['user_id'], 'q' => $searchTerm])); ?>#linked-vehicles">
                                                        <span class="material-symbols-outlined text-lg">history</span>
                                                    </a>
                                                    <a class="p-1.5 hover:bg-slate-100 text-secondary rounded transition-colors"
                                                        title="Open vehicle admin"
                                                        href="vehicleadmin.php?<?php echo h(http_build_query(['shop' => $loginSlug, 'user_id' => (int) $customer['user_id']])); ?>">
                                                        <span class="material-symbols-outlined text-lg">directions_car</span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="mt-auto px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/40">
                        <p class="text-xs text-slate-500 font-medium">Showing <span
                                class="text-on-surface font-bold"><?php echo $startEntry; ?>-<?php echo $endEntry; ?></span>
                            of <?php echo number_format($filteredTotal); ?> customers</p>
                        <div class="flex gap-1">
                            <?php
                            $pageParams = ['shop' => $loginSlug, 'q' => $searchTerm];
                            if ($selectedCustomerId > 0) {
                                $pageParams['customer_id'] = $selectedCustomerId;
                            }
                            ?>
                            <a class="p-2 border border-outline rounded bg-white hover:bg-slate-50 <?php echo $page <= 1 ? 'pointer-events-none opacity-50' : ''; ?>"
                                href="customeradmin.php?<?php echo h(http_build_query($pageParams + ['page' => max(1, $page - 1)])); ?>">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </a>
                            <?php for ($pageNumber = max(1, $page - 1); $pageNumber <= min($totalPages, $page + 1); $pageNumber++): ?>
                                <a class="px-3 py-1 <?php echo $pageNumber === $page ? 'bg-primary text-white' : 'hover:bg-slate-100'; ?> text-xs font-bold rounded"
                                    href="customeradmin.php?<?php echo h(http_build_query($pageParams + ['page' => $pageNumber])); ?>">
                                    <?php echo $pageNumber; ?>
                                </a>
                            <?php endfor; ?>
                            <a class="p-2 border border-outline rounded bg-white hover:bg-slate-50 <?php echo $page >= $totalPages ? 'pointer-events-none opacity-50' : ''; ?>"
                                href="customeradmin.php?<?php echo h(http_build_query($pageParams + ['page' => min($totalPages, $page + 1)])); ?>">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 xl:col-span-4 space-y-6">
                    <div id="linked-vehicles" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <div class="flex items-start justify-between gap-3 mb-5">
                            <div>
                                <h3 class="font-bold text-on-surface">Linked Vehicles</h3>
                                <p class="text-xs text-slate-500"><?php echo h($selectedCustomerName); ?></p>
                            </div>
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-primary bg-primary-container px-2 py-1 rounded-full"><?php echo $selectedCustomerVehiclesCount; ?>
                                vehicles</span>
                        </div>

                        <?php if ($selectedCustomer): ?>
                            <div class="rounded-xl bg-slate-50 p-4 mb-4 border border-slate-200">
                                <p class="text-sm font-bold text-on-surface"><?php echo h($selectedCustomer['fullName']); ?>
                                </p>
                                <p class="text-xs text-slate-500 mt-1"><?php echo h($selectedCustomer['email']); ?></p>
                                <p class="text-xs text-slate-500 mt-1"><?php echo h($selectedCustomer['contactNumber']); ?>
                                </p>
                                <p class="text-xs text-slate-500 mt-1">
                                    <?php echo h($selectedCustomer['address'] ?: 'No address set'); ?></p>
                                <div class="grid grid-cols-2 gap-3 mt-4 text-center">
                                    <div class="rounded-lg bg-white border border-slate-200 p-3">
                                        <p class="text-lg font-black text-on-surface">
                                            <?php echo $selectedCustomerVehiclesCount; ?></p>
                                        <p class="text-[10px] uppercase tracking-widest text-slate-500">Vehicles</p>
                                    </div>
                                    <div class="rounded-lg bg-white border border-slate-200 p-3">
                                        <p class="text-lg font-black text-on-surface">
                                            <?php echo $selectedCustomerActiveVehicles; ?></p>
                                        <p class="text-[10px] uppercase tracking-widest text-slate-500">Active</p>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <a href="vehicleadmin.php?<?php echo h(http_build_query(['shop' => $loginSlug, 'user_id' => $selectedCustomerId, 'add_vehicle' => 1])); ?>"
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-primary text-white text-xs font-bold">
                                        <span class="material-symbols-outlined text-sm">add_circle</span>
                                        Add Vehicle
                                    </a>
                                    <a href="vehicleadmin.php?<?php echo h(http_build_query(['shop' => $loginSlug, 'user_id' => $selectedCustomerId])); ?>"
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-outline text-secondary text-xs font-bold bg-white">
                                        <span class="material-symbols-outlined text-sm">garage</span>
                                        Open Vehicles
                                    </a>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <?php if (empty($selectedVehicles)): ?>
                                    <div class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">No
                                        vehicles are linked to this customer yet.</div>
                                <?php else: ?>
                                    <?php foreach ($selectedVehicles as $vehicleRow): ?>
                                        <div class="rounded-xl border border-slate-200 p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-bold text-on-surface">
                                                        <?php echo h(trim((string) $vehicleRow['brand'] . ' ' . (string) $vehicleRow['model'])); ?>
                                                    </p>
                                                    <p class="text-xs text-slate-500 mt-1">
                                                        <?php echo h(($vehicleRow['year_model'] ?: '----') . ' • ' . ($vehicleRow['plate_number'] ?: 'No plate')); ?>
                                                    </p>
                                                </div>
                                                <span
                                                    class="text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded-full <?php echo customerStatusClass($vehicleRow['status'] ?? null); ?>"><?php echo h($vehicleRow['status'] ?? 'Unknown'); ?></span>
                                            </div>
                                            <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500">
                                                <span><?php echo h($vehicleRow['color'] ?: 'No color set'); ?></span>
                                                <span><?php echo h(formatDateValue($vehicleRow['date_added'], 'Unknown date')); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">
                                Select a customer from the table to see their linked vehicles.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h3 class="font-bold text-on-surface mb-5 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl">notifications_active</span>
                            Recent Activity
                        </h3>
                        <div class="space-y-4">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-sm text-slate-500">No recent vehicle activity yet.</div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="rounded-xl border border-slate-200 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-bold text-on-surface">
                                                    <?php echo h($activity['customer_name']); ?></p>
                                                <p class="text-xs text-slate-500 mt-1">
                                                    <?php echo h(trim((string) $activity['brand'] . ' ' . (string) $activity['model'])); ?>
                                                    • <?php echo h($activity['plate_number'] ?: 'No plate'); ?></p>
                                            </div>
                                            <span
                                                class="text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded-full <?php echo customerStatusClass($activity['status'] ?? null); ?>"><?php echo h($activity['status'] ?? 'Unknown'); ?></span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 mt-3">
                                            <?php echo h(formatDateValue($activity['date_added'], 'Unknown date')); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-primary to-slate-800 rounded-xl p-6 text-white shadow-md relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-xs font-bold uppercase tracking-widest opacity-80">Customer Coverage</p>
                            <h4 class="text-xl font-black mt-2 leading-tight">
                                <?php echo number_format($customerStats['customers_with_vehicles']); ?> customers
                                already linked</h4>
                            <p class="text-xs mt-3 opacity-90 leading-relaxed">Use the open vehicle button to move
                                directly into the filtered vehicle list for the selected customer.</p>
                        </div>
                        <div class="absolute -right-4 -bottom-4 opacity-10">
                            <span class="material-symbols-outlined text-[120px]">auto_awesome</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>