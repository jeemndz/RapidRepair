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

function initials($name)
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

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatBytes($bytes)
{
    $bytes = (float)$bytes;
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int)floor(log($bytes, 1024));
    $power = max(0, min($power, count($units) - 1));
    $value = $bytes / (1024 ** $power);
    return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
}

function listBackupFiles($backupDir)
{
    if (!is_dir($backupDir)) {
        return [];
    }

    $files = glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    $result = [];

    foreach ($files as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        $basename = basename($filePath);
        $mtime = filemtime($filePath) ?: 0;
        $size = filesize($filePath) ?: 0;

        $result[] = [
            'name' => $basename,
            'path' => $filePath,
            'mtime' => $mtime,
            'size' => $size,
        ];
    }

    usort($result, static function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });

    return $result;
}

function createDatabaseBackup(mysqli $conn, $backupPath)
{
    $tables = [];
    $tablesRes = $conn->query('SHOW TABLES');
    if (!$tablesRes) {
        return false;
    }

    while ($row = $tablesRes->fetch_row()) {
        if (!empty($row[0])) {
            $tables[] = $row[0];
        }
    }
    $tablesRes->free();

    if (empty($tables)) {
        return false;
    }

    $content = "-- RapidRepair Database Backup\n";
    $content .= '-- Generated at: ' . date('Y-m-d H:i:s') . "\n";
    $content .= '-- Database: ' . $conn->real_escape_string($conn->query('SELECT DATABASE()')->fetch_row()[0] ?? 'unknown') . "\n\n";
    $content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $content .= "SET AUTOCOMMIT = 0;\n";
    $content .= "START TRANSACTION;\n";
    $content .= "SET time_zone = \"+00:00\";\n\n";

    foreach ($tables as $table) {
        $safeTable = '`' . str_replace('`', '``', $table) . '`';

        $createRes = $conn->query('SHOW CREATE TABLE ' . $safeTable);
        if (!$createRes) {
            continue;
        }

        $createRow = $createRes->fetch_assoc();
        $createRes->free();
        if (!$createRow || !isset($createRow['Create Table'])) {
            continue;
        }

        $content .= "-- ----------------------------\n";
        $content .= '-- Table structure for ' . $table . "\n";
        $content .= "-- ----------------------------\n";
        $content .= 'DROP TABLE IF EXISTS ' . $safeTable . ";\n";
        $content .= $createRow['Create Table'] . ";\n\n";

        $dataRes = $conn->query('SELECT * FROM ' . $safeTable);
        if (!$dataRes) {
            continue;
        }

        if ($dataRes->num_rows > 0) {
            $content .= "-- ----------------------------\n";
            $content .= '-- Records of ' . $table . "\n";
            $content .= "-- ----------------------------\n";

            while ($row = $dataRes->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string((string)$value) . "'";
                    }
                }

                $content .= 'INSERT INTO ' . $safeTable . ' VALUES (' . implode(', ', $values) . ");\n";
            }
            $content .= "\n";
        }

        $dataRes->free();
    }

    $content .= "COMMIT;\n";

    return file_put_contents($backupPath, $content) !== false;
}

$backupDir = realpath(__DIR__ . '/../backups');
if ($backupDir === false) {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    $backupDir = realpath($backupDir) ?: $backupDir;
}

$message = '';
$messageType = '';
$search = trim($_GET['q'] ?? '');

if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    $fileName = basename((string)$_GET['file']);
    $target = realpath($backupDir . DIRECTORY_SEPARATOR . $fileName);

    if ($target && strpos($target, realpath($backupDir)) === 0 && is_file($target)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($target));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($target);
        exit();
    }

    $message = 'Backup file not found.';
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_backup'])) {
        if (!is_writable($backupDir)) {
            $message = 'Backup folder is not writable.';
            $messageType = 'error';
        } else {
            $fileName = 'backup_rapidrepair_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

            if (createDatabaseBackup($conn, $backupPath)) {
                header('Location: superbackup.php?success=' . urlencode($fileName));
                exit();
            }

            $message = 'Backup creation failed. Please try again.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
        $fileName = basename((string)$_POST['backup_file']);
        $target = realpath($backupDir . DIRECTORY_SEPARATOR . $fileName);
        $basePath = realpath($backupDir);

        if ($target && $basePath && strpos($target, $basePath) === 0 && is_file($target)) {
            if (@unlink($target)) {
                header('Location: superbackup.php?deleted=' . urlencode($fileName));
                exit();
            }
            $message = 'Could not delete backup file.';
            $messageType = 'error';
        } else {
            $message = 'Invalid backup file.';
            $messageType = 'error';
        }
    }
}

