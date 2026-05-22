<?php
include __DIR__ . "/../db.php";

$errors = [];
$successMessage = "";

$formData = [
    'firstName' => '',
    'lastName' => '',
    'email' => '',
    'password' => '',
    'confirmPassword' => ''
];

function clientInfoColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM client_info LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

function ensureClientPasswordColumn($conn)
{
    if (clientInfoColumnExists($conn, 'password_hash')) {
        return true;
    }

    $alterSql = "ALTER TABLE client_info ADD COLUMN password_hash VARCHAR(255) NULL AFTER email";
    return mysqli_query($conn, $alterSql) !== false;
}

function validatePasswordStrength($password)
{
    $requirements = [
        'length' => strlen($password) >= 8,
        'uppercase' => preg_match('/[A-Z]/', $password),
        'lowercase' => preg_match('/[a-z]/', $password),
        'number' => preg_match('/[0-9]/', $password),
        'special' => preg_match('/[!@#$%^&*()_\+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)
    ];

    return $requirements;
}

function isPasswordStrong($password)
{
    $requirements = validatePasswordStrength($password);
    return array_reduce($requirements, function ($carry, $item) {
        return $carry && $item;
    }, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registerClient'])) {
    $formData['firstName'] = trim((string) ($_POST['firstName'] ?? ''));
    $formData['lastName'] = trim((string) ($_POST['lastName'] ?? ''));
    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $formData['password'] = (string) ($_POST['password'] ?? '');
    $formData['confirmPassword'] = (string) ($_POST['confirmPassword'] ?? '');

    if ($formData['firstName'] === '' || $formData['lastName'] === '' || $formData['email'] === '' || $formData['password'] === '' || $formData['confirmPassword'] === '') {
        $errors[] = 'First name, last name, email, and password fields are required.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($formData['password'] !== '' && strlen($formData['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($formData['password'] !== '' && !isPasswordStrong($formData['password'])) {
        $errors[] = 'Password must contain uppercase, lowercase, number, and special character (e.g., Rapidrepair1!).';
    }

    if ($formData['password'] !== $formData['confirmPassword']) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (count($errors) === 0) {
        if (!ensureClientPasswordColumn($conn)) {
            $errors[] = 'System setup issue: password column is missing in client_info.';
        }
    }

    if (count($errors) === 0) {
        $emailCheckStmt = mysqli_prepare($conn, "SELECT clientID FROM client_info WHERE email = ? LIMIT 1");
        if ($emailCheckStmt) {
            mysqli_stmt_bind_param($emailCheckStmt, 's', $formData['email']);
            mysqli_stmt_execute($emailCheckStmt);
            mysqli_stmt_store_result($emailCheckStmt);
            if (mysqli_stmt_num_rows($emailCheckStmt) > 0) {
                $errors[] = 'This email is already registered.';
            }
            mysqli_stmt_close($emailCheckStmt);
        }
    }

    if (count($errors) === 0) {
        $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
        $hasLegacyPasswordColumn = clientInfoColumnExists($conn, 'password');

        if ($hasLegacyPasswordColumn) {
            $stmt = mysqli_prepare($conn, "INSERT INTO client_info (firstName, lastName, email, password, password_hash) VALUES (?, ?, ?, ?, ?)");
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO client_info (firstName, lastName, email, password_hash) VALUES (?, ?, ?, ?)");
        }

        if ($stmt) {
            if ($hasLegacyPasswordColumn) {
                mysqli_stmt_bind_param(
                    $stmt,
                    'sssss',
                    $formData['firstName'],
                    $formData['lastName'],
                    $formData['email'],
                    $passwordHash,
                    $passwordHash
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssss',
                    $formData['firstName'],
                    $formData['lastName'],
                    $formData['email'],
                    $passwordHash
                );
            }

            if (mysqli_stmt_execute($stmt)) {
                $successMessage = 'Account Registered Successfully.';
                $formData = [
                    'firstName' => '',
                    'lastName' => '',
                    'email' => '',
                    'password' => '',
                    'confirmPassword' => ''
                ];
            } else {
                $errors[] = 'Unable to submit registration right now. Please try again.';
            }

            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Unable to prepare registration request. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>RapidRepairCo. - Register Account</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f6f6f8;
        }

        .architectural-grid {
            background-image: radial-gradient(#cbd5e1 0.5px, transparent 0.5px);
            background-size: 24px 24px;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "secondary-fixed": "#e2e8f0",
                        "on-primary-container": "#1152d4",
                        "surface-variant": "#f1f5f9",
                        "outline-variant": "#cbd5e1",
                        "secondary-fixed-dim": "#cbd5e1",
                        "surface-container-low": "#ffffff",
                        "on-surface-variant": "#64748b",
                        "inverse-primary": "#b4c5ff",
                        "inverse-surface": "#1e293b",
                        "error-container": "#fee2e2",
                        "tertiary-container": "#fef3c7",
                        "primary-fixed-dim": "#bfdbfe",
                        "on-error": "#ffffff",
                        "tertiary-fixed": "#ffedd5",
                        "outline": "#e2e8f0",
                        "on-secondary-fixed-variant": "#334155",
                        "surface-container-highest": "#ffffff",
                        "surface": "#f6f6f8",
                        "on-secondary": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "surface-container": "#ffffff",
                        "primary-container": "#eef2ff",
                        "on-error-container": "#991b1b",
                        "on-secondary-fixed": "#0f172a",
                        "background": "#f6f6f8",
                        "tertiary-fixed-dim": "#fed7aa",
                        "surface-container-high": "#ffffff",
                        "on-background": "#0f172a",
                        "surface-dim": "#d9d9e4",
                        "error": "#ef4444",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-secondary-container": "#1e293b",
                        "tertiary": "#f59e0b",
                        "on-primary": "#ffffff",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "surface-container-lowest": "#ffffff",
                        "surface-bright": "#ffffff",
                        "secondary": "#475569",
                        "primary-fixed": "#dbeafe",
                        "primary": "#1152d4",
                        "surface-tint": "#1152d4",
                        "on-tertiary-container": "#92400e",
                        "on-primary-fixed": "#1e3a8a",
                        "on-surface": "#0f172a",
                        "inverse-on-surface": "#f8fafc"
                    },
                    fontFamily: {
                        "headline": ["Inter"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem" },
                },
            },
        }
    </script>
</head>

<body class="bg-background text-on-surface min-h-screen flex flex-col">
    <!-- TopNavBar -->
    <nav
        class="fixed top-0 w-full z-50 flex justify-between items-center px-8 h-16 bg-white dark:bg-slate-900 shadow-sm dark:shadow-none border-b border-slate-200 dark:border-slate-800 font-['Inter'] antialiased tracking-tight">
        <div class="text-[20px] font-black text-[#1152d4] dark:text-blue-500 uppercase tracking-tighter">
            RapidRepairCo.
        </div>
        <a href="clientlogin.php"
            class="bg-primary text-on-primary px-4 py-2 rounded-lg text-[14px] font-bold active:opacity-80 transition-all inline-flex items-center">
            Login
        </a>
    </nav>
    <!-- Main Registration Content -->
    <main class="flex-grow flex items-stretch pt-16">
        <!-- Left: Branding & Visual (Architectural North Star) -->
        <div
            class="hidden lg:flex w-1/2 bg-on-background relative overflow-hidden flex-col justify-center px-20">
            <!-- Background Image Carousel -->
            <div class="absolute inset-0 overflow-hidden">
                <div id="bg-image-1" class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-100" style="background-image: linear-gradient(135deg, rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('../adspictures/ads1.png');"></div>
                <div id="bg-image-2" class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-0" style="background-image: linear-gradient(135deg, rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('../adspictures/ads2.png');"></div>
                <div id="bg-image-3" class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 opacity-0" style="background-image: linear-gradient(135deg, rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('../adspictures/ads3.png');"></div>
            </div>
            <div class="absolute inset-0 bg-gradient-to-br from-primary/20 to-transparent"></div>
            <div class="relative z-10">
                <div
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary-fixed-dim text-[12px] font-bold tracking-widest uppercase mb-6">
                    Register Account
                </div>
                <h1 class="text-[48px] font-black text-white leading-[1.1] tracking-tight mb-6">
                    Start Managing Your Repair Shop
                </h1>
                <p class="text-[18px] text-slate-400 max-w-lg leading-relaxed mb-12">
                    RapidRepairCo. helps car repair shops manage appointments, repair jobs, customer records, payments, and daily operations in one easy-to-use system.
                </p>
                <div class="grid grid-cols-1 gap-8">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center text-primary-fixed-dim border border-primary/30">
                            <span class="material-symbols-outlined">architecture</span>
                        </div>
                        <div>
                            <h3 class="text-white font-bold">Organized Daily Operations</h3>
                            <p class="text-slate-500 text-sm">Keep track of repair jobs, schedules, and customer records more easily.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center text-primary-fixed-dim border border-primary/30">
                            <span class="material-symbols-outlined">network_node</span>
                        </div>
                        <div>
                            <h3 class="text-white font-bold">All-in-One Repair Shop System</h3>
                            <p class="text-slate-500 text-sm">Manage appointments, mechanics, inventory, and payments in one platform.</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Abstract UI Decoration -->
            <div class="absolute bottom-[-100px] right-[-50px] w-96 h-96 opacity-20 pointer-events-none">
                <div class="w-full h-full border border-primary-fixed-dim/30 rounded-xl transform rotate-12"></div>
                <div
                    class="absolute top-10 left-10 w-full h-full border border-primary-fixed-dim/20 rounded-xl transform rotate-6">
                </div>
            </div>
        </div>
        <!-- Right: Registration Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 md:p-12 lg:p-24 bg-surface">
            <div class="w-full max-w-4xl">
                <div class="mb-10 lg:hidden">
                    <div class="text-[20px] font-black text-[#1152d4] uppercase tracking-tighter mb-2">RapidRepairCo.
                    </div>
                    <h2 class="text-[24px] font-bold tracking-tight">Create Your Repair Shop Account</h2>
                </div>
                <div class="bg-white p-8 md:p-10 rounded-lg border border-slate-200 shadow-sm">
                    <div class="mb-8">
                        <h2 class="text-[20px] font-bold text-on-surface mb-2 tracking-tight">Register Account</h2>
                        <p class="text-on-surface-variant text-[14px]">Fill in your details to create your account.</p>
                    </div>
                    <?php if ($successMessage !== ''): ?>
                        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($errors) > 0): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form class="space-y-5" method="post" action="">
                        <input type="hidden" name="registerClient" value="1" />
                        <div class="space-y-5">
                            <div class="space-y-1.5">
                                <label class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">First Name</label>
                                <input
                                    class="w-full h-11 bg-surface-container-highest border border-outline px-4 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary text-[14px] outline-none transition-all"
                                    placeholder="First Name" type="text" name="firstName" required
                                    value="<?php echo htmlspecialchars($formData['firstName'], ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">Last Name</label>
                                <input
                                    class="w-full h-11 bg-surface-container-highest border border-outline px-4 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary text-[14px] outline-none transition-all"
                                    placeholder="Last Name" type="text" name="lastName" required
                                    value="<?php echo htmlspecialchars($formData['lastName'], ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">
                                Email Address</label>
                            <input
                                class="w-full h-11 bg-surface-container-highest border border-outline px-4 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary text-[14px] outline-none transition-all"
                                placeholder="manager@shop.com" type="email" name="email" required
                                value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="space-y-5">
                            <div class="space-y-1.5">
                                <label
                                    class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">Create
                                    Password</label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        class="w-full h-11 bg-surface-container-highest border border-outline px-4 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary text-[14px] outline-none transition-all pr-12"
                                        placeholder="••••••••" type="password" name="password" required
                                        oninput="updatePasswordStrength(this.value)" />
                                    <button
                                        type="button"
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors"
                                        onclick="togglePasswordVisibility('password', this)">
                                        <span class="material-symbols-outlined text-[20px]" id="password-eye">visibility</span>
                                    </button>
                                </div>
                                
                                <!-- Password Strength Indicator -->
                                <div class="mt-3 space-y-2">
                                    <div class="flex gap-1">
                                        <div id="strength-1" class="h-1 flex-1 bg-slate-200 rounded-full transition-colors"></div>
                                        <div id="strength-2" class="h-1 flex-1 bg-slate-200 rounded-full transition-colors"></div>
                                        <div id="strength-3" class="h-1 flex-1 bg-slate-200 rounded-full transition-colors"></div>
                                        <div id="strength-4" class="h-1 flex-1 bg-slate-200 rounded-full transition-colors"></div>
                                        <div id="strength-5" class="h-1 flex-1 bg-slate-200 rounded-full transition-colors"></div>
                                    </div>
                                    <div id="strength-text" class="text-[12px] text-slate-500">Password strength: —</div>
                                </div>

                                <!-- Password Requirements -->
                                <div class="mt-2 space-y-1">
                                    <div class="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Password Requirements:</div>
                                    <div class="grid grid-cols-3 gap-1 text-[11px]">
                                        <div class="flex items-center gap-1.5">
                                            <span id="req-length" class="text-lg leading-none">○</span>
                                            <span class="text-on-surface-variant">8+ characters</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span id="req-upper" class="text-lg leading-none">○</span>
                                            <span class="text-on-surface-variant">Uppercase</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span id="req-lower" class="text-lg leading-none">○</span>
                                            <span class="text-on-surface-variant">Lowercase</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span id="req-number" class="text-lg leading-none">○</span>
                                            <span class="text-on-surface-variant">Number</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span id="req-special" class="text-lg leading-none">○</span>
                                            <span class="text-on-surface-variant">Special char.</span>
                                        </div>
                                    </div>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">Confirm
                                Password</label>
                            <div class="relative">
                                <input
                                    id="confirmPassword"
                                    class="w-full h-11 bg-surface-container-highest border border-outline px-4 rounded-lg focus:ring-1 focus:ring-primary focus:border-primary text-[14px] outline-none transition-all pr-12"
                                    placeholder="••••••••" type="password" name="confirmPassword" required />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors"
                                    onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <span class="material-symbols-outlined text-[20px]" id="confirmPassword-eye">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="pt-4 space-y-3">
                            <div class="flex items-start gap-2.5 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                <input class="w-4 h-4 text-primary border-outline rounded focus:ring-primary mt-0.5 flex-shrink-0" id="terms"
                                    type="checkbox" required />
                                <label class="text-[12px] text-on-surface-variant leading-relaxed" for="terms">I agree to the
                                    <a class="text-primary font-bold hover:underline" href="#">Terms and Conditionse</a> and <a
                                        class="text-primary font-bold hover:underline" href="#">Privacy Policy</a>.</label>
                            </div>
                            <button
                                class="w-full h-16 bg-primary text-white font-bold rounded-lg hover:opacity-90 active:scale-[0.98] transition-all tracking-tight shadow-sm text-[16px]"
                                type="submit">
                                Create Account
                            </button>
                            <div class="text-center pt-2">
                                <span class="text-[14px] text-on-surface-variant">Already have an account? </span>
                                <a class="text-[14px] text-primary font-bold hover:underline" href="clientlogin.php">Login</a>
                            </div>
                        </div>
                    </form>
                </div>
                <p class="mt-8 text-center text-[12px] text-on-surface-variant">
                    Your account information is securely protected by RapidRepairCo.
                </p>
            </div>
        </div>
    </main>
    <!-- Footer -->
    <footer
        class="w-full py-6 px-8 flex flex-col md:flex-row justify-between items-center gap-2 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 font-['Inter'] text-[12px] leading-relaxed">
        <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
            RapidRepairCo.
        </div>
        <div class="text-slate-500 dark:text-slate-400 text-center md:text-left">
            © 2026 RapidRepairCo. All rights reserved.
        </div>
        <div class="flex gap-6">
            <a class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                href="#">Privacy Policy</a>
            <a class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                href="#">Terms of Service</a>
            <a class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                href="#">Help Center</a>
            <a class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                href="#">Support</a>
        </div>
    </footer>
    <script>
        // Background Image Carousel
        const adImages = ['ads1.png', 'ads2.png', 'ads3.png']; // All available images
        let currentImageIndex = 0;
        
        function rotateBackground() {
            // Hide current image
            const currentElement = document.getElementById(`bg-image-${currentImageIndex + 1}`);
            if (currentElement) {
                currentElement.style.opacity = '0';
            }
            
            // Move to next image
            currentImageIndex = (currentImageIndex + 1) % adImages.length;
            
            // Show next image
            const nextElement = document.getElementById(`bg-image-${currentImageIndex + 1}`);
            if (nextElement) {
                nextElement.style.opacity = '1';
            }
        }
        
        // Rotate every 5 seconds
        setInterval(rotateBackground, 5000);

        function togglePasswordVisibility(fieldId, button) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(fieldId + '-eye');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.textContent = 'visibility_off';
            } else {
                passwordField.type = 'password';
                eyeIcon.textContent = 'visibility';
            }
        }

        function updatePasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_\+\-=\[\]{};:'"",.<>?\/\\|`~]/.test(password)
            };

            // Update requirement indicators
            updateRequirementIndicator('req-length', requirements.length);
            updateRequirementIndicator('req-upper', requirements.uppercase);
            updateRequirementIndicator('req-lower', requirements.lowercase);
            updateRequirementIndicator('req-number', requirements.number);
            updateRequirementIndicator('req-special', requirements.special);

            // Calculate strength score
            const metRequirements = Object.values(requirements).filter(val => val).length;
            const strengthPercentage = (metRequirements / 5) * 100;

            // Update strength bars
            for (let i = 1; i <= 5; i++) {
                const bar = document.getElementById(`strength-${i}`);
                if ((i / 5) * 100 <= strengthPercentage) {
                    bar.style.backgroundColor = getStrengthColor(metRequirements);
                } else {
                    bar.style.backgroundColor = '#e2e8f0';
                }
            }

            // Update strength text
            const strengthText = document.getElementById('strength-text');
            if (password.length === 0) {
                strengthText.textContent = 'Password strength: —';
            } else if (metRequirements === 5) {
                strengthText.textContent = 'Password strength: Strong ✓';
                strengthText.style.color = '#16a34a';
            } else if (metRequirements >= 3) {
                strengthText.textContent = 'Password strength: Fair';
                strengthText.style.color = '#f59e0b';
            } else {
                strengthText.textContent = 'Password strength: Weak';
                strengthText.style.color = '#ef4444';
            }
        }

        function updateRequirementIndicator(elementId, isMet) {
            const element = document.getElementById(elementId);
            if (isMet) {
                element.textContent = '✓';
                element.style.color = '#16a34a';
                element.style.fontSize = '16px';
            } else {
                element.textContent = '○';
                element.style.color = '#cbd5e1';
                element.style.fontSize = '24px';
            }
        }

        function getStrengthColor(metRequirements) {
            if (metRequirements === 5) return '#16a34a'; // Green
            if (metRequirements >= 3) return '#f59e0b'; // Orange
            return '#ef4444'; // Red
        }
    </script>
</body>

</html>