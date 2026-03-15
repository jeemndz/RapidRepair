<?php
// superaddlogin.php
session_start();
require_once "db.php"; // your database connection file

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Query the database for the superadmin
    $stmt = $conn->prepare("SELECT superadmin_id, email, password FROM superadmin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Plain text password check
        if ($password === $row['password']) {
            // Successful login
            $_SESSION['superadmin_id'] = $row['superadmin_id'];
            $_SESSION['email'] = $row['email'];

            // Redirect to superadd.php
            header("Location: superadd.php");
            exit();
        } else {
            $message = "Incorrect password.";
        }
    } else {
        $message = "No superadmin account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Superadmin Login | AutoFix Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "navy-deep": "#092463",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-[480px]">
        <!-- Branding Header -->
        <div class="flex flex-col items-center mb-8">
            <div class="size-12 bg-navy-deep text-white rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-navy-deep/20">
                <span class="material-symbols-outlined text-3xl">shield_person</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Rapid Repair SuperAdmin Portal</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Car Repair Shop Management System</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            <!-- Access Badge -->
            <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-primary text-sm">verified_user</span>
                <span class="text-xs font-bold uppercase tracking-widest text-primary">Superadmin Access</span>
            </div>

            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Welcome Back</h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">Please enter your credentials to manage the platform.</p>
                </div>

                <form class="space-y-5" method="POST" action="">
                    <!-- Email Field -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">Email address</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
                            <input type="email" name="email" id="email" placeholder="admin@autofix.com" required
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="password">Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock</span>
                            <input type="password" name="password" id="password" placeholder="••••••••" required
                                class="w-full pl-10 pr-12 py-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400">
                        </div>
                    </div>

                    <!-- Display login error message -->
                    <?php if(!empty($message)) { ?>
                        <p class="text-red-500 text-sm"><?= htmlspecialchars($message) ?></p>
                    <?php } ?>

                    <!-- Options -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-primary focus:ring-primary bg-transparent">
                            <span class="text-sm text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-slate-200 transition-colors">Remember this session</span>
                        </label>
                        <a class="text-sm font-medium text-primary hover:underline" href="#">Forgot Password?</a>
                    </div>

                    <!-- Sign In Button -->
                    <button type="submit"
                        class="w-full bg-navy-deep hover:bg-slate-800 text-white font-bold py-3.5 rounded-lg transition-all flex items-center justify-center gap-2 shadow-lg hover:shadow-navy-deep/20 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-xl">login</span>
                        Sign In as Superadmin
                    </button>
                </form>
            </div>

            <!-- Security Footer -->
            <div class="px-8 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center justify-center gap-4">
                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 uppercase font-semibold tracking-tighter">
                    <span class="material-symbols-outlined text-sm">lock_outline</span>
                    Encrypted Connection
                </div>
                <div class="w-px h-3 bg-slate-300 dark:bg-slate-700"></div>
                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 uppercase font-semibold tracking-tighter">
                    <span class="material-symbols-outlined text-sm">security</span>
                    Multi-factor Enabled
                </div>
            </div>
        </div>

        <!-- Secondary Navigation -->
        <div class="mt-8 flex justify-center gap-6">
            <a class="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary transition-colors" href="#">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Return to Main Site
            </a>
            <a class="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary transition-colors" href="#">
                <span class="material-symbols-outlined text-lg">help</span>
                Support Center
            </a>
        </div>

        <!-- Footer Info -->
        <div class="mt-12 text-center">
            <p class="text-xs text-slate-400">© 2026 AutoFix Car Repair Platform. Internal System Use Only.</p>
        </div>
    </div>
</body>
</html>