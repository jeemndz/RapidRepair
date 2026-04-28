<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../log_helper.php';
include __DIR__ . '/paymongo/paymongo_helper.php';

$tenantID = isset($_GET['tenantID']) ? (int) trim($_GET['tenantID']) : 0;
$paymentID = isset($_GET['paymentID']) ? trim($_GET['paymentID']) : '';
$paymentIntentID = isset($_GET['paymentIntentID']) ? trim($_GET['paymentIntentID']) : '';

// Use paymentIntentID if provided, otherwise use paymentID
$transactionRef = $paymentIntentID ?: $paymentID;

if ($tenantID === 0 || $transactionRef === '') {
    header('Location: clientlanding.php');
    exit();
}

// Get tenant details
$tenantSql = "SELECT * FROM owners WHERE tenantID = " . $tenantID . " LIMIT 1";
$tenantResult = mysqli_query($conn, $tenantSql);
$tenant = $tenantResult ? mysqli_fetch_assoc($tenantResult) : null;

if (!$tenant) {
    header('Location: clientlanding.php');
    exit();
}

// Get payment details
$paymentSql = "SELECT * FROM subscription_payments WHERE transaction_reference = '" . 
    mysqli_real_escape_string($conn, $transactionRef) . "' AND tenantID = " . $tenantID . " LIMIT 1";
$paymentResult = mysqli_query($conn, $paymentSql);
$payment = $paymentResult ? mysqli_fetch_assoc($paymentResult) : null;

// If we have a payment intent ID and no local payment record yet, verify with Paymongo
if ($paymentIntentID && !$payment) {
    try {
        $gateway = initializePaymongoGateway();
        if ($gateway) {
            $intentResult = $gateway->retrievePaymentIntent($paymentIntentID);
            if ($intentResult['success'] && isset($intentResult['data']['data'])) {
                $intentData = $intentResult['data']['data'];
                // If payment is succeeded in Paymongo, update local database
                if ($intentData['attributes']['status'] === 'succeeded') {
                    $updateSql = "UPDATE subscription_payments 
                                  SET payment_status = 'paid', paid_at = NOW() 
                                  WHERE transaction_reference = '" . 
                                  mysqli_real_escape_string($conn, $paymentIntentID) . "' AND tenantID = " . $tenantID;
                    mysqli_query($conn, $updateSql);
                    
                    // Re-fetch the updated payment record
                    $paymentResult = mysqli_query($conn, $paymentSql);
                    $payment = $paymentResult ? mysqli_fetch_assoc($paymentResult) : null;
                }
            }
        }
    } catch (Exception $e) {
        error_log('Payment verification error: ' . $e->getMessage());
    }
}

$inviteCode = $tenant['invite_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Payment Received | RapidRepairCo.</title>
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
                    "colors": {
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "on-secondary-fixed": "#0f172a",
                        "primary-fixed": "#dbeafe",
                        "outline": "#e2e8f0",
                        "on-secondary": "#ffffff",
                        "surface-container": "#ffffff",
                        "on-error-container": "#991b1b",
                        "secondary-fixed": "#e2e8f0",
                        "surface-container-highest": "#ffffff",
                        "tertiary-container": "#fef3c7",
                        "on-secondary-container": "#1e293b",
                        "primary-fixed-dim": "#bfdbfe",
                        "on-primary": "#ffffff",
                        "surface-container-low": "#ffffff",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-secondary-fixed-variant": "#334155",
                        "outline-variant": "#cbd5e1",
                        "error-container": "#fee2e2",
                        "background": "#f6f6f8",
                        "surface-bright": "#ffffff",
                        "surface": "#f6f6f8",
                        "on-tertiary-container": "#92400e",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-fixed": "#1e3a8a",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "inverse-on-surface": "#f8fafc",
                        "tertiary-fixed": "#ffedd5",
                        "on-primary-container": "#1152d4",
                        "on-error": "#ffffff",
                        "inverse-primary": "#b4c5ff",
                        "surface-container-high": "#ffffff",
                        "on-background": "#0f172a",
                        "inverse-surface": "#1e293b",
                        "surface-variant": "#f1f5f9",
                        "secondary": "#475569",
                        "error": "#ef4444",
                        "secondary-container": "#f1f5f9",
                        "primary-container": "#eef2ff",
                        "on-tertiary": "#ffffff",
                        "surface-tint": "#1152d4",
                        "tertiary": "#f59e0b",
                        "surface-dim": "#d9d9e4",
                        "on-surface-variant": "#64748b",
                        "tertiary-fixed-dim": "#fed7aa",
                        "primary": "#1152d4",
                        "on-surface": "#0f172a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "fontFamily": {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    }
                },
            },
        }
    </script>
