<?php
session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../PHPMailer/src/SMTP.php";
require_once __DIR__ . "/../PHPMailer/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate the correct base URL for reset links
 */
function getResetLinkBaseUrl() {
    $currentHost = $_SERVER['HTTP_HOST'];
    
    if (defined('PRODUCTION_DOMAIN') && PRODUCTION_DOMAIN && (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false)) {
        return 'https://' . PRODUCTION_DOMAIN;
    }
    
    return 'https://' . $currentHost;
}

$message = "";
$messageType = "";

// Ensure reset columns exist in client_info table
function ensureClientResetColumnsExist()
{
    global $conn;
    
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_info' AND COLUMN_NAME = 'reset_token' LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE client_info ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
            $conn->query("ALTER TABLE client_info ADD COLUMN reset_expires DATETIME DEFAULT NULL");
        }
    }
}

ensureClientResetColumnsExist();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginInput = trim($_POST['email'] ?? '');
    
    if (empty($loginInput)) {
        $message = "Please enter your email address.";
        $messageType = "error";
    } else {
        // Check if client exists
        $stmt = mysqli_prepare($conn, "SELECT clientID, email, firstName, lastName FROM client_info WHERE email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $loginInput);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $client = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($client) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database
                $updateStmt = $conn->prepare("UPDATE client_info SET reset_token = ?, reset_expires = ? WHERE clientID = ?");
                $updateStmt->bind_param("ssi", $resetToken, $resetExpires, $client['clientID']);
                $updateStmt->execute();
                
                // Send reset email
                try {
                    $mail = new PHPMailer(true);
                    
                    // SMTP configuration with error debugging
                    $mail->isSMTP();
                    $mail->SMTPDebug = 0; // Change to 2 for detailed debugging
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'rapidrepair224@gmail.com';
                    $mail->Password = 'gabd xcqy gbgq rtwj';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->Timeout = 10;
                    $mail->ConnectTimeout = 10;
                    
                    // Email content
                    $mail->setFrom('rapidrepair224@gmail.com', 'Rapid Repair');
                    $mail->addAddress($client['email'], $client['firstName'] . ' ' . $client['lastName']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - Rapid Repair';
                    
                    $resetLink = getResetLinkBaseUrl() . dirname($_SERVER['REQUEST_URI']) . "/client_reset_password.php?token=" . urlencode($resetToken);
                    
                    $htmlBody = "
                    <html>
                    <head>
                        <style>
                            body { font-family: 'Inter', Arial, sans-serif; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb; }
                            .card { background-color: white; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                            .header { color: #1f2937; margin-bottom: 20px; }
                            .header h1 { margin: 0; font-size: 24px; }
                            .content { color: #4b5563; line-height: 1.6; margin: 20px 0; }
                            .button-container { text-align: center; margin: 30px 0; }
                            .button { display: inline-block; padding: 12px 30px; background-color: #1152d4; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
                            .button:hover { background-color: #0d3ba0; }
                            .warning { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; font-size: 14px; }
                            .footer { border-top: 1px solid #e5e7eb; padding-top: 20px; font-size: 12px; color: #6b7280; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='card'>
                                <div class='header'>
                                    <h1>Password Reset Request</h1>
                                </div>
                                
                                <div class='content'>
                                    <p>Hello <strong>" . htmlspecialchars($client['firstName'] . ' ' . $client['lastName']) . "</strong>,</p>
                                    
                                    <p>We received a request to reset the password for your Rapid Repair account. If you made this request, click the button below to reset your password.</p>
                                    
                                    <div class='button-container'>
                                        <a href='" . htmlspecialchars($resetLink) . "' class='button'>Reset Password</a>
                                    </div>
                                    
                                    <p>Or copy and paste this link in your browser:</p>
                                    <p style='word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-size: 12px;'>" . htmlspecialchars($resetLink) . "</p>
                                    
                                    <div class='warning'>
                                        <strong>⚠️ Security Notice:</strong> This link will expire in 1 hour. If you did not request a password reset, please ignore this email and your password will remain unchanged. If you believe your account is compromised, please contact support immediately.
                                    </div>
                                </div>
                                
                                <div class='footer'>
                                    <p>© 2026 Rapid Repair. All rights reserved.</p>
                                    <p>This is an automated email. Please do not reply to this message.</p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mail->Body = $htmlBody;
                    $mail->AltBody = "Password Reset Request\n\nIf you requested a password reset, visit this link:\n" . $resetLink . "\n\nThis link expires in 1 hour.";
                    
                    $mail->send();
                    
                    $message = "If an account exists with that email, a password reset link has been sent. Please check your email.";
                    $messageType = "success";
                } catch (Exception $e) {
                    // Log the error for debugging
                    error_log("Client Forgot Password Email Error: " . $e->getMessage());
                    error_log("PHPMailer ErrorInfo: " . (isset($mail) ? $mail->ErrorInfo : 'Mail object not initialized'));
                    
                    // For user - generic message for security
                    $message = "Unable to send reset email. Please try again later or contact support.";
                    $messageType = "error";
                }
            } else {
                // For security, don't reveal whether account exists
                $message = "If an account exists with that email, a password reset link has been sent. Please check your email.";
                $messageType = "success";
            }
        } else {
            $message = "Database query error. Please try again.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4"
                    },
                    fontFamily: {
                        "body": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 antialiased">
    <main class="min-h-screen flex flex-col md:flex-row overflow-hidden">
        <!-- Left Side -->
        <section class="hidden md:flex md:w-1/2 lg:w-3/5 relative bg-slate-900">
            <div class="absolute inset-0 bg-cover bg-center"
                style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBEtOuuJSzqVzZjHADVkpFT4TDfTPXEAcEaSLkWOU5QmzDnBAjLV_-CRL4fTsRl62TXWxPAFqH0RxKVKJWsC7nCQUBqXTfdys2e3-uiXn9uiT1L_JyNHSh9KmCwqXdKG1SPsNyQ2lb-iqaXJGFWptTRq0kn01G6CAvT_Jr09D5rDZhcv1oJUvrCLrSAREFTuLsIzdv2NwMw5Ra67OMx4HN9PDzActEJY8XoSy6wm5BD7mcoC8rCgTocwAihHtKWYfyDGU9m_LKRlOUQ')">
            </div>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-900/95 via-slate-900/70 to-transparent"></div>
            
            <div class="relative z-10 p-16 flex flex-col justify-between h-full w-full">
                <div class="flex items-center gap-2">
                    <span class="text-3xl font-black tracking-tighter text-primary">RapidRepairCo.</span>
                </div>
                <div class="max-w-md">
                    <h1 class="text-5xl text-white leading-tight tracking-tight mb-6 font-bold">
                        Secure Your Account
                    </h1>
                    <p class="text-slate-300 text-lg leading-relaxed font-medium">
                        Reset your password quickly and securely. We'll send you a link to your email.
                    </p>
                </div>
            </div>
        </section>

        <!-- Right Side: Reset Form -->
        <section class="relative w-full md:w-1/2 lg:w-2/5 bg-white flex flex-col justify-center px-8 sm:px-12 lg:px-24 py-12">
            <nav class="absolute top-0 right-0 p-8">
                <a class="flex items-center gap-2 text-xs font-bold text-gray-600 hover:text-primary transition-colors uppercase tracking-widest"
                    href="clientlogin.php">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Back to Login
                </a>
            </nav>

            <div class="w-full max-w-sm mx-auto">
                <!-- Mobile Branding -->
                <div class="md:hidden mb-12 flex items-center gap-2">
                    <span class="text-2xl font-black text-primary tracking-tighter">RapidRepairCo.</span>
                </div>

                <header class="mb-10">
                    <h2 class="text-3xl text-gray-900 tracking-tight mb-2 font-bold">Forgot Password?</h2>
                    <p class="text-gray-600 font-medium">Enter your email to receive a password reset link.</p>
                </header>

                <?php if ($message): ?>
                    <div class="mb-6 rounded-lg border <?php echo $messageType === 'success' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'; ?> px-4 py-3">
                        <p class="<?php echo $messageType === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="post" action="">
                    <!-- Email Input -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-gray-700 uppercase tracking-widest" for="email">
                            Email Address
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">alternate_email</span>
                            </div>
                            <input
                                class="block w-full pl-10 pr-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-lg focus:border-primary focus:ring-0 text-sm font-semibold transition-all duration-200"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                required
                                type="email" />
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        class="w-full flex justify-center items-center py-4 px-6 bg-primary text-white text-sm font-bold uppercase tracking-[0.2em] rounded-lg shadow-lg hover:shadow-primary/20 hover:translate-y-[-1px] active:translate-y-[1px] transition-all duration-300 group"
                        type="submit">
                        Send Reset Link
                        <span class="material-symbols-outlined ml-3 text-[20px] group-hover:translate-x-1 transition-transform">mail</span>
                    </button>

                    <div class="text-center">
                        <a class="text-sm text-gray-600 hover:text-primary font-medium" href="clientlogin.php">
                            Return to login
                        </a>
                    </div>
                </form>

                <!-- Support Footer -->
                <footer class="mt-12 pt-8 border-t border-gray-200 flex flex-col gap-4">
                    <div class="flex items-center gap-3 text-gray-600">
                        <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">verified_user</span>
                        </div>
                        <p class="text-[11px] leading-tight font-medium">
                            Your account is secure. The reset link will expire in 1 hour for your safety.
                        </p>
                    </div>
                    <div class="flex justify-between items-center mt-4">
                        <div class="flex gap-4">
                            <a class="text-[10px] text-gray-500 hover:text-primary font-bold uppercase tracking-tight"
                                href="#">Help Center</a>
                            <a class="text-[10px] text-gray-500 hover:text-primary font-bold uppercase tracking-tight"
                                href="#">Contact Support</a>
                        </div>
                        <span class="text-[10px] text-gray-400 font-bold uppercase">RapidRepairCo.</span>
                    </div>
                </footer>
            </div>
        </section>
    </main>

    <!-- Visual Polish -->
    <div class="fixed inset-0 pointer-events-none z-50 opacity-[0.03]"
        style="background-image: radial-gradient(#1152d4 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>
</body>

</html>
