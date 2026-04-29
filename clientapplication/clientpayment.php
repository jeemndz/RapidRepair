<?php
session_start();

include __DIR__ . "/../db.php";
include __DIR__ . "/client_helpers.php";

$errors = [];
$successMessage = "";

$tenantID = isset($_GET['tenantID']) ? (int) trim($_GET['tenantID']) : 0;
$selectedPlanCode = isset($_GET['plan']) ? strtolower(trim($_GET['plan'])) : '';
$billingCycle = isset($_GET['billingCycle']) ? strtolower(trim($_GET['billingCycle'])) : 'monthly';

$tenant = null;

if ($tenantID !== 0) {
    $tenant = getTenantDetails($conn, $tenantID);

    if (!$tenant) {
        $errors[] = 'Tenant not found. Please complete registration first.';
    }
} else {
    $errors[] = 'Invalid tenant information. Please register first.';
}

$subscriptionPlans = loadSubscriptionPlans($conn);

if (!$subscriptionPlans || count($subscriptionPlans) === 0) {
    $errors[] = 'No subscription plans found.';
    $subscriptionPlans = [];
}

$selectedPlan = null;

if ($selectedPlanCode !== '') {
    $selectedPlan = getPlanByCode($conn, $selectedPlanCode);
}

if (!$selectedPlan && count($subscriptionPlans) > 0) {
    $selectedPlan = $subscriptionPlans[0];
}

function calculatePlanAmount($monthlyPrice, $billingCycle)
{
    $amount = (float) $monthlyPrice;

    if ($billingCycle === 'quarterly') {
        $amount *= 3;
    } elseif ($billingCycle === 'yearly') {
        $amount *= 12;
    }

    return $amount;
}

$displayAmount = $selectedPlan ? calculatePlanAmount($selectedPlan['monthly_price'], $billingCycle) : 0;
$checkoutAmountCentavos = (int) round($displayAmount * 100);
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Complete Payment | RapidRepairCo.</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet" />

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary-fixed-dim": "#bfdbfe",
                        "primary-fixed": "#dbeafe",
                        "outline": "#e2e8f0",
                        "on-primary": "#ffffff",
                        "background": "#f6f6f8",
                        "surface": "#f6f6f8",
                        "on-primary-container": "#1152d4",
                        "on-background": "#0f172a",
                        "primary-container": "#eef2ff",
                        "surface-tint": "#1152d4",
                        "primary": "#1152d4",
                        "on-surface": "#0f172a",
                        "on-surface-variant": "#64748b"
                    },
                    borderRadius: {
                        DEFAULT: "0.125rem",
                        lg: "0.25rem",
                        xl: "0.5rem",
                        full: "0.75rem"
                    },
                    fontFamily: {
                        headline: ["Inter"],
                        body: ["Inter"],
                        label: ["Inter"]
                    }
                }
            }
        }
    </script>

    <script>
        const plansData = <?php echo json_encode(array_map(function ($plan) {
            return [
                'plan_id' => $plan['plan_id'],
                'plan_code' => $plan['plan_code'],
                'plan_name' => $plan['plan_name'],
                'monthly_price' => (float) $plan['monthly_price'],
                'description' => $plan['description'] ?? ''
            ];
        }, $subscriptionPlans)); ?>;

        let currentBillingCycle = "<?php echo htmlspecialchars($billingCycle); ?>";

        function calculateAmount(monthlyPrice, billingCycle) {
            let amount = parseFloat(monthlyPrice);

            if (billingCycle === 'quarterly') {
                amount *= 3;
            } else if (billingCycle === 'yearly') {
                amount *= 12;
            }

            return amount;
        }

        function formatPeso(amount) {
            return '₱' + amount.toFixed(2);
        }

        function updateSelectedPlan(planCode) {
            const plan = plansData.find(p => p.plan_code.toLowerCase() === planCode.toLowerCase());
            if (!plan) return;

            const amount = calculateAmount(plan.monthly_price, currentBillingCycle);

            document.getElementById('selectedPlanCode').value = plan.plan_code;
            document.getElementById('checkoutPlanName').value = plan.plan_name + ' Plan - ' + currentBillingCycle.charAt(0).toUpperCase() + currentBillingCycle.slice(1) + ' Billing';
            document.getElementById('checkoutAmount').value = Math.round(amount * 100);

            document.getElementById('planCodeDisplay').textContent = plan.plan_code;
            document.getElementById('planNameDisplay').textContent = plan.plan_name + ' Plan';
            document.getElementById('planPriceDisplay').textContent = formatPeso(amount);
            document.getElementById('orderSummaryPlanName').textContent = plan.plan_name + ' Plan';
            document.getElementById('orderSummaryPrice').textContent = formatPeso(amount);
            document.getElementById('subtotalPrice').textContent = formatPeso(amount);
            document.getElementById('totalPrice').textContent = formatPeso(amount);
            document.getElementById('billingCycleDisplay').textContent = currentBillingCycle;

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

        function updateBillingCycle(cycle, button) {
            currentBillingCycle = cycle;

            document.getElementById('billingCycleInput').value = cycle;

            button.parentElement.querySelectorAll('button').forEach(b => {
                b.classList.remove('border-primary', 'bg-primary-fixed', 'text-primary');
                b.classList.add('border-slate-200', 'text-on-surface-variant');
            });

            button.classList.remove('border-slate-200', 'text-on-surface-variant');
            button.classList.add('border-primary', 'bg-primary-fixed', 'text-primary');

            const selectedPlanCode = document.getElementById('selectedPlanCode').value;
            updateSelectedPlan(selectedPlanCode);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const paymentForm = document.getElementById('paymentForm');
            const paymentButton = document.getElementById('paymentButton');

            if (paymentForm && paymentButton) {
                paymentForm.addEventListener('submit', function () {
                    paymentButton.disabled = true;
                    paymentButton.innerHTML = 'Processing...';
                });
            }
        });
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