if (isset($_GET['success'])) {
    $message = 'Backup created successfully: ' . basename((string)$_GET['success']);
    $messageType = 'success';
} elseif (isset($_GET['deleted'])) {
    $message = 'Backup deleted: ' . basename((string)$_GET['deleted']);
    $messageType = 'success';
}

$allBackups = listBackupFiles($backupDir);
$backups = $allBackups;

if ($search !== '') {
    $backups = array_values(array_filter($allBackups, static function ($file) use ($search) {
        return stripos($file['name'], $search) !== false;
    }));
}

$latestBackup = $allBackups[0] ?? null;
$totalStorage = array_sum(array_column($allBackups, 'size'));
$canWriteBackups = is_writable($backupDir);
$maxDisplay = 12;
$displayBackups = array_slice($backups, 0, $maxDisplay);
?>

<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>System Backup &amp; Recovery | Cobalt Precision Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
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
                        "tertiary-container": "#fef3c7",
                        "inverse-surface": "#18181b",
                        "on-tertiary": "#ffffff",
                        "primary-container": "#fee2e2",
                        "on-error-container": "#991b1b",
                        "tertiary-fixed": "#ffedd5",
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
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#f59e0b",
                        "surface-container-low": "#ffffff",
                        "secondary-fixed-dim": "#d4d4d8",
                        "on-tertiary-fixed-variant": "#9a3412"
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
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-surface text-on-surface antialiased">
    <!-- SideNavBar Component -->
    <!-- SideNavBar (Shared Component) -->
        <aside
            class="flex flex-col fixed left-0 top-0 h-full z-50 w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 font-['Inter'] antialiased tracking-tight shadow-sm dark:shadow-none">
            <div class="p-6 flex items-center gap-3">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined block text-2xl">directions_car</span>
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
            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superauditlogs.php">
                <span class="material-symbols-outlined" data-icon="assignment">assignment</span>
                <span class="text-sm">Audit Logs</span>
            </a>

            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
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
        <div class="p-4 border-t border-slate-100 space-y-2">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-semibold text-sm">
                    <?php echo htmlspecialchars(initials($superadminName)); ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate"><?php echo htmlspecialchars($superadminName); ?></h3>
                    <p class="text-xs text-slate-500 truncate">Superadmin</p>
                </div>
            </div>
            <form method="POST" class="w-full">
                <button type="submit" name="logout_superadmin"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 transition-colors cursor-pointer text-left mt-2">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </button>
            </form>
        </div>
    </aside>
    <!-- TopNavBar Component -->
    <header class="fixed top-0 right-0 left-64 h-16 border-b border-slate-200 bg-white/80 backdrop-blur-md z-40">
        <div class="flex items-center justify-between px-8 h-full">
            <div class="flex items-center gap-8">
                <span class="text-lg font-black text-primary tracking-tight">System Backup &amp; Recovery</span>
                <form method="GET" class="relative group">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
                    <input
                        name="q"
                        value="<?php echo h($search); ?>"
                        class="pl-10 pr-4 py-1.5 bg-slate-100 border-none rounded-lg text-sm w-64 focus:ring-2 focus:ring-primary/20 transition-all"
                        placeholder="Search backup file..." type="text" />
                </form>
            </div>
            <div class="flex items-center gap-4">
                <button type="button"
                    class="px-4 py-2 text-sm font-bold text-primary border border-primary/20 rounded-lg hover:bg-red-50 transition-colors cursor-not-allowed"
                    title="Restore flow is not implemented on this page yet" disabled>Restore
                    System</button>
                <form method="POST">
                    <button type="submit" name="create_backup"
                        class="px-4 py-2 text-sm font-bold text-white bg-primary rounded-lg shadow-sm hover:opacity-90 active:opacity-80 transition-all">Create
                        Backup</button>
                </form>
                <div class="h-6 w-[1px] bg-slate-200 mx-2"></div>
                <div class="flex items-center gap-2">
                    <button class="p-2 hover:bg-slate-100 rounded-md transition-colors relative">
                        <span class="material-symbols-outlined text-slate-600">notifications</span>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-error rounded-full border-2 border-white"></span>
                    </button>
                    <button class="p-2 hover:bg-slate-100 rounded-md transition-colors">
                        <span class="material-symbols-outlined text-slate-600">help_outline</span>
                    </button>
                </div>
            </div>
        </div>
    </header>
    <!-- Main Content Canvas -->
    <main class="ml-64 pt-24 p-8 min-h-screen">
        <div class="max-w-7xl mx-auto space-y-8">
            <?php if ($message !== ''): ?>
                <div class="rounded-lg border px-4 py-3 <?php echo $messageType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>
            <!-- Status Overview: Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 border border-slate-200 rounded-lg shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]">update</span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Last Backup Date</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo $latestBackup ? h(date('M d, Y', $latestBackup['mtime'])) : 'No Backup'; ?></h3>
                        <p class="text-xs text-slate-400 mt-1 italic"><?php echo $latestBackup ? h(date('h:i:s A', $latestBackup['mtime'])) : 'Create your first backup'; ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 border border-slate-200 rounded-lg shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]">cloud_queue</span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Storage Used</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo h(formatBytes($totalStorage)); ?></h3>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                            <div class="bg-primary h-full w-[<?php echo min(100, count($allBackups) * 10); ?>%]"></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 border border-slate-200 rounded-lg shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]">check_circle</span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">System Status</p>
                        <h3 class="text-2xl font-black text-slate-900 mt-1"><?php echo $canWriteBackups ? 'Operational' : 'Attention'; ?></h3>
                        <p class="text-xs <?php echo $canWriteBackups ? 'text-emerald-600' : 'text-red-600'; ?> font-bold mt-1 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 <?php echo $canWriteBackups ? 'bg-emerald-500' : 'bg-red-500'; ?> rounded-full"></span>
                            <?php echo $canWriteBackups ? 'Backup folder writable' : 'Backup folder needs write permission'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Table Area -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Backup History Table -->
                    <section class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900">Backup History</h2>
                            <span class="text-primary text-xs font-bold"><?php echo count($backups); ?> file(s)</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead
                                    class="bg-slate-50 text-[10px] uppercase font-black tracking-widest text-slate-400">
                                    <tr>
                                        <th class="px-6 py-3 border-b border-slate-100">Date/Time</th>
                                        <th class="px-6 py-3 border-b border-slate-100">Type</th>
                                        <th class="px-6 py-3 border-b border-slate-100">Size</th>
                                        <th class="px-6 py-3 border-b border-slate-100">Status</th>
                                        <th class="px-6 py-3 border-b border-slate-100 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (empty($displayBackups)): ?>
                                        <tr>
                                            <td class="px-6 py-6 text-sm text-slate-500" colspan="5">No backup files found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($displayBackups as $backup): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-bold text-slate-900"><?php echo h(date('M d, Y', $backup['mtime'])); ?></div>
                                                    <div class="text-xs text-slate-500"><?php echo h(date('h:i:s A', $backup['mtime'])); ?></div>
                                                </td>
                                                <td class="px-6 py-4 text-xs font-medium text-slate-600">Full (.sql)</td>
                                                <td class="px-6 py-4 text-xs font-medium text-slate-600"><?php echo h(formatBytes($backup['size'])); ?></td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 py-1 text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 rounded-full">Available</span>
                                                </td>
                                                <td class="px-6 py-4 text-right space-x-2">
                                                    <a href="superbackup.php?action=download&amp;file=<?php echo urlencode($backup['name']); ?>"
                                                        class="inline-flex p-1.5 hover:bg-red-50 text-primary rounded transition-colors">
                                                        <span class="material-symbols-outlined text-[18px]">download</span>
                                                    </a>
                                                    <form method="POST" class="inline-block" onsubmit="return confirm('Delete this backup file?');">
                                                        <input type="hidden" name="backup_file" value="<?php echo h($backup['name']); ?>">
                                                        <button type="submit" name="delete_backup" class="p-1.5 hover:bg-error-container text-error rounded transition-colors">
                                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <!-- Restore Points Section -->
                    <section>
                        <h2 class="text-lg font-bold text-slate-900 mb-4">Verified Restore Points</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Card 1 -->
                            <div
                                class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm hover:border-primary/40 transition-all group">
                                <div class="flex items-center justify-between mb-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-primary">
                                        <span class="material-symbols-outlined text-[20px]"
                                            data-weight="fill">verified</span>
                                    </div>
                                    <span
                                        class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded">Stable
                                        Branch</span>
                                </div>
                                <h4 class="text-base font-bold text-slate-900">v4.12.0 Production Build</h4>
                                <p class="text-xs text-slate-500 mt-1 mb-4">Last verified integrity check: 2 hours ago.
                                    100% data consistency score.</p>
                                <div class="flex items-center justify-between mt-auto pt-4 border-t border-slate-50">
                                    <div class="text-[11px] text-slate-400">Oct 20, 2023 • 12:00 PM</div>
                                    <button
                                        class="text-primary text-xs font-black uppercase tracking-tight group-hover:underline">Restore
                                        State</button>
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div
                                class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm hover:border-primary/40 transition-all group">
                                <div class="flex items-center justify-between mb-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-primary">
                                        <span class="material-symbols-outlined text-[20px]"
                                            data-weight="fill">verified</span>
                                    </div>
                                    <span
                                        class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded">Pre-Migration</span>
                                </div>
                                <h4 class="text-base font-bold text-slate-900">Database Schema v.88</h4>
                                <p class="text-xs text-slate-500 mt-1 mb-4">Snapshot taken before manual DB migration on
                                    Cluster A-12.</p>
                                <div class="flex items-center justify-between mt-auto pt-4 border-t border-slate-50">
                                    <div class="text-[11px] text-slate-400">Oct 18, 2023 • 08:45 AM</div>
                                    <button
                                        class="text-primary text-xs font-black uppercase tracking-tight group-hover:underline">Restore
                                        State</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <!-- Right Sidebar Content -->
                <div class="space-y-8">
                    <!-- Stored Backup Files Section -->
                    <section class="bg-white border border-slate-200 rounded-lg shadow-sm">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Stored Backups</h2>
                            <span class="material-symbols-outlined text-slate-400 text-[20px]">more_vert</span>
                        </div>
                        <div class="p-6 space-y-4">
                            <?php $quickList = array_slice($allBackups, 0, 3); ?>
                            <?php if (empty($quickList)): ?>
                                <div class="text-sm text-slate-500">No backups available yet.</div>
                            <?php else: ?>
                                <?php foreach ($quickList as $item): ?>
                                    <div class="flex items-center gap-4 p-3 rounded-lg border border-slate-100 hover:bg-slate-50 transition-colors group">
                                        <div class="w-10 h-10 rounded flex items-center justify-center bg-red-50 group-hover:bg-primary transition-colors">
                                            <span class="material-symbols-outlined text-primary group-hover:text-white transition-colors">description</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-slate-900 truncate"><?php echo h($item['name']); ?></div>
                                            <div class="text-[10px] text-slate-400"><?php echo h(formatBytes($item['size'])); ?> • <?php echo h(date('M d, Y h:i A', $item['mtime'])); ?></div>
                                        </div>
                                        <a href="superbackup.php?action=download&amp;file=<?php echo urlencode($item['name']); ?>" class="material-symbols-outlined text-slate-400 text-sm hover:text-primary">download</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-b-lg">
                            <form method="POST">
                                <button type="submit" name="create_backup"
                                    class="w-full py-2 text-xs font-bold text-slate-600 border border-slate-200 rounded hover:bg-white transition-all">Create
                                    New Backup</button>
                            </form>
                        </div>
                    </section>
                    <!-- System Information Tooltip/Card -->
                    <section class="bg-primary p-6 rounded-lg text-white shadow-lg relative overflow-hidden">
                        <div class="absolute -right-8 -bottom-8 opacity-10">
                            <span class="material-symbols-outlined text-[160px]">shield</span>
                        </div>
                        <h3 class="text-lg font-black tracking-tight mb-2">Automated Policy</h3>
                        <p class="text-xs text-red-100 leading-relaxed mb-6">
                            Daily incremental backups are scheduled for 04:00 AM UTC. Full system snapshots are
                            generated every Sunday.
                        </p>
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-red-100">Next backup in
                                14h 22m</span>
                        </div>
                        <button
                            class="mt-6 w-full py-2.5 bg-white text-primary font-bold text-xs rounded hover:bg-red-50 transition-colors">Manage
                            Schedule</button>
                    </section>
                </div>
            </div>
        </div>
    </main>
</body>

</html>