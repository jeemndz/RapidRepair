<?php
session_start();
include __DIR__ . "/../db.php";
include __DIR__ . "/paymongo/paymongo_helper.php";
include __DIR__ . "/client_helpers.php";

$errors = [];
$successMessage = "";
$tenantID = isset($_GET['tenantID']) ? (int) trim($_GET['tenantID']) : 0;
$selectedPlanCode = isset($_GET['plan']) ? strtolower(trim($_GET['plan'])) : '';
$billingCycle = isset($_GET['billingCycle']) ? strtolower(trim($_GET['billingCycle'])) : 'monthly';

// Verify tenant exists
$tenant = null;
if ($tenantID !== 0) {
    $tenant = getTenantDetails($conn, $tenantID);
    if (!$tenant) {
        $errors[] = 'Tenant not found. Please complete registration first.';
    }
} else {
    $errors[] = 'Invalid tenant information. Please register first.';
}

// Load subscription plans
$subscriptionPlans = loadSubscriptionPlans($conn);

// Find selected plan
$selectedPlan = null;
if ($selectedPlanCode !== '') {
    $selectedPlan = getPlanByCode($conn, $selectedPlanCode);
}

// Default to first plan if none selected
if (!$selectedPlan) {
    $selectedPlan = $subscriptionPlans[0];
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['processPayment'])) {
        // Initial payment form submission to create payment intent
        $cardholderName = trim($_POST['cardholderName'] ?? '');
        $selectedPlanCodeFromForm = isset($_POST['selectedPlanCode']) ? strtolower(trim($_POST['selectedPlanCode'])) : '';
        $billingCycleFromForm = isset($_POST['billingCycle']) ? strtolower(trim($_POST['billingCycle'])) : 'monthly';
        
        // Re-fetch the selected plan from form submission
        if ($selectedPlanCodeFromForm !== '') {
            $selectedPlan = getPlanByCode($conn, $selectedPlanCodeFromForm);
            if (!$selectedPlan) {
                $selectedPlan = $subscriptionPlans[0];
            }
        }
        $billingCycle = $billingCycleFromForm;
        
        // Validation
        if ($cardholderName === '' || strlen($cardholderName) < 2) {
            $errors[] = 'Valid cardholder name is required (at least 2 characters).';
        }
        
        if (count($errors) === 0 && $tenant) {
            // Calculate amount based on billing cycle
            $amount = $selectedPlan['monthly_price'];
            
            if ($billingCycle === 'quarterly') {
                $amount = $amount * 3;
            } else if ($billingCycle === 'yearly') {
                $amount = $amount * 12;
            }
            
            $description = $selectedPlan['plan_name'] . ' Plan - ' . ucfirst($billingCycle) . ' Billing';
            
            // Create payment intent using cURL
            $paymentIntentResult = processPaymongoPaymentIntent($conn, $tenantID, $amount, 'PHP', $description);
            
            if ($paymentIntentResult['success']) {
                // Store payment intent ID in session
                $_SESSION['paymongo_payment_intent_id'] = $paymentIntentResult['paymentIntentId'];
                $_SESSION['paymongo_public_key'] = $paymentIntentResult['publicKey'];
                $_SESSION['paymongo_cardholder'] = $cardholderName;
                $_SESSION['paymongo_amount'] = $amount;
                $_SESSION['paymongo_billing_cycle'] = $billingCycle;
                
                // Redirect to payment checkout
                header('Location: payment_checkout.php?tenantID=' . urlencode($tenantID) . '&paymentIntentID=' . urlencode($paymentIntentResult['paymentIntentId']));
                exit();
            } else {
                $errors[] = $paymentIntentResult['error'] ?? 'Failed to initialize payment. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>

<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Complete Payment | RapidRepairCo.</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
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
    <script>
        // Simple payment form handler
        document.addEventListener('DOMContentLoaded', function() {
            const paymentButton = document.getElementById('paymentButton');
            
            if (paymentButton) {
                paymentButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const form = document.querySelector('form');
                    const cardholderName = document.querySelector('input[name="cardholderName"]').value.trim();
                    const errorDiv = document.getElementById('payment-error');

                    // Validation
                    if (!cardholderName || cardholderName.length < 2) {
                        errorDiv.textContent = 'Please enter a valid cardholder name (at least 2 characters)';
                        return;
                    }

                    // Disable button during processing
                    paymentButton.disabled = true;
                    paymentButton.textContent = 'Processing...';
                    errorDiv.textContent = '';

                    // Submit form to create payment intent
                    form.submit();
                });
            }
        });
    </script>
    <script>
        // Plans data from PHP
        const plansData = <?php echo json_encode(array_map(function($plan) {
            return [
                'plan_id' => $plan['plan_id'],
                'plan_code' => $plan['plan_code'],
                'plan_name' => $plan['plan_name'],
                'monthly_price' => (float)$plan['monthly_price'],
                'description' => isset($plan['description']) ? $plan['description'] : ''
            ];
        }, $subscriptionPlans)); ?>;

        function updatePaymentMethodFields() {
            // For Paymongo, only card is available
            const cardFields = document.getElementById('cardFields');
            if (cardFields) {
                cardFields.style.display = 'block';
            }
        }

        function updateSelectedPlan(planCode) {
            // Find the plan in our data
            const plan = plansData.find(p => p.plan_code.toLowerCase() === planCode.toLowerCase());
            if (!plan) return;

            // Update the hidden input
            document.getElementById('selectedPlanCode').value = plan.plan_code;

            // Update display
            document.getElementById('planCodeDisplay').textContent = plan.plan_code;
            document.getElementById('planNameDisplay').textContent = plan.plan_name + ' Plan';
            document.getElementById('planPriceDisplay').textContent = '₱' + plan.monthly_price.toFixed(2);
            document.getElementById('orderSummaryPrice').textContent = '₱' + plan.monthly_price.toFixed(2);
            document.getElementById('subtotalPrice').textContent = '₱' + plan.monthly_price.toFixed(2);
            document.getElementById('totalPrice').textContent = '₱' + plan.monthly_price.toFixed(2);

            // Update plan selector cards if they exist
            document.querySelectorAll('[data-plan-card]').forEach(card => {
                const cardPlanCode = card.dataset.planCard.toLowerCase();
                if (cardPlanCode === planCode.toLowerCase()) {
                    card.classList.remove('border-slate-200', 'hover:border-primary');
                    card.classList.add('border-2', 'border-primary', 'bg-primary-fixed');
                } else {
                    card.classList.remove('border-2', 'border-primary', 'bg-primary-fixed');
                    card.classList.add('border-slate-200', 'hover:border-primary');
                }
            });
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .architectural-gradient {
            background: linear-gradient(180deg, rgba(17, 82, 212, 0.05) 0%, rgba(17, 82, 212, 0) 100%);
        }
    </style>
</head>

<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- TopNavBar -->
    <nav
        class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-none flex justify-between items-center w-full px-8 h-16 max-w-full font-['Inter'] tracking-tight fixed top-0 z-50">
        <div class="text-xl font-black text-primary dark:text-blue-500 uppercase">RapidRepairCo.</div>
        <div class="hidden md:flex items-center space-x-8">
            <span class="text-slate-600 dark:text-slate-400 font-medium">Payment Setup</span>
        </div>
        <div class="flex items-center gap-4">
            <a href="clientlanding.php" class="text-slate-600 dark:text-slate-400 font-medium hover:text-primary transition-colors">Back</a>
        </div>
    </nav>
    <main class="mt-16 flex-grow flex flex-col items-center px-4 md:px-8 py-12 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <header class="w-full mb-12 text-center md:text-left">
            <h1 class="text-[30px] font-black text-on-background tracking-tight mb-2">Complete Your Payment</h1>
            <p class="text-on-surface-variant text-sm font-medium">Select your subscription plan and provide payment details. After submission, your application will be reviewed by our team.</p>
        </header>

        <?php if ($successMessage !== ''): ?>
            <div class="w-full mb-6 rounded-lg border border-green-200 bg-green-50 px-6 py-4 text-sm text-green-800">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <div><?php echo htmlspecialchars($successMessage); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($errors) > 0): ?>
            <div class="w-full mb-6 rounded-lg border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <?php if (in_array('Failed to initialize payment. Please try again.', $errors)): ?>
                            <div style="margin-top: 8px; font-size: 0.85em; opacity: 0.9;">
                                <small><strong>Debug:</strong> If this persists, check that Paymongo credentials are configured in Azure App Service settings. <a href="debug_paymongo.php" target="_blank" style="color: inherit; text-decoration: underline;">Debug credentials</a></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 w-full">
            <!-- Plan Selection & Payment (LHS) -->
            <div class="lg:col-span-8 space-y-8">
                <!-- Plan Selection -->
                <section class="space-y-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary" data-icon="layers">layers</span>
                        <h2 class="text-2xl font-bold tracking-tight">Choose Your Plan</h2>
                    </div>
                    
                    <!-- Plan Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($subscriptionPlans as $plan): ?>
                            <div 
                                class="plan-card p-6 border-2 rounded-lg transition-all cursor-pointer <?php echo strtolower($selectedPlan['plan_code']) === strtolower($plan['plan_code']) ? 'border-primary bg-primary-fixed' : 'border-slate-200 hover:border-primary'; ?>"
                                data-plan-card="<?php echo htmlspecialchars($plan['plan_code']); ?>"
                                onclick="updateSelectedPlan('<?php echo htmlspecialchars($plan['plan_code']); ?>')">
                                <div class="mb-3">
                                    <span class="text-xs font-bold uppercase tracking-widest text-primary"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                                    <h3 class="text-lg font-bold mt-2"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                </div>
                                <div class="mb-4">
                                    <span class="text-2xl font-black">₱<?php echo number_format($plan['monthly_price'], 2); ?></span>
                                    <span class="text-sm text-on-surface-variant">/month</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Selected Plan Summary -->
                <section class="bg-white border-2 border-primary rounded-xl p-8 shadow-md">
                    <h3 class="text-lg font-bold mb-4">Your Selected Plan</h3>
                    <div class="mb-6">
                        <span class="text-xs font-bold uppercase tracking-widest text-primary" id="planCodeDisplay"><?php echo htmlspecialchars($selectedPlan['plan_code']); ?></span>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-4xl font-black text-on-background" id="planPriceDisplay">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            <span class="text-on-surface-variant text-sm">/month</span>
                        </div>
                        <p class="mt-3 text-sm text-on-surface-variant" id="planNameDisplay"><?php echo htmlspecialchars($selectedPlan['plan_name']); ?> Plan</p>
                    </div>
                    <div class="pt-4 border-t border-outline">
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-2">Billing Cycle</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button type="button" class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'monthly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                onclick="document.querySelector('input[name=\'billingCycle\']').value='monthly'; this.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary')); this.parentElement.querySelectorAll('button').forEach(b => b.classList.add('border-slate-200', 'text-on-surface-variant')); this.classList.remove('border-slate-200', 'text-on-surface-variant'); this.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');">
                                Monthly
                            </button>
                            <button type="button" class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'quarterly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                onclick="document.querySelector('input[name=\'billingCycle\']').value='quarterly'; this.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary')); this.parentElement.querySelectorAll('button').forEach(b => b.classList.add('border-slate-200', 'text-on-surface-variant')); this.classList.remove('border-slate-200', 'text-on-surface-variant'); this.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');">
                                Quarterly
                            </button>
                            <button type="button" class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'yearly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                onclick="document.querySelector('input[name=\'billingCycle\']').value='yearly'; this.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary')); this.parentElement.querySelectorAll('button').forEach(b => b.classList.add('border-slate-200', 'text-on-surface-variant')); this.classList.remove('border-slate-200', 'text-on-surface-variant'); this.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');">
                                Yearly
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Payment Section -->
                <section class="bg-white border border-slate-200 rounded-xl p-8 space-y-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary" data-icon="lock">lock</span>
                        <h2 class="text-xl font-bold tracking-tight">Complete Your Payment</h2>
                    </div>

                    <form class="space-y-6" method="post" action="">
                        <input type="hidden" name="processPayment" value="1" />
                        <input type="hidden" id="selectedPlanCode" name="selectedPlanCode" value="<?php echo htmlspecialchars($selectedPlan['plan_code']); ?>" />
                        <input type="hidden" name="billingCycle" value="<?php echo htmlspecialchars($billingCycle); ?>" />

                        <!-- Cardholder Name -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter">Cardholder Name</label>
                            <input type="text" name="cardholderName" required
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400"
                                placeholder="Full legal name" />
                        </div>

                        <div id="payment-error" class="text-red-600 text-sm"></div>

                        <div class="pt-4 border-t border-slate-200">
                            <button type="button" id="paymentButton"
                                class="w-full py-4 bg-primary text-white text-sm font-black uppercase tracking-widest rounded-lg shadow-lg hover:shadow-primary/20 hover:brightness-110 transition-all flex items-center justify-center gap-2">
                                Complete Payment with Paymongo
                                <span class="material-symbols-outlined" data-icon="arrow_forward">arrow_forward</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- Summary (RHS) -->
            <div class="lg:col-span-4">
                <aside class="sticky top-28 space-y-6">
                    <div class="bg-slate-900 text-white rounded-xl p-8 shadow-xl overflow-hidden relative">
                        <!-- Decorative element -->
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/20 rounded-full blur-3xl"></div>
                        <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary"
                                data-icon="receipt_long">receipt_long</span>
                            Order Summary
                        </h3>
                        <div class="space-y-4 relative z-10">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-bold text-white" id="orderSummaryPlanName"><?php echo htmlspecialchars($selectedPlan['plan_name']); ?> Plan</p>
                                    <p class="text-xs text-slate-400">Billing Cycle: <span class="capitalize font-semibold text-slate-300"><?php echo htmlspecialchars($billingCycle); ?></span></p>
                                </div>
                                <span class="text-sm font-bold" id="orderSummaryPrice">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm border-t border-slate-800 pt-4">
                                <span class="text-slate-400">Subtotal</span>
                                <span id="subtotalPrice">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Tax (0%)</span>
                                <span>₱0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Setup Fee</span>
                                <span class="text-primary-fixed-dim font-bold">WAIVED</span>
                            </div>
                            <div class="flex justify-between items-center pt-6 border-t border-slate-800">
                                <span class="text-lg font-black uppercase tracking-tight">Total</span>
                                <span class="text-2xl font-black text-primary-fixed-dim" id="totalPrice">₱<?php echo number_format($selectedPlan['monthly_price'], 2); ?></span>
                            </div>
                        </div>
                        <p
                            class="mt-6 text-[10px] text-center text-slate-500 uppercase tracking-widest leading-relaxed">
                            Secure Encrypted Payment. Your application will be reviewed within 1-2 business days.
                        </p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary text-lg"
                                    data-icon="verified_user">verified_user</span>
                            </div>
                            <p class="text-sm font-bold text-on-primary-container">Secure Guarantee</p>
                        </div>
                        <p class="text-xs text-on-surface-variant leading-relaxed">
                            All payment information is encrypted and secure. You can modify your plan or cancel anytime.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </main>
    <!-- Footer -->
    <footer
        class="bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center px-8 py-4 w-full mt-auto font-['Inter'] text-xs font-semibold">
        <div class="text-slate-500 dark:text-slate-500">© 2026 RapidRepairCo. Secure Encrypted Environment.
        </div>
        <div class="flex gap-6">
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors"
                href="#">Privacy Policy</a>
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors"
                href="#">Terms of Service</a>
            <a class="text-slate-500 dark:text-slate-500 hover:text-primary dark:hover:text-blue-400 transition-colors"
                href="#">Support</a>
        </div>
    </footer>
</body>

</html>