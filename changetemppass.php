<?php
session_start();
include "db.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \"'");
            if (!getenv($key)) {
                putenv($key . '=' . $value);
            }
        }
    }
}

// Check if tenant is logged in
if (!isset($_SESSION['tenantID'])) {
    header("Location: tenantlogin.php");
    exit;
}

$tenantID = $_SESSION['tenantID'];
$email = "";
$shopName = "";
$error = "";

// Get tenant email and shop name
$query = mysqli_query($conn, "SELECT email, shopName FROM owners WHERE tenantID='$tenantID'");
if ($row = mysqli_fetch_assoc($query)) {
    $email = $row['email'];
    $shopName = $row['shopName'];
}

// Handle password change submission
if (isset($_POST['submit'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else if (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        // Generate verification code and store temp hashed password in session
        $verification_code = rand(100000, 999999);
        $_SESSION['temp_pass'] = password_hash($new_password, PASSWORD_DEFAULT);
        $_SESSION['verification_code'] = $verification_code;

        // Send verification email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
            $smtpEncryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));
            $smtpUsername = getenv('SMTP_USERNAME') ?: 'rapidrepair224@gmail.com';
            $smtpPassword = getenv('SMTP_PASSWORD') ?: 'gabd xcqy gbgq rtwj';
            $mailFromAddress = getenv('MAIL_FROM_ADDRESS') ?: $smtpUsername;
            $mailFromName = getenv('MAIL_FROM_NAME') ?: 'Rapid Repair Portal';

            $mail->isSMTP();
            $mail->SMTPDebug = 0; // change to 2 for debugging
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = ($smtpEncryption === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;

            $mail->setFrom($mailFromAddress, $mailFromName);
            $mail->addAddress($email, $shopName);

            $mail->isHTML(false);
            $mail->Subject = 'Verify Your Temporary Password';
            $mail->Body = "Hello $shopName,\n\nYour verification code is: $verification_code\n\nIf you did not request this, ignore this email.";

            $mail->send();
            header("Location: verifytempass.php");
            exit;

        } catch (Exception $e) {
            $error = "Failed to send verification email. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Change Password - <?= htmlspecialchars($shopName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
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
        <header
            class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-dark px-6 md:px-10 py-3 sticky top-0 z-10">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center size-10 bg-primary rounded-lg text-white">
                    <span class="material-symbols-outlined">car_repair</span>
                </div>
                <h2 class="text-slate-900 dark:text-white text-lg font-bold"><?= htmlspecialchars($shopName) ?></h2>
            </div>
        </header>

        <!-- Main -->
        <main class="flex-1 flex flex-col items-center justify-center p-6 md:p-12">
            <div
                class="w-full max-w-2xl bg-white dark:bg-navy-dark rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div
                    class="relative h-32 w-full bg-primary/10 flex items-center px-8 border-b border-slate-100 dark:border-slate-800 overflow-hidden">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Change Your Password</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Keep your account secure with a strong password.
                    </p>
                    <div class="absolute right-8 hidden sm:block">
                        <span class="material-symbols-outlined text-6xl text-primary/20">lock_reset</span>
                    </div>
                </div>

                <div class="p-8">
                    <?php if ($error): ?>
                        <div class="mb-4 text-red-500 font-semibold"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <!-- Email -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Email
                                Address</label>
                            <div class="relative">
                                <span
                                    class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400">mail</span>
                                <input type="email" value="<?= htmlspecialchars($email) ?>" readonly
                                    class="w-full pl-12 pr-4 h-12 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 cursor-not-allowed focus:ring-0 focus:border-slate-200" />
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">New Password</label>
                            <div class="relative flex items-center">
                                <span class="absolute left-4 material-symbols-outlined text-slate-400">key</span>
                                <input type="password" name="new_password" placeholder="Min. 8 characters"
                                    class="w-full pl-12 pr-12 h-12 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" />
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Confirm New
                                Password</label>
                            <div class="relative flex items-center">
                                <span
                                    class="absolute left-4 material-symbols-outlined text-slate-400">verified_user</span>
                                <input type="password" name="confirm_password" placeholder="Re-type your new password"
                                    class="w-full pl-12 pr-12 h-12 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" />
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex flex-col sm:flex-row items-center gap-4 pt-4">
                            <button type="submit" name="submit"
                                class="w-full sm:flex-1 h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">save</span> Save Changes
                            </button>
                            <a href="dashboardadmin.php"
                                class="w-full sm:w-32 h-12 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-lg flex items-center justify-center">Cancel</a>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                        Password Requirements</h3>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-green-500">check_circle</span> At
                            least 8 characters
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-slate-300">circle</span> One special
                            character
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-slate-300">circle</span> One
                            uppercase letter
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-slate-300">circle</span> One number
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>

</html>