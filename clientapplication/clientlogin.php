<?php
session_start();
include __DIR__ . "/../db.php";

$errors = [];
$formData = [
    'email' => ''
];

if (isset($_SESSION['client_id'])) {
    header('Location: clientlanding.php');
    exit;
}

function clientInfoColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM client_info LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loginClient'])) {
    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($formData['email'] === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!clientInfoColumnExists($conn, 'password_hash')) {
        $errors[] = 'Login is not ready yet. Please register again after database update.';
    }

    if (count($errors) === 0) {
        $stmt = mysqli_prepare($conn, "SELECT clientID, firstName, lastName, email, password_hash FROM client_info WHERE email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $formData['email']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $client = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($client && isset($client['password_hash']) && password_verify($password, (string) $client['password_hash'])) {
                $_SESSION['client_id'] = (int) $client['clientID'];
                $_SESSION['client_email'] = (string) $client['email'];
                $_SESSION['client_name'] = trim(((string) $client['firstName']) . ' ' . ((string) $client['lastName']));

                header('Location: clientlanding.php');
                exit;
            }

            $errors[] = 'Invalid email or password.';
        } else {
            $errors[] = 'Unable to process login right now. Please try again.';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "inverse-surface": "#1e293b",
                        "background": "#f6f6f8",
                        "primary-container": "#eef2ff",
                        "on-tertiary-fixed": "#7c2d12",
                        "on-error": "#ffffff",
                        "secondary-fixed": "#e2e8f0",
                        "tertiary-container": "#fef3c7",
                        "surface-container-high": "#ffffff",
                        "surface-variant": "#f1f5f9",
                        "error": "#ef4444",
                        "primary-fixed-dim": "#bfdbfe",
                        "outline": "#e2e8f0",
                        "on-tertiary-fixed-variant": "#9a3412",
                        "surface": "#f6f6f8",
                        "secondary": "#475569",
                        "on-tertiary": "#ffffff",
                        "inverse-primary": "#b4c5ff",
                        "tertiary": "#f59e0b",
                        "secondary-fixed-dim": "#cbd5e1",
                        "on-surface": "#0f172a",
                        "outline-variant": "#cbd5e1",
                        "on-secondary-container": "#1e293b",
                        "surface-dim": "#d9d9e4",
                        "surface-container-low": "#ffffff",
                        "on-secondary-fixed": "#0f172a",
                        "surface-container-highest": "#ffffff",
                        "surface-bright": "#ffffff",
                        "surface-container": "#ffffff",
                        "on-primary": "#ffffff",
                        "on-primary-fixed": "#1e3a8a",
                        "on-surface-variant": "#64748b",
                        "surface-container-lowest": "#ffffff",
                        "secondary-container": "#f1f5f9",
                        "tertiary-fixed": "#ffedd5",
                        "on-primary-container": "#1152d4",
                        "tertiary-fixed-dim": "#fed7aa",
                        "on-tertiary-container": "#92400e",
                        "inverse-on-surface": "#f8fafc",
                        "surface-tint": "#1152d4",
                        "primary-fixed": "#dbeafe",
                        "on-secondary-fixed-variant": "#334155",
                        "on-error-container": "#991b1b",
                        "on-secondary": "#ffffff",
                        "on-primary-fixed-variant": "#1d4ed8",
                        "on-background": "#0f172a",
                        "error-container": "#fee2e2",
                        "primary": "#1152d4"
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
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-background text-on-background antialiased selection:bg-primary/20 selection:text-primary">
    <main class="min-h-screen flex flex-col md:flex-row overflow-hidden">
        <!-- Left Side: Visual Anchor -->
        <section class="hidden md:flex md:w-1/2 lg:w-3/5 relative bg-inverse-surface">
            <!-- Background Image with Overlay -->
            <div class="absolute inset-0 bg-cover bg-center"
                data-alt="High-tech automotive workshop with clinical lighting"
                style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBEtOuuJSzqVzZjHADVkpFT4TDfTPXEAcEaSLkWOU5QmzDnBAjLV_-CRL4fTsRl62TXWxPAFqH0RxKVKJWsC7nCQUBqXTfdys2e3-uiXn9uiT1L_JyNHSh9KmCwqXdKG1SPsNyQ2lb-iqaXJGFWptTRq0kn01G6CAvT_Jr09D5rDZhcv1oJUvrCLrSAREFTuLsIzdv2NwMw5Ra67OMx4HN9PDzActEJY8XoSy6wm5BD7mcoC8rCgTocwAihHtKWYfyDGU9m_LKRlOUQ')">
            </div>
            <div
                class="absolute inset-0 bg-gradient-to-r from-inverse-surface/90 via-inverse-surface/40 to-transparent">
            </div>
            <!-- Branding Overlay Content -->
            <div class="relative z-10 p-16 flex flex-col justify-between h-full w-full">
                <div class="flex items-center gap-2">
                    <span class="text-3xl font-black tracking-tighter text-primary">RapidRepairCo.</span>
                </div>
                <div class="max-w-md">
                    <div
                        class="inline-flex items-center px-3 py-1 bg-primary/20 border border-primary/30 backdrop-blur-md rounded-lg mb-6">
                        <span class="text-xs font-bold tracking-widest text-primary-fixed-dim uppercase">Operational
                            Core</span>
                    </div>
                    <h1 class="text-5xl text-white leading-tight tracking-tight mb-6 font-bold">
                        The Clinical <br />Architect of Repair.
                    </h1>
                    <p class="text-slate-400 text-lg leading-relaxed font-medium">
                        Secure access to the global precision network. Monitor system health, manage high-density tenant
                        data, and execute high-stakes operations.
                    </p>
                </div>
                <div class="flex gap-8 border-t border-white/10 pt-8">
                    <div>
                        <div class="text-2xl font-bold text-white">99.9%</div>
                        <div class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Uptime SLA</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-white">256-bit</div>
                        <div class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Encryption</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-white">v4.0.2</div>
                        <div class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Precision Core
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Right Side: Login Form -->
        <section
            class="relative w-full md:w-1/2 lg:w-2/5 bg-white flex flex-col justify-center px-8 sm:px-12 lg:px-24 py-12">
            <nav class="absolute top-0 right-0 p-8">
                <a class="flex items-center gap-2 text-xs font-bold text-on-surface-variant hover:text-primary transition-colors uppercase tracking-widest"
                    href="clientlanding.php">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Back to Website
                </a>
            </nav>
            <div class="w-full max-w-sm mx-auto">
                <!-- Mobile Branding -->
                <div class="md:hidden mb-12 flex items-center gap-2">
                    <span class="text-2xl font-black text-primary tracking-tighter">RapidRepairCo.</span>
                </div>
                <header class="mb-10">
                    <h2 class="text-3xl text-on-surface tracking-tight mb-2 font-bold">LogIn</h2>
                    <p class="text-on-surface-variant font-medium">Enter your credentials to enter the network.</p>
                </header>
                <?php if (count($errors) > 0): ?>
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="post" action="">
                    <input type="hidden" name="loginClient" value="1" />
                    <!-- Email Input -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-on-surface-variant uppercase tracking-widest"
                            for="email">Administrative Email</label>
                        <div class="relative group">
                            <div
                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant group-focus-within:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">alternate_email</span>
                            </div>
                            <input
                                class="block w-full pl-10 pr-4 py-3 bg-surface-variant border-transparent border-b-2 border-b-outline focus:border-b-primary focus:ring-0 text-sm font-semibold transition-all duration-200"
                                id="email" name="email" placeholder="name@rapidrepairco.com" required=""
                                type="email" value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                    </div>
                    <!-- Password Input -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-end">
                            <label class="text-xs font-bold text-on-surface-variant uppercase tracking-widest"
                                for="password">Secure Passkey</label>
                            <a class="text-[11px] font-bold text-primary hover:underline uppercase tracking-wider"
                                href="#">Forgot Password?</a>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-on-surface-variant group-focus-within:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">lock_person</span>
                            </div>
                            <input
                                class="block w-full pl-10 pr-4 py-3 bg-surface-variant border-transparent border-b-2 border-b-outline focus:border-b-primary focus:ring-0 text-sm font-semibold transition-all duration-200"
                                id="password" name="password" placeholder="••••••••••••" required="" type="password" />
                        </div>
                    </div>
                    <!-- Remember Me & Security Policy -->
                    <div class="flex items-center">
                        <input class="h-4 w-4 text-primary border-outline-variant rounded-sm focus:ring-primary/20"
                            id="remember_me" name="remember_me" type="checkbox" />
                        <label class="ml-2 block text-xs font-medium text-on-surface-variant" for="remember_me">Keep me
                            logged in for 24 hours</label>
                    </div>
                    <!-- Submit Button -->
                    <button
                        class="w-full flex justify-center items-center py-4 px-6 bg-primary text-white text-sm font-bold uppercase tracking-[0.2em] rounded-lg shadow-lg hover:shadow-primary/20 hover:translate-y-[-1px] active:translate-y-[1px] transition-all duration-300 group"
                        type="submit">Authorize Access <span
                            class="material-symbols-outlined ml-3 text-[20px] group-hover:translate-x-1 transition-transform">arrow_forward</span></button>
                    <div class="text-center">
                        <span class="text-sm text-on-surface-variant">Don't have an account? </span>
                        <a class="text-sm font-bold text-primary hover:underline" href="clientregister.php">Register here</a>
                    </div>
                </form>
                <!-- Support Footer -->
                <footer class="mt-12 pt-8 border-t border-outline flex flex-col gap-4">
                    <div class="flex items-center gap-3 text-on-surface-variant">
                        <div class="w-8 h-8 rounded bg-surface-variant flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">verified_user</span>
                        </div>
                        <p class="text-[11px] leading-tight font-medium">
                            Authorized personnel only. Access is monitored under <br /> <span
                                class="text-on-surface font-bold">Policy Protocol 7-B</span>.
                        </p>
                    </div>
                    <div class="flex justify-between items-center mt-4">
                        <div class="flex gap-4">
                            <a class="text-[10px] text-on-surface-variant hover:text-primary font-bold uppercase tracking-tight"
                                href="#">System Status</a>
                            <a class="text-[10px] text-on-surface-variant hover:text-primary font-bold uppercase tracking-tight"
                                href="#">Support Desk</a>
                        </div>
                        <span class="text-[10px] text-outline-variant font-bold uppercase">v4.0.2-GA</span>
                    </div>
                </footer>
            </div>
        </section>
    </main>
    <!-- Visual Polish: Architectural Grid Overlay (Subtle) -->
    <div class="fixed inset-0 pointer-events-none z-50 opacity-[0.03]"
        style="background-image: radial-gradient(#1152d4 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>
</body>

</html>