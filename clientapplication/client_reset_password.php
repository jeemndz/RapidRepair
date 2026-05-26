<?php
session_start();
require_once __DIR__ . "/../db.php";

$message = "";
$messageType = "";
$tokenValid = false;
$token = trim($_GET['token'] ?? '');
$passwordErrorsList = [];

if (empty($token)) {
    $message = "Invalid or missing password reset token.";
    $messageType = "error";
} else {
    // Validate token in client_info table
    $stmt = $conn->prepare("SELECT clientID, email, firstName, lastName FROM client_info WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
    
    if ($userData) {
        $tokenValid = true;
    } else {
        $message = "This password reset link is invalid or has expired. Please request a new password reset.";
        $messageType = "error";
    }
}

// Function to validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z)";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z)";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0-9)";
    }
    
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*)";
    }
    
    return $errors;
}

// Handle password reset submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $tokenValid) {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validate input
    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Please enter and confirm your new password.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        // Validate password strength
        $passwordErrorsList = validatePasswordStrength($newPassword);
        
        if (count($passwordErrorsList) > 0) {
            $message = "Password does not meet security requirements.";
            $messageType = "error";
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $updateStmt = $conn->prepare("UPDATE client_info SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE clientID = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userData['clientID']);
            
            if ($updateStmt->execute()) {
                $message = "Your password has been successfully reset. You can now log in with your new password.";
                $messageType = "success";
                $tokenValid = false;
                $userData = null;
            } else {
                $message = "An error occurred while updating your password. Please try again.";
                $messageType = "error";
            }
            $updateStmt->close();
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

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4"
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

<body class="bg-gray-50 text-gray-900">
    <main class="min-h-screen flex flex-col md:flex-row overflow-hidden">
        <!-- Left Side -->
        <section class="hidden md:flex md:w-1/2 lg:w-3/5 relative bg-slate-900">
            <div class="absolute inset-0 bg-cover bg-center"
                style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBEtOuuJSzqVzZjHADVkpFT4TDfTPXEAcEaSLkWOU5QmzDnBAjLV_-CRL4fTsRl62TXWxPAFqH0RxKVKJWsC7nCQUBqXTfdys2e3-uiXn9uiT1L_JyNHSh9KmCwqXdKG1SPsNyQ2lb-iqaXJGFWptTRq0kn01G6CAvT_Jr09D5rDZhcv1oJUvrCLrSAREFTuLsIzdv2NwMw5Ra67OMx4HN9PDzActEJY8XoSy6wm5BD7mcoC8rCgTocwAihHtKWYfyDGU9m_LKRlOUQ')">
            </div>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-900/95 via-slate-900/70 to-transparent"></div>

            <div class="relative z-20 flex flex-col justify-end p-16 w-full">
                <div class="max-w-md">
                    <h1 class="text-4xl font-bold text-white mb-4 leading-tight">
                        Create New Password
                    </h1>

                    <p class="text-slate-300 text-lg">
                        Enter a new password to secure your account.
                    </p>
                </div>
            </div>
        </section>

        <!-- Right Side: Reset Form -->
        <section class="w-full md:w-1/2 lg:w-2/5 bg-white flex flex-col justify-center p-8 sm:p-12 md:p-16">
            <div class="w-full max-w-[440px] mx-auto">
                <!-- Mobile Branding -->
                <div class="md:hidden mb-10 flex items-center gap-2">
                    <span class="text-2xl font-black text-primary tracking-tighter">RapidRepairCo.</span>
                </div>

                <div class="mb-8">
                    <h2 class="text-3xl font-bold mb-2 text-gray-900">
                        Reset Password
                    </h2>
                    <p class="text-gray-600">
                        Enter a new password to regain access to your account.
                    </p>
                </div>

                <?php if ($message != ""): ?>
                    <div class="mb-6 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?> p-4 rounded-lg">
                        <p class="<?php echo $messageType === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($tokenValid): ?>
                    <form class="space-y-6" method="POST" action="">
                        <!-- New Password -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-700" for="new_password">
                                New Password
                            </label>
                            <div class="relative group">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400 group-focus-within:text-primary text-xl">lock</span>
                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    placeholder="••••••••••••"
                                    class="w-full pl-10 pr-4 h-12 rounded-lg border border-gray-200 focus:border-primary focus:ring-0 text-gray-900 transition-colors"
                                    required />
                            </div>
                        </div>

                        <!-- Password Requirements -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-xs font-bold text-blue-900 mb-3 uppercase">Password Requirements:</p>
                            <ul class="space-y-2 text-xs text-blue-800">
                                <li class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    At least 8 characters
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    One uppercase letter (A-Z)
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    One lowercase letter (a-z)
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    One number (0-9)
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    One special character (!@#$%^&*)
                                </li>
                            </ul>
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-700" for="confirm_password">
                                Confirm Password
                            </label>
                            <div class="relative group">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400 group-focus-within:text-primary text-xl">lock_check</span>
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="••••••••••••"
                                    class="w-full pl-10 pr-4 h-12 rounded-lg border border-gray-200 focus:border-primary focus:ring-0 text-gray-900 transition-colors"
                                    required />
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 shadow-lg hover:shadow-primary/20">
                            <span class="material-symbols-outlined text-lg">check_circle</span>
                            Reset Password
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center">
                        <a href="clientlogin.php" class="text-primary font-bold hover:underline">
                            Return to login
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500">
                    <span>Secure Connection</span>
                    <span>Password Protected</span>
                </div>
            </div>
        </section>
    </main>

    <!-- Visual Polish -->
    <div class="fixed inset-0 pointer-events-none z-50 opacity-[0.03]"
        style="background-image: radial-gradient(#1152d4 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>
</body>

</html>
