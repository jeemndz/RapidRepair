<?php
// superadd.php
session_start();
require_once "db.php";

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

// Redirect if not logged in
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: superaddlogin.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Get superadmin info
$superadmin_id = $_SESSION['superadmin_id'];
$stmt = $conn->prepare("SELECT fullName, email FROM superadmin WHERE superadmin_id = ?");
$stmt->bind_param("i", $superadmin_id);
$stmt->execute();
$result = $stmt->get_result();
$superadmin = $result->fetch_assoc();

// Metrics
$totalTenants = $conn->query("SELECT COUNT(*) as total FROM owners")->fetch_assoc()['total'];
$activeShops = $totalTenants; // Assuming all tenants are active shops
$pendingApprovals = rand(0, 50); // Example: random pending, replace with real logic

// Recent Activity (last 5 owners)
$recentOwners = $conn->query("SELECT ownerName, created_at FROM owners ORDER BY created_at DESC LIMIT 5");
?>


<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Superadmin Dashboard - Car Repair Platform</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
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
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Side Navigation -->
        <aside
            class="w-72 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shrink-0">
            <div class="p-6 flex items-center gap-3">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined block text-2xl">directions_car</span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                    RapidRepair <span class="text-primary">SuperAdmin</span>
                </h2>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1">
                <!-- Dashboard -->
                <a href="superadd.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-semibold cursor-pointer hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined">dashboard</span>
                    <p class="text-sm">Dashboard</p>
                </a>

                <!-- Tenants -->
                <a href="superaddtenants.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">group</span>
                    <p class="text-sm font-medium">Tenants</p>
                </a>

                <!-- System Health -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined">analytics</span>
                    <p class="text-sm font-medium">System Health</p>
                </a>

                <!-- Subscriptions -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined">payments</span>
                    <p class="text-sm font-medium">Subscriptions</p>
                </a>

                <!-- Settings -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined">settings</span>
                    <p class="text-sm font-medium">Settings</p>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <!-- Profile -->
                <a href="#"
                    class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                    <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 bg-cover bg-center"
                        style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAA7ZvS0RT24pYl7zsQUKsnC9inrzmoUQVQC8PvdcW5_q4FtMWEC8ZD9Ke8mBa8iRwi4vfG0NbuLhEY9U_mYTQt3gBMRoNS0jNV_aJYQ-QCLtauVwWdyP53SHmFLjb5bQvwjbvvF24yHFp3moy4K6rJ0tVvtMIzdIUNohESEbLUilTPScnQYQQutAW0bzWhFZkGsX1GwwAl_2_9yXjauFnRNg0uTHfeR3lnfDRxLlk9Jo_hIr7N64rr5SWZq57QEfMdbFLkygzUgb-A')">
                    </div>
                    <div class="flex flex-col min-w-0">
                        <h3 class="text-sm font-semibold truncate">Alex Rivera</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                    </div>
                </a>

                <!-- Logout -->
                <form method="POST" class="w-full">
                    <button type="submit" name="logout_superadmin"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer text-left">
                        <span class="material-symbols-outlined">logout</span>
                        <p class="text-sm font-medium">Logout</p>
                    </button>
                </form>
            </div>
        </aside>
        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <!-- Header -->
            <header
                class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white/80 dark:bg-slate-900/80 px-8 backdrop-blur-md dark:border-slate-800">
                <div class="flex items-center gap-4 text-slate-900 dark:text-white">
                    <span class="material-symbols-outlined text-primary">auto_awesome</span>
                    <h2 class="text-lg font-bold tracking-tight">Superadmin Dashboard</h2>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative w-64">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
                        <input
                            class="w-full rounded-lg border-slate-200 bg-slate-50 py-1.5 pl-10 text-sm focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200"
                            placeholder="Search insights..." type="text" />
                    </div>
                    <button
                        class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined">notifications</span>
                        <span
                            class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"></span>
                    </button>
                    <button
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                        <span class="material-symbols-outlined">chat_bubble</span>
                    </button>
                    <div
                        class="h-10 w-10 overflow-hidden rounded-full border-2 border-primary/20 bg-slate-100 dark:bg-slate-800">
                        <img class="h-full w-full object-cover" data-alt="User profile avatar"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuAA7ZvS0RT24pYl7zsQUKsnC9inrzmoUQVQC8PvdcW5_q4FtMWEC8ZD9Ke8mBa8iRwi4vfG0NbuLhEY9U_mYTQt3gBMRoNS0jNV_aJYQ-QCLtauVwWdyP53SHmFLjb5bQvwjbvvF24yHFp3moy4K6rJ0tVvtMIzdIUNohESEbLUilTPScnQYQQutAW0bzWhFZkGsX1GwwAl_2_9yXjauFnRNg0uTHfeR3lnfDRxLlk9Jo_hIr7N64rr5SWZq57QEfMdbFLkygzUgb-A" />
                    </div>
                </div>
            </header>
            <div class="p-8 space-y-8">
                <!-- Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Tenants</p>
                            <span
                                class="material-symbols-outlined text-primary bg-primary/10 rounded-lg p-2">storefront</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white">1,284</h3>
                            <p class="mt-1 text-sm text-green-600 font-medium flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">trending_up</span> +12% vs last month
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Active Shops</p>
                            <span
                                class="material-symbols-outlined text-primary bg-primary/10 rounded-lg p-2">check_circle</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white">942</h3>
                            <p class="mt-1 text-sm text-green-600 font-medium flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">trending_up</span> +5% vs last month
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Approvals</p>
                            <span
                                class="material-symbols-outlined text-amber-500 bg-amber-500/10 rounded-lg p-2">pending_actions</span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white">48</h3>
                            <p class="mt-1 text-sm text-red-600 font-medium flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">trending_down</span> -2% decrease
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Main Analytics Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Tenant Growth Chart -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Tenant Growth Trend</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Monthly shop registrations (12
                                    Months)</p>
                            </div>
                            <div class="flex gap-2">
                                <span
                                    class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">+240
                                    new shops</span>
                            </div>
                        </div>
                        <div class="relative h-[250px] w-full">
                            <svg class="h-full w-full" preserveaspectratio="none" viewbox="0 0 400 150">
                                <defs>
                                    <lineargradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#1152d4" stop-opacity="0.2"></stop>
                                        <stop offset="100%" stop-color="#1152d4" stop-opacity="0"></stop>
                                    </lineargradient>
                                </defs>
                                <path d="M0 120 C 40 110, 80 130, 120 100 S 200 40, 240 60 S 320 10, 400 30 V 150 H 0 Z"
                                    fill="url(#chartGradient)"></path>
                                <path d="M0 120 C 40 110, 80 130, 120 100 S 200 40, 240 60 S 320 10, 400 30"
                                    fill="transparent" stroke="#1152d4" stroke-width="3"></path>
                            </svg>
                            <div class="flex justify-between mt-4 px-2">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Jan</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Apr</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Jul</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Oct</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Dec</span>
                            </div>
                        </div>
                    </div>
                    <!-- Geographic Distribution -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Geographic Distribution
                                </h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Top performance regions by shop
                                    volume</p>
                            </div>
                            <button class="text-xs text-primary font-medium hover:underline">View Map</button>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-sm mb-1.5">
                                    <span class="text-slate-600 dark:text-slate-300">California, USA</span>
                                    <span class="font-semibold">342 Shops</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: 85%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1.5">
                                    <span class="text-slate-600 dark:text-slate-300">Texas, USA</span>
                                    <span class="font-semibold">218 Shops</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: 65%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1.5">
                                    <span class="text-slate-600 dark:text-slate-300">New York, USA</span>
                                    <span class="font-semibold">186 Shops</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: 55%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1.5">
                                    <span class="text-slate-600 dark:text-slate-300">Florida, USA</span>
                                    <span class="font-semibold">124 Shops</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: 40%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Subscription Breakdown -->
                    <div
                        class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white">Service &amp; Tier
                                    Breakdown</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Revenue distribution by membership
                                    tier</p>
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row items-center gap-12">
                            <div class="relative w-48 h-48">
                                <svg class="w-full h-full" viewbox="0 0 100 100">
                                    <circle class="dark:stroke-slate-800" cx="50" cy="50" fill="transparent" r="40"
                                        stroke="#e2e8f0" stroke-width="12"></circle>
                                    <circle cx="50" cy="50" fill="transparent" r="40" stroke="#1152d4"
                                        stroke-dasharray="251.2" stroke-dashoffset="62.8" stroke-width="12"
                                        transform="rotate(-90 50 50)"></circle>
                                    <circle cx="50" cy="50" fill="transparent" r="40" stroke="#60a5fa"
                                        stroke-dasharray="251.2" stroke-dashoffset="188.4" stroke-width="12"
                                        transform="rotate(45 50 50)"></circle>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-2xl font-bold">78%</span>
                                    <span class="text-[10px] uppercase font-bold text-slate-400">Elite / Pro</span>
                                </div>
                            </div>
                            <div class="flex-1 space-y-4 w-full">
                                <div class="flex items-center gap-4">
                                    <div class="h-3 w-3 rounded-full bg-primary"></div>
                                    <span class="flex-1 text-sm text-slate-600 dark:text-slate-400">Elite
                                        Subscription</span>
                                    <span class="text-sm font-bold">55%</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="h-3 w-3 rounded-full bg-blue-400"></div>
                                    <span class="flex-1 text-sm text-slate-600 dark:text-slate-400">Pro Tier</span>
                                    <span class="text-sm font-bold">23%</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="h-3 w-3 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                                    <span class="flex-1 text-sm text-slate-600 dark:text-slate-400">Basic Plan</span>
                                    <span class="text-sm font-bold">22%</span>
                                </div>
                                <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium">Monthly Recurring Revenue</span>
                                        <span class="text-primary font-bold">$124,500</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Recent Activity Feed -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white">Recent Activity</h4>
                            <span class="material-symbols-outlined text-slate-400">bolt</span>
                        </div>
                        <div class="space-y-6">
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="h-2 w-2 rounded-full bg-green-500 ring-4 ring-green-500/10"></div>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-900 dark:text-white"><span class="font-bold">Elite
                                            Motors</span> registered</p>
                                    <p class="text-xs text-slate-400 mt-1">2 minutes ago</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="h-2 w-2 rounded-full bg-primary ring-4 ring-primary/10"></div>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-900 dark:text-white"><span
                                            class="font-bold">System</span> maintenance scheduled</p>
                                    <p class="text-xs text-slate-400 mt-1">45 minutes ago</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="h-2 w-2 rounded-full bg-amber-500 ring-4 ring-amber-500/10"></div>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-900 dark:text-white"><span class="font-bold">Pro-Fix
                                            Garage</span> updated plan</p>
                                    <p class="text-xs text-slate-400 mt-1">3 hours ago</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="h-2 w-2 rounded-full bg-slate-300 ring-4 ring-slate-300/10"></div>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-900 dark:text-white"><span class="font-bold">Alex
                                            Rivera</span> requested support</p>
                                    <p class="text-xs text-slate-400 mt-1">5 hours ago</p>
                                </div>
                            </div>
                        </div>
                        <button
                            class="w-full mt-8 rounded-lg border border-slate-200 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800 transition-colors">
                            View All Events
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>-