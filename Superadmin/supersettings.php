<?php
session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../log_helper.php";

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

if (isset($conn) && $conn instanceof mysqli) {
    $columnsToEnsure = [
        'role' => "VARCHAR(100) NOT NULL DEFAULT 'Superadmin' AFTER `password`",
        'access_scope' => "VARCHAR(255) NOT NULL DEFAULT 'Global Root' AFTER `role`",
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'Active' AFTER `access_scope`",
        'last_modified' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"
    ];

    $checkColumnStmt = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'superadmin' AND COLUMN_NAME = ? LIMIT 1"
    );

    if ($checkColumnStmt) {
        foreach ($columnsToEnsure as $columnName => $columnDefinition) {
            $checkColumnStmt->bind_param("s", $columnName);
            $checkColumnStmt->execute();
            $columnResult = $checkColumnStmt->get_result();

            if (!$columnResult || $columnResult->num_rows === 0) {
                $conn->query("ALTER TABLE superadmin ADD COLUMN `{$columnName}` {$columnDefinition}");
            }
        }

        $checkColumnStmt->close();
    }
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

// Handle Create Superadmin
if (isset($_POST['createSuperadmin'])) {
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $accessScope = trim($_POST['accessScope'] ?? 'Global Root');
    
    $errors = [];
    
    if ($fullName === '') {
        $errors[] = 'Full Name is required';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    if ($username === '' || strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters';
    }
    if ($password === '' || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    // Check if username already exists
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT superadmin_id FROM superadmin WHERE username = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = 'Username already exists';
            }
            $checkStmt->close();
        }
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT superadmin_id FROM superadmin WHERE email = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = 'Email already exists';
            }
            $checkStmt->close();
        }
    }
    
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $role = 'Superadmin';
        $status = 'Active';
        
        $insertStmt = $conn->prepare("
            INSERT INTO superadmin (fullName, email, username, password, role, access_scope, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($insertStmt) {
            $insertStmt->bind_param("sssssss", $fullName, $email, $username, $hashedPassword, $role, $accessScope, $status);
            if ($insertStmt->execute()) {
                $newAdminId = $insertStmt->insert_id;
                $logDetails = "Created new Superadmin: $fullName ($username), Access Scope: $accessScope";
                log_event($conn, "Create Superadmin Account", "Superadmin", (int)$newAdminId, $logDetails);
                
                $_SESSION['admin_notice'] = 'Superadmin account created successfully';
                header("Location: supersettings.php");
                exit();
            }
            $insertStmt->close();
        }
        if (empty($_SESSION['admin_notice'])) {
            $_SESSION['admin_notice'] = 'Error: Failed to create superadmin account';
        }
    } else {
        $_SESSION['admin_error'] = implode(', ', $errors);
    }
}

// Handle Update Superadmin
if (isset($_POST['updateSuperadmin'])) {
    $superadminId = (int)($_POST['superadmin_id'] ?? 0);
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $accessScope = trim($_POST['accessScope'] ?? 'Global Root');
    $status = trim($_POST['status'] ?? 'Active');
    
    $errors = [];
    
    if ($superadminId <= 0) {
        $errors[] = 'Invalid superadmin ID';
    }
    if ($fullName === '') {
        $errors[] = 'Full Name is required';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($errors)) {
        $updateStmt = $conn->prepare("
            UPDATE superadmin 
            SET fullName = ?, email = ?, access_scope = ?, status = ?
            WHERE superadmin_id = ?
        ");
        
        if ($updateStmt) {
            $updateStmt->bind_param("ssssi", $fullName, $email, $accessScope, $status, $superadminId);
            if ($updateStmt->execute()) {
                $logDetails = "Updated Superadmin: $fullName, Access Scope: $accessScope, Status: $status";
                log_event($conn, "Update Superadmin Account", "Superadmin", $superadminId, $logDetails);
                
                $_SESSION['admin_notice'] = 'Superadmin account updated successfully';
                header("Location: supersettings.php");
                exit();
            }
            $updateStmt->close();
        }
        if (empty($_SESSION['admin_notice'])) {
            $_SESSION['admin_notice'] = 'Error: Failed to update superadmin account';
        }
    } else {
        $_SESSION['admin_error'] = implode(', ', $errors);
    }
}

// Handle Delete Superadmin
if (isset($_POST['deleteSuperadmin'])) {
    $superadminId = (int)($_POST['superadmin_id'] ?? 0);
    $currentAdminId = (int)$_SESSION['superadmin_id'];
    
    if ($superadminId > 0 && $superadminId !== $currentAdminId) {
        // Get admin details before deletion
        $getStmt = $conn->prepare("SELECT fullName, username FROM superadmin WHERE superadmin_id = ? LIMIT 1");
        $adminName = 'Unknown';
        if ($getStmt) {
            $getStmt->bind_param("i", $superadminId);
            $getStmt->execute();
            $res = $getStmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $adminName = $row['fullName'];
            }
            $getStmt->close();
        }
        
        $deleteStmt = $conn->prepare("DELETE FROM superadmin WHERE superadmin_id = ? LIMIT 1");
        if ($deleteStmt) {
            $deleteStmt->bind_param("i", $superadminId);
            if ($deleteStmt->execute()) {
                $logDetails = "Deleted Superadmin account: $adminName";
                log_event($conn, "Delete Superadmin Account", "Superadmin", $superadminId, $logDetails);
                
                $_SESSION['admin_notice'] = 'Superadmin account deleted successfully';
            } else {
                $_SESSION['admin_notice'] = 'Error: Failed to delete superadmin account';
            }
            $deleteStmt->close();
        }
    } else if ($superadminId === $currentAdminId) {
        $_SESSION['admin_notice'] = 'Error: Cannot delete your own account';
    } else {
        $_SESSION['admin_notice'] = 'Error: Invalid superadmin ID';
    }
    header("Location: supersettings.php");
    exit();
}

