<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/../log_helper.php';

// Safe fallback if access helper variables/functions are not loaded on this page
$accessibleModules = $accessibleModules ?? [];

if (!function_exists('canAccessModule')) {
    function canAccessModule($moduleName, $accessibleModules = [])
    {
        return true;
    }
}

// Check if tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (string) $_SESSION['tenantID'];

// Get logged-in user information
$loggedInUserName = '';
$loggedInUserRole = '';
if (($_SESSION['userType'] ?? 'owner') === 'owner') {
    $loggedInUserName = isset($_SESSION['shopName']) ? $_SESSION['shopName'] : 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = (isset($_SESSION['firstName']) ? $_SESSION['firstName'] : '') . ' ' . (isset($_SESSION['lastName']) ? $_SESSION['lastName'] : '');
    $loggedInUserName = trim($loggedInUserName) ?: 'User';
    $loggedInUserRole = isset($_SESSION['userRole']) ? $_SESSION['userRole'] : 'Staff Member';
}

// Get shop name from database
$shopName = 'RapidRepair';
$ownerStmt = mysqli_prepare($conn, 'SELECT shopName FROM owners WHERE tenantID = ? LIMIT 1');
if ($ownerStmt) {
    mysqli_stmt_bind_param($ownerStmt, 's', $tenantID);
    mysqli_stmt_execute($ownerStmt);
    $ownerResult = mysqli_stmt_get_result($ownerStmt);
    if ($ownerResult && $row = mysqli_fetch_assoc($ownerResult)) {
        $shopName = $row['shopName'] ?: 'RapidRepair';
    }
    mysqli_stmt_close($ownerStmt);
}
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
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
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
    <aside
        class="w-64 flex-shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 h-screen sticky top-0 flex flex-col overflow-y-auto">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Website Customizer</p>
                </div>
            </div>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="dashboardadmin.php">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="repairjobsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="vehicleadmin.php">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="appointmentadmin.php">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="reportsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="inventoryadmin.php">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="customeradmin.php">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    href="paymentsadmin.php">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                
                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-sm"
                                href="accountbillingadmin.php">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                Account Billing
                            </a>
                            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-sm border-t border-slate-100 dark:border-slate-700"
                                href="websitecustomeadmin.php">
                                <span class="material-symbols-outlined text-[18px]">palette</span>
                                Website Customizer
                            </a>
                            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-sm border-t border-slate-100 dark:border-slate-700"
                                href="settingsadmin.php">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Settings
                            </a>
                            <?php if (canAccessModule('storage_managementadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-sm border-t border-slate-100 dark:border-slate-700"
                                href="storage_managementadmin.php">
                                <span class="material-symbols-outlined text-[18px]">storage</span>
                                Storage Management
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate text-slate-900 dark:text-white"><?php echo htmlspecialchars($loggedInUserName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo htmlspecialchars($loggedInUserRole, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <form method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Website Customizer</h2>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button id="saveButton"
                    class="bg-primary text-white px-5 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-blue-800 transition-all">Save
                    Changes</button>
            </div>
        </header>
        <!-- Customizer Workspace -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Left Panel: Configuration -->
            <section class="w-[420px] bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 overflow-y-auto">
                <div class="p-8 space-y-10">
                    <!-- Section: Brand Identity -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="fingerprint">fingerprint</span>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Brand Identity</h3>
                        </div>
                        <div class="space-y-4">
                            <label class="block">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">Shop Logo</span>
                                <div
                                    id="logoUploadZone" class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-6 flex flex-col items-center justify-center bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                                    <img id="logoUploadPreview" alt="Logo preview" class="h-12 w-auto mb-3"
                                        data-alt="minimalist modern automotive shop logo with stylized A icon in cobalt blue and white"
                                        src="https://placehold.co/160x80/e2e8f0/64748b?text=Upload+Logo" />
                                    <span class="text-xs text-slate-500 font-medium">Click to upload or drag &amp;
                                        drop</span>
                                    <span class="text-[10px] text-slate-400 mt-1">SVG, PNG or JPG (Max 2MB)</span>
                                </div>
                            </label>
                            <div class="space-y-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block">Primary Brand Color</span>
                                <div class="flex items-center gap-3">
                                    <div
                                        id="primaryColorSwatch" class="h-10 w-10 rounded-lg bg-primary ring-2 ring-offset-2 ring-primary cursor-pointer shadow-sm">
                                    </div>
                                    <input
                                        class="flex-1 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg text-sm font-mono focus:ring-primary focus:border-primary dark:focus:ring-primary"
                                        id="primaryColorInput" type="text" value="#1152d4" />
                                </div>
                                <div class="grid grid-cols-7 gap-2 pt-2">
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#1152d4] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#1152d4" title="Royal Blue"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#2563eb] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#2563eb" title="Blue"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#7c3aed] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#7c3aed" title="Purple"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#ec4899] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#ec4899" title="Pink"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#ef4444] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#ef4444" title="Red"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#f97316] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#f97316" title="Orange"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#eab308] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#eab308" title="Yellow"></button>

                                    <button type="button" class="h-7 w-7 rounded-full bg-[#22c55e] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#22c55e" title="Green"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#14b8a6] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#14b8a6" title="Teal"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#06b6d4] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#06b6d4" title="Cyan"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#0f172a] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#0f172a" title="Dark Navy"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#000000] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#000000" title="Black"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-[#64748b] hover:scale-110 transition-transform border-2 border-white shadow" data-color="#64748b" title="Slate"></button>
                                    <button type="button" class="h-7 w-7 rounded-full bg-gradient-to-r from-pink-500 via-red-500 to-yellow-500 hover:scale-110 transition-transform border-2 border-white shadow" data-color="#ec4899" title="Gradient Pink"></button>
                                </div>

                                <div class="mt-4">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">
                                        Custom Color Picker
                                    </label>
                                    <div class="flex items-center gap-3">
                                        <input type="color"
                                            id="advancedColorPicker"
                                            value="#1152d4"
                                            class="h-12 w-20 rounded-lg border border-slate-200 cursor-pointer bg-transparent">

                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            Choose any custom color for your website branding.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Section: Hero Section -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-primary" data-icon="image">image</span>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Hero Section</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Main Heading</label>
                                <textarea
                                    class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg text-sm focus:ring-primary focus:border-primary font-bold"
                                    id="heroHeadingInput" rows="2">Precision Engineering. Absolute Reliability.</textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Subtext</label>
                                <textarea
                                    class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg text-sm focus:ring-primary focus:border-primary"
                                    id="heroSubtextInput" rows="3">Expert automotive repair and maintenance services for performance vehicles and daily drivers alike.</textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block">Hero Background</label>
                                <div
                                    id="heroUploadZone" class="relative group rounded-xl overflow-hidden aspect-video border border-slate-200 cursor-pointer">
                                    <img id="heroBackgroundUploadPreview" alt="Hero background" class="w-full h-full object-cover"
                                        data-alt="dark atmospheric car garage with luxury vehicle on hydraulic lift and dramatic industrial lighting"
                                        src="https://placehold.co/800x450/e2e8f0/64748b?text=Upload+Hero+Background" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button type="button"
                                            class="bg-white/90 text-slate-900 px-3 py-1.5 rounded text-[10px] font-bold">Replace
                                            Image</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- Section: Pictures Carousel -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="collections">collections</span>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Pictures Carousel</h3>
                            </h3>
                        </div>
                        <div class="space-y-3">
                            <div id="carouselGrid" class="grid grid-cols-3 gap-2">
                                <!-- Thumbnail 1 -->
                                <div
                                    class="relative aspect-square rounded-lg overflow-hidden border border-slate-200 group">
                                    <img alt="Carousel thumbnail" class="w-full h-full object-cover"
                                        src="https://placehold.co/300x300/f1f5f9/64748b?text=Carousel+Image" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button class="material-symbols-outlined text-white text-sm hover:text-red-400"
                                            data-icon="delete">delete</button>
                                    </div>
                                </div>
                                <!-- Thumbnail 2 -->
                                <div
                                    class="relative aspect-square rounded-lg overflow-hidden border border-slate-200 group">
                                    <img alt="Carousel thumbnail" class="w-full h-full object-cover"
                                        src="https://placehold.co/300x300/f1f5f9/64748b?text=Carousel+Image" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button class="material-symbols-outlined text-white text-sm hover:text-red-400"
                                            data-icon="delete">delete</button>
                                    </div>
                                </div>
                                <!-- Thumbnail 3 -->
                                <div
                                    class="relative aspect-square rounded-lg overflow-hidden border border-slate-200 group">
                                    <img alt="Carousel thumbnail" class="w-full h-full object-cover"
                                        src="https://placehold.co/300x300/f1f5f9/64748b?text=Carousel+Image" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button class="material-symbols-outlined text-white text-sm hover:text-red-400"
                                            data-icon="delete">delete</button>
                                    </div>
                                </div>
                            </div>
                            <button id="addCarouselImageBtn" type="button"
                                class="w-full py-2 border-2 border-dashed border-slate-200 rounded-lg text-xs font-bold text-slate-500 hover:border-primary hover:text-primary transition-all flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm"
                                    data-icon="add_photo_alternate">add_photo_alternate</span>
                                Add Image
                            </button>
                        </div>
                    </div>
                    <!-- Section: Services -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-primary" data-icon="build">build</span>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Services</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="p-3 border border-slate-200 rounded-lg bg-slate-50 flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary mt-1"
                                    data-icon="troubleshoot">troubleshoot</span>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <h4 class="text-xs font-bold text-slate-900">Diagnostics</h4>
                                        <button class="material-symbols-outlined text-slate-400 text-sm"
                                            data-icon="edit">edit</button>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">State-of-the-art computer
                                        analysis to identify vehicle issues rapidly.</p>
                                </div>
                            </div>
                            <div class="p-3 border border-slate-200 rounded-lg bg-slate-50 flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary mt-1"
                                    data-icon="car_repair">car_repair</span>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <h4 class="text-xs font-bold text-slate-900">Brake Repair</h4>
                                        <button class="material-symbols-outlined text-slate-400 text-sm"
                                            data-icon="edit">edit</button>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">Full system inspection,
                                        pad replacement, and rotor resurfacing.</p>
                                </div>
                            </div>
                            <button
                                class="w-full py-2 border-2 border-dashed border-slate-200 rounded-lg text-xs font-bold text-slate-500 hover:border-primary hover:text-primary transition-all flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm" data-icon="add_circle">add_circle</span>
                                Add Service
                            </button>
                        </div>
                    </div>
                    <!-- Section: Call to Action -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-primary" data-icon="ads_click">ads_click</span>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Call to Action</h3>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Primary Button Text</label>
                            <input
                                class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg text-sm focus:ring-primary focus:border-primary"
                                id="ctaButtonTextInput" type="text" value="Book Appointment" />
                        </div>
                    </div>
                </div>
            </section>
            <!-- Right Panel: Live Preview Area -->
            <section class="flex-1 bg-slate-50 dark:bg-slate-800/50 p-12 overflow-hidden flex flex-col items-center">
                <!-- Preview Canvas -->
                <div
                    class="w-full max-w-[1024px] h-[720px] bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden relative">
                    <!-- Browser Chrome -->
                    <div class="h-10 bg-slate-100 border-b border-slate-200 flex items-center px-4 gap-2">
                        <div class="flex gap-1.5">
                            <div class="h-3 w-3 rounded-full bg-red-400"></div>
                            <div class="h-3 w-3 rounded-full bg-amber-400"></div>
                            <div class="h-3 w-3 rounded-full bg-emerald-400"></div>
                        </div>
                        <div
                            class="mx-auto bg-white rounded-full px-8 py-1 border border-slate-200 text-[10px] text-slate-500 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[12px]" data-icon="lock">lock</span>
                            apex-autocare.cobalt.com
                        </div>
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-slate-400 text-sm"
                                data-icon="desktop_windows">desktop_windows</span>
                            <span class="material-symbols-outlined text-slate-400 text-sm"
                                data-icon="smartphone">smartphone</span>
                        </div>
                    </div>
                    <!-- Scaled Content -->
                    <div id="websitePreview" class="w-full h-full overflow-y-auto bg-surface text-on-surface" style="--preview-primary:#1152d4; font-family: Inter, sans-serif;">
                        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-sm">
                            <nav class="flex justify-between items-center h-16 px-6 md:px-10 max-w-7xl mx-auto">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img id="previewLogoImg" alt="Shop logo" class="hidden h-10 w-10 rounded-lg object-contain bg-white border border-slate-200" src="" />
                                    <div id="previewLogoFallback" class="h-10 w-10 rounded-lg flex items-center justify-center text-white font-black text-lg" style="background:var(--preview-primary);">
                                        <?php echo strtoupper(substr($shopName, 0, 1)); ?>
                                    </div>
                                    <div id="previewShopName" class="text-xl font-black tracking-tighter text-[#0F4B3C] truncate max-w-[260px]"><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="hidden md:flex items-center space-x-8 tracking-tight text-sm font-medium">
                                    <a class="pb-1 border-b-2" style="color:#0F4B3C;border-color:#0F4B3C;" href="#">Home</a>
                                    <a class="text-slate-600" href="#">Services</a>
                                    <a class="text-slate-600" href="#">Mobile App</a>
                                    <a class="text-slate-600" href="#">About</a>
                                </div>
                                <button id="previewHeaderCta" class="text-white px-5 py-2 rounded-lg font-semibold text-sm" style="background:var(--preview-primary);">Book Appointment</button>
                            </nav>
                        </header>

                        <main>
                            <section class="relative w-full overflow-hidden bg-[#1A2A2A] py-20 md:py-28">
                                <div class="absolute inset-0 opacity-40">
                                    <img id="previewHeroImage" alt="Modern Auto Repair Shop" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAEtRZx2VtJU_zvHyWwsPzD6V-hQgNfAn2ej099PlXa6HKYmZqm9u0Cl5K4y-AzSzT4KPlh897GoHs2N4t_PifJp3y-dT-rj5YsB98I9Dnp799aPfP0rZ-vQZhqRNpq_Ll2qyR361GWZxFHoYgrFfUTBzh8STIl_1B0aQTSEGfgyxNhO7ix91KeXhv26XzL0sHPtMcsrGNRwCP_RGCYJ8Ny0heOO9T8o7EUb9hcDp1dSNVs5Fja1CgIgUO3RtwhBFeHSdHhfk06o3Lo" />
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-r from-[#1A2A2A] via-[#1A2A2A]/80 to-transparent"></div>
                                <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-12">
                                    <div class="max-w-2xl">
                                        <h1 id="previewHeroHeading" class="text-white text-5xl md:text-6xl font-black tracking-tight mb-6">Precision Engineering. Absolute Reliability.</h1>
                                        <p id="previewHeroSubtext" class="text-slate-300 text-lg mb-8 font-medium leading-relaxed">Expert automotive repair and maintenance services for performance vehicles and daily drivers alike.</p>
                                        <div class="flex flex-col sm:flex-row gap-4">
                                            <button id="previewHeroCta" class="inline-flex items-center justify-center text-white px-8 py-4 rounded-lg font-bold text-base shadow-lg transition-all" style="background:var(--preview-primary);">Book Appointment</button>
                                            <button class="inline-flex items-center justify-center border border-white/20 bg-white/5 backdrop-blur-md text-white px-8 py-4 rounded-lg font-bold text-base">View Services</button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="py-20 bg-white">
                                <div class="max-w-7xl mx-auto px-6 md:px-12">
                                    <div class="text-center mb-14">
                                        <span class="font-bold tracking-widest text-xs uppercase" style="color:var(--preview-primary);">The <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?> Advantage</span>
                                        <h2 class="text-4xl font-black tracking-tight mt-2">Engineered Excellence</h2>
                                    </div>
                                    <div id="previewAdvantageGrid" class="grid grid-cols-1 md:grid-cols-3 gap-10">
                                        <div class="group"><div class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6"><span class="material-symbols-outlined text-3xl">troubleshoot</span></div><h3 class="text-xl font-bold mb-3">Expert Diagnostics</h3><p class="text-slate-600 text-sm leading-relaxed">Advanced diagnostics to detect issues before they become costly repairs.</p></div>
                                        <div class="group"><div class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6"><span class="material-symbols-outlined text-3xl">precision_manufacturing</span></div><h3 class="text-xl font-bold mb-3">Precision Tuning</h3><p class="text-slate-600 text-sm leading-relaxed">Performance-focused service built for daily drivers and business fleets.</p></div>
                                        <div class="group"><div class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6"><span class="material-symbols-outlined text-3xl">verified</span></div><h3 class="text-xl font-bold mb-3">Genuine Parts</h3><p class="text-slate-600 text-sm leading-relaxed">Quality components and transparent work from start to finish.</p></div>
                                    </div>
                                </div>
                            </section>

                            <section class="py-20 max-w-7xl mx-auto px-6 md:px-12">
                                <div class="mb-14">
                                    <span class="font-bold tracking-widest text-xs uppercase" style="color:var(--preview-primary);">Core Capabilities</span>
                                    <h2 class="text-4xl font-black tracking-tight mt-2">Expert Maintenance</h2>
                                </div>
                                <div id="previewServicesGrid" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                    <div class="md:col-span-8 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col md:flex-row">
                                        <div class="md:w-1/2 p-10 flex flex-col justify-center">
                                            <div class="w-12 h-12 rounded-lg flex items-center justify-center mb-6" style="background:rgba(17,82,212,.12);color:var(--preview-primary);"><span class="material-symbols-outlined">computer</span></div>
                                            <h3 class="text-2xl font-bold mb-4">Engine Diagnostics</h3>
                                            <p class="text-slate-600 text-sm leading-relaxed mb-6">Using scanning technology to identify engine, ECU, and sensor problems fast.</p>
                                            <ul class="space-y-2 mb-8 text-sm font-medium"><li class="flex items-center gap-2"><span class="material-symbols-outlined text-sm" style="color:var(--preview-primary);">check_circle</span> Fault Code Analysis</li><li class="flex items-center gap-2"><span class="material-symbols-outlined text-sm" style="color:var(--preview-primary);">check_circle</span> Sensor Calibration</li></ul>
                                        </div>
                                        <div class="md:w-1/2 h-64 md:h-auto"><img class="w-full h-full object-cover" alt="Engine Diagnostics" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAeKwRX9gbVEj_ZDeKQztbtFR3l2RkUj4tNbp_L-TFGvm_1jZRDe4LRtNoz63vt3g288vvZCM_Tg1tfutLMBK9h5Ojugz9xfAK3phYFqL7orQYkLgJ7BNYUiZXRmr9yhQZWdtcu-H43u1PiDPSiYjV_X8l32DK-Ng8x_9u4W86W7VeI9Xyc0VSEb0QYIXm2S6VSbd-TacddkVNW8kRnGzKMsZ5WzvgQbpNT945kqTAtanzRhHEk4ink6T4g7Gyl7w2l5iUpLdBrUjyK" /></div>
                                    </div>
                                    <div class="md:col-span-4 bg-white border border-slate-200 rounded-xl p-10 shadow-sm">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center mb-6" style="background:rgba(17,82,212,.12);color:var(--preview-primary);"><span class="material-symbols-outlined">settings_backup_restore</span></div>
                                        <h3 class="text-2xl font-bold mb-4">Brake Systems</h3>
                                        <p class="text-slate-600 text-sm leading-relaxed">Brake inspection, pad replacement, and rotor servicing for safe stopping power.</p>
                                    </div>
                                    <div class="md:col-span-4 bg-white border border-slate-200 rounded-xl p-10 shadow-sm">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center mb-6" style="background:rgba(17,82,212,.12);color:var(--preview-primary);"><span class="material-symbols-outlined">oil_barrel</span></div>
                                        <h3 class="text-2xl font-bold mb-4">Precision Lube</h3>
                                        <p class="text-slate-600 text-sm leading-relaxed">Synthetic fluids and premium filtration to extend vehicle life.</p>
                                    </div>
                                    <div class="md:col-span-8 text-white rounded-xl p-10 shadow-lg flex flex-col md:flex-row items-center gap-8" style="background:var(--preview-primary);">
                                        <div class="md:w-2/3"><h3 class="text-3xl font-black mb-4">Commercial Fleet Solutions</h3><p class="text-white/80 text-sm leading-relaxed mb-6">Priority scheduling and maintenance plans that keep business vehicles moving.</p><button class="bg-white px-6 py-2 rounded-lg font-bold text-xs uppercase tracking-widest" style="color:var(--preview-primary);">Inquire Now</button></div>
                                        <div class="md:w-1/3 flex justify-center"><span class="material-symbols-outlined text-[120px] text-white/20">local_shipping</span></div>
                                    </div>
                                </div>
                            </section>

                            <section class="py-20 bg-white">
                                <div class="max-w-7xl mx-auto px-6 md:px-12 grid md:grid-cols-2 gap-14 items-center">
                                    <div class="aspect-square rounded-xl overflow-hidden border-8 border-slate-100 shadow-xl"><img id="previewCarouselMain" alt="Workshop Interior" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCNivH_gwqpoPun0DlJsk-9blL2X9GmrPZadgtfgSk_cfbrHcq_2Jj_Kv_acazMye-HCCWv3eZvFJsHjr-YrA_PdQ2Dc85Dk6eNetLGhRSxIlWTGUnZdLAs5em2s8xJ6Xxvy0C3isvjOWRs5KrnrdAJb6MuRGEgio9BCz-zTo3KZ4fhPwzI1i3ItWpxuos6F8vcgwsySr6X68R7sHCMNE6BJhr6e3-3uZv38v00EUgCyTafeawupM7Aoy0SfVrV6rKZjcAueOVkLia1" /></div>
                                    <div><span class="font-bold tracking-widest text-xs uppercase" style="color:var(--preview-primary);">Legacy &amp; Vision</span><h2 class="text-4xl font-black tracking-tight mt-2 mb-8">About <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></h2><p class="text-slate-600 leading-relaxed">Founded on transparency and technical mastery, <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?> delivers professional automotive care with clear service tracking and reliable workmanship.</p></div>
                                </div>
                            </section>

                            <section id="previewImageCarouselSection" class="py-20 bg-slate-50">
                                <div class="max-w-7xl mx-auto px-6 md:px-12">
                                    <div class="text-center mb-12">
                                        <span class="font-bold tracking-widest text-xs uppercase" style="color:var(--preview-primary);">Shop Gallery</span>
                                        <h2 class="text-4xl font-black tracking-tight mt-2">Pictures Carousel</h2>
                                        <p class="text-slate-600 mt-4">Uploaded carousel images appear here in real time.</p>
                                    </div>
                                    <div id="previewCarouselGrid" class="grid grid-cols-1 md:grid-cols-3 gap-6"></div>
                                </div>
                            </section>

                            <section class="py-20 bg-white overflow-hidden">
                                <div class="max-w-7xl mx-auto px-6 md:px-12">
                                    <div class="text-center max-w-3xl mx-auto mb-14">
                                        <span class="font-bold tracking-widest text-xs uppercase" style="color:var(--preview-primary);">Owner Satisfaction</span>
                                        <h2 class="text-4xl font-black tracking-tight mt-2">The <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?> Standard</h2>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                        <div class="bg-slate-50 p-8 border border-slate-200 rounded-xl shadow-sm"><div class="flex gap-1 text-amber-500 mb-6"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span></div><p class="text-slate-700 text-sm italic leading-relaxed mb-8">Professional diagnostics, clear reports, and dependable repair service.</p><div class="text-sm font-bold">Marcus Chen</div><div class="text-xs text-slate-500">Vehicle Owner</div></div>
                                        <div class="bg-slate-50 p-8 border border-slate-200 rounded-xl shadow-sm"><div class="flex gap-1 text-amber-500 mb-6"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span></div><p class="text-slate-700 text-sm italic leading-relaxed mb-8">Clean, organized, and transparent from booking to completion.</p><div class="text-sm font-bold">Sarah Jenkins</div><div class="text-xs text-slate-500">Fleet Manager</div></div>
                                        <div class="bg-slate-50 p-8 border border-slate-200 rounded-xl shadow-sm"><div class="flex gap-1 text-amber-500 mb-6"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">star</span></div><p class="text-slate-700 text-sm italic leading-relaxed mb-8">A reliable shop with attention to detail and excellent follow-through.</p><div class="text-sm font-bold">David Rossi</div><div class="text-xs text-slate-500">Classic Car Collector</div></div>
                                    </div>
                                </div>
                            </section>

                            <section class="py-20 bg-slate-900 overflow-hidden text-white relative">
                                <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row items-center gap-14">
                                    <div class="md:w-1/2">
                                        <span class="font-bold tracking-widest text-xs uppercase mb-4 block" style="color:var(--preview-primary);">Connected Service</span>
                                        <h2 class="text-4xl md:text-5xl font-black tracking-tight mb-6">Manage Your Vehicle from Your Pocket</h2>
                                        <p class="text-slate-400 text-lg mb-10 leading-relaxed">Download the <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?> app to track service history, appointments, and repair updates.</p>
                                        <button class="text-white border rounded-xl px-6 py-3 flex items-center gap-3 font-bold" style="background:var(--preview-primary);border-color:var(--preview-primary);"><span class="material-symbols-outlined">folder</span> Download Here</button>
                                    </div>
                                    <div class="md:w-1/2 flex justify-center"><div class="w-56 h-[420px] bg-slate-800 rounded-[2.5rem] border-8 border-slate-700 p-5 shadow-2xl"><div class="h-full bg-white rounded-[2rem] p-5 text-slate-900"><div class="text-sm font-black mb-5"><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?> App</div><div class="space-y-3"><div class="h-16 rounded-xl bg-slate-100"></div><div class="h-16 rounded-xl bg-slate-100"></div><div class="h-24 rounded-xl" style="background:var(--preview-primary);"></div><div class="h-16 rounded-xl bg-slate-100"></div></div></div></div></div>
                                </div>
                            </section>
                        </main>
                    </div>
                    <!-- Overlay Label -->
                    <div
                        class="absolute bottom-6 right-6 bg-slate-900 text-white px-4 py-2 rounded-full flex items-center gap-2 text-xs font-bold shadow-xl animate-pulse">
                        <span class="h-2 w-2 bg-emerald-400 rounded-full"></span>
                        Live Preview Mode
                    </div>
                </div>
                <!-- Preview Info -->
                <div class="mt-8 flex gap-12 text-slate-500 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg" data-icon="speed">speed</span>
                        98 Performance Score
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg" data-icon="check_circle">check_circle</span>
                        SEO Optimized
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg"
                            data-icon="mobile_friendly">mobile_friendly</span>
                        Mobile Responsive
                    </div>
                </div>
            </section>
        </div>
    </main>
    <script>
        // Customization System
        const customizationSystem = {
            tenantID: <?php echo json_encode($tenantID); ?>,
            shopName: <?php echo json_encode($shopName); ?>,
            defaultHeroImage: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAEtRZx2VtJU_zvHyWwsPzD6V-hQgNfAn2ej099PlXa6HKYmZqm9u0Cl5K4y-AzSzT4KPlh897GoHs2N4t_PifJp3y-dT-rj5YsB98I9Dnp799aPfP0rZ-vQZhqRNpq_Ll2qyR361GWZxFHoYgrFfUTBzh8STIl_1B0aQTSEGfgyxNhO7ix91KeXhv26XzL0sHPtMcsrGNRwCP_RGCYJ8Ny0heOO9T8o7EUb9hcDp1dSNVs5Fja1CgIgUO3RtwhBFeHSdHhfk06o3Lo',
            placeholderThumb: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="#f1f5f9"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#64748b" font-family="Arial" font-size="24">Image preview unavailable</text></svg>'),
            carouselImages: [],
            services: [],

            init() {
                this.cacheElements();
                this.attachEventListeners();
                this.loadCustomization();
                this.updatePreview();
            },

            cacheElements() {
                this.el = {
                    saveButton: document.getElementById('saveButton'),
                    primaryColorInput: document.getElementById('primaryColorInput'),
                    primaryColorSwatch: document.getElementById('primaryColorSwatch'),
                    heroHeadingInput: document.getElementById('heroHeadingInput'),
                    heroSubtextInput: document.getElementById('heroSubtextInput'),
                    ctaButtonTextInput: document.getElementById('ctaButtonTextInput'),
                    logoZone: document.getElementById('logoUploadZone'),
                    logoUploadPreview: document.getElementById('logoUploadPreview'),
                    heroZone: document.getElementById('heroUploadZone'),
                    heroBackgroundUploadPreview: document.getElementById('heroBackgroundUploadPreview'),
                    carouselGrid: document.getElementById('carouselGrid'),
                    addCarouselImageBtn: document.getElementById('addCarouselImageBtn'),
                    websitePreview: document.getElementById('websitePreview'),
                    previewLogoImg: document.getElementById('previewLogoImg'),
                    previewLogoFallback: document.getElementById('previewLogoFallback'),
                    previewShopName: document.getElementById('previewShopName'),
                    previewHeaderCta: document.getElementById('previewHeaderCta'),
                    previewHeroImage: document.getElementById('previewHeroImage'),
                    previewCarouselGrid: document.getElementById('previewCarouselGrid'),
                    previewCarouselMain: document.getElementById('previewCarouselMain'),
                    previewHeroHeading: document.getElementById('previewHeroHeading'),
                    previewHeroSubtext: document.getElementById('previewHeroSubtext'),
                    previewHeroCta: document.getElementById('previewHeroCta')
                };
            },

            async loadCustomization() {
                try {
                    const response = await fetch('customization_handler.php?action=get_customization', { credentials: 'same-origin' });
                    const result = await response.json();

                    if (result.status === 'success' && result.data) {
                        this.populateFormData(result.data);
                        this.updatePreview();
                    }
                } catch (error) {
                    console.log('No existing customization found', error);
                }
            },

            populateFormData(data) {
                if (this.el.primaryColorInput && data.primaryColor) this.el.primaryColorInput.value = data.primaryColor;
                if (this.el.heroHeadingInput && data.heroHeading) this.el.heroHeadingInput.value = data.heroHeading;
                if (this.el.heroSubtextInput && data.heroSubtext) this.el.heroSubtextInput.value = data.heroSubtext;
                if (this.el.ctaButtonTextInput && data.ctaButtonText) this.el.ctaButtonTextInput.value = data.ctaButtonText;

                if (data.logoPath) {
                    this.el.logoUploadPreview.src = this.toDisplayPath(data.logoPath);
                    this.el.logoUploadPreview.dataset.path = data.logoPath;
                }

                if (data.heroBackground) {
                    this.el.heroBackgroundUploadPreview.src = this.toDisplayPath(data.heroBackground);
                    this.el.heroBackgroundUploadPreview.dataset.path = data.heroBackground;
                }

                this.carouselImages = Array.isArray(data.carouselImages) ? data.carouselImages : [];
                this.services = Array.isArray(data.services) ? data.services : [];
                if (this.carouselImages.length) this.renderCarouselThumbnails();
            },

            attachEventListeners() {
                if (this.el.saveButton) this.el.saveButton.addEventListener('click', () => this.saveCustomization());

                [this.el.primaryColorInput, this.el.heroHeadingInput, this.el.heroSubtextInput, this.el.ctaButtonTextInput].forEach(field => {
                    if (!field) return;
                    field.addEventListener('input', () => this.updatePreview());
                    field.addEventListener('change', () => this.updatePreview());
                });

                document.querySelectorAll('[data-color]').forEach(circle => {
                    circle.addEventListener('click', () => {
                        this.el.primaryColorInput.value = circle.dataset.color;

                        const advancedPicker = document.getElementById('advancedColorPicker');
                        if (advancedPicker) {
                            advancedPicker.value = circle.dataset.color;
                        }

                        this.updatePreview();
                    });
                });

                const advancedPicker = document.getElementById('advancedColorPicker');

                if (advancedPicker) {
                    advancedPicker.addEventListener('input', (e) => {
                        this.el.primaryColorInput.value = e.target.value;
                        this.updatePreview();
                    });
                }

                this.attachDropZone(this.el.logoZone, 'logo');
                this.attachDropZone(this.el.heroZone, 'hero');

                if (this.el.addCarouselImageBtn) {
                    this.el.addCarouselImageBtn.addEventListener('click', () => this.triggerImageUpload('carousel'));
                }

                if (this.el.carouselGrid) {
                    this.el.carouselGrid.addEventListener('click', (e) => {
                        const deleteBtn = e.target.closest('[data-delete-carousel-index]');
                        if (!deleteBtn) return;
                        this.carouselImages.splice(Number(deleteBtn.dataset.deleteCarouselIndex), 1);
                        this.renderCarouselThumbnails();
                        this.updatePreview();
                    });
                }
            },

            attachDropZone(zone, type) {
                if (!zone) return;
                zone.addEventListener('click', (e) => {
                    if (e.target.closest('button')) e.preventDefault();
                    this.triggerImageUpload(type);
                });
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.classList.add('bg-blue-50', 'dark:bg-slate-700');
                });
                zone.addEventListener('dragleave', () => zone.classList.remove('bg-blue-50', 'dark:bg-slate-700'));
                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.classList.remove('bg-blue-50', 'dark:bg-slate-700');
                    if (e.dataTransfer.files.length > 0) this.uploadImage(e.dataTransfer.files[0], type);
                });
            },

            triggerImageUpload(type) {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';
                input.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) this.uploadImage(e.target.files[0], type);
                });
                input.click();
            },

            async uploadImage(file, type) {
                const maxSize = 2 * 1024 * 1024;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

                if (!allowedTypes.includes(file.type)) {
                    this.showNotification('Only JPG, PNG, WEBP, or SVG images are allowed.', 'error');
                    return;
                }

                if (file.size > maxSize) {
                    this.showNotification('Image must not exceed 2MB.', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('image', file);
                formData.append('type', type);

                try {
                    const response = await fetch('customization_handler.php?action=upload_image', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    const result = await response.json();
                    if (result.status !== 'success') {
                        this.showNotification('Error uploading image: ' + result.message, 'error');
                        return;
                    }

                    const uploadedPath = result.data?.path || result.path || result.data?.url || '';
                    const displayPath = result.data?.url || uploadedPath;
                    this.handleImageUploadSuccess(type, uploadedPath, displayPath);
                } catch (error) {
                    this.showNotification('Error uploading image: ' + error.message, 'error');
                }
            },

            handleImageUploadSuccess(type, savedPath, displayPath) {
                if (type === 'logo') {
                    this.el.logoUploadPreview.src = this.toDisplayPath(displayPath);
                    this.el.logoUploadPreview.dataset.path = savedPath;
                    this.showNotification('Logo uploaded successfully', 'success');
                } else if (type === 'hero') {
                    this.el.heroBackgroundUploadPreview.src = this.toDisplayPath(displayPath);
                    this.el.heroBackgroundUploadPreview.dataset.path = savedPath;
                    this.showNotification('Hero background uploaded successfully', 'success');
                } else if (type === 'carousel') {
                    this.carouselImages.push(savedPath);
                    this.renderCarouselThumbnails();
                    this.showNotification('Carousel image added successfully', 'success');
                }
                this.updatePreview();
            },

            renderCarouselThumbnails() {
                if (!this.el.carouselGrid) return;
                const defaultThumbs = Array.from(this.el.carouselGrid.querySelectorAll('img')).map(img => img.src);
                const images = this.carouselImages.length ? this.carouselImages : defaultThumbs.slice(0, 3);

                this.el.carouselGrid.innerHTML = images.map((src, index) => `
                    <div class="relative aspect-square rounded-lg overflow-hidden border border-slate-200 group">
                        <img alt="Carousel thumbnail" class="w-full h-full object-cover" src="${this.escapeAttr(this.toDisplayPath(src))}" onerror="this.onerror=null;this.src='${this.placeholderThumb}'" />
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <button type="button" class="material-symbols-outlined text-white text-sm hover:text-red-400" data-delete-carousel-index="${index}">delete</button>
                        </div>
                    </div>
                `).join('');
            },

            collectFormData() {
                return {
                    primaryColor: this.normalizeHex(this.el.primaryColorInput?.value || '#1152d4'),
                    logoPath: this.el.logoUploadPreview?.dataset.path || '',
                    heroHeading: this.el.heroHeadingInput?.value.trim() || 'Precision Engineering. Absolute Reliability.',
                    heroSubtext: this.el.heroSubtextInput?.value.trim() || 'Expert automotive repair and maintenance services for performance vehicles and daily drivers alike.',
                    heroBackground: this.el.heroBackgroundUploadPreview?.dataset.path || '',
                    ctaButtonText: this.el.ctaButtonTextInput?.value.trim() || 'Book Appointment',
                    services: this.services,
                    carouselImages: this.carouselImages
                };
            },

            updatePreview() {
                const data = this.collectFormData();
                const color = this.normalizeHex(data.primaryColor);

                if (this.el.websitePreview) this.el.websitePreview.style.setProperty('--preview-primary', color);
                if (this.el.primaryColorSwatch) {
                    this.el.primaryColorSwatch.style.backgroundColor = color;
                    this.el.primaryColorSwatch.style.boxShadow = `0 0 0 2px white, 0 0 0 4px ${color}`;
                }

                if (this.el.previewShopName) this.el.previewShopName.textContent = this.shopName;
                if (this.el.previewHeaderCta) this.el.previewHeaderCta.textContent = data.ctaButtonText;
                if (this.el.previewHeroCta) this.el.previewHeroCta.textContent = data.ctaButtonText;
                if (this.el.previewHeroHeading) this.el.previewHeroHeading.innerHTML = this.formatPreviewHeading(data.heroHeading);
                if (this.el.previewHeroSubtext) this.el.previewHeroSubtext.textContent = data.heroSubtext;

                const logoPath = this.el.logoUploadPreview?.src || this.toDisplayPath(data.logoPath);
                if (data.logoPath || (logoPath && !logoPath.includes('aida-public'))) {
                    this.el.previewLogoImg.src = logoPath;
                    this.el.previewLogoImg.classList.remove('hidden');
                    this.el.previewLogoFallback.classList.add('hidden');
                } else {
                    this.el.previewLogoImg.classList.add('hidden');
                    this.el.previewLogoFallback.classList.remove('hidden');
                }

                const heroSrc = this.el.heroBackgroundUploadPreview?.src || this.toDisplayPath(data.heroBackground) || this.defaultHeroImage;
                if (this.el.previewHeroImage) this.el.previewHeroImage.src = heroSrc;

                const carouselDisplayImages = this.carouselImages.length ? this.carouselImages : [];
                if (this.el.previewCarouselMain && carouselDisplayImages.length > 0) {
                    this.el.previewCarouselMain.src = this.toDisplayPath(carouselDisplayImages[0]);
                }
                if (this.el.previewCarouselGrid) {
                    if (carouselDisplayImages.length === 0) {
                        this.el.previewCarouselGrid.innerHTML = `<div class="md:col-span-3 border-2 border-dashed border-slate-300 rounded-xl p-10 text-center text-slate-500 font-semibold">Uploaded picture carousel images will show here.</div>`;
                    } else {
                        this.el.previewCarouselGrid.innerHTML = carouselDisplayImages.map((src, index) => `
                            <div class="rounded-xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                                <img src="${this.escapeAttr(this.toDisplayPath(src))}" alt="Carousel image ${index + 1}" class="w-full h-64 object-cover" onerror="this.onerror=null;this.src='${this.placeholderThumb}'" />
                            </div>
                        `).join('');
                    }
                }
            },

            toDisplayPath(path) {
                path = String(path || '').trim();
                if (!path) return '';
                if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:') || path.startsWith('blob:')) return path;
                if (path.startsWith('../') || path.startsWith('./')) return path;
                if (path.startsWith('/uploads/')) return '..' + path;
                if (path.startsWith('uploads/')) return '../' + path;
                return path;
            },

            normalizeHex(color) {
                color = String(color || '').trim();
                return /^#[0-9A-Fa-f]{6}$/.test(color) ? color : '#1152d4';
            },

            formatPreviewHeading(text) {
                const safe = this.escapeHtml(text || 'Precision Engineering. Absolute Reliability.');
                const parts = safe.split(/\s+/);
                if (parts.length <= 2) return safe;
                const lastTwo = parts.splice(-2).join(' ');
                return `${parts.join(' ')} <br><span style="color:var(--preview-primary);">${lastTwo}</span>`;
            },

            escapeHtml(value) {
                return String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
            },

            escapeAttr(value) {
                return this.escapeHtml(value).replace(/`/g, '&#96;');
            },

            async saveCustomization() {
                const customizationData = this.collectFormData();

                try {
                    const response = await fetch('customization_handler.php?action=save_customization', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(customizationData),
                        credentials: 'same-origin'
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        this.showNotification('Changes saved successfully!', 'success');
                    } else {
                        this.showNotification('Error: ' + result.message, 'error');
                    }
                } catch (error) {
                    this.showNotification('Error saving changes: ' + error.message, 'error');
                }
            },

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `fixed bottom-8 right-8 px-6 py-4 rounded-lg text-white font-semibold shadow-lg z-50 ${
                    type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
                }`;
                notification.textContent = message;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            }
        };

        document.addEventListener('DOMContentLoaded', () => customizationSystem.init());
    </script>
    <script>
        // Dropdown menu click handler
        document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('hidden');
                }
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdownBtn = document.querySelector('.settings-dropdown-btn');
            const dropdown = document.querySelector('[data-dropdown="settings"].settings-dropdown');
            if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
    </div>
</body>

</html>