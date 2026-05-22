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
 * Prefers production domain over localhost for email links
 */
function getResetLinkBaseUrl() {
    $currentHost = $_SERVER['HTTP_HOST'];
    
    // If current host is localhost/127.0.0.1, use production domain if available
    if (defined('PRODUCTION_DOMAIN') && PRODUCTION_DOMAIN && !defined('ALLOW_LOCALHOST_LINKS')) {
        return 'https://' . PRODUCTION_DOMAIN;
    }
    
    // If current host is localhost and ALLOW_LOCALHOST_LINKS is true, check which to prioritize
    if (defined('PRODUCTION_DOMAIN') && PRODUCTION_DOMAIN && (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false)) {
        // Use production domain for email links when accessed from localhost
        return 'https://' . PRODUCTION_DOMAIN;
    }
    
    // Use current host (production domain)
    return 'https://' . $currentHost;
}

$message = "";
$messageType = "";
$requestedShop = isset($_GET['shop']) ? trim($_GET['shop']) : '';

// Load customization data if shop is provided
$customization = null;
if ($requestedShop) {
    $stmt = mysqli_prepare($conn, "SELECT tc.* FROM tenant_customizations tc 
                                    INNER JOIN owners o ON tc.tenantID = o.tenantID 
                                    WHERE o.login_slug = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $requestedShop);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customization = mysqli_fetch_assoc($result);
}

// Default values
$shop_name = $customization['shop_name'] ?? 'RAPIDREPAIR';
$primary_color = $customization['primary_color'] ?? '#3f75eb';
$accent_color = $customization['accent_color'] ?? '#3f75eb';
$welcome_heading = $customization['welcome_heading'] ?? 'Streamline your shop management with precision.';
$welcome_subtext = $customization['welcome_subtext'] ?? 'The all-in-one platform for modern automotive service centers.';
$hero_image_path = $customization['hero_image_path'] ?? '';
$hero_image = $hero_image_path !== '' ? '../pictures/' . $hero_image_path : 'https://lh3.googleusercontent.com/aida-public/AB6AXuDSvLJ3cZ6ER79yp4o0Y6WzI13dqdVNHhZHyLZ4Kme87pJYEmODEmNSRjQ0g63jOoVZm4UaDpyBha6ec962kjUuNBIniN-rnrETo8k-FO4-O39ZFYyuu6p97SuzraheAFkzXxwABqt3ur6ZemstwDJC3DK8JRm5f8I_Wg39e4nQFobYSlTPUeKHAi9IREjo2PztGF8l1xTOkR0Thn92ufrXf2K5DCTcgO9BDNrLqPYjloFAqFRHq3Wug_cHDUq7vyyX-0hUWfzOyqxn';

// Ensure reset token columns exist
function ensureResetColumnsExist()
{
    global $conn;
    
    // Check owners table
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'owners' AND COLUMN_NAME = 'reset_token' LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE owners ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
            $conn->query("ALTER TABLE owners ADD COLUMN reset_expires DATETIME DEFAULT NULL");
        }
    }
    
    // Check roles table
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'reset_token' LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE roles ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
            $conn->query("ALTER TABLE roles ADD COLUMN reset_expires DATETIME DEFAULT NULL");
        }
    }
}

