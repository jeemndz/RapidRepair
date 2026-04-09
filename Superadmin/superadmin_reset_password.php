<?php
// superadmin_reset_password.php
session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../log_helper.php";

$message = "";
$messageType = "";
$tokenValid = false;
$token = trim($_GET['token'] ?? '');
$passwordErrorsList = [];

if (empty($token)) {
    $message = "Invalid or missing password reset token.";
    $messageType = "error";
} else {
    // Verify token and check if it's not expired
    $stmt = $conn->prepare("SELECT superadmin_id, email, username, fullName FROM superadmin WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $tokenValid = true;
        $superadminData = $result->fetch_assoc();
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
    
    if (empty($newPassword)) {
        $message = "Please enter a new password.";
        $messageType = "error";
    } else {
        // Validate password strength
        $passwordErrors = validatePasswordStrength($newPassword);
        
        if (!empty($passwordErrors)) {
            $message = implode("\n", $passwordErrors);
            $messageType = "error";
            $passwordErrorsList = $passwordErrors;
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match. Please try again.";
            $messageType = "error";
        } else {
        // Hash the password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database and clear reset token
        $updateStmt = $conn->prepare("UPDATE superadmin SET password = ?, reset_token = NULL, reset_expires = NULL WHERE superadmin_id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $superadminData['superadmin_id']);
        
        if ($updateStmt->execute()) {
            log_event($conn, "Password Reset Completed", "Superadmin", null, "Password successfully reset for: " . htmlspecialchars($superadminData['email']));
            
            $message = "Your password has been successfully reset. You can now log in with your new password.";
            $messageType = "success";
            $tokenValid = false; // Prevent further resets with same token
        } else {
            $message = "An error occurred while resetting your password. Please try again.";
            $messageType = "error";
            log_event($conn, "Password Reset Failed", "Superadmin", null, "Error updating password for: " . htmlspecialchars($superadminData['email']));
        }
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
    <title>Reset Password | RapidRepair</title>
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
                <p class="mt-8 text-sm text-red-100/90 max-w-md mx-auto">Create a new password to secure your account.</p>
            </section>

            <!-- Right Form Container -->
            <section class="p-8 md:p-12 flex flex-col justify-center">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900">Create New Password</h2>
                    <p class="text-slate-500 text-sm mt-2">Enter a new password for your account.</p>
                </div>

                <?php if (!$tokenValid && $messageType === 'error') { ?>
                    <div class="bg-red-50 border border-red-200 p-4 rounded-lg mb-6">
                        <p class="text-red-700 text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                    <div class="text-center">
                        <a href="superadmin_forgot_password.php" class="text-red-600 hover:underline font-medium">Request a new password reset</a>
                    </div>
                <?php } elseif ($messageType === 'success') { ?>
                    <div class="bg-green-50 border border-green-200 p-4 rounded-lg mb-6">
                        <p class="text-green-700 text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                    <div class="text-center">
                        <a href="superaddlogin.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3.5 px-8 rounded-lg transition-all inline-block">Return to Login</a>
                    </div>
                <?php } else { ?>
                    <form class="space-y-5" method="POST" action="">
                    <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-700" for="new_password">New Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock</span>
                                <input
                                    type="password"
                                    name="new_password"
                                    id="new_password"
                                    placeholder="••••••••"
                                    required
                                    minlength="8"
                                    class="w-full pl-10 pr-12 py-3 rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-red-600 focus:border-transparent outline-none transition-all placeholder:text-slate-400"
                                >
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-600 transition toggle-password"
                                    data-target="new_password"
                                    aria-label="Show password"
                                >
                                    <span class="material-symbols-outlined toggle-icon">visibility</span>
                                </button>
                            </div>
                            <div id="password-strength" class="mt-2">
                                <p class="text-xs text-slate-600 mb-2 font-medium">Password Requirements:</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="length">○</span> At least 8 characters</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="upper">○</span> One uppercase letter (A-Z)</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="lower">○</span> One lowercase letter (a-z)</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="number">○</span> One number (0-9)</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="special">○</span> One special character (!@#$%...)</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-700" for="confirm_password">Confirm Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock</span>
                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    placeholder="••••••••"
                                    required
                                    minlength="8"
                                    class="w-full pl-10 pr-12 py-3 rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-red-600 focus:border-transparent outline-none transition-all placeholder:text-slate-400"
                                >
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-600 transition toggle-password"
                                    data-target="confirm_password"
                                    aria-label="Show password"
                                >
                                    <span class="material-symbols-outlined toggle-icon">visibility</span>
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($message)) { ?>
                            <div class="<?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?> p-4 rounded-lg">
                                <?php if (!empty($passwordErrorsList) && $messageType === 'error') { ?>
                                    <p class="text-red-700 text-sm font-medium mb-2">Password does not meet requirements:</p>
                                    <ul class="text-red-700 text-sm list-disc list-inside space-y-1">
                                        <?php foreach ($passwordErrorsList as $error) { ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php } ?>
                                    </ul>
                                <?php } else { ?>
                                    <p class="<?php echo $messageType === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm"><?= htmlspecialchars($message) ?></p>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <button
                            type="submit"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3.5 rounded-lg transition-all flex items-center justify-center gap-2 shadow-lg hover:shadow-red-600/20 active:scale-[0.98]"
                        >
                            <span class="material-symbols-outlined text-xl">check_circle</span>
                            Reset Password
                        </button>

                        <div class="text-center pt-4">
                            <a href="superaddlogin.php" class="text-sm text-red-600 hover:underline font-medium">Back to Login</a>
                        </div>
                    </form>
                <?php } ?>

                <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between text-xs text-slate-400">
                    <span>Secure Connection</span>
                    <span>Password Protected</span>
                </div>
            </section>
        </div>
    </div>

    <script>
        // Password strength checker
        function checkPasswordStrength(password) {
            const checks = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            
            return checks;
        }
        
        // Update requirement indicators
        const passwordInput = document.getElementById('new_password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function () {
                const checks = checkPasswordStrength(this.value);
                
                document.querySelectorAll('.requirement-check').forEach(check => {
                    const requirement = check.getAttribute('data-require');
                    const isValid = checks[requirement];
                    
                    if (isValid) {
                        check.textContent = '✓';
                        check.className = 'requirement-check text-green-600 font-bold';
                    } else {
                        check.textContent = '○';
                        check.className = 'requirement-check text-slate-400';
                    }
                });
            });
        }
        
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('.toggle-icon');
                
                if (input) {
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    icon.textContent = isHidden ? 'visibility_off' : 'visibility';
                    this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                }
            });
        });
    </script>
</body>
</html>
