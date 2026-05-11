<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';
include __DIR__ . '/access_control.php';
include __DIR__ . '/../log_helper.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];

enforceModuleAccess($tenantID, basename(__FILE__));

$accessibleModules = getAccessibleModules($tenantID);

function canAccessModule($moduleFile, $accessibleModules)
{
    return in_array($moduleFile, $accessibleModules, true);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_currency($amount)
{
    return '₱' . number_format((float) $amount, 2);
}

$loggedInUserName = '';
$loggedInUserRole = '';

if (($_SESSION['userType'] ?? '') === 'owner') {
    $loggedInUserName = $_SESSION['shopName'] ?? 'Shop Owner';
    $loggedInUserRole = 'Administrator';
} else {
    $loggedInUserName = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? '')) ?: 'User';
    $loggedInUserRole = $_SESSION['userRole'] ?? 'Staff Member';
}

$loginSlug = '';

if (!empty($_SESSION['login_slug'])) {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (!empty($_GET['shop'])) {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$ownerStmt = mysqli_prepare($conn, "
    SELECT shopName, email, contactNumber 
    FROM owners 
    WHERE tenantID = ? AND login_slug = ? 
    LIMIT 1
");

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
$shopSlug = $loginSlug;
$shopQuery = urlencode($loginSlug);

$currentScript = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug)) {
    header('Location: ' . $currentScript . '?shop=' . $shopQuery);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get latest subscription from subscriptions table
|--------------------------------------------------------------------------
| Do NOT read plan_id from owners because owners has no plan_id column.
*/
$ownerSubscription = null;

$ownerSubStmt = mysqli_prepare(
    $conn,
    "SELECT 
        s.subscription_id,
        s.tenantID,
        s.plan_id,
        s.billing_cycle,
        s.start_date,
        s.end_date,
        s.next_billing_date,
        s.amount,
        s.status,
        COALESCE(sp.plan_name, CONCAT('Plan #', s.plan_id)) AS subscription_plan
     FROM subscriptions s
     LEFT JOIN subscription_plans sp ON sp.plan_id = s.plan_id
     WHERE s.tenantID = ?
     ORDER BY s.subscription_id DESC
     LIMIT 1"
);

if ($ownerSubStmt) {
    $tenantIDString = (string) $tenantID;
    mysqli_stmt_bind_param($ownerSubStmt, 's', $tenantIDString);
    mysqli_stmt_execute($ownerSubStmt);
    $ownerSubResult = mysqli_stmt_get_result($ownerSubStmt);
    $ownerSubscription = $ownerSubResult ? mysqli_fetch_assoc($ownerSubResult) : null;
    mysqli_stmt_close($ownerSubStmt);
}

$billingAmount = ($ownerSubscription && isset($ownerSubscription['amount']))
    ? (int) round(((float) $ownerSubscription['amount']) * 100)
    : 0;

$billingPlanName = $ownerSubscription
    ? ($ownerSubscription['subscription_plan'] . ' Subscription')
    : 'RapidRepairCo. Subscription';

/*
|--------------------------------------------------------------------------
| Payment methods
|--------------------------------------------------------------------------
*/
$paymentMethods = [];

$paymentStmt = mysqli_prepare(
    $conn,
    "SELECT * FROM payment_methods 
     WHERE tenantID = ? 
     ORDER BY is_primary DESC, created_at DESC"
);

if ($paymentStmt) {
    mysqli_stmt_bind_param($paymentStmt, 'i', $tenantID);
    mysqli_stmt_execute($paymentStmt);
    $paymentResult = mysqli_stmt_get_result($paymentStmt);

    while ($pm = mysqli_fetch_assoc($paymentResult)) {
        $paymentMethods[] = $pm;
    }

    mysqli_stmt_close($paymentStmt);
}

/*
|--------------------------------------------------------------------------
| Delete payment method
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment_method'])) {
    $pmId = (int) $_POST['payment_method_id'];

    $deleteStmt = mysqli_prepare(
        $conn,
        "DELETE FROM payment_methods WHERE payment_method_id = ? AND tenantID = ? LIMIT 1"
    );

    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'ii', $pmId, $tenantID);
        if (mysqli_stmt_execute($deleteStmt)) {
            log_event($conn, 'DELETE PaymentMethod', 'payment_method', $pmId, 'Deleted PaymentMethod with ID: ' . $pmId);
        }
        mysqli_stmt_close($deleteStmt);
    }

    header('Location: accountbillingadmin.php?shop=' . $shopQuery);
    exit;
}

/*
|--------------------------------------------------------------------------
| Set primary payment method
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_primary'])) {
    $pmId = (int) $_POST['payment_method_id'];

    mysqli_begin_transaction($conn);

    $resetStmt = mysqli_prepare($conn, "UPDATE payment_methods SET is_primary = FALSE WHERE tenantID = ?");
    mysqli_stmt_bind_param($resetStmt, 'i', $tenantID);
    if (mysqli_stmt_execute($resetStmt)) {
        log_event($conn, 'UPDATE PaymentMethod', 'payment_method', $tenantID, 'Updated is_primary to FALSE for tenant payment methods');
    }
    mysqli_stmt_close($resetStmt);

    $setPrimaryStmt = mysqli_prepare(
        $conn,
        "UPDATE payment_methods SET is_primary = TRUE WHERE payment_method_id = ? AND tenantID = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($setPrimaryStmt, 'ii', $pmId, $tenantID);
    if (mysqli_stmt_execute($setPrimaryStmt)) {
        log_event($conn, 'UPDATE PaymentMethod', 'payment_method', $pmId, 'Updated is_primary to TRUE');
    }
    mysqli_stmt_close($setPrimaryStmt);

    mysqli_commit($conn);

    header('Location: accountbillingadmin.php?shop=' . $shopQuery);
    exit;
}

/*
|--------------------------------------------------------------------------
| Payment history
|--------------------------------------------------------------------------
*/
$invoices = [];

$invoiceStmt = mysqli_prepare(
    $conn,
    "SELECT 
        payment_id AS invoice_id,
        amount,
        payment_provider,
        payment_method,
        payment_status AS status,
        transaction_reference AS invoice_number,
        paid_at AS invoice_date,
        billing_period_start,
        billing_period_end
     FROM subscription_payments 
     WHERE tenantID = ? 
     ORDER BY paid_at DESC, payment_id DESC 
     LIMIT 10"
);

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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

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
    <!-- Mobile Menu Toggle -->
    <div class="md:hidden fixed top-0 left-0 right-0 bg-white border-b border-slate-200 px-4 py-3 z-50 flex items-center justify-between">
        <button id="sidebarToggle" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-slate-100 transition-colors">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <h2 class="text-lg font-bold truncate flex-1 ml-3"><?php echo h($shopName); ?></h2>
    </div>
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-30 md:hidden"></div>

    <aside id="sidebar" class="fixed md:fixed left-0 top-0 bottom-0 w-64 border-r border-slate-200 bg-white z-40 flex flex-col h-full -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out md:transition-none pt-16 md:pt-0 overflow-y-auto">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-blue-700 rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($shopName); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Your Repair Shop</p>
                </div>
            </div>

            <nav class="space-y-1">
                <?php if (canAccessModule('dashboardadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                        href="dashboardadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('repairjobsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors font-medium"
                        href="repairjobsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">build</span>Repair Jobs
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('vehicleadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="vehicleadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('appointmentadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="appointmentadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">event</span>Appointments
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('reportsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="reportsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">description</span>Reports
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('inventoryadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="inventoryadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('customeradmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="customeradmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">group</span>Customers
                    </a>
                <?php endif; ?>

                <?php if (canAccessModule('paymentsadmin.php', $accessibleModules)): ?>
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>">
                        <span class="material-symbols-outlined text-[22px]">payments</span>Payments
                    </a>
                <?php endif; ?>

                <div class="pt-4 mt-4 border-t border-slate-100">
                    <div class="relative group">
                        <button class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-blue-50 text-blue-700 transition-colors w-full text-left settings-dropdown-btn">
                            <span class="material-symbols-outlined text-[22px]">settings</span>
                            <span>Settings</span>
                            <span class="material-symbols-outlined text-[16px] ml-auto">expand_more</span>
                        </button>

                        <div class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg hidden z-50 settings-dropdown">
                            <?php if (canAccessModule('accountbillingadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-t-lg text-blue-700 bg-blue-50 transition-colors text-sm"
                                    href="accountbillingadmin.php?shop=<?php echo h($shopQuery); ?>">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                    Account Billing
                                </a>
                            <?php endif; ?>
                            <a class="flex items-center gap-3 px-3 py-2.5 text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                href="websitecustomadmin.php?shop=<?php echo h($shopQuery); ?>">
                                <span class="material-symbols-outlined text-[18px]">palette</span>
                                Website Customizer
                            </a>
                            <?php if (canAccessModule('settingsadmin.php', $accessibleModules)): ?>
                                <a class="flex items-center gap-3 px-3 py-2.5 rounded-b-lg text-slate-600 hover:bg-blue-50 transition-colors text-sm border-t border-slate-100"
                                    href="settingsadmin.php?shop=<?php echo h($shopQuery); ?>">
                                    <span class="material-symbols-outlined text-[18px]">settings</span>
                                    Settings
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
            <h2 class="text-lg font-black text-slate-900 tracking-tight">Account Billing</h2>

            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-blue-700 transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-blue-700 transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>
        </header>

        <div class="px-8 pb-12 pt-8">
            <div class="mb-8">
                <h2 class="text-3xl font-black tracking-tight">Subscription & Billing</h2>
                <p class="text-slate-600 font-medium mt-1">Manage your professional shop plan and payment settings.</p>
            </div>

            <div class="grid grid-cols-12 gap-6">
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

                        <?php if ($ownerSubscription && $ownerSubscription['amount']): ?>
                            <div class="mt-8">
                                <span class="text-4xl font-black tracking-tighter">
                                    <?php echo format_currency($ownerSubscription['amount']); ?>
                                </span>
                                <span class="text-white/70 text-sm font-medium">
                                    / <?php echo h($ownerSubscription['billing_cycle']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-6 flex-1 space-y-4">
                        <?php if ($ownerSubscription): ?>

                            <div class="flex justify-between items-center text-sm border-b border-slate-100 pb-3">
                                <span class="text-slate-600">Plan Started</span>
                                <span class="font-bold text-slate-900">
                                    <?php echo !empty($ownerSubscription['start_date']) ? date('M d, Y', strtotime($ownerSubscription['start_date'])) : 'N/A'; ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center text-sm border-b border-slate-100 pb-3">
                                <span class="text-slate-600">Next Billing Date</span>
                                <span class="font-bold text-slate-900">
                                    <?php echo !empty($ownerSubscription['next_billing_date']) ? date('M d, Y', strtotime($ownerSubscription['next_billing_date'])) : 'N/A'; ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-600">Plan Expires</span>
                                <span class="font-bold text-slate-900">
                                    <?php echo !empty($ownerSubscription['end_date']) ? date('M d, Y', strtotime($ownerSubscription['end_date'])) : 'N/A'; ?>
                                </span>
                            </div>

                            <?php if ($billingAmount > 0): ?>
                                <form method="POST" action="../clientapplication/paymongo/create_checkout.php" class="mt-6 pt-4">
                                    <input type="hidden" name="payment_source" value="accountbillingadmin">
                                    <input type="hidden" name="tenant_id" value="<?php echo h($tenantID); ?>">
                                    <input type="hidden" name="plan_id" value="<?php echo h($ownerSubscription['plan_id'] ?? 0); ?>">
                                    <input type="hidden" name="billingCycle" value="<?php echo h($ownerSubscription['billing_cycle'] ?? 'monthly'); ?>">
                                    <input type="hidden" name="amount" value="<?php echo h($billingAmount); ?>">
                                    <input type="hidden" name="plan_name" value="<?php echo h($billingPlanName); ?>">
                                    <input type="hidden" name="name" value="<?php echo h($shopName); ?>">
                                    <input type="hidden" name="email" value="<?php echo h($owner['email'] ?? 'test@example.com'); ?>">
                                    <input type="hidden" name="phone" value="<?php echo h($owner['contactNumber'] ?? '09171234567'); ?>">

                                    <button type="submit" class="w-full bg-blue-700 text-white font-bold text-sm py-3 rounded-lg hover:bg-blue-800 transition-colors flex items-center justify-center gap-2">
                                        <span class="material-symbols-outlined text-lg">payments</span>
                                        Pay Subscription with PayMongo
                                    </button>
                                </form>
                            <?php endif; ?>

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

                <div class="col-span-12 lg:col-span-7 bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold tracking-tight text-slate-900">Payment Methods</h3>
                        <span class="text-xs text-slate-500 font-semibold">Managed securely by PayMongo</span>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-blue-50 p-5">
                        <div class="flex gap-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-700 text-white flex items-center justify-center">
                                <span class="material-symbols-outlined">lock</span>
                            </div>

                            <div>
                                <h4 class="font-bold text-slate-900">Secure PayMongo Checkout</h4>
                                <p class="text-sm text-slate-600 mt-1">
                                    Card, GCash, PayMaya, GrabPay, and QRPH payments are processed by PayMongo.
                                    RapidRepairCo. does not store raw card or wallet details.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
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
                                                <p class="text-sm font-bold text-slate-900">
                                                    <?php echo h($pm['card_brand']); ?> ending in <?php echo h($pm['card_last_four']); ?>
                                                </p>
                                            <?php elseif ($pm['method_type'] === 'wallet'): ?>
                                                <p class="text-sm font-bold text-slate-900">
                                                    <?php echo h($pm['wallet_provider']); ?> Wallet
                                                </p>
                                            <?php else: ?>
                                                <p class="text-sm font-bold text-slate-900">
                                                    <?php echo h($pm['bank_name']); ?>
                                                </p>
                                            <?php endif; ?>

                                            <p class="text-xs text-slate-600">
                                                <?php echo $pm['is_primary'] ? '<span class="text-blue-600 font-bold">Primary</span>' : 'Saved reference'; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <?php if (!$pm['is_primary']): ?>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="payment_method_id" value="<?php echo (int) $pm['payment_method_id']; ?>">
                                                <input type="hidden" name="set_primary" value="1">
                                                <button type="submit" class="text-xs font-bold text-blue-700 px-2 py-1 rounded hover:bg-blue-100">
                                                    Make Primary
                                                </button>
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
                                <p class="text-sm mb-2">No saved payment method references.</p>
                                <p class="text-xs">Use PayMongo checkout to complete subscription payments securely.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-span-12 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="text-xl font-bold tracking-tight text-slate-900">Payment History</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50/50 text-slate-600 font-bold border-b border-slate-100">
                                    <th class="px-6 py-4">Invoice ID</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Amount</th>
                                    <th class="px-6 py-4">Provider</th>
                                    <th class="px-6 py-4">Method</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-50">
                                <?php if (!empty($invoices)): ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4 font-mono font-medium text-xs text-blue-700">
                                                <?php echo h($invoice['invoice_number'] ?: 'PAY-' . str_pad($invoice['invoice_id'], 6, '0', STR_PAD_LEFT)); ?>
                                            </td>

                                            <td class="px-6 py-4 text-slate-900">
                                                <?php echo !empty($invoice['invoice_date']) ? date('M d, Y', strtotime($invoice['invoice_date'])) : 'N/A'; ?>
                                            </td>

                                            <td class="px-6 py-4 font-bold text-slate-900">
                                                <?php echo format_currency($invoice['amount']); ?>
                                            </td>

                                            <td class="px-6 py-4 text-xs text-slate-600">
                                                <?php echo h($invoice['payment_provider'] ?? 'PayMongo'); ?>
                                            </td>

                                            <td class="px-6 py-4 text-xs text-slate-600">
                                                <?php echo h($invoice['payment_method']); ?>
                                            </td>

                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold 
                                                <?php
                                                if (strtolower($invoice['status']) === 'paid')
                                                    echo 'bg-green-100 text-green-700';
                                                elseif (strtolower($invoice['status']) === 'failed')
                                                    echo 'bg-red-100 text-red-700';
                                                elseif (strtolower($invoice['status']) === 'refunded')
                                                    echo 'bg-slate-100 text-slate-700';
                                                else
                                                    echo 'bg-yellow-100 text-yellow-700';
                                                ?>">
                                                    <?php echo ucfirst(h($invoice['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                            No payment history found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        document.querySelectorAll('.settings-dropdown-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = document.querySelector('.settings-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('hidden');
                }
            });
        });

        document.addEventListener('click', function(e) {
            const dropdownBtn = document.querySelector('.settings-dropdown-btn');
            const dropdown = document.querySelector('.settings-dropdown');

            if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    <script>
    (function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const navLinks = document.querySelectorAll('aside a');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }

        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        });
    })();
    </script>

</body>

</html>