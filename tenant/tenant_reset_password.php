<?php
session_start();
require_once __DIR__ . "/../db.php";

$message = "";
$messageType = "";
$tokenValid = false;
$token = trim($_GET['token'] ?? '');
$requestedShop = trim($_GET['shop'] ?? '');
$passwordErrorsList = [];
$userType = ''; // owner or staff

if (empty($token) || empty($requestedShop)) {
    $message = "Invalid or missing password reset token.";
    $messageType = "error";
} else {
    // First try to validate token in owners table
    $ownerStmt = $conn->prepare("SELECT tenantID, email, username, shopName FROM owners WHERE reset_token = ? AND reset_expires > NOW() AND login_slug = ? LIMIT 1");
    $ownerStmt->bind_param("ss", $token, $requestedShop);
    $ownerStmt->execute();
    $ownerResult = $ownerStmt->get_result();
    $userData = $ownerResult->fetch_assoc();
    $ownerStmt->close();
    
    if ($userData) {
        $tokenValid = true;
        $userType = 'owner';
    } else {
        // Try staff table
        $staffStmt = $conn->prepare("SELECT role_id, email, username, first_name, last_name FROM roles WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
        $staffStmt->bind_param("s", $token);
        $staffStmt->execute();
        $staffResult = $staffStmt->get_result();
        $userData = $staffResult->fetch_assoc();
        $staffStmt->close();
        
        if ($userData) {
            $tokenValid = true;
            $userType = 'staff';
        }
    }
    
    if (!$tokenValid) {
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
            $message = "Password does not meet requirements";
            $messageType = "error";
            $passwordErrorsList = $passwordErrors;
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match. Please try again.";
            $messageType = "error";
        } else {
            // Hash the password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password in database and clear reset token
            if ($userType === 'owner') {
                $updateStmt = $conn->prepare("UPDATE owners SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ? AND login_slug = ?");
                $updateStmt->bind_param("sss", $hashedPassword, $token, $requestedShop);
            } else {
                $updateStmt = $conn->prepare("UPDATE roles SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
                $updateStmt->bind_param("ss", $hashedPassword, $token);
            }
            
            if ($updateStmt->execute()) {
                $message = "Your password has been successfully reset. You can now log in with your new password.";
                $messageType = "success";
                $tokenValid = false; // Prevent further resets with same token
                $updateStmt->close();
            } else {
                $message = "An error occurred while resetting your password. Please try again.";
                $messageType = "error";
                $updateStmt->close();
            }
        }
    }
}

// Load customization data
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
$hero_image_path = $customization['hero_image_path'] ?? '';
$hero_image = $hero_image_path !== '' ? '../pictures/' . $hero_image_path : 'https://lh3.googleusercontent.com/aida-public/AB6AXuDSvLJ3cZ6ER79yp4o0Y6WzI13dqdVNHhZHyLZ4Kme87pJYEmODEmNSRjQ0g63jOoVZm4UaDpyBha6ec962kjUuNBIniN-rnrETo8k-FO4-O39ZFYyuu6p97SuzraheAFkzXxwABqt3ur6ZemstwDJC3DK8JRm5f8I_Wg39e4nQFobYSlTPUeKHAi9IREjo2PztGF8l1xTOkR0Thn92ufrXf2K5DCTcgO9BDNrLqPYjloFAqFRHq3Wug_cHDUq7vyyX-0hUWfzOyqxn';
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
                        Create New Password
                    </h1>

                    <p class="text-slate-300 text-lg">
                        Enter a new password to secure your account.
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
                        Enter a new password to regain access to your account.
                    </p>

                </div>

                <?php if (!$tokenValid && $messageType === 'error') { ?>
                    <div class="bg-red-50 border border-red-200 p-4 rounded-lg mb-6">
                        <p class="text-red-700 text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                    <div class="text-center">
                        <a href="tenant_forgot_password.php?shop=<?php echo urlencode($requestedShop); ?>" class="text-primary hover:underline font-medium">Request a new password reset</a>
                    </div>
                <?php } elseif ($messageType === 'success') { ?>
                    <div class="bg-green-50 border border-green-200 p-4 rounded-lg mb-6">
                        <p class="text-green-700 text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                    <div class="text-center">
                        <a href="tenantlogin.php?shop=<?php echo urlencode($requestedShop); ?>" class="w-full h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl primary-glow transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 inline-flex">
                            <span class="material-symbols-outlined text-lg">login</span>
                            <span>Return to Login</span>
                        </a>
                    </div>
                <?php } else { ?>
                    <form class="space-y-5" method="POST" action="">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700" for="new_password">New Password</label>
                            <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-slate-50">
                                <div class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                    <span class="material-symbols-outlined text-gray-custom text-xl">lock</span>
                                </div>
                                <input id="new_password" name="new_password"
                                    class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                    placeholder="••••••••" type="password" required />
                                <button type="button" class="flex items-center justify-center bg-slate-100 px-3 border-l border-slate-200 text-slate-500 hover:text-primary transition-colors toggle-password" data-target="new_password" aria-label="Show password">
                                    <span class="material-symbols-outlined text-xl toggle-icon">visibility</span>
                                </button>
                            </div>
                            <div id="password-strength" class="mt-2">
                                <p class="text-xs text-slate-600 mb-2 font-medium">Password Requirements:</p>
                                <div class="grid grid-cols-1 gap-1">
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="length">○</span> At least 8 characters</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="upper">○</span> One uppercase letter (A-Z)</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="lower">○</span> One lowercase letter (a-z)</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="number">○</span> One number (0-9)</p>
                                    <p class="text-xs flex items-center gap-2"><span class="requirement-check" data-require="special">○</span> One special character (!@#$%...)</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700" for="confirm_password">Confirm Password</label>
                            <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-slate-50">
                                <div class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                    <span class="material-symbols-outlined text-gray-custom text-xl">lock</span>
                                </div>
                                <input id="confirm_password" name="confirm_password"
                                    class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                    placeholder="••••••••" type="password" required />
                                <button type="button" class="flex items-center justify-center bg-slate-100 px-3 border-l border-slate-200 text-slate-500 hover:text-primary transition-colors toggle-password" data-target="confirm_password" aria-label="Show password">
                                    <span class="material-symbols-outlined text-xl toggle-icon">visibility</span>
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

                        <button type="submit"
                            class="w-full h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl primary-glow transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">

                            <span class="material-symbols-outlined text-lg">check_circle</span>

                            <span>Reset Password</span>

                        </button>

                        <div class="text-center pt-4">
                            <a href="tenantlogin.php?shop=<?php echo urlencode($requestedShop); ?>" class="text-sm text-primary font-bold hover:underline">Back to Login</a>
                        </div>
                    </form>
                <?php } ?>

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
