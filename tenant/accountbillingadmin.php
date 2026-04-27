<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';

// Check if tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

// Enforce access control for this module
enforceModuleAccess($tenantID, basename(__FILE__));

// Get accessible modules for navigation
$accessibleModules = getAccessibleModules($tenantID);
$isStaffUser = isset($_SESSION['userType']) && $_SESSION['userType'] === 'staff';

// Helper function to check if a module should be accessible
function canAccessModule($moduleFile, $accessibleModules) {
    return in_array($moduleFile, $accessibleModules);
}

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

// Try session slug first, then URL slug
$loginSlug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

// If still no slug, force login
if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

// Validate tenant + slug
$ownerStmt = mysqli_prepare($conn, "SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1");
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

// Re-store correct slug in session
$_SESSION['login_slug'] = $loginSlug;
$shopName = isset($owner['shopName']) && $owner['shopName'] !== '' ? $owner['shopName'] : 'AutoFix Pro';
$shopSlug = $loginSlug;
$shopQuery = urlencode($loginSlug);

// Keep URL consistent
$currentScript = basename($_SERVER['PHP_SELF']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug)) {
    header('Location: ' . $currentScript . '?shop=' . $shopQuery);
    exit;
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) {
    return '₱' . number_format((float) $amount, 2);
}

// Fetch subscription details from owners table
$ownerSubscription = null;
$ownerSubStmt = mysqli_prepare($conn,
    "SELECT subscription_plan, billing_cycle, subscription_start, subscription_end, plan_price, next_billing_date, status
     FROM owners 
     WHERE tenantID = ? LIMIT 1");
if ($ownerSubStmt) {
    mysqli_stmt_bind_param($ownerSubStmt, 'i', $tenantID);
    mysqli_stmt_execute($ownerSubStmt);
    $ownerSubResult = mysqli_stmt_get_result($ownerSubStmt);
    $ownerSubscription = mysqli_fetch_assoc($ownerSubResult);
    mysqli_stmt_close($ownerSubStmt);
}

// Fetch payment methods
$paymentMethods = [];
$paymentStmt = mysqli_prepare($conn,
    "SELECT * FROM payment_methods 
     WHERE tenantID = ? 
     ORDER BY is_primary DESC, created_at DESC");
if ($paymentStmt) {
    mysqli_stmt_bind_param($paymentStmt, 'i', $tenantID);
    mysqli_stmt_execute($paymentStmt);
    $paymentResult = mysqli_stmt_get_result($paymentStmt);
    while ($pm = mysqli_fetch_assoc($paymentResult)) {
        $paymentMethods[] = $pm;
    }
    mysqli_stmt_close($paymentStmt);
}

// Handle delete payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment_method'])) {
    $pmId = (int) $_POST['payment_method_id'];
    $deleteStmt = mysqli_prepare($conn,
        "DELETE FROM payment_methods WHERE payment_method_id = ? AND tenantID = ? LIMIT 1");
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'ii', $pmId, $tenantID);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);
        header('Location: accountbillingadmin.php?shop=' . $shopQuery);
        exit;
    }
}

// Handle set as primary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_primary'])) {
    $pmId = (int) $_POST['payment_method_id'];
    mysqli_begin_transaction($conn);
    
    // Set all to non-primary for this tenant
    $resetStmt = mysqli_prepare($conn,
        "UPDATE payment_methods SET is_primary = FALSE WHERE tenantID = ?");
    mysqli_stmt_bind_param($resetStmt, 'i', $tenantID);
    mysqli_stmt_execute($resetStmt);
    mysqli_stmt_close($resetStmt);
    
    // Set this one as primary
    $setPrimaryStmt = mysqli_prepare($conn,
        "UPDATE payment_methods SET is_primary = TRUE WHERE payment_method_id = ? AND tenantID = ? LIMIT 1");
    mysqli_stmt_bind_param($setPrimaryStmt, 'ii', $pmId, $tenantID);
    mysqli_stmt_execute($setPrimaryStmt);
    mysqli_stmt_close($setPrimaryStmt);
    
    mysqli_commit($conn);
    header('Location: accountbillingadmin.php?shop=' . $shopQuery);
    exit;
}

