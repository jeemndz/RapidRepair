<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/../log_helper.php';

// Check if tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Get logged-in user information
$loggedInUserName = '';
$loggedInUserRole = '';
if ($_SESSION['userType'] === 'owner') {
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
    mysqli_stmt_bind_param($ownerStmt, 'i', $tenantID);
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
                                href="websitecustomadmin.php">
                                <span class="material-symbols-outlined text-[18px]">palette</span>
                                Website Customizer
                            </a>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-sm border-t border-slate-100 dark:border-slate-700"
                                href="settingsadmin.php">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Settings
                            </a>
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
                    <button type="submit" class="text-slate-400 hover:text-error transition-colors" title="Logout">
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
                                    class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-6 flex flex-col items-center justify-center bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                                    <img alt="Logo preview" class="h-12 w-auto mb-3"
                                        data-alt="minimalist modern automotive shop logo with stylized A icon in cobalt blue and white"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuAAzMMnJym9F6czXksfZXzrPtKMH183e-EOvioThq9iGIP3-ZXYZuUlhftXt5iWp0siIIMP3RX_OHGVe8o0rIlmeediw01JDpZidL9P_J9IQ7DsJRwERvxhhak-_y4XDJusAg-qL6jvoC1C81Lg0YZsesYJhR5yUDW2J1Emkod0ZuLnYTdHtlOk3esIXKqlSMY8Oel5Ww6W9apG8yrx2P_JcyubEQwcoitk1GMzDXL2NtMS4qxv2aqd-JKps70-LX_rwkcCLR_Sl0oZ" />
                                    <span class="text-xs text-slate-500 font-medium">Click to upload or drag &amp;
                                        drop</span>
                                    <span class="text-[10px] text-slate-400 mt-1">SVG, PNG or JPG (Max 2MB)</span>
                                </div>
                            </label>
                            <div class="space-y-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block">Primary Brand Color</span>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-10 w-10 rounded-lg bg-primary ring-2 ring-offset-2 ring-primary cursor-pointer shadow-sm">
                                    </div>
                                    <input
                                        class="flex-1 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg text-sm font-mono focus:ring-primary focus:border-primary dark:focus:ring-primary"
                                        type="text" value="#1152d4" />
                                </div>
                                <div class="flex gap-2 pt-1">
                                    <div class="h-6 w-6 rounded-full bg-[#1152d4] cursor-pointer"></div>
                                    <div class="h-6 w-6 rounded-full bg-[#d41111] cursor-pointer"></div>
                                    <div class="h-6 w-6 rounded-full bg-[#11d441] cursor-pointer"></div>
                                    <div class="h-6 w-6 rounded-full bg-[#000000] cursor-pointer"></div>
                                    <div class="h-6 w-6 rounded-full bg-[#6366f1] cursor-pointer"></div>
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
                                    rows="2">Precision Engineering. Absolute Reliability.</textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Subtext</label>
                                <textarea
                                    class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg text-sm focus:ring-primary focus:border-primary"
                                    rows="3">Expert automotive repair and maintenance services for performance vehicles and daily drivers alike.</textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block">Hero Background</label>
                                <div
                                    class="relative group rounded-xl overflow-hidden aspect-video border border-slate-200">
                                    <img alt="Hero background" class="w-full h-full object-cover"
                                        data-alt="dark atmospheric car garage with luxury vehicle on hydraulic lift and dramatic industrial lighting"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuAAEZoRvP3cMoUAVGKEn0It-hmRsA1SyxyvB4Q7rQ1ZCdmQ2cV7q-hIMLuoBa7_CqNQTWi6jDYPp6v959Tj6DrZDcqxD8TmkVnQ7sKMZ3lAzHhx24IHW8cZP9InEDM4nLAtrMtf-KjqkjRqQNiwDGk-5lw-86c7XQPqFA2yy73An2brlcVRgz1YyvIOd-Zyed5aD9ilvLWqBCDN18ZJhQ-cVSgeiEhlAJb7GFZdWldZ9ttKApne0LizHsag6CM4J3ytnsVga2qyAPy0" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button
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
                            <div class="grid grid-cols-3 gap-2">
                                <!-- Thumbnail 1 -->
                                <div
                                    class="relative aspect-square rounded-lg overflow-hidden border border-slate-200 group">
                                    <img alt="Carousel thumbnail" class="w-full h-full object-cover"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCwGXEfTy8DlOw1YNRg6RLzDpkkwxZlAKRx2lvHV2OzUdmIBAI-80Yb82sDcTixdMIjcM2Bje12VI2U06L83ro_Gg8kT8tyW4eTj5Fl-8jCeUIhU1obV_sG15n45_cU-1nHjIlN6Vr14rB36GtVUC58vqCEpZN0r2k-p0xM2huhLhR9r-uPnAu21ulzIqlMGwy-PIbqQB4vGaeuYxj5rETFjptOlHV3_qferqA4FOg-GWBckKwV3Y24CIZHunAsnqwtq2QIlw3xPZEo" />
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
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuC6WYbEGYRuc18Q-3jm5xKblfYfIFt3AdDPamfPaDpDfp9hNkz_lsQp6vALQCLbG7QZCVbRdfvfe0pvhng3qJuP0MR7jGnci3Mtbwre28IYur9PRpR3vmJvUntKxdpEQLTEip-8nSxws-lOz4gPWEL2pOmQ4cBqo7Hjjw2a3itXG9nF_Ng-gDY8pJVD5wAJIZFaHJogBo1Y6Hn0kfW6R3bIBWTb_d_3qe9CYorXDTti1_564wc8wvJh2bVlBQGv5Igs6ptBRLmcABQo" />
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
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuD0vciZ07jYRKeWn1spvw7knCLljGODiLcThQQZOShgePH8_nh2Il4JTMJV5oEiGIrlCWYA00WxUR0y94y48RyqANFlzJff4f0Of_TmutplWAoCW_oRQLA4itduTywEgTWiAUgWXzzCr-dWkYykHDUue32coaGylSYy5X4GaCbuBNVOJlaxk2GjHYznAAXeJIFI-Ii2HQPT0SA0tf4D8w1MqwPBWOh7A8mKvmgQL9GOSk_8jOoVpOz888V6U-vy2fLs6diY36YRDXvU" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button class="material-symbols-outlined text-white text-sm hover:text-red-400"
                                            data-icon="delete">delete</button>
                                    </div>
                                </div>
                            </div>
                            <button
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
                                type="text" value="Book Appointment" />
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
                    <div class="w-full h-full overflow-y-auto">
                        <!-- Website Nav -->
                        <nav class="h-20 px-12 flex items-center justify-between border-b border-slate-100 bg-white">
                            <div class="flex items-center gap-2">
                                <div
                                    class="h-10 w-10 bg-primary rounded flex items-center justify-center text-white font-black text-lg">
                                    A</div>
                                <span class="font-black text-xl tracking-tight text-slate-900">APEX AUTO</span>
                            </div>
                            <div class="flex gap-8 text-sm font-semibold text-slate-600">
                                <a class="text-primary" href="#">Home</a>
                                <a href="#">Services</a>
                                <a href="#">About</a>
                                <a href="#">Contact</a>
                            </div>
                            <button class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold">Book
                                Appointment</button>
                        </nav>
                        <!-- Website Hero -->
                        <section class="relative h-[480px] flex items-center px-24 overflow-hidden">
                            <img alt="Hero Preview" class="absolute inset-0 w-full h-full object-cover"
                                data-alt="professional mechanic workshop with sleek high-end cars and clinical blue lighting"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuA0zmutvRnUl-GPzbS-QT9sT-esKJdfNuSKhnLh1fTUPzENyd0Zzo-ohnNpN66RlwQJhlPCQBYqVW7PmRJvJAt1gI9zDLzS0xpQ4ZaGQH3uOhzpSZqipJvISctGkdw4YVFw_zt_rbiGBEUyeTlMPK7HcscBwu3_2-GD3DZHdWU5KkNZQT59iorXGFpytVXaffS-fTig4CY1nMCp8T6BeGaC0UI4EKJJ0UfZYWgUmhPD29Lf9QDPBG4Amzp9lzVaaoUnf0SjEl2chzzJ" />
                            <div class="absolute inset-0 bg-slate-900/60"></div>
                            <div class="relative max-w-2xl text-white">
                                <h2 class="text-5xl font-black tracking-tight mb-6 leading-tight">Precision Engineering.
                                    Absolute Reliability.</h2>
                                <p class="text-xl text-slate-200 mb-8 leading-relaxed">Expert automotive repair and
                                    maintenance services for performance vehicles and daily drivers alike.</p>
                                <div class="flex gap-4">
                                    <button class="bg-primary px-8 py-4 rounded-lg font-bold text-lg">Book
                                        Appointment</button>
                                    <button
                                        class="bg-white/10 backdrop-blur-md border border-white/20 px-8 py-4 rounded-lg font-bold text-lg">View
                                        Services</button>
                                </div>
                            </div>
                        </section>
                        <!-- Website Services -->
                        <section class="py-24 px-24 bg-white">
                            <div class="text-center mb-16">
                                <span class="text-primary font-bold text-sm tracking-widest uppercase mb-2 block">Our
                                    Expertise</span>
                                <h3 class="text-4xl font-black text-slate-900 tracking-tight">World Class Service</h3>
                            </div>
                            <div class="grid grid-cols-3 gap-8">
                                <div class="p-10 bg-slate-50 border border-slate-100 rounded-xl">
                                    <div
                                        class="h-14 w-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
                                        <span class="material-symbols-outlined text-3xl"
                                            data-icon="troubleshoot">troubleshoot</span>
                                    </div>
                                    <h4 class="text-xl font-bold text-slate-900 mb-3">Diagnostics</h4>
                                    <p class="text-slate-500 leading-relaxed">State-of-the-art computer analysis to
                                        identify vehicle issues rapidly.</p>
                                </div>
                                <div class="p-10 bg-slate-50 border border-slate-100 rounded-xl">
                                    <div
                                        class="h-14 w-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
                                        <span class="material-symbols-outlined text-3xl"
                                            data-icon="car_repair">car_repair</span>
                                    </div>
                                    <h4 class="text-xl font-bold text-slate-900 mb-3">Brake Repair</h4>
                                    <p class="text-slate-500 leading-relaxed">Full system inspection, pad replacement,
                                        and rotor resurfacing.</p>
                                </div>
                                <div class="p-10 bg-slate-50 border border-slate-100 rounded-xl">
                                    <div
                                        class="h-14 w-14 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
                                        <span class="material-symbols-outlined text-3xl"
                                            data-icon="oil_barrel">oil_barrel</span>
                                    </div>
                                    <h4 class="text-xl font-bold text-slate-900 mb-3">Oil Changes</h4>
                                    <p class="text-slate-500 leading-relaxed">Premium synthetic fluids and
                                        high-efficiency filtration systems.</p>
                                </div>
                            </div>
                        </section>
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
            tenantID: <?php echo $tenantID; ?>,
            
            // Initialize the system
            init() {
                this.loadCustomization();
                this.attachEventListeners();
            },
            
            // Load existing customization from database
            async loadCustomization() {
                try {
                    const response = await fetch(`customization_handler.php?action=get_customization`);
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        this.populateFormData(result.data);
                    }
                } catch (error) {
                    console.log('No existing customization found');
                }
            },
            
            // Populate form with existing data
            populateFormData(data) {
                // Shop Name
                const shopNameInput = document.querySelector('textarea');
                if (shopNameInput && data.shopName) {
                    shopNameInput.value = data.shopName;
                }
                
                // Primary Color
                const colorInput = document.querySelector('input[type="text"][value="#1152d4"]');
                if (colorInput && data.primaryColor) {
                    colorInput.value = data.primaryColor;
                    const colorPreview = document.querySelector('.bg-primary.ring-2');
                    if (colorPreview) {
                        colorPreview.style.backgroundColor = data.primaryColor;
                        colorPreview.style.borderColor = data.primaryColor;
                    }
                }
                
                // Hero Section
                const heroTextareas = document.querySelectorAll('textarea');
                if (heroTextareas[1] && data.heroHeading) {
                    heroTextareas[1].value = data.heroHeading;
                }
                if (heroTextareas[2] && data.heroSubtext) {
                    heroTextareas[2].value = data.heroSubtext;
                }
                
                // CTA Button Text
                const ctaInput = document.querySelector('input[value="Book Appointment"]');
                if (ctaInput && data.ctaButtonText) {
                    ctaInput.value = data.ctaButtonText;
                }
            },
            
            // Attach event listeners
            attachEventListeners() {
                const saveButton = document.getElementById('saveButton');
                if (saveButton) {
                    saveButton.addEventListener('click', () => this.saveCustomization());
                }
                
                // Color picker circles
                document.querySelectorAll('.bg-\\[\\#1152d4\\], .bg-\\[\\#d41111\\], .bg-\\[\\#11d441\\], .bg-\\[\\#000000\\], .bg-\\[\\#6366f1\\]').forEach((circle, index) => {
                    circle.addEventListener('click', (e) => {
                        const colors = ['#1152d4', '#d41111', '#11d441', '#000000', '#6366f1'];
                        const colorInput = document.querySelector('input[type="text"][value="#1152d4"]');
                        if (colorInput) {
                            colorInput.value = colors[index];
                        }
                    });
                });
                
                // Image upload handlers
                this.attachImageUploadHandlers();
            },
            
            // Attach image upload handlers
            attachImageUploadHandlers() {
                const logoDragZone = document.querySelector('[data-alt*="minimalist modern automotive"]').parentElement.parentElement;
                if (logoDragZone) {
                    logoDragZone.addEventListener('click', () => this.triggerImageUpload('logo'));
                    logoDragZone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        logoDragZone.classList.add('bg-blue-50');
                    });
                    logoDragZone.addEventListener('dragleave', () => {
                        logoDragZone.classList.remove('bg-blue-50');
                    });
                    logoDragZone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        logoDragZone.classList.remove('bg-blue-50');
                        const files = e.dataTransfer.files;
                        if (files.length > 0) {
                            this.uploadImage(files[0], 'logo');
                        }
                    });
                }
            },
            
            // Trigger file input for image upload
            triggerImageUpload(type) {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';
                input.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        this.uploadImage(e.target.files[0], type);
                    }
                });
                input.click();
            },
            
            // Upload image to server
            async uploadImage(file, type) {
                const formData = new FormData();
                formData.append('image', file);
                
                try {
                    const response = await fetch(`customization_handler.php?action=upload_image`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        this.handleImageUploadSuccess(type, result.path, file.name);
                    } else {
                        this.showNotification('Error uploading image: ' + result.message, 'error');
                    }
                } catch (error) {
                    this.showNotification('Error uploading image: ' + error.message, 'error');
                }
            },
            
            // Handle successful image upload
            handleImageUploadSuccess(type, path, originalName) {
                if (type === 'logo') {
                    const logoImg = document.querySelector('[data-alt*="minimalist modern automotive"]');
                    if (logoImg) {
                        logoImg.src = path;
                    }
                    this.showNotification('Logo uploaded successfully', 'success');
                }
            },
            
            // Save customization to database
            async saveCustomization() {
                const customizationData = this.collectFormData();
                
                try {
                    const response = await fetch('customization_handler.php?action=save_customization', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(customizationData)
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
            
            // Collect form data
            collectFormData() {
                const textareas = document.querySelectorAll('textarea');
                const inputs = document.querySelectorAll('input[type="text"]');
                
                return {
                    shopName: textareas[0]?.value || '',
                    primaryColor: inputs[0]?.value || '#1152d4',
                    shopLogo: inputs[0]?.dataset.logoPath || '',
                    heroHeading: textareas[1]?.value || '',
                    heroSubtext: textareas[2]?.value || '',
                    heroBackground: inputs[1]?.dataset.bgPath || '',
                    ctaButtonText: inputs[2]?.value || 'Book Appointment',
                    services: this.collectServices()
                };
            },
            
            // Collect services data
            collectServices() {
                const services = [];
                document.querySelectorAll('.p-3.border.border-slate-200.rounded-lg.bg-slate-50').forEach(serviceDiv => {
                    const title = serviceDiv.querySelector('p')?.textContent || '';
                    const icon = serviceDiv.querySelector('.material-symbols-outlined')?.getAttribute('data-icon') || '';
                    services.push({ title, icon });
                });
                return services;
            },
            
            // Show notification
            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `fixed bottom-8 right-8 px-6 py-4 rounded-lg text-white font-semibold shadow-lg z-50 ${
                    type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' :
                    'bg-blue-500'
                }`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        };
        
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            customizationSystem.init();
        });
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