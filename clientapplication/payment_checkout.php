<?php
session_start();
include __DIR__ . "/../db.php";
include __DIR__ . "/paymongo/paymongo_helper.php";
include __DIR__ . "/client_helpers.php";

$errors = [];
$tenantID = isset($_GET['tenantID']) ? (int) trim($_GET['tenantID']) : 0;
$paymentIntentID = isset($_GET['paymentIntentID']) ? trim($_GET['paymentIntentID']) : '';

// Verify session data exists
if (empty($_SESSION['paymongo_payment_intent_id']) || empty($paymentIntentID)) {
    header('Location: clientpayment.php');
    exit();
}

$publicKey = $_SESSION['paymongo_public_key'] ?? '';
$cardholderName = $_SESSION['paymongo_cardholder'] ?? '';
$amount = $_SESSION['paymongo_amount'] ?? 0;
$billingCycle = $_SESSION['paymongo_billing_cycle'] ?? 'monthly';

// Verify tenant exists
$tenant = null;
if ($tenantID !== 0) {
    $tenant = getTenantDetails($conn, $tenantID);
    if (!$tenant) {
        $errors[] = 'Tenant not found.';
    }
} else {
    $errors[] = 'Invalid tenant information.';
}

// Get selected plan
$selectedPlan = null;
$query = "SELECT * FROM subscription_plans WHERE monthly_price > 0 LIMIT 1";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    $selectedPlan = mysqli_fetch_assoc($result);
}

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Payment Checkout | RapidRepairCo.</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "on-background": "#0f172a",
                        "surface": "#f6f6f8",
                        "background": "#f6f6f8",
                        "error": "#ef4444",
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- TopNavBar -->
    <nav class="bg-white border-b border-slate-200 shadow-sm flex justify-between items-center w-full px-8 h-16 fixed top-0 z-50">
        <div class="text-xl font-black text-primary uppercase">RapidRepairCo.</div>
        <div class="flex items-center gap-4">
            <a href="clientpayment.php?tenantID=<?php echo $tenantID; ?>" class="text-slate-600 font-medium hover:text-primary transition-colors">Back</a>
        </div>
    </nav>

    <main class="mt-16 flex-grow flex flex-col items-center px-4 md:px-8 py-12 max-w-4xl mx-auto w-full">
        <header class="w-full mb-12 text-center">
            <h1 class="text-[30px] font-black text-on-background tracking-tight mb-2">Secure Payment</h1>
            <p class="text-slate-600 text-sm font-medium">Enter your card details to complete your subscription</p>
        </header>

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

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 w-full">
            <!-- Payment Form -->
            <div class="lg:col-span-8">
                <section class="bg-white border border-slate-200 rounded-xl p-8 space-y-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary">payment</span>
                        <h2 class="text-xl font-bold tracking-tight">Payment Method</h2>
                    </div>

                    <form id="paymentForm" class="space-y-6" method="post" action="">
                        <!-- Card Number -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-tighter">Card Number</label>
                            <input type="text" id="cardNumber" name="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400" required />
                        </div>

                        <!-- Expiry and CVV -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-tighter">Expiry Date</label>
                                <input type="text" id="expiryDate" name="expiryDate" placeholder="MM/YY" maxlength="5"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400" required />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-tighter">CVV</label>
                                <input type="text" id="cvv" name="cvv" placeholder="***" maxlength="4"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400" required />
                            </div>
                        </div>

                        <!-- Cardholder Name -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-tighter">Cardholder Name</label>
                            <input type="text" id="cardholderName" name="cardholderName" value="<?php echo htmlspecialchars($cardholderName); ?>" readonly
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm bg-slate-100 opacity-75" />
                        </div>

                        <div id="payment-error" class="text-red-600 text-sm"></div>

                        <div class="pt-4 border-t border-slate-200">
                            <button type="button" id="paymentButton"
                                class="w-full py-4 bg-primary text-white text-sm font-black uppercase tracking-widest rounded-lg shadow-lg hover:shadow-primary/20 hover:brightness-110 transition-all flex items-center justify-center gap-2">
                                Complete Payment
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- Summary -->
            <div class="lg:col-span-4">
                <aside class="sticky top-28">
                    <div class="bg-slate-900 text-white rounded-xl p-8 shadow-xl overflow-hidden relative">
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/20 rounded-full blur-3xl"></div>
                        
                        <h3 class="text-lg font-bold mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">receipt_long</span>
                            Order Summary
                        </h3>

                        <div class="space-y-4 relative z-10">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($selectedPlan['plan_name'] ?? ''); ?> Plan</p>
                                    <p class="text-xs text-slate-400">Billing: <span class="capitalize font-semibold text-slate-300"><?php echo htmlspecialchars($billingCycle); ?></span></p>
                                </div>
                                <span class="text-sm font-bold">₱<?php echo number_format($amount, 2); ?></span>
                            </div>

                            <div class="flex justify-between items-center text-sm border-t border-slate-800 pt-4">
                                <span class="text-slate-400">Subtotal</span>
                                <span>₱<?php echo number_format($amount, 2); ?></span>
                            </div>

                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Tax</span>
                                <span>₱0.00</span>
                            </div>

                            <div class="flex justify-between items-center pt-6 border-t border-slate-800">
                                <span class="text-lg font-black uppercase tracking-tight">Total</span>
                                <span class="text-2xl font-black text-blue-300">₱<?php echo number_format($amount, 2); ?></span>
                            </div>
                        </div>

                        <p class="mt-6 text-[10px] text-center text-slate-500 uppercase tracking-widest leading-relaxed">
                            Secure encrypted payment powered by Paymongo
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <script>
        // Format card number
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^\d]/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });

        // Format expiry date
        document.getElementById('expiryDate').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Format CVV
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });

        // Handle payment submission
        document.getElementById('paymentButton').addEventListener('click', async function(e) {
            e.preventDefault();

            const cardNumber = document.getElementById('cardNumber').value.replace(/\s+/g, '');
            const expiryDate = document.getElementById('expiryDate').value;
            const cvv = document.getElementById('cvv').value;
            const errorDiv = document.getElementById('payment-error');

            // Validation
            if (!cardNumber || cardNumber.length < 13) {
                errorDiv.textContent = 'Please enter a valid card number';
                return;
            }

            if (!expiryDate || !expiryDate.match(/^\d{2}\/\d{2}$/)) {
                errorDiv.textContent = 'Please enter expiry date in MM/YY format';
                return;
            }

            if (!cvv || cvv.length < 3) {
                errorDiv.textContent = 'Please enter a valid CVV';
                return;
            }

            // Disable button
            this.disabled = true;
            this.textContent = 'Processing Payment...';
            errorDiv.textContent = '';

            try {
                // Send payment to server
                const response = await fetch('payment_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tenantID: <?php echo $tenantID; ?>,
                        paymentIntentID: '<?php echo htmlspecialchars($paymentIntentID); ?>',
                        cardNumber: cardNumber,
                        expiryDate: expiryDate,
                        cvv: cvv,
                        cardholderName: '<?php echo htmlspecialchars($cardholderName); ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Redirect to success page
                    window.location.href = 'payment_success.php?tenantID=<?php echo $tenantID; ?>&paymentIntentID=' + encodeURIComponent(result.paymentIntentID);
                } else {
                    errorDiv.textContent = result.error || 'Payment failed. Please try again.';
                    this.disabled = false;
                    this.textContent = 'Complete Payment';
                }
            } catch (error) {
                console.error('Error:', error);
                errorDiv.textContent = 'An error occurred. Please try again.';
                this.disabled = false;
                this.textContent = 'Complete Payment';
            }
        });
    </script>
</body>
</html>