// Handle add payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_method'])) {
    $methodType = trim((string) $_POST['method_type']);
    $isDefault = isset($_POST['set_as_primary']) ? 1 : 0;
    
    // Validate method type
    if (!in_array($methodType, ['card', 'wallet', 'bank_transfer'])) {
        header('Location: accountbillingadmin.php?shop=' . $shopQuery . '&error=invalid_method');
        exit;
    }
    
    // If setting as primary, unset others
    if ($isDefault) {
        $resetStmt = mysqli_prepare($conn,
            "UPDATE payment_methods SET is_primary = FALSE WHERE tenantID = ?");
        mysqli_stmt_bind_param($resetStmt, 'i', $tenantID);
        mysqli_stmt_execute($resetStmt);
        mysqli_stmt_close($resetStmt);
    }
    
    // Insert based on method type
    $insertStmt = null;
    if ($methodType === 'card') {
        $cardBrand = trim((string) $_POST['card_brand']);
        $cardLastFour = trim((string) $_POST['card_last_four']);
        $expiryMonth = (int) $_POST['expiry_month'];
        $expiryYear = (int) $_POST['expiry_year'];
        
        $insertStmt = mysqli_prepare($conn,
            "INSERT INTO payment_methods (tenantID, method_type, card_brand, card_last_four, card_expiry_month, card_expiry_year, is_primary, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($insertStmt, 'isssiii', $tenantID, $methodType, $cardBrand, $cardLastFour, $expiryMonth, $expiryYear, $isDefault);
    } elseif ($methodType === 'wallet') {
        $provider = trim((string) $_POST['wallet_provider']);
        $identifier = trim((string) $_POST['wallet_identifier']);
        
        $insertStmt = mysqli_prepare($conn,
            "INSERT INTO payment_methods (tenantID, method_type, wallet_provider, wallet_identifier, is_primary, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($insertStmt, 'isssi', $tenantID, $methodType, $provider, $identifier, $isDefault);
    } else {
        $bankName = trim((string) $_POST['bank_name']);
        $accountNumber = trim((string) $_POST['bank_account_number']);
        $accountType = trim((string) $_POST['bank_account_type']);
        
        $insertStmt = mysqli_prepare($conn,
            "INSERT INTO payment_methods (tenantID, method_type, bank_name, bank_account_number, bank_account_type, is_primary, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($insertStmt, 'issssi', $tenantID, $methodType, $bankName, $accountNumber, $accountType, $isDefault);
    }
    
    if ($insertStmt) {
        mysqli_stmt_execute($insertStmt);
        mysqli_stmt_close($insertStmt);
        header('Location: accountbillingadmin.php?shop=' . $shopQuery . '&success=payment_added');
        exit;
    }
}

// Fetch payment history from subscription_payments
$invoices = [];
$invoiceStmt = mysqli_prepare($conn,
    "SELECT payment_id as invoice_id, amount, payment_method, payment_status as status, 
            transaction_reference as invoice_number, paid_at as invoice_date,
            billing_period_start, billing_period_end
     FROM subscription_payments 
     WHERE tenantID = ? 
     ORDER BY paid_at DESC 
     LIMIT 10");
if ($invoiceStmt) {
    mysqli_stmt_bind_param($invoiceStmt, 'i', $tenantID);
    mysqli_stmt_execute($invoiceStmt);
    $invoiceResult = mysqli_stmt_get_result($invoiceStmt);
    while ($inv = mysqli_fetch_assoc($invoiceResult)) {
        $invoices[] = $inv;
    }
    mysqli_stmt_close($invoiceStmt);
}
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo h($shopName); ?> | Account Billing</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {}
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

<body class="bg-slate-50 text-slate-900 antialiased">
    <aside class="fixed left-0 top-0 bottom-0 w-64 border-r border-slate-200 bg-white z-50 flex flex-col h-full">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-blue-700 rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium" href="dashboardadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard</a>
                <?php endif; ?>
                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium" href="repairjobsadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">build</span>Repair Jobs</a>
                <?php endif; ?>
                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="vehicleadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles</a>
                <?php endif; ?>
                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">event</span>Appointments</a>
                <?php endif; ?>
                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="reportsadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">description</span>Reports</a>
                <?php endif; ?>
                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="inventoryadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory</a>
                <?php endif; ?>
                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="customeradmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">group</span>Customers</a>
                <?php endif; ?>
                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>"><span class="material-symbols-outlined text-[22px]">payments</span>Payments</a>
                <?php endif; ?>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors w-full text-left settings-dropdown-btn" data-dropdown="settings">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg hidden z-50 settings-dropdown" data-dropdown="settings">
                            <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm"
                                href="settingsadmin.php?shop=<?php echo h($shopQuery); ?>">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Settings
                            </a>
                            <?php endif; ?>
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                href="accountbillingadmin.php?shop=<?php echo h($shopQuery); ?>">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                Account Billing
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($loggedInUserName); ?></p>
                    <p class="text-xs text-slate-500 truncate"><?php echo h($loggedInUserRole); ?></p>
                </div>
                <form method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <input type="hidden" name="shop" value="<?php echo h($shopSlug); ?>" />
                    <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors" title="Logout">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="ml-64 min-h-screen bg-slate-50">
        <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/90 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Account Billing</h2>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>
        <div class="px-8 pb-12 pt-8">
            <!-- Page Heading -->
            <div class="mb-8">
                <h2 class="text-3xl font-black tracking-tight">Subscription & Billing</h2>
                <p class="text-slate-600 font-medium mt-1">Manage your professional shop plan and payment settings.</p>
            </div>
            <!-- Bento Grid Layout -->
            <div class="grid grid-cols-12 gap-6">
                <!-- Current Plan (Impact Card) -->
                <div class="col-span-12 lg:col-span-5 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                    <div class="p-6 bg-blue-700 text-white">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="bg-white/20 text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-widest">
                                    <?php echo $ownerSubscription ? h(ucfirst($ownerSubscription['status'])) : 'No Plan'; ?>
                                </span>
                                <h3 class="text-2xl font-black mt-2 tracking-tight">
                                    <?php echo $ownerSubscription ? h($ownerSubscription['subscription_plan']) : 'No Active Subscription'; ?>
                                </h3>
                            </div>
                            <span class="material-symbols-outlined text-3xl opacity-50">verified</span>
                        </div>
                        <?php if ($ownerSubscription && $ownerSubscription['plan_price']): ?>
                        <div class="mt-8">
                            <span class="text-4xl font-black tracking-tighter">
                                <?php echo format_currency($ownerSubscription['plan_price']); ?>
                            </span>
                            <span class="text-white/70 text-sm font-medium">/ <?php echo h($ownerSubscription['billing_cycle']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6 flex-1 space-y-4">
                        <?php if ($ownerSubscription && $ownerSubscription['subscription_start']): ?>
                        <div class="flex justify-between items-center text-sm border-b border-slate-100 pb-3">
                            <span class="text-slate-600">Plan Started</span>
                            <span class="font-bold text-slate-900"><?php echo date('M d, Y', strtotime($ownerSubscription['subscription_start'])); ?></span>
                        </div>
                        <div class="flex justify-between items-center text-sm border-b border-slate-100 pb-3">
                            <span class="text-slate-600">Next Billing Date</span>
                            <span class="font-bold text-slate-900"><?php echo $ownerSubscription['next_billing_date'] ? date('M d, Y', strtotime($ownerSubscription['next_billing_date'])) : 'N/A'; ?></span>
                        </div>
                        <?php if ($ownerSubscription['subscription_end']): ?>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-600">Plan Expires</span>
                            <span class="font-bold text-slate-900"><?php echo date('M d, Y', strtotime($ownerSubscription['subscription_end'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="mt-6 pt-4 flex gap-3">
                            <button class="flex-1 border border-blue-700 text-blue-700 font-bold text-sm py-2 rounded-lg hover:bg-blue-50 transition-colors">Change Plan</button>
                            <button class="flex-1 bg-slate-900 text-white font-bold text-sm py-2 rounded-lg hover:bg-slate-800 transition-colors">Cancel Plan</button>
                        </div>
                        <?php else: ?>
                        <div class="text-sm text-slate-600 py-4">
                            <p class="mb-4">No active subscription plan. Choose a plan to get started.</p>
                            <button class="w-full bg-blue-700 text-white font-bold text-sm py-2 rounded-lg hover:bg-blue-800 transition-colors">
                                Browse Plans
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Payment Methods Section -->
                <div class="col-span-12 lg:col-span-7 bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold tracking-tight text-slate-900">Payment Methods</h3>
                        <button onclick="document.getElementById('addPaymentModal').classList.remove('hidden')" class="flex items-center gap-2 text-blue-700 font-bold text-sm hover:underline">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Add New
                        </button>
                    </div>
                    <div class="space-y-4">
                        <?php if (!empty($paymentMethods)): ?>
                            <?php foreach ($paymentMethods as $pm): ?>
                            <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-slate-50 transition-colors group <?php echo $pm['is_primary'] ? 'border-blue-100 bg-blue-50/30' : 'border-slate-100'; ?>">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-8 bg-slate-200 rounded flex items-center justify-center text-[11px] font-bold text-slate-600">
                                        <?php 
                                        if ($pm['method_type'] === 'card') {
                                            echo strtoupper(h(substr($pm['card_brand'], 0, 4)));
                                        } elseif ($pm['method_type'] === 'wallet') {
                                            echo strtoupper(substr(h($pm['wallet_provider']), 0, 4));
                                        } else {
                                            echo 'BANK';
                                        }
                                        ?>
                                    </div>
                                    <div>
                                        <?php if ($pm['method_type'] === 'card'): ?>
                                        <p class="text-sm font-bold text-slate-900"><?php echo h($pm['card_brand']); ?> ending in <?php echo h($pm['card_last_four']); ?></p>
                                        <p class="text-xs text-slate-600">Expires <?php echo str_pad((int) $pm['card_expiry_month'], 2, '0', STR_PAD_LEFT) . '/' . substr((string) $pm['card_expiry_year'], -2); ?><?php echo $pm['is_primary'] ? ' • <span class="text-blue-600 font-bold">Primary</span>' : ''; ?></p>
                                        <?php elseif ($pm['method_type'] === 'wallet'): ?>
                                        <p class="text-sm font-bold text-slate-900"><?php echo h($pm['wallet_provider']); ?> Wallet</p>
                                        <p class="text-xs text-slate-600">ID: <?php echo h(substr($pm['wallet_identifier'], -8)); ?><?php echo $pm['is_primary'] ? ' • <span class="text-blue-600 font-bold">Primary</span>' : ''; ?></p>
                                        <?php else: ?>
                                        <p class="text-sm font-bold text-slate-900"><?php echo h($pm['bank_name']); ?></p>
                                        <p class="text-xs text-slate-600">Account: <?php echo h(substr($pm['bank_account_number'], -4)); ?><?php echo $pm['is_primary'] ? ' • <span class="text-blue-600 font-bold">Primary</span>' : ''; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <?php if (!$pm['is_primary']): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="payment_method_id" value="<?php echo (int) $pm['payment_method_id']; ?>">
                                        <input type="hidden" name="set_primary" value="1">
                                        <button type="submit" class="text-xs font-bold text-blue-700 px-2 py-1 rounded hover:bg-blue-100">Make Primary</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" class="inline" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="payment_method_id" value="<?php echo (int) $pm['payment_method_id']; ?>">
                                        <input type="hidden" name="delete_payment_method" value="1">
                                        <button type="submit" class="p-2 text-slate-600 hover:text-red-600 transition-colors">
                                            <span class="material-symbols-outlined text-lg">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center py-8 text-slate-500">
                            <p class="text-sm mb-4">No payment methods saved yet.</p>
                            <button onclick="document.getElementById('addPaymentModal').classList.remove('hidden')" class="text-blue-700 font-bold text-sm hover:underline">Add a payment method</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-8 p-4 bg-slate-50 rounded-lg flex gap-4 items-center">
                        <span class="material-symbols-outlined text-slate-400">security</span>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Your payment information is stored securely with end-to-end encryption. RapidRepair does not store raw card numbers on its servers.
                        </p>
                    </div>
                </div>
                <!-- Payment History Table -->
                <div class="col-span-12 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="text-xl font-bold tracking-tight text-slate-900">Payment History</h3>
                        <div class="flex gap-2">
                            <button class="flex items-center gap-2 border border-slate-300 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50">
                                <span class="material-symbols-outlined text-sm">filter_list</span>
                                Filter
                            </button>
                            <button class="flex items-center gap-2 border border-slate-300 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50">
                                <span class="material-symbols-outlined text-sm">download</span>
                                Export All
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50/50 text-slate-600 font-bold border-b border-slate-100">
                                    <th class="px-6 py-4">Invoice ID</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Amount</th>
                                    <th class="px-6 py-4">Method</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php if (!empty($invoices)): ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 font-mono font-medium text-xs text-blue-700"><?php echo h($invoice['invoice_number'] ?? 'PAY-' . str_pad($invoice['invoice_id'], 6, '0', STR_PAD_LEFT)); ?></td>
                                        <td class="px-6 py-4 text-slate-900"><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td class="px-6 py-4 font-bold text-slate-900"><?php echo format_currency($invoice['amount']); ?></td>
                                        <td class="px-6 py-4 text-xs text-slate-600"><?php echo h($invoice['payment_method']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold 
                                                <?php 
                                                if (strtolower($invoice['status']) === 'paid') echo 'bg-green-100 text-green-700';
                                                elseif (strtolower($invoice['status']) === 'failed') echo 'bg-red-100 text-red-700';
                                                elseif (strtolower($invoice['status']) === 'refunded') echo 'bg-slate-100 text-slate-700';
                                                else echo 'bg-yellow-100 text-yellow-700';
                                                ?>
                                            ">
                                                <span class="w-1 h-1 rounded-full
                                                    <?php 
                                                    if (strtolower($invoice['status']) === 'paid') echo 'bg-green-700';
                                                    elseif (strtolower($invoice['status']) === 'failed') echo 'bg-red-700';
                                                    elseif (strtolower($invoice['status']) === 'refunded') echo 'bg-slate-700';
                                                    else echo 'bg-yellow-700';
                                                    ?>
                                                "></span>
                                                <?php echo ucfirst(h($invoice['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button class="text-blue-700 hover:text-blue-900 font-bold text-xs">View Invoice</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No payment history found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-100 flex justify-between items-center text-xs text-slate-600 font-medium">
                        <p>Showing 4 of 24 invoices</p>
                        <div class="flex gap-2">
                            <button class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50 transition-colors">Previous</button>
                            <button class="px-3 py-1 bg-blue-700 text-white rounded">1</button>
                            <button class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50 transition-colors">2</button>
                            <button class="px-3 py-1 border border-slate-300 rounded hover:bg-slate-50 transition-colors">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Payment Method Modal -->
    <div id="addPaymentModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="sticky top-0 flex items-center justify-between p-6 border-b border-slate-200 bg-white">
                <h2 class="text-xl font-bold text-slate-900">Add Payment Method</h2>
                <button onclick="document.getElementById('addPaymentModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <!-- Payment Method Type Tabs -->
                <div class="flex gap-2 mb-6 border-b border-slate-200">
                    <button onclick="switchTab('card')" class="tab-btn active px-4 py-2 font-bold text-slate-600 border-b-2 border-transparent hover:text-slate-900 data-tab-card" data-tab="card">
                        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">credit_card</span>Credit/Debit Card</span>
                    </button>
                    <button onclick="switchTab('wallet')" class="tab-btn px-4 py-2 font-bold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-tab="wallet">
                        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">account_balance_wallet</span>Digital Wallet</span>
                    </button>
                    <button onclick="switchTab('bank_transfer')" class="tab-btn px-4 py-2 font-bold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-tab="bank_transfer">
                        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">account_balance</span>Bank Transfer</span>
                    </button>
                </div>

                <!-- Payment Form -->
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="add_payment_method" value="1">
                    <input type="hidden" name="method_type" id="methodType" value="card">

                    <!-- Card Tab -->
                    <div id="tab-card" class="tab-content space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Card Brand</label>
                            <select name="card_brand" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
                                <option value="Visa">Visa</option>
                                <option value="Mastercard">Mastercard</option>
                                <option value="American Express">American Express</option>
                                <option value="Discover">Discover</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Last 4 Digits</label>
                            <input type="text" name="card_last_four" maxlength="4" placeholder="1234" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-900 mb-1">Expiry Month</label>
                                <select name="expiry_month" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                                    <option value="">Select Month</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-900 mb-1">Expiry Year</label>
                                <select name="expiry_year" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                                    <option value="">Select Year</option>
                                    <?php for ($y = date('Y'); $y <= date('Y') + 20; $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Digital Wallet Tab -->
                    <div id="tab-wallet" class="tab-content space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Wallet Provider</label>
                            <select name="wallet_provider" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                                <option value="">Select Provider</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Google Pay">Google Pay</option>
                                <option value="Apple Pay">Apple Pay</option>
                                <option value="GCash">GCash</option>
                                <option value="Paymaya">PayMaya</option>
                                <option value="Alipay">Alipay</option>
                                <option value="WeChat Pay">WeChat Pay</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Wallet Email/Phone/ID</label>
                            <input type="text" name="wallet_identifier" placeholder="your@email.com or +63XXXXXXXXX" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                            <p class="text-xs text-slate-500 mt-1">Enter the email, phone number, or identifier associated with your wallet account.</p>
                        </div>
                    </div>

                    <!-- Bank Transfer Tab -->
                    <div id="tab-bank_transfer" class="tab-content space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g., BDO, BPI, Metrobank" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Account Number</label>
                            <input type="text" name="bank_account_number" placeholder="Account number (last 4 digits will be shown)" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-900 mb-1">Account Type</label>
                            <select name="bank_account_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors" required>
                                <option value="">Select Type</option>
                                <option value="Savings">Savings</option>
                                <option value="Checking">Checking</option>
                                <option value="Business">Business</option>
                            </select>
                        </div>
                    </div>

                    <!-- Set as Primary & Submit -->
                    <div class="mt-6">
                        <label class="flex items-center gap-2 mb-6">
                            <input type="checkbox" name="set_as_primary" class="w-4 h-4 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer">
                            <span class="text-sm font-bold text-slate-600">Set as primary payment method</span>
                        </label>
                        <div class="flex gap-3">
                            <button type="button" onclick="document.getElementById('addPaymentModal').classList.add('hidden')" class="flex-1 px-4 py-2 border border-slate-300 text-slate-600 font-bold rounded-lg hover:bg-slate-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="flex-1 px-4 py-2 bg-blue-700 text-white font-bold rounded-lg hover:bg-blue-800 transition-colors">
                                Add Payment Method
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
            });
            // Show selected tab
            const tab = document.getElementById('tab-' + tabName);
            if (tab) {
                tab.classList.remove('hidden');
            }
            // Update active button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-b-2', 'border-blue-700', 'text-blue-700');
                btn.classList.add('border-transparent', 'text-slate-600');
            });
            event.target.closest('.tab-btn').classList.add('border-b-2', 'border-blue-700', 'text-blue-700');
            
            // Update hidden method type
            document.getElementById('methodType').value = tabName;
        }

        // Initialize first tab
        document.addEventListener('DOMContentLoaded', function() {
            // Check for success message
            const params = new URLSearchParams(window.location.search);
            if (params.get('success') === 'payment_added') {
                alert('Payment method added successfully!');
                // Remove the query parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname + '?shop=<?php echo h($shopQuery); ?>');
            }
            if (params.get('error')) {
                alert('Error: ' + params.get('error').replace(/_/g, ' '));
            }
        });

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
</body>

</html>