<body class="bg-background text-on-background min-h-screen flex flex-col">

    <nav
        class="bg-white border-b border-slate-200 shadow-sm flex justify-between items-center w-full px-8 h-16 max-w-full font-['Inter'] tracking-tight fixed top-0 z-50">
        <div class="text-xl font-black text-primary uppercase">RapidRepairCo.</div>

        <div class="hidden md:flex items-center space-x-8">
            <span class="text-slate-600 font-medium">Payment Setup</span>
        </div>

        <div class="flex items-center gap-4">
            <a href="clientlanding.php" class="text-slate-600 font-medium hover:text-primary transition-colors">Back</a>
        </div>
    </nav>

    <main class="mt-16 flex-grow flex flex-col items-center px-4 md:px-8 py-12 max-w-7xl mx-auto w-full">

        <header class="w-full mb-12 text-center md:text-left">
            <h1 class="text-[30px] font-black text-on-background tracking-tight mb-2">Complete Your Payment</h1>
            <p class="text-on-surface-variant text-sm font-medium">
                Select your subscription plan and proceed to secure PayMongo checkout.
            </p>
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
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($selectedPlan): ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 w-full">

                <div class="lg:col-span-8 space-y-8">

                    <section class="space-y-6">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined text-primary">layers</span>
                            <h2 class="text-2xl font-bold tracking-tight">Choose Your Plan</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($subscriptionPlans as $plan): ?>
                                <div class="plan-card p-6 border-2 rounded-lg transition-all cursor-pointer <?php echo strtolower($selectedPlan['plan_code']) === strtolower($plan['plan_code']) ? 'border-primary bg-primary-fixed' : 'border-slate-200 hover:border-primary'; ?>"
                                    data-plan-card="<?php echo htmlspecialchars($plan['plan_code']); ?>"
                                    onclick="updateSelectedPlan('<?php echo htmlspecialchars($plan['plan_code']); ?>')">

                                    <div class="mb-3">
                                        <span class="text-xs font-bold uppercase tracking-widest text-primary">
                                            <?php echo htmlspecialchars($plan['plan_code']); ?>
                                        </span>

                                        <h3 class="text-lg font-bold mt-2">
                                            <?php echo htmlspecialchars($plan['plan_name']); ?>
                                        </h3>
                                    </div>

                                    <div class="mb-4">
                                        <span class="text-2xl font-black">
                                            ₱<?php echo number_format($plan['monthly_price'], 2); ?>
                                        </span>
                                        <span class="text-sm text-on-surface-variant">/month</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="bg-white border-2 border-primary rounded-xl p-8 shadow-md">
                        <h3 class="text-lg font-bold mb-4">Your Selected Plan</h3>

                        <div class="mb-6">
                            <span class="text-xs font-bold uppercase tracking-widest text-primary" id="planCodeDisplay">
                                <?php echo htmlspecialchars($selectedPlan['plan_code']); ?>
                            </span>

                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="text-4xl font-black text-on-background" id="planPriceDisplay">
                                    ₱<?php echo number_format($displayAmount, 2); ?>
                                </span>
                                <span class="text-on-surface-variant text-sm">
                                    /<?php echo htmlspecialchars($billingCycle); ?>
                                </span>
                            </div>

                            <p class="mt-3 text-sm text-on-surface-variant" id="planNameDisplay">
                                <?php echo htmlspecialchars($selectedPlan['plan_name']); ?> Plan
                            </p>
                        </div>

                        <div class="pt-4 border-t border-outline">
                            <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-tighter mb-2">
                                Billing Cycle
                            </label>

                            <div class="grid grid-cols-3 gap-3">
                                <button type="button"
                                    class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'monthly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                    onclick="updateBillingCycle('monthly', this)">
                                    Monthly
                                </button>

                                <button type="button"
                                    class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'quarterly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                    onclick="updateBillingCycle('quarterly', this)">
                                    Quarterly
                                </button>

                                <button type="button"
                                    class="py-2 px-3 rounded-lg border-2 transition-all text-sm font-semibold <?php echo $billingCycle === 'yearly' ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-on-surface-variant hover:border-primary'; ?>"
                                    onclick="updateBillingCycle('yearly', this)">
                                    Yearly
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white border border-slate-200 rounded-xl p-8 space-y-6">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined text-primary">lock</span>
                            <h2 class="text-xl font-bold tracking-tight">Complete Your Payment</h2>
                        </div>

                        <form id="paymentForm" class="space-y-6" method="post" action="paymongo/create_checkout.php">

                            <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($tenantID); ?>" />

                            <input type="hidden" id="selectedPlanCode" name="selectedPlanCode"
                                value="<?php echo htmlspecialchars($selectedPlan['plan_code']); ?>" />

                            <input type="hidden" id="checkoutPlanName" name="plan_name"
                                value="<?php echo htmlspecialchars($selectedPlan['plan_name'] . ' Plan - ' . ucfirst($billingCycle) . ' Billing'); ?>" />

                            <input type="hidden" id="billingCycleInput" name="billingCycle"
                                value="<?php echo htmlspecialchars($billingCycle); ?>" />

                            <input type="hidden" id="checkoutAmount" name="amount"
                                value="<?php echo htmlspecialchars($checkoutAmountCentavos); ?>" />

                            <input type="hidden" name="name"
                                value="<?php echo htmlspecialchars($tenant['owner_name'] ?? $tenant['shop_name'] ?? 'Customer'); ?>" />

                            <input type="hidden" name="email"
                                value="<?php echo htmlspecialchars($tenant['email'] ?? 'test@example.com'); ?>" />

                            <input type="hidden" name="phone"
                                value="<?php echo htmlspecialchars($tenant['contact_number'] ?? '09171234567'); ?>" />

                            <div id="payment-error" class="text-red-600 text-sm"></div>

                            <div style="margin-bottom:20px;">

                                <h3 style="margin-bottom:10px;">Preview Payment Methods</h3>

                                <div style="display:flex; gap:20px; justify-content:center; flex-wrap:wrap;">

                                    <!-- GCash -->
                                    <div style="text-align:center;">
                                        <p style="font-weight:bold;">GCash</p>
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=GCash-Test"
                                            style="border:1px solid #ddd; padding:8px; border-radius:10px;">
                                    </div>

                                    <!-- PayMaya -->
                                    <div style="text-align:center;">
                                        <p style="font-weight:bold;">PayMaya</p>
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=PayMaya-Test"
                                            style="border:1px solid #ddd; padding:8px; border-radius:10px;">
                                    </div>

                                    <!-- GrabPay -->
                                    <div style="text-align:center;">
                                        <p style="font-weight:bold;">GrabPay</p>
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=GrabPay-Test"
                                            style="border:1px solid #ddd; padding:8px; border-radius:10px;">
                                    </div>

                                </div>

                                <p style="font-size:12px; color:#777; margin-top:10px; text-align:center;">
                                    Preview only. Actual payment happens securely via PayMongo.
                                </p>

                            </div>


                            <div class="pt-4 border-t border-slate-200">
                                <button type="submit" id="paymentButton"
                                    class="w-full py-4 bg-primary text-white text-sm font-black uppercase tracking-widest rounded-lg shadow-lg hover:shadow-primary/20 hover:brightness-110 transition-all flex items-center justify-center gap-2">
                                    Complete Payment with PayMongo
                                    <span class="material-symbols-outlined">arrow_forward</span>
                                </button>
                            </div>
                        </form>
                    </section>
                </div>

                <div class="lg:col-span-4">
                    <aside class="sticky top-28 space-y-6">
                        <div class="bg-slate-900 text-white rounded-xl p-8 shadow-xl overflow-hidden relative">
                            <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/20 rounded-full blur-3xl"></div>

                            <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">receipt_long</span>
                                Order Summary
                            </h3>

                            <div class="space-y-4 relative z-10">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-bold text-white" id="orderSummaryPlanName">
                                            <?php echo htmlspecialchars($selectedPlan['plan_name']); ?> Plan
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            Billing Cycle:
                                            <span class="capitalize font-semibold text-slate-300" id="billingCycleDisplay">
                                                <?php echo htmlspecialchars($billingCycle); ?>
                                            </span>
                                        </p>
                                    </div>

                                    <span class="text-sm font-bold" id="orderSummaryPrice">
                                        ₱<?php echo number_format($displayAmount, 2); ?>
                                    </span>
                                </div>

                                <div class="flex justify-between items-center text-sm border-t border-slate-800 pt-4">
                                    <span class="text-slate-400">Subtotal</span>
                                    <span id="subtotalPrice">₱<?php echo number_format($displayAmount, 2); ?></span>
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
                                    <span class="text-2xl font-black text-primary-fixed-dim" id="totalPrice">
                                        ₱<?php echo number_format($displayAmount, 2); ?>
                                    </span>
                                </div>
                            </div>

                            <p
                                class="mt-6 text-[10px] text-center text-slate-500 uppercase tracking-widest leading-relaxed">
                                Secure Encrypted Payment. You will be redirected to PayMongo Checkout.
                            </p>
                        </div>

                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-6">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary text-lg">verified_user</span>
                                </div>

                                <p class="text-sm font-bold text-on-primary-container">Secure Guarantee</p>
                            </div>

                            <p class="text-xs text-on-surface-variant leading-relaxed">
                                All payment information is handled securely through PayMongo.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <footer
        class="bg-slate-50 border-t border-slate-200 flex justify-between items-center px-8 py-4 w-full mt-auto font-['Inter'] text-xs font-semibold">
        <div class="text-slate-500">© 2026 RapidRepairCo. Secure Encrypted Environment.</div>

        <div class="flex gap-6">
            <a class="text-slate-500 hover:text-primary transition-colors" href="#">Privacy Policy</a>
            <a class="text-slate-500 hover:text-primary transition-colors" href="#">Terms of Service</a>
            <a class="text-slate-500 hover:text-primary transition-colors" href="#">Support</a>
        </div>
    </footer>

</body>

</html>