</head>

<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- TopNavBar -->
    <nav
        class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none flex justify-between items-center w-full px-8 h-16 max-w-full font-['Inter'] tracking-tight fixed top-0 z-50">
        <div class="text-xl font-black text-primary dark:text-blue-500 uppercase">RapidRepairCo.</div>
        <div class="hidden md:flex items-center space-x-8">
            <span class="text-slate-600 dark:text-slate-400 font-medium">Payment Received</span>
        </div>
    </nav>
    
    <main class="mt-16 flex-grow flex flex-col items-center justify-center px-4 md:px-8 py-12">
        <!-- Success Message -->
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <!-- Success Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-24 h-24 rounded-full bg-green-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-600" style="font-size: 56px;">check_circle</span>
                    </div>
                </div>
                
                <h1 class="text-4xl font-black mb-3">Payment Received!</h1>
                <p class="text-on-surface-variant text-lg">Your application payment has been successfully processed.</p>
            </div>

            <!-- Details Card -->
            <div class="bg-white border border-slate-200 rounded-xl p-8 space-y-6 mb-8 shadow-md">
                <div>
                    <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-1">Payment Reference</p>
                    <p class="text-lg font-bold font-mono break-all"><?php echo htmlspecialchars($transactionRef); ?></p>
                </div>

                <?php if ($payment): ?>
                    <div>
                        <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-1">Amount Paid</p>
                        <p class="text-2xl font-black text-primary">₱<?php echo number_format($payment['amount'], 2); ?></p>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-1">Payment Status</p>
                        <p class="text-sm font-bold capitalize">
                            <?php if ($payment['payment_status'] === 'paid'): ?>
                                <span class="inline-block px-3 py-1 rounded-lg bg-green-100 text-green-700">Paid</span>
                            <?php elseif ($payment['payment_status'] === 'pending'): ?>
                                <span class="inline-block px-3 py-1 rounded-lg bg-yellow-100 text-yellow-700">Pending</span>
                            <?php else: ?>
                                <span class="inline-block px-3 py-1 rounded-lg bg-slate-100 text-slate-700"><?php echo htmlspecialchars($payment['payment_status']); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div>
                    <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-1">Tenant ID</p>
                    <p class="text-lg font-bold font-mono"><?php echo htmlspecialchars($tenantID); ?></p>
                </div>

                <div>
                    <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-1">Tenant Name</p>
                    <p class="text-lg font-bold"><?php echo htmlspecialchars($tenant['name'] ?? 'N/A'); ?></p>
                </div>

                <?php if (!empty($inviteCode)): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-2">Your Invite Code</p>
                        <div class="bg-white border-2 border-primary rounded-lg p-4 text-center">
                            <p class="text-3xl font-black tracking-widest text-primary"><?php echo htmlspecialchars($inviteCode); ?></p>
                            <p class="text-xs text-on-surface-variant mt-2">Use this code to invite team members</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary mt-1">info</span>
                    <div>
                        <p class="font-bold text-sm mb-2">What Happens Next?</p>
                        <p class="text-xs text-on-surface-variant leading-relaxed">
                            Your application has been received and your payment has been processed. Our team will review your application and activate your account within 1-2 business days. You'll receive an email confirmation with your login credentials and account details.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <a href="clientlanding.php"
                    class="w-full py-4 bg-primary text-white text-sm font-black uppercase tracking-widest rounded-lg shadow-lg hover:shadow-primary/20 hover:brightness-110 transition-all text-center flex items-center justify-center gap-2">
                    Return to Dashboard
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
                <a href="clientlogin.php"
                    class="w-full py-4 bg-slate-100 text-slate-700 text-sm font-black uppercase tracking-widest rounded-lg hover:bg-slate-200 transition-all text-center">
                    Go to Login
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center px-8 py-4 w-full font-['Inter'] text-xs font-semibold mt-auto">
        <div class="text-slate-500 dark:text-slate-500">© 2026 RapidRepairCo. All rights reserved.</div>
        <div class="flex gap-6">
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors" href="#">Privacy Policy</a>
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors" href="#">Terms of Service</a>
        </div>
    </footer>
</body>
</html>