ensureResetColumnsExist();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginInput = trim($_POST['login_input'] ?? '');
    
    if (empty($requestedShop)) {
        $message = "Invalid shop link. Please use your shop's login page.";
        $messageType = "error";
    } elseif (empty($loginInput)) {
        $message = "Please enter your username or email.";
        $messageType = "error";
    } else {
        // Get tenantID from login_slug
        $tenantStmt = mysqli_prepare($conn, "SELECT tenantID FROM owners WHERE login_slug = ? LIMIT 1");
        mysqli_stmt_bind_param($tenantStmt, "s", $requestedShop);
        mysqli_stmt_execute($tenantStmt);
        $tenantResult = mysqli_stmt_get_result($tenantStmt);
        $tenantRow = mysqli_fetch_assoc($tenantResult);
        $tenantID = $tenantRow ? (int)$tenantRow['tenantID'] : 0;
        mysqli_stmt_close($tenantStmt);
        
        if ($tenantID <= 0) {
            $message = "If an account exists with that username or email, a password reset link has been sent. Please check your email.";
            $messageType = "success";
        } else {
            // Try to find owner first
            $ownerStmt = mysqli_prepare($conn, "SELECT tenantID, email, username, shopName FROM owners WHERE login_slug = ? AND (email = ? OR username = ?) LIMIT 1");
            mysqli_stmt_bind_param($ownerStmt, "sss", $requestedShop, $loginInput, $loginInput);
            mysqli_stmt_execute($ownerStmt);
            $ownerResult = mysqli_stmt_get_result($ownerStmt);
            $ownerUser = mysqli_fetch_assoc($ownerResult);
            mysqli_stmt_close($ownerStmt);
            
            if ($ownerUser) {
                // Generate and send reset token for owner
                $resetToken = bin2hex(random_bytes(32));
                $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $updateStmt = $conn->prepare("UPDATE owners SET reset_token = ?, reset_expires = ? WHERE tenantID = ? AND login_slug = ?");
                mysqli_stmt_bind_param($updateStmt, "ssss", $resetToken, $resetExpires, $tenantID, $requestedShop);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
                
                // Send reset email
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'rapidrepair224@gmail.com';
                    $mail->Password = 'gabd xcqy gbgq rtwj';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    $mail->setFrom('rapidrepair224@gmail.com', 'Rapid Repair');
                    $mail->addAddress($ownerUser['email']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - Rapid Repair';
                    
                    $resetLink = getResetLinkBaseUrl() . dirname($_SERVER['REQUEST_URI']) . "/tenant_reset_password.php?token=" . urlencode($resetToken) . "&shop=" . urlencode($requestedShop);
                    
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
                            .button { background-color: " . $primary_color . "; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600; }
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
                                    <p>Hello <strong>" . htmlspecialchars($ownerUser['shopName']) . "</strong>,</p>
                                    
                                    <p>We received a request to reset the password for your Rapid Repair account. If you made this request, click the button below to reset your password.</p>
                                    
                                    <div class='button-container'>
                                        <a href='" . htmlspecialchars($resetLink) . "' class='button'>Reset Password</a>
                                    </div>
                                    
                                    <p>Or copy and paste this link in your browser:</p>
                                    <p style='word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-size: 12px;'>" . htmlspecialchars($resetLink) . "</p>
                                    
                                    <div class='warning'>
                                        <strong>⚠️ Security Notice:</strong> This link will expire in 1 hour. If you did not request a password reset, please ignore this email. If you believe your account is compromised, please contact support immediately.
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
                    
                    $message = "If an account exists with that username or email, a password reset link has been sent. Please check your email.";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Unable to send reset email. Please try again later or contact support.";
                    $messageType = "error";
                }
            } else {
                // Try to find staff member
                $staffStmt = mysqli_prepare($conn, "SELECT role_id, email, username, first_name, last_name FROM roles WHERE tenantID = ? AND (email = ? OR username = ?) AND is_active = 1 AND status = 'Active' LIMIT 1");
                mysqli_stmt_bind_param($staffStmt, "iss", $tenantID, $loginInput, $loginInput);
                mysqli_stmt_execute($staffStmt);
                $staffResult = mysqli_stmt_get_result($staffStmt);
                $staffUser = mysqli_fetch_assoc($staffResult);
                mysqli_stmt_close($staffStmt);
                
                if ($staffUser) {
                    // Generate and send reset token for staff
                    $resetToken = bin2hex(random_bytes(32));
                    $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    $updateStmt = $conn->prepare("UPDATE roles SET reset_token = ?, reset_expires = ? WHERE role_id = ?");
                    mysqli_stmt_bind_param($updateStmt, "ssi", $resetToken, $resetExpires, $staffUser['role_id']);
                    mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);
                    
                    // Send reset email
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'rapidrepair224@gmail.com';
                        $mail->Password = 'gabd xcqy gbgq rtwj';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        
                        $mail->setFrom('rapidrepair224@gmail.com', 'Rapid Repair');
                        $mail->addAddress($staffUser['email']);
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request - Rapid Repair';
                        
                        $resetLink = getResetLinkBaseUrl() . dirname($_SERVER['REQUEST_URI']) . "/tenant_reset_password.php?token=" . urlencode($resetToken) . "&shop=" . urlencode($requestedShop);
                        
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
                                .button { background-color: " . $primary_color . "; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600; }
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
                                        <p>Hello <strong>" . htmlspecialchars($staffUser['first_name'] . ' ' . $staffUser['last_name']) . "</strong>,</p>
                                        
                                        <p>We received a request to reset the password for your Rapid Repair account. If you made this request, click the button below to reset your password.</p>
                                        
                                        <div class='button-container'>
                                            <a href='" . htmlspecialchars($resetLink) . "' class='button'>Reset Password</a>
                                        </div>
                                        
                                        <p>Or copy and paste this link in your browser:</p>
                                        <p style='word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-size: 12px;'>" . htmlspecialchars($resetLink) . "</p>
                                        
                                        <div class='warning'>
                                            <strong>⚠️ Security Notice:</strong> This link will expire in 1 hour. If you did not request a password reset, please ignore this email. If you believe your account is compromised, please contact support immediately.
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
                        
                        $message = "If an account exists with that username or email, a password reset link has been sent. Please check your email.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $message = "Unable to send reset email. Please try again later or contact support.";
                        $messageType = "error";
                    }
                } else {
                    // Account not found, but show generic message for security
                    $message = "If an account exists with that username or email, a password reset link has been sent. Please check your email.";
                    $messageType = "success";
                }
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />

    <title>Reset Password - RapidRepair</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet" />

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "<?php echo $primary_color; ?>",
                        "brand-dark": "#0f172a",
                        "brand-charcoal": "#1e293b",
                        "navy-custom": "#020617",
                        "gray-custom": "#94a3b8"
                    },
                    fontFamily: {
                        display: ["Public Sans", "sans-serif"]
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    }
                }
            }
        }
    </script>

    <style>
        .primary-glow {
            box-shadow: 0 0 15px rgba(63, 117, 235, 0.4);
        }

        :root {
            --primary-color:
                <?php echo $primary_color; ?>
            ;
            --accent-color:
                <?php echo $accent_color; ?>
            ;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .border-primary {
            border-color: var(--primary-color) !important;
        }

        .focus-within\\:border-primary:focus-within {
            border-color: var(--primary-color) !important;
        }

        .hover\\:bg-primary\\/90:hover {
            background-color: color-mix(in srgb, var(--primary-color) 90%, black) !important;
        }
    </style>

</head>

<body class="font-display text-slate-900 antialiased bg-slate-50">

    <div class="flex min-h-screen">

        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-slate-100">

            <div class="absolute inset-0 z-10 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>

            <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700 hover:scale-105"
                style="background-image:url('<?php echo htmlspecialchars($hero_image); ?>');">
            </div>

            <div class="relative z-20 flex flex-col justify-end p-16 w-full">

                <div class="max-w-md">

                    <div class="flex items-center gap-2 mb-6">
                        <div class="bg-primary p-2 rounded-lg">
                            <span class="material-symbols-outlined text-white">handyman</span>
                        </div>

                        <span class="text-2xl font-black tracking-tight text-white">
                            <?php echo htmlspecialchars(substr($shop_name, 0, 12)); ?><span
                                class="text-primary"><?php echo htmlspecialchars(substr($shop_name, 12)); ?></span>
                        </span>

                    </div>

                    <h1 class="text-4xl font-bold text-white mb-4 leading-tight">
                        Regain access to your account
                    </h1>

                    <p class="text-slate-300 text-lg">
                        Enter your email or username to receive a password reset link.
                    </p>

                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 md:p-16 bg-white">

            <div class="w-full max-w-[440px]">

                <div class="mb-10 lg:hidden flex items-center gap-2">

                    <div class="bg-primary p-1.5 rounded-lg">
                        <span class="material-symbols-outlined text-white text-xl">handyman</span>
                    </div>

                    <span class="text-xl font-black tracking-tight text-slate-900">
                        <?php echo htmlspecialchars(substr($shop_name, 0, 12)); ?><span
                            class="text-primary"><?php echo htmlspecialchars(substr($shop_name, 12)); ?></span>
                    </span>

                </div>

                <div class="mb-8">

                    <h2 class="text-3xl font-bold mb-2 text-slate-900">
                        Reset Password
                    </h2>

                    <p class="text-slate-600">
                        Enter your username or email to receive a password reset link.
                    </p>

                </div>

                <?php if ($message != "") { ?>
                    <div class="mb-4 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?> p-4 rounded-lg">
                        <p class="<?php echo $messageType === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php } ?>

                <form class="space-y-5" method="POST" action="">
                    <input type="hidden" name="shop" value="<?php echo htmlspecialchars($requestedShop); ?>" />
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700">
                            Username or Email
                        </label>
                        <div
                            class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-slate-50">
                            <div class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                <span class="material-symbols-outlined text-gray-custom text-xl">person</span>
                            </div>
                            <input name="login_input"
                                class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                placeholder="Enter username or email" type="text" required />
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl primary-glow transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">

                        <span class="material-symbols-outlined text-lg">mail</span>

                        <span>Send Reset Link</span>

                    </button>

                </form>

                <div class="mt-8 text-center">

                    <a href="tenantlogin.php?shop=<?php echo urlencode($requestedShop); ?>" class="text-sm text-primary font-bold hover:underline">
                        Back to Login
                    </a>

                </div>

                <div class="mt-12 flex justify-center gap-6">

                    <a class="text-xs text-slate-500 hover:text-primary transition-colors" href="#">
                        Privacy Policy
                    </a>

                    <a class="text-xs text-slate-500 hover:text-primary transition-colors" href="#">
                        Terms of Service
                    </a>

                    <a class="text-xs text-slate-500 hover:text-primary transition-colors" href="#">
                        Help Center
                    </a>

                </div>

            </div>
        </div>

    </div>

</body>

</html>
