<?php
// superadmin_forgot_password.php
session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../log_helper.php";
require_once __DIR__ . "/../PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../PHPMailer/src/SMTP.php";
require_once __DIR__ . "/../PHPMailer/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$messageType = "";

// Check if database has reset columns, if not add them
function ensureResetColumnsExist()
{
    global $conn;
    
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'superadmin' AND COLUMN_NAME = 'reset_token' LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            // Add reset_token column
            $conn->query("ALTER TABLE superadmin ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
            $conn->query("ALTER TABLE superadmin ADD COLUMN reset_expires DATETIME DEFAULT NULL");
        }
    }
}

ensureResetColumnsExist();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginInput = trim($_POST['login_input'] ?? '');
    
    if (empty($loginInput)) {
        $message = "Please enter your username or email.";
        $messageType = "error";
    } else {
        // Check if superadmin exists
        $stmt = $conn->prepare("SELECT superadmin_id, email, username, fullName FROM superadmin WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param("ss", $loginInput, $loginInput);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $superadminId = $row['superadmin_id'];
            $email = $row['email'];
            $username = $row['username'];
            $fullName = $row['fullName'];
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token in database
            $updateStmt = $conn->prepare("UPDATE superadmin SET reset_token = ?, reset_expires = ? WHERE superadmin_id = ?");
            $updateStmt->bind_param("ssi", $resetToken, $resetExpires, $superadminId);
            $updateStmt->execute();
            
            // Send reset email
            try {
                $mail = new PHPMailer(true);
                
                // SMTP configuration
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'rapidrepair224@gmail.com';
                $mail->Password = 'gabd xcqy gbgq rtwj';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Email content
                $mail->setFrom('rapidrepair224@gmail.com', 'Rapid Repair Admin');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - Rapid Repair';
                
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/superadmin_reset_password.php?token=" . urlencode($resetToken);
                
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
                        .button { background-color: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600; }
                        .footer { border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 20px; color: #6b7280; font-size: 12px; }
                        .warning { background-color: #fef3c7; border: 1px solid #fcd34d; padding: 12px; border-radius: 4px; margin: 15px 0; color: #92400e; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='card'>
                            <div class='header'>
                                <h1>Password Reset Request</h1>
                            </div>
                            
                            <div class='content'>
                                <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                                
                                <p>We received a request to reset the password for your Rapid Repair SuperAdmin account. If you made this request, click the button below to reset your password.</p>
                                
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
                
                // Log the action
                log_event($conn, "Forgot Password Request", "Superadmin", null, "Password reset requested for: " . htmlspecialchars($email));
                
                $message = "If an account exists with that username or email, a password reset link has been sent to " . substr($email, 0, 3) . "***@***.com. Please check your email.";
                $messageType = "success";
            } catch (Exception $e) {
                // Log email send failure
                log_event($conn, "Forgot Password - Email Send Failed", "Superadmin", null, "Failed to send reset email for: " . htmlspecialchars($email) . ". Error: " . $e->getMessage());
                
                $message = "Unable to send reset email. Please try again later or contact support.";
                $messageType = "error";
            }
        } else {
            // For security, don't reveal whether account exists
            $message = "If an account exists with that username or email, a password reset link has been sent. Please check your email.";
            $messageType = "success";
            
            log_event($conn, "Forgot Password Request - No Account", "Superadmin", null, "Password reset requested for non-existent account: " . htmlspecialchars($loginInput));
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Forgot Password | RapidRepair</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
        }
    </style>
</head>

<body class="bg-zinc-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-6xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[620px]">
            <!-- Left Branding Container -->
            <section class="bg-gradient-to-br from-black via-zinc-900 to-red-900 text-white p-10 lg:p-14 flex flex-col justify-center items-center text-center">
                <img src="../pictures/RRlogo2.png" alt="Rapid Repair logo" class="w-44 md:w-56 h-auto object-contain mb-8 mx-auto">
                <h1 class="text-3xl md:text-4xl font-bold leading-tight">Rapid Repair Super Admin Portal</h1>
                <p class="mt-3 text-slate-200 text-base md:text-lg max-w-md mx-auto">Car Repair Shop Management System</p>
                <p class="mt-8 text-sm text-red-100/90 max-w-md mx-auto">Reset your password to regain access to your account.</p>
            </section>

            <!-- Right Form Container -->
            <section class="p-8 md:p-12 flex flex-col justify-center">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900">Reset Password</h2>
                    <p class="text-slate-500 text-sm mt-2">Enter your username or email to receive a password reset link.</p>
                </div>

                <form class="space-y-5" method="POST" action="">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700" for="login_input">Username or Email</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">person</span>
                            <input
                                type="text"
                                name="login_input"
                                id="login_input"
                                placeholder="Enter username or email"
                                required
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-red-600 focus:border-transparent outline-none transition-all placeholder:text-slate-400"
                            >
                        </div>
                    </div>

                    <?php if (!empty($message)) { ?>
                        <div class="<?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?> p-4 rounded-lg">
                            <p class="<?php echo $messageType === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm"><?= htmlspecialchars($message) ?></p>
                        </div>
                    <?php } ?>

                    <button
                        type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3.5 rounded-lg transition-all flex items-center justify-center gap-2 shadow-lg hover:shadow-red-600/20 active:scale-[0.98]"
                    >
                        <span class="material-symbols-outlined text-xl">mail</span>
                        Send Reset Link
                    </button>

                    <div class="text-center pt-4">
                        <a href="superaddlogin.php" class="text-sm text-red-600 hover:underline font-medium">Back to Login</a>
                    </div>
                </form>

                <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between text-xs text-slate-400">
                    <span>Secure Connection</span>
                    <span>Email Verification</span>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
