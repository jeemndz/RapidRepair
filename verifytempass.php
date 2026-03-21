<?php
session_start();
include "db.php";

function buildTenantDashboardUrl($loginSlug)
{
    $loginSlug = trim((string) $loginSlug);
    if ($loginSlug === '') {
        return 'dashboardadmin.php';
    }

    $baseDomain = trim((string) (getenv('TENANT_BASE_DOMAIN') ?: ''));
    if ($baseDomain !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $loginSlug . '.' . $baseDomain . '/dashboardadmin.php';
    }

    return 'dashboardadmin.php?shop=' . urlencode($loginSlug);
}

// Check if tenant is logged in and temp password exists
if (!isset($_SESSION['tenantID']) || !isset($_SESSION['temp_pass']) || !isset($_SESSION['verification_code'])) {
    header("Location: tenantlogin.php");
    exit;
}

$tenantID = $_SESSION['tenantID'];
$error = "";
$loginSlug = isset($_SESSION['login_slug']) ? (string) $_SESSION['login_slug'] : '';

if ($loginSlug === '') {
    $tenantQuery = mysqli_query($conn, "SELECT login_slug FROM owners WHERE tenantID='" . mysqli_real_escape_string($conn, (string) $tenantID) . "' LIMIT 1");
    if ($tenantQuery && ($tenantRow = mysqli_fetch_assoc($tenantQuery))) {
        $loginSlug = trim((string) ($tenantRow['login_slug'] ?? ''));
        if ($loginSlug !== '') {
            $_SESSION['login_slug'] = $loginSlug;
        }
    }
}

// Handle code verification
if (isset($_POST['verify'])) {
    $input_code = preg_replace('/\D/', '', (string) ($_POST['verification_code'] ?? ''));
    $expected_code = str_pad((string) $_SESSION['verification_code'], 6, '0', STR_PAD_LEFT);

    if (strlen($input_code) !== 6) {
        $error = "Please enter the 6-digit verification code.";
    } elseif ($input_code === $expected_code) {

        // Code is correct, update password in database
        $hashed_password = $_SESSION['temp_pass'];
        $update = mysqli_query($conn, "UPDATE owners SET password='$hashed_password', first_login=0 WHERE tenantID='$tenantID'");

        if ($update) {
            // Clear temp session variables
            unset($_SESSION['temp_pass']);
            unset($_SESSION['verification_code']);

            // Redirect to tenant-specific dashboard URL context
            header("Location: " . buildTenantDashboardUrl($loginSlug));
            exit;
        } else {
            $error = "Failed to update password. Try again.";
        }
    } else {
        $error = "Invalid verification code. Please check your email.";
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify Temporary Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "navy-dark": "#0f172a",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen">
    <div class="flex flex-col min-h-screen w-full">
        <!-- Top Navigation -->
        <header class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-dark px-6 md:px-10 py-3 sticky top-0 z-10">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center size-10 bg-primary rounded-lg text-white">
                    <span class="material-symbols-outlined">car_repair</span>
                </div>
                <h2 class="text-slate-900 dark:text-white text-lg font-bold">AutoFix Portal</h2>
            </div>
        </header>

        <!-- Main -->
        <main class="flex-1 flex flex-col items-center justify-center p-6 md:p-12">
            <div class="w-full max-w-2xl bg-white dark:bg-navy-dark rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="relative h-32 w-full bg-primary/10 flex flex-col justify-center px-8 border-b border-slate-100 dark:border-slate-800 overflow-hidden">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Verify Your New Password</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Enter the verification code sent to your email.</p>
                    <div class="absolute right-8 hidden sm:block">
                        <span class="material-symbols-outlined text-6xl text-primary/20">lock</span>
                    </div>
                </div>

                <div class="p-8">
                    <?php if ($error): ?>
                        <div class="mb-4 text-red-500 font-semibold"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <!-- Verification Code -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Verification Code</label>
                            <div>
                                <input type="hidden" id="verification_code" name="verification_code" required />
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-lg font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" />
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-lg font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" />
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-lg font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" />
                                    <span class="text-slate-400 font-bold px-0.5">-</span>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-lg font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" />
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-lg font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" />
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-lg font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" />
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Enter the 6-digit code sent to your email.</p>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex flex-col sm:flex-row items-center gap-4 pt-4">
                            <button type="submit" name="verify" class="w-full sm:flex-1 h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">verified</span> Verify
                            </button>
                            <a href="changetemppass.php" class="w-full sm:w-32 h-12 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-lg flex items-center justify-center">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const digitInputs = Array.from(document.querySelectorAll('.code-digit'));
            const hiddenCodeInput = document.getElementById('verification_code');
            const verifyForm = document.querySelector('form[method="POST"]');

            function syncCode() {
                hiddenCodeInput.value = digitInputs.map(function (el) {
                    return (el.value || '').replace(/\D/g, '').slice(0, 1);
                }).join('');
            }

            digitInputs.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    input.value = input.value.replace(/\D/g, '').slice(0, 1);
                    syncCode();

                    if (input.value !== '' && index < digitInputs.length - 1) {
                        digitInputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && input.value === '' && index > 0) {
                        digitInputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function (event) {
                    const pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
                    const digits = pasted.replace(/\D/g, '').slice(0, 6).split('');
                    if (digits.length === 0) {
                        return;
                    }

                    event.preventDefault();
                    digitInputs.forEach(function (el, i) {
                        el.value = digits[i] || '';
                    });
                    syncCode();

                    const targetIndex = Math.min(digits.length, digitInputs.length) - 1;
                    if (targetIndex >= 0) {
                        digitInputs[targetIndex].focus();
                    }
                });
            });

            if (digitInputs.length > 0) {
                digitInputs[0].focus();
            }

            verifyForm.addEventListener('submit', function (event) {
                syncCode();
                if (hiddenCodeInput.value.length !== 6) {
                    event.preventDefault();
                    alert('Please enter the complete 6-digit code.');
                }
            });
        })();
    </script>
</body>
</html>