<?php
session_start();
include __DIR__ . "/../db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';
require __DIR__ . '/../PHPMailer/src/Exception.php';

/* =========================================================
   LOAD .env FILE
   ========================================================= */
function loadEnvFile($path)
{
    if (!file_exists($path)) {
        return;
    }

    $envLines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($envLines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/../.env');

/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */
function envValue($key, $default = '')
{
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

function writeMailLog($message)
{
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/mail_error.log';
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $message\n", 3, $logFile);
}

function sendVerificationEmail($recipientEmail, $shopName, $verificationCode)
{
    $smtpDebug = (int) envValue('SMTP_DEBUG', 0);

    $smtpConfigs = [
        [
            'label' => 'PRIMARY SMTP',
            'host' => envValue('SMTP_HOST', 'smtp.gmail.com'),
            'port' => (int) envValue('SMTP_PORT', 587),
            'encryption' => strtolower(envValue('SMTP_ENCRYPTION', 'tls')),
            'username' => envValue('SMTP_USERNAME'),
            'password' => envValue('SMTP_PASSWORD'),
            'from_address' => envValue('MAIL_FROM_ADDRESS', envValue('SMTP_USERNAME')),
            'from_name' => envValue('MAIL_FROM_NAME', 'Rapid Repair Admin'),
            'reply_to' => envValue('MAIL_REPLY_TO', ''),
            'reply_to_name' => envValue('MAIL_REPLY_TO_NAME', 'Rapid Repair Support'),
        ],
        [
            'label' => 'FALLBACK SMTP',
            'host' => envValue('SMTP_FALLBACK_HOST', 'smtp.gmail.com'),
            'port' => (int) envValue('SMTP_FALLBACK_PORT', 587),
            'encryption' => strtolower(envValue('SMTP_FALLBACK_ENCRYPTION', 'tls')),
            'username' => envValue('SMTP_FALLBACK_USERNAME'),
            'password' => envValue('SMTP_FALLBACK_PASSWORD'),
            'from_address' => envValue('SMTP_FALLBACK_FROM_ADDRESS', envValue('SMTP_FALLBACK_USERNAME')),
            'from_name' => envValue('SMTP_FALLBACK_FROM_NAME', 'Rapid Repair Admin'),
            'reply_to' => envValue('SMTP_FALLBACK_REPLY_TO', ''),
            'reply_to_name' => envValue('SMTP_FALLBACK_REPLY_TO_NAME', 'Rapid Repair Support'),
        ],
    ];

    $lastError = '';

    foreach ($smtpConfigs as $config) {
        if (empty($config['username']) || empty($config['password'])) {
            writeMailLog($config['label'] . ' skipped: missing SMTP username or password.');
            continue;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->SMTPDebug = $smtpDebug;
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];

            if ($config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $config['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['from_address'], $config['from_name']);
            $mail->addAddress($recipientEmail, $shopName ?: $recipientEmail);

            if (!empty($config['reply_to'])) {
                $mail->addReplyTo($config['reply_to'], $config['reply_to_name']);
            }

            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Temporary Password';
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; color: #111827; line-height: 1.6;">
                    <h2 style="color: #1152d4; margin-bottom: 8px;">Rapid Repair Password Verification</h2>
                    <p>Hello ' . htmlspecialchars($shopName ?: 'User') . ',</p>
                    <p>You requested to change your temporary password.</p>
                    <p>Your verification code is:</p>
                    <div style="font-size: 28px; font-weight: bold; letter-spacing: 4px; background: #f3f4f6; padding: 14px 18px; border-radius: 8px; display: inline-block;">
                        ' . htmlspecialchars($verificationCode) . '
                    </div>
                    <p style="margin-top: 20px;">If you did not request this, please ignore this email.</p>
                    <p>Thank you,<br>Rapid Repair Admin</p>
                </div>
            ';
            $mail->AltBody = "Hello " . ($shopName ?: 'User') . ",\n\nYour verification code is: $verificationCode\n\nIf you did not request this, please ignore this email.\n\nRapid Repair Admin";

            $mail->send();
            writeMailLog($config['label'] . ' success: verification email sent to ' . $recipientEmail);
            return [
                'success' => true,
                'message' => 'Verification email sent successfully.',
                'used' => $config['label'],
            ];
        } catch (Exception $e) {
            $lastError = $mail->ErrorInfo ?: $e->getMessage();
            writeMailLog($config['label'] . ' failed for ' . $recipientEmail . ': ' . $lastError);
        }
    }

    return [
        'success' => false,
        'message' => 'Failed to send verification email. Last error: ' . $lastError,
        'used' => '',
    ];
}

/* =========================================================
   CHECK LOGIN SESSION
   ========================================================= */
if (!isset($_SESSION['tenantID'])) {
    header("Location: tenantlogin.php");
    exit;
}

$tenantID = $_SESSION['tenantID'];
$email = '';
$shopName = '';
$login_slug = '';
$error = '';
$success = '';

/* =========================================================
   GET TENANT DETAILS SAFELY
   ========================================================= */
$stmt = $conn->prepare("SELECT email, shopName, login_slug FROM owners WHERE tenantID = ? LIMIT 1");
$stmt->bind_param("s", $tenantID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $email = trim($row['email'] ?? '');
    $shopName = trim($row['shopName'] ?? '');
    $login_slug = trim($row['login_slug'] ?? '');
}
$stmt->close();

/* =========================================================
   HANDLE PASSWORD CHANGE FORM
   ========================================================= */
if (isset($_POST['submit'])) {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Your account has no valid email address. Please update the tenant email first.";
    } elseif ($new_password === '' || $confirm_password === '') {
        $error = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $verification_code = random_int(100000, 999999);

        $_SESSION['temp_pass'] = password_hash($new_password, PASSWORD_DEFAULT);
        $_SESSION['verification_code'] = (string) $verification_code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['verification_expires'] = time() + (10 * 60); // 10 minutes

        $sendResult = sendVerificationEmail($email, $shopName, $verification_code);

        if ($sendResult['success']) {
            header("Location: verifytempass.php");
            exit;
        } else {
            unset($_SESSION['temp_pass'], $_SESSION['verification_code'], $_SESSION['verification_email'], $_SESSION['verification_expires']);
            $error = $sendResult['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Change Password - <?= htmlspecialchars($shopName ?: 'Rapid Repair') ?></title>
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
        <header class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-dark px-6 md:px-10 py-3 sticky top-0 z-10">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center size-10 bg-primary rounded-lg text-white">
                    <span class="material-symbols-outlined">car_repair</span>
                </div>
                <h2 class="text-slate-900 dark:text-white text-lg font-bold">
                    <?= htmlspecialchars($shopName ?: 'Rapid Repair') ?>
                </h2>
            </div>
        </header>

        <main class="flex-1 flex flex-col items-center justify-center p-6 md:p-12">
            <div class="w-full max-w-2xl bg-white dark:bg-navy-dark rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="relative h-32 w-full bg-primary/10 flex items-center px-8 border-b border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Change Your Password</h1>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">A verification code will be sent to your registered email.</p>
                    </div>
                    <div class="absolute right-8 hidden sm:block">
                        <span class="material-symbols-outlined text-6xl text-primary/20">lock_reset</span>
                    </div>
                </div>

                <div class="p-8">
                    <?php if ($error): ?>
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 font-semibold">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 font-semibold">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Email Address</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400">mail</span>
                                <input type="email" value="<?= htmlspecialchars($email) ?>" readonly class="w-full pl-12 pr-4 h-12 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 cursor-not-allowed focus:ring-0 focus:border-slate-200" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">New Password</label>
                            <div class="relative flex items-center">
                                <span class="absolute left-4 material-symbols-outlined text-slate-400">key</span>
                                <input type="password" name="new_password" placeholder="Min. 8 characters" required minlength="8" class="w-full pl-12 pr-12 h-12 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Confirm New Password</label>
                            <div class="relative flex items-center">
                                <span class="absolute left-4 material-symbols-outlined text-slate-400">verified_user</span>
                                <input type="password" name="confirm_password" placeholder="Re-type your new password" required minlength="8" class="w-full pl-12 pr-12 h-12 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" />
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-4 pt-4">
                            <button type="submit" name="submit" class="w-full sm:flex-1 h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">mail</span>
                                Send Verification Code
                            </button>
                            <a href="tenantlogin.php?shop=<?= urlencode($login_slug) ?>" class="w-full sm:w-32 h-12 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-lg flex items-center justify-center">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="px-8 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Password Requirements</h3>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-green-500">check_circle</span> At least 8 characters
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-slate-300">circle</span> Use a password you do not use on other accounts
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-slate-300">circle</span> Avoid using your shop name
                        </li>
                        <li class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="material-symbols-outlined text-[14px] text-slate-300">circle</span> Verification code expires in 10 minutes
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
