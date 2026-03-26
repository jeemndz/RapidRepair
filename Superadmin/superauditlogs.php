<?php
session_start();
require_once __DIR__ . "/../db.php";

if (isset($_POST['logout_superadmin'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header("Location: superaddlogin.php");
    exit();
}

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

$superadminName = "Superadmin";
$superadminStmt = $conn->prepare("SELECT fullName FROM superadmin WHERE superadmin_id = ? LIMIT 1");
if ($superadminStmt) {
    $superadminStmt->bind_param("i", $_SESSION['superadmin_id']);
    $superadminStmt->execute();
    $superadminRes = $superadminStmt->get_result();
    if ($superadminRes && $superadminRes->num_rows > 0) {
        $superadminRow = $superadminRes->fetch_assoc();
        $superadminName = $superadminRow['fullName'] ?: $superadminName;
    }
    $superadminStmt->close();
}

$q = trim($_GET['q'] ?? '');
$dateRange = trim($_GET['date_range'] ?? '30d');
$action = trim($_GET['action'] ?? '');
$role = trim($_GET['role'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(user_name LIKE ? OR action LIKE ? OR details LIKE ? OR entity_type LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if ($action !== '') {
    $where[] = "action = ?";
    $params[] = $action;
    $types .= 's';
}

if ($role !== '') {
    $where[] = "user_role = ?";
    $params[] = $role;
    $types .= 's';
}

if ($entityType !== '') {
    $where[] = "entity_type = ?";
    $params[] = $entityType;
    $types .= 's';
}

switch ($dateRange) {
    case '24h':
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        break;
    case '7d':
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30d':
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'all':
    default:
        break;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) AS total FROM system_logs $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt && !empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$totalRows = 0;
if ($countStmt) {
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $totalRows = (int)($countRes->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$selectSql = "
    SELECT log_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, created_at
    FROM system_logs
    $whereSql
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
$selectStmt = $conn->prepare($selectSql);
$rows = [];
if ($selectStmt) {
    $paramsWithPaging = $params;
    $typesWithPaging = $types . 'ii';
    $paramsWithPaging[] = $perPage;
    $paramsWithPaging[] = $offset;
    $selectStmt->bind_param($typesWithPaging, ...$paramsWithPaging);
    $selectStmt->execute();
    $rows = $selectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $selectStmt->close();
}

$actions = [];
$actionRes = $conn->query("SELECT DISTINCT action FROM system_logs WHERE action IS NOT NULL AND action <> '' ORDER BY action ASC");
if ($actionRes) {
    while ($a = $actionRes->fetch_assoc()) {
        $actions[] = $a['action'];
    }
}

$roles = [];
$roleRes = $conn->query("SELECT DISTINCT user_role FROM system_logs WHERE user_role IS NOT NULL AND user_role <> '' ORDER BY user_role ASC");
if ($roleRes) {
    while ($r = $roleRes->fetch_assoc()) {
        $roles[] = $r['user_role'];
    }
}

$entityTypes = [];
$entityRes = $conn->query("SELECT DISTINCT entity_type FROM system_logs WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type ASC");
if ($entityRes) {
    while ($e = $entityRes->fetch_assoc()) {
        $entityTypes[] = $e['entity_type'];
    }
}

$logsToday = 0;
$todayRes = $conn->query("SELECT COUNT(*) AS total FROM system_logs WHERE DATE(created_at) = CURDATE()");
if ($todayRes) {
    $logsToday = (int)($todayRes->fetch_assoc()['total'] ?? 0);
}

$activeSessions = 0;
$activeRes = $conn->query("SELECT COUNT(DISTINCT ip_address) AS total FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND action LIKE '%login%' AND ip_address IS NOT NULL AND ip_address <> ''");
if ($activeRes) {
    $activeSessions = (int)($activeRes->fetch_assoc()['total'] ?? 0);
}

$criticalActions = 0;
$criticalRes = $conn->query("SELECT COUNT(*) AS total FROM system_logs WHERE LOWER(action) REGEXP 'delete|remove|drop|disable|revoke|block|suspend|reset' AND DATE(created_at) = CURDATE()");
if ($criticalRes) {
    $criticalActions = (int)($criticalRes->fetch_assoc()['total'] ?? 0);
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvSql = "
        SELECT user_name, user_role, action, entity_type, entity_id, details, ip_address, created_at
        FROM system_logs
        $whereSql
        ORDER BY created_at DESC
    ";
    $csvStmt = $conn->prepare($csvSql);
    if ($csvStmt && !empty($params)) {
        $csvStmt->bind_param($types, ...$params);
    }

    $csvRows = [];
    if ($csvStmt) {
        $csvStmt->execute();
        $csvRows = $csvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $csvStmt->close();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['User', 'Role', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address', 'Timestamp']);
    foreach ($csvRows as $csvRow) {
        fputcsv($out, [
            $csvRow['user_name'] ?? '',
            $csvRow['user_role'] ?? '',
            $csvRow['action'] ?? '',
            $csvRow['entity_type'] ?? '',
            $csvRow['entity_id'] ?? '',
            $csvRow['details'] ?? '',
            $csvRow['ip_address'] ?? '',
            $csvRow['created_at'] ?? ''
        ]);
    }
    fclose($out);
    exit();
}

function initials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'NA';
    }
    $parts = preg_split('/\s+/', $name);
    if (!$parts) {
        return 'NA';
    }
    $first = strtoupper(substr($parts[0], 0, 1));
    $second = count($parts) > 1 ? strtoupper(substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . ($second ?: '');
}

function actionBadgeClass(string $action): string
{
    $value = strtolower(trim($action));
    if (strpos($value, 'create') !== false || strpos($value, 'add') !== false || strpos($value, 'approve') !== false) {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (strpos($value, 'delete') !== false || strpos($value, 'remove') !== false || strpos($value, 'drop') !== false) {
        return 'bg-red-100 text-red-800';
    }
    if (strpos($value, 'login') !== false || strpos($value, 'auth') !== false || strpos($value, 'signin') !== false) {
        return 'bg-slate-100 text-slate-700';
    }
    return 'bg-slate-100 text-slate-700';
}

$queryBase = $_GET;
unset($queryBase['page'], $queryBase['export']);
$showFrom = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$showTo = min($totalRows, $page * $perPage);
$prevPage = max(1, $page - 1);
$nextPage = min($totalPages, $page + 1);
?>

<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Audit Logs | RepidRepair</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "secondary-fixed": "#e5e7eb",
                        "on-secondary": "#ffffff",
                        "on-secondary-container": "#18181b",
                        "tertiary": "#f59e0b",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "inverse-surface": "#18181b",
                        "on-tertiary": "#ffffff",
                        "primary-container": "#fee2e2",
                        "on-error-container": "#991b1b",
                        "tertiary-fixed": "#ffedd5",
                        "tertiary-container": "#fef3c7",
                        "outline": "#e5e7eb",
                        "on-secondary-fixed": "#111827",
                        "on-surface-variant": "#525252",
                        "surface-container-lowest": "#ffffff",
                        "error-container": "#fee2e2",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-bright": "#ffffff",
                        "secondary-container": "#f5f5f5",
                        "on-primary-fixed": "#7f1d1d",
                        "surface-tint": "#b91c1c",
                        "surface-dim": "#e5e7eb",
                        "error": "#dc2626",
                        "on-tertiary-container": "#92400e",
                        "primary-fixed": "#fee2e2",
                        "on-secondary-fixed-variant": "#3f3f46",
                        "surface": "#ffffff",
                        "background": "#ffffff",
                        "outline-variant": "#d4d4d8",
                        "on-primary": "#ffffff",
                        "inverse-on-surface": "#f8fafc",
                        "on-tertiary-fixed": "#7c2d12",
                        "surface-container": "#ffffff",
                        "secondary": "#3f3f46",
                        "primary": "#b91c1c",
                        "on-primary-container": "#7f1d1d",
                        "surface-container-highest": "#ffffff",
                        "primary-fixed-dim": "#fecaca",
                        "on-surface": "#111827",
                        "on-background": "#0a0a0a",
                        "inverse-primary": "#fecaca",
                        "surface-container-high": "#ffffff",
                        "surface-variant": "#f5f5f5",
                        "on-primary-fixed-variant": "#991b1b",
                        "on-error": "#ffffff",
                        "surface-container-low": "#ffffff",
                        "secondary-fixed-dim": "#d4d4d8"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-surface text-on-surface antialiased overflow-x-hidden">
    <!-- Side Navigation -->
    <aside
        class="flex flex-col fixed left-0 top-0 h-full z-40 h-screen w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
        <!-- Brand Header -->
            <div class="p-6 flex items-center gap-3">
                <div class="size-12 rounded-lg bg-white p-1 shadow-md dark:bg-slate-900">
                    <img src="../pictures/RRlogo3.png" alt="Rapid Repair logo" class="h-full w-full object-contain drop-shadow-sm">
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                    RapidRepair <span class="text-primary">SuperAdmin</span>
                </h2>
            </div>
           <!-- Navigation Links -->
        <nav class="flex-1 px-4 space-y-1 mt-4">
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superadd.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                <span class="text-sm">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superaddtenants.php">
                <span class="material-symbols-outlined" data-icon="groups">groups</span>
                <span class="text-sm">Tenants</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superreports.php">
                <span class="material-symbols-outlined" data-icon="bar_chart">bar_chart</span>
                <span class="text-sm">Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="subscriptionmanage.php">
                <span class="material-symbols-outlined" data-icon="subscriptions">subscriptions</span>
                <span class="text-sm">Subscriptions</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersalesreport.php">
                <span class="material-symbols-outlined" data-icon="monitoring">monitoring</span>
                <span class="text-sm">Sales Reports</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>

            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superbackup.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>
            
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>

        </nav>
        <!-- Footer Actions matching SCREEN_11 -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-semibold text-sm">
                    <?= htmlspecialchars(initials($superadminName)) ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?= htmlspecialchars($superadminName) ?></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <form method="POST">
                <button type="submit" name="logout_superadmin"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="text-sm font-medium">Logout</span>
                </button>
            </form>
        </div>
    </aside>
    <!-- TopNavBar matching SCREEN_11 -->
    <header
        class="flex items-center justify-between px-8 sticky top-0 z-30 ml-64 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md h-16 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <form method="GET" class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-variant">
                    <span class="material-symbols-outlined text-lg" data-icon="search">search</span>
                </span>
                <input name="q"
                    class="pl-10 pr-4 py-1.5 bg-slate-100 dark:bg-slate-800 border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all outline-none"
                    placeholder="Search audit logs by user, action, or entity..." type="text" value="<?= htmlspecialchars($q) ?>" />
                <input type="hidden" name="date_range" value="<?= htmlspecialchars($dateRange) ?>">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                <input type="hidden" name="entity_type" value="<?= htmlspecialchars($entityType) ?>">
            </form>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined">notifications</span>
            </button>
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined">help_outline</span>
            </button>
        </div>
    </header>
    <!-- Main Content -->
    <main class="ml-64 p-8 min-h-[calc(100vh-16px)] bg-surface">
        <div class="max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-[1.875rem] font-black text-on-surface tracking-tight font-headline">Audit Logs</h1>
                    <p class="text-on-surface-variant text-sm mt-1">Comprehensive system activity monitoring and
                        compliance tracking.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($queryBase, ['q' => $q, 'date_range' => $dateRange, 'action' => $action, 'role' => $role, 'entity_type' => $entityType, 'export' => 'csv']))) ?>"
                        class="inline-flex items-center px-4 py-2 bg-white border border-outline hover:bg-slate-50 text-on-surface text-sm font-semibold rounded-lg transition-all shadow-sm">
                        <span class="material-symbols-outlined mr-2 text-lg" data-icon="download">download</span>
                        Export CSV
                    </a>
                    <button onclick="window.print()" type="button"
                        class="inline-flex items-center px-4 py-2 bg-white border border-outline hover:bg-slate-50 text-on-surface text-sm font-semibold rounded-lg transition-all shadow-sm">
                        <span class="material-symbols-outlined mr-2 text-lg" data-icon="picture_as_pdf">picture_as_pdf</span>
                        Print / PDF
                    </button>
                </div>
            </div>
            <!-- Filter Bar -->
            <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Date
                            Range</label>
                        <select name="date_range"
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-primary focus:border-primary py-2">
                            <option value="24h" <?= $dateRange === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                            <option value="7d" <?= $dateRange === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30d" <?= $dateRange === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All Time</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Action
                            Category</label>
                        <select name="action"
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-primary focus:border-primary py-2">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $actionOption): ?>
                                <option value="<?= htmlspecialchars($actionOption) ?>" <?= $action === $actionOption ? 'selected' : '' ?>><?= htmlspecialchars($actionOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Admin/User</label>
                        <select name="role"
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-primary focus:border-primary py-2">
                            <option value="">All Users</option>
                            <?php foreach ($roles as $roleOption): ?>
                                <option value="<?= htmlspecialchars($roleOption) ?>" <?= $role === $roleOption ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($roleOption)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-1">Target Entity</label>
                        <select name="entity_type"
                            class="w-full bg-slate-50 border-slate-200 text-sm rounded-lg focus:ring-primary focus:border-primary py-2">
                            <option value="">All Entities</option>
                            <?php foreach ($entityTypes as $entityOption): ?>
                                <option value="<?= htmlspecialchars($entityOption) ?>" <?= $entityType === $entityOption ? 'selected' : '' ?>><?= htmlspecialchars($entityOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full bg-red-700 hover:bg-red-800 text-white font-bold text-sm py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg" data-icon="filter_list">filter_list</span>
                            Apply Filters
                        </button>
                    </div>
                </div>
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
            </form>
            <!-- Audit Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">User /
                                Administrator</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Action
                            </th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Target
                                Entity</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Change
                                Details</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                Timestamp</th>
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">
                                Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500 font-medium">No audit logs found for the selected filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    $userName = $row['user_name'] ?: 'Unknown User';
                                    $userRole = $row['user_role'] ?: 'Unknown Role';
                                    $entityLabel = 'Global System';
                                    if (!empty($row['entity_type'])) {
                                        $entityLabel = $row['entity_type'];
                                        if (!empty($row['entity_id'])) {
                                            $entityLabel .= ' #' . (int)$row['entity_id'];
                                        }
                                    }
                                    $timestamp = strtotime((string)$row['created_at']);
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded bg-primary-container text-primary flex items-center justify-center font-bold text-xs">
                                                <?= htmlspecialchars(initials($userName)) ?>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-on-surface"><?= htmlspecialchars($userName) ?></div>
                                                <div class="text-xs text-on-surface-variant"><?= htmlspecialchars($userRole) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= actionBadgeClass((string)$row['action']) ?>">
                                            <?= htmlspecialchars($row['action'] ?: 'Action') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><div class="text-sm font-medium text-on-surface"><?= htmlspecialchars($entityLabel) ?></div></td>
                                    <td class="px-6 py-4"><div class="text-sm text-on-surface-variant max-w-xs truncate" title="<?= htmlspecialchars($row['details'] ?: '') ?>"><?= htmlspecialchars($row['details'] ?: 'No details available.') ?></div></td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-on-surface"><?= htmlspecialchars($timestamp ? date('M d, Y', $timestamp) : '-') ?></div>
                                        <div class="text-[10px] text-on-surface-variant"><?= htmlspecialchars($timestamp ? date('H:i:s', $timestamp) : '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="text-xs text-slate-500">#<?= (int)$row['log_id'] ?></div>
                                        <div class="text-[10px] text-slate-400 truncate max-w-[140px] ml-auto" title="<?= htmlspecialchars($row['ip_address'] ?: '') ?>"><?= htmlspecialchars($row['ip_address'] ?: 'No IP') ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Pagination -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="text-sm text-on-surface-variant">
                        Showing <span class="font-bold text-on-surface"><?= (int)$showFrom ?> - <?= (int)$showTo ?></span> of <span class="font-bold text-on-surface"><?= (int)$totalRows ?></span> logs
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="?<?= htmlspecialchars(http_build_query(array_merge($queryBase, ['q' => $q, 'date_range' => $dateRange, 'action' => $action, 'role' => $role, 'entity_type' => $entityType, 'page' => $prevPage]))) ?>"
                            class="p-1 rounded hover:bg-slate-200 text-slate-400 transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-30' : '' ?>">
                            <span class="material-symbols-outlined" data-icon="chevron_left">chevron_left</span>
                        </a>
                        <span class="h-8 min-w-8 px-2 flex items-center justify-center rounded bg-red-700 text-white text-xs font-bold"><?= (int)$page ?></span>
                        <a href="?<?= htmlspecialchars(http_build_query(array_merge($queryBase, ['q' => $q, 'date_range' => $dateRange, 'action' => $action, 'role' => $role, 'entity_type' => $entityType, 'page' => $nextPage]))) ?>"
                            class="p-1 rounded hover:bg-slate-200 text-slate-400 transition-colors <?= $page >= $totalPages ? 'pointer-events-none opacity-30' : '' ?>">
                            <span class="material-symbols-outlined" data-icon="chevron_right">chevron_right</span>
                        </a>
                    </div>
                </div>
            </div>
            <!-- Summary Stats (Bento Grid Style) -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-lg bg-red-50 flex items-center justify-center text-red-700">
                        <span class="material-symbols-outlined" data-icon="security">security</span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Active Sessions
                        </div>
                        <div class="text-2xl font-black text-on-surface"><?= (int)$activeSessions ?></div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700">
                        <span class="material-symbols-outlined" data-icon="history">history</span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Logs Today</div>
                        <div class="text-2xl font-black text-on-surface"><?= (int)$logsToday ?></div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700">
                        <span class="material-symbols-outlined" data-icon="warning">warning</span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Critical Actions
                        </div>
                        <div class="text-2xl font-black text-on-surface"><?= (int)$criticalActions ?></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>