// Fetch all superadmin accounts
$allAdmins = [];
$adminsStmt = $conn->prepare("
    SELECT superadmin_id, fullName, email, username, access_scope, status, last_modified
    FROM superadmin
    ORDER BY created_at DESC
");
if ($adminsStmt) {
    $adminsStmt->execute();
    $allAdmins = $adminsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $adminsStmt->close();
}

// Get notices from session
$adminNotice = $_SESSION['admin_notice'] ?? '';
$adminError = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_notice'], $_SESSION['admin_error']);
$showCreateModal = isset($_GET['showCreateModal']) ? true : false;
$editingAdminId = isset($_GET['editAdminId']) ? (int)$_GET['editAdminId'] : null;
$editingAdmin = null;

if ($editingAdminId) {
    foreach ($allAdmins as $admin) {
        if ($admin['superadmin_id'] === $editingAdminId) {
            $editingAdmin = $admin;
            break;
        }
    }
}
?>

<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>System Settings | RapidRepiarCo.</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet" />
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

<body class="bg-background text-on-background antialiased selection:bg-primary-container selection:text-primary">
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

            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors rounded-lg active:scale-95"
                href="superbackup.php">
                <span class="material-symbols-outlined" data-icon="backup"
                    style="font-variation-settings: 'FILL' 1;">backup</span>
                <span class="text-sm">System Backup</span>
            </a>

            <a class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold border-r-4 border-red-700 dark:border-red-500 rounded-lg active:scale-95"
                href="supersettings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="text-sm">Settings</span>
            </a>
        </nav>
        <!-- Footer Actions (Exactly as Screen 11) -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-semibold text-sm">
                    <?php echo htmlspecialchars(initials($superadminName)); ?>
                </div>
                <div class="flex flex-col min-w-0">
                    <h3 class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo htmlspecialchars($superadminName); ?></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                </div>
            </div>
            <form method="POST" class="w-full">
                <button type="submit" name="logout_superadmin"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </button>
            </form>
        </div>
    </aside>
    <!-- TopAppBar Shell (Exactly as Screen 11) -->
    <header
        class="flex items-center justify-between px-8 sticky top-0 z-30 ml-64 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md h-16 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-variant">
                    <span class="material-symbols-outlined text-lg" data-icon="search">search</span>
                </span>
                <input
                    class="pl-10 pr-4 py-1.5 bg-surface-variant border-none text-sm rounded-lg focus:ring-2 focus:ring-primary w-64 transition-all"
                    placeholder="Search parameters..." type="text" />
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined" data-icon="notifications">notifications</span>
            </button>
            <button class="p-2 text-slate-500 hover:text-primary transition-colors">
                <span class="material-symbols-outlined" data-icon="help_outline">help_outline</span>
            </button>
        </div>
    </header>
    <!-- Main Content Canvas -->
    <main class="ml-64 p-8 min-h-screen">
        <div class="w-full">
            <div class="mb-8">
                <h2 class="text-[1.875rem] font-black text-on-background tracking-tight">System Configuration</h2>
                <p class="text-slate-500 text-sm mt-1">Manage global branding, scaling limits, and core architectural
                    permissions.</p>
            </div>
            <div class="grid grid-cols-12 gap-6">
                <!-- Notice Messages -->
                <?php if ($adminNotice !== ''): ?>
                    <div class="col-span-12 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-3">
                        <span class="material-symbols-outlined text-green-700">check_circle</span>
                        <p class="text-sm font-medium text-green-700"><?= htmlspecialchars($adminNotice) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($adminError !== ''): ?>
                    <div class="col-span-12 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3">
                        <span class="material-symbols-outlined text-red-700">error</span>
                        <p class="text-sm font-medium text-red-700"><?= htmlspecialchars($adminError) ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Section 1: System Branding (Bento Style) -->
                <section class="col-span-12 lg:col-span-8 bg-white border border-slate-200 rounded-lg shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-primary-container flex items-center justify-center rounded-lg">
                            <span class="material-symbols-outlined text-primary">palette</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">System Branding</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">System
                                    Name</label>
                                <input
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                                    type="text" value="Cobalt Precision" />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Primary
                                    Branding Color</label>
                                <div class="flex items-center gap-3">
                                    <input class="h-10 w-10 p-0 border-0 rounded cursor-pointer overflow-hidden"
                                        type="color" value="#b91c1c" />
                                    <input class="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-sm font-mono"
                                        type="text" value="#B91C1C" />
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-slate-50 border border-dashed border-slate-300 rounded-lg p-6 flex flex-col items-center justify-center text-center">
                            <img class="h-12 w-12 mb-3 opacity-80"
                                data-alt="System branding logo placeholder with architectural design"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCl5adYWZB3DQQed5l4hP_RHz1zAoS_cJ5A3u1puaDfwtqve1cj_Mlzs0UfUP663oUN9Fv43SsvhjSsk9Upm1DOGNwZSlfYRskHCL3lEDoty4vXFurSJ3gYm_GMcNDgdAd7DjtzP8lpJuW3oIa12cNn-XcQ2m35EsYAFEs59zWyXTiwwvhCOEqGwIQLo4M5ypa8DVK2_DPz7nTXFxohsVMS0O-AESbCybQquP0sBXvszqDAb20y4rndguddCX-XG07HFFIzR_Xh7st_" />
                            <p class="text-sm font-bold text-slate-700">System Logo</p>
                            <p class="text-xs text-slate-400 mt-1 mb-4">SVG, PNG or JPG. Max 2MB.</p>
                            <button
                                class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-50 transition-colors shadow-sm">Replace
                                Logo</button>
                        </div>
                    </div>
                </section>
                <!-- Section 2: Tenant Limits (Compact Sidebar Card) -->
                <section class="col-span-12 lg:col-span-4 bg-white border border-slate-200 rounded-lg shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-primary-container flex items-center justify-center rounded-lg">
                            <span class="material-symbols-outlined text-primary">analytics</span>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">Tenant Limits</h3>
                    </div>
                    <div class="space-y-5">
                        <div class="flex justify-between items-end">
                            <div class="flex-1">
                                <label
                                    class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Max
                                    Tenants</label>
                                <input
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                                    type="number" value="250" />
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Storage
                                Limit (Per Tenant)</label>
                            <div class="flex items-center gap-2">
                                <input
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                                    type="number" value="50" />
                                <span class="text-xs font-bold text-slate-400">GB</span>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-slate-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold text-slate-700">Auto-approval</p>
                                    <p class="text-xs text-slate-400">Instant activation for new tenants</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input class="sr-only peer" type="checkbox" value="" />
                                    <div
                                        class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
                <!-- Section 3: User Roles & Permissions (Full Width Table) -->
                <section class="col-span-12 bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-container flex items-center justify-center rounded-lg">
                                <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900">User Roles &amp; Permissions</h3>
                        </div>
                        <button
                            onclick="openCreateModal()"
                            class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-opacity-90 active:scale-95 transition-all shadow-md flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Create New Superadmin
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Role
                                    </th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Access Scope</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Last
                                        Modified</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($allAdmins)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">No superadmin accounts found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allAdmins as $admin): ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($admin['fullName']) ?></span>
                                                    <span class="text-xs text-slate-500"><?= htmlspecialchars($admin['username']) ?> (<?= htmlspecialchars($admin['email']) ?>)</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-0.5 bg-primary-container text-primary text-[10px] font-bold rounded-full">
                                                    <?= htmlspecialchars($admin['access_scope']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2.5 py-1 <?= strtolower($admin['status']) === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?> text-xs font-bold rounded-full">
                                                    <?= htmlspecialchars($admin['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500">
                                                <?= $admin['last_modified'] ? date('M d, Y', strtotime($admin['last_modified'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-6 py-4 text-right flex gap-2 justify-end">
                                                <?php if ((int)$admin['superadmin_id'] !== (int)$_SESSION['superadmin_id']): ?>
                                                    <button 
                                                        onclick="openEditModal(<?= (int)$admin['superadmin_id'] ?>)"
                                                        class="text-slate-400 hover:text-primary transition-colors p-1">
                                                        <span class="material-symbols-outlined text-lg">edit</span>
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this superadmin account?');">
                                                        <input type="hidden" name="superadmin_id" value="<?= (int)$admin['superadmin_id'] ?>">
                                                        <button type="submit" name="deleteSuperadmin" class="text-slate-400 hover:text-red-500 transition-colors p-1">
                                                            <span class="material-symbols-outlined text-lg">delete</span>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-slate-300 text-xs font-semibold px-2 py-1 bg-slate-50 rounded">Your Account</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="col-span-12 flex justify-end gap-3 mt-4 mb-12">
                    <button
                        class="px-6 py-2.5 bg-white border border-slate-300 text-slate-600 text-sm font-bold rounded-lg hover:bg-slate-50 transition-all">Discard
                        Changes</button>
                    <button
                        class="px-8 py-2.5 bg-primary text-white text-sm font-bold rounded-lg hover:shadow-lg active:scale-95 transition-all">Save
                        Global Settings</button>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Create/Edit Superadmin Modal -->
    <div id="adminModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h2 id="modalTitle" class="text-xl font-bold text-slate-900">Create New Superadmin</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form id="adminForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" id="superadmin_id" name="superadmin_id" value="">
                <input type="hidden" id="formMode" name="formMode" value="create">
                
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Full Name</label>
                    <input 
                        type="text" 
                        id="fullName"
                        name="fullName"
                        placeholder="John Doe"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                        required />
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Email Address</label>
                    <input 
                        type="email" 
                        id="email"
                        name="email"
                        placeholder="admin@example.com"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                        required />
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Username</label>
                    <input 
                        type="text" 
                        id="username"
                        name="username"
                        placeholder="johndoe123"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                        required />
                </div>
                
                <div id="passwordDiv">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Password</label>
                    <input 
                        type="password" 
                        id="password"
                        name="password"
                        placeholder="Min. 6 characters"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none"
                        required />
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Access Scope</label>
                    <select 
                        id="accessScope"
                        name="accessScope"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="Global Root">Global Root</option>
                        <option value="Tenant Management">Tenant Management</option>
                        <option value="Financial">Financial</option>
                        <option value="Audit & Compliance">Audit & Compliance</option>
                        <option value="Support">Support</option>
                    </select>
                </div>
                
                <div id="statusDiv" class="hidden">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Status</label>
                    <select 
                        id="status"
                        name="status"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button 
                        type="button" 
                        onclick="closeModal()"
                        class="flex-1 px-4 py-2 border border-slate-300 text-slate-600 text-sm font-bold rounded-lg hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        id="submitBtn"
                        class="flex-1 px-4 py-2 bg-primary text-white text-sm font-bold rounded-lg hover:bg-opacity-90 transition-all">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Superadmin';
            document.getElementById('adminForm').reset();
            document.getElementById('superadmin_id').value = '';
            document.getElementById('formMode').value = 'create';
            document.getElementById('passwordDiv').classList.remove('hidden');
            document.getElementById('statusDiv').classList.add('hidden');
            document.getElementById('submitBtn').textContent = 'Create';
            document.getElementById('adminForm').onsubmit = function(e) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'createSuperadmin';
                input.value = '1';
                this.appendChild(input);
            };
            document.getElementById('adminModal').classList.remove('hidden');
        }
        
        function openEditModal(adminId) {
            const allAdmins = <?= json_encode($allAdmins) ?>;
            const admin = allAdmins.find(a => a.superadmin_id == adminId);
            
            if (!admin) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Superadmin Account';
            document.getElementById('superadmin_id').value = admin.superadmin_id;
            document.getElementById('fullName').value = admin.fullName;
            document.getElementById('email').value = admin.email;
            document.getElementById('username').value = admin.username;
            document.getElementById('username').disabled = true;
            document.getElementById('accessScope').value = admin.access_scope;
            document.getElementById('status').value = admin.status;
            
            document.getElementById('passwordDiv').classList.add('hidden');
            document.getElementById('statusDiv').classList.remove('hidden');
            document.getElementById('submitBtn').textContent = 'Update';
            
            document.getElementById('adminForm').onsubmit = function(e) {
                // Remove old hidden inputs
                const oldInputs = this.querySelectorAll('input[name="createSuperadmin"], input[name="updateSuperadmin"]');
                oldInputs.forEach(inp => inp.remove());
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'updateSuperadmin';
                input.value = '1';
                this.appendChild(input);
            };
            
            document.getElementById('adminModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('adminModal').classList.add('hidden');
            document.getElementById('adminForm').reset();
            document.getElementById('username').disabled = false;
        }
        
        // Close modal when clicking outside
        document.getElementById('adminModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>

</html>