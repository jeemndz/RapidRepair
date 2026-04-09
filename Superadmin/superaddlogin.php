<?php
// superaddlogin.php
session_start();
require_once __DIR__ . "/../db.php"; // your database connection file
require_once __DIR__ . "/../log_helper.php"; // audit logging
include __DIR__ . "/../session_security.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginInput = trim($_POST['login_input']);
    $password = trim($_POST['password']);

    // Query the database for the superadmin using username OR email
    $stmt = $conn->prepare("SELECT superadmin_id, email, username, password FROM superadmin WHERE email = ? OR username = ? LIMIT 1");
    $stmt->bind_param("ss", $loginInput, $loginInput);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verify password using password_verify for bcrypt hashing
        if (password_verify($password, $row['password'])) {
            // Successful login
            $_SESSION['superadmin_id'] = $row['superadmin_id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['username'] = $row['username'];

            // Log successful login - create temporary session for logging
            $tempName = $row['username'];
            $_SESSION['_temp_admin_name'] = $tempName;
            log_event($conn, "Superadmin Login", "Superadmin", (int)$row['superadmin_id'], "Successful login via username/email: " . htmlspecialchars($loginInput));
            unset($_SESSION['_temp_admin_name']);

            // Redirect to superadd.php
            header("Location: superadd.php");
            exit();
        } else {
            // Log failed password attempt
            $logDetails = "Failed login attempt with username/email: " . htmlspecialchars($loginInput) . " (incorrect password)";
            log_event($conn, "Superadmin Login Failed", "Superadmin", null, $logDetails);
            $message = "Incorrect password.";
        }
    } else {
        // Log failed login - no account found
        $logDetails = "Failed login attempt - account not found for: " . htmlspecialchars($loginInput);
        log_event($conn, "Superadmin Login Failed", "Superadmin", null, $logDetails);
        $message = "No account found with that username or email.";
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
    <title>Login | RepidRepair</title>
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
                <p class="mt-8 text-sm text-red-100/90 max-w-md mx-auto">Welcome back. Sign in to manage tenants, reports, and subscriptions.</p>
            </section>

            <!-- Right Login Container -->
            <section class="p-8 md:p-12 flex flex-col justify-center">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900">Log In</h2>
                    <p class="text-slate-500 text-sm mt-2">Please enter your credentials to access the SuperAdmin dashboard.</p>
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

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700" for="passwordInput">Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock</span>
                            <input
                                type="password"
                                name="password"
                                id="passwordInput"
                                placeholder="••••••••"
                                required
                                class="w-full pl-10 pr-12 py-3 rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-red-600 focus:border-transparent outline-none transition-all placeholder:text-slate-400"
                            >
                            <button
                                type="button"
                                id="togglePassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-600 transition"
                                aria-label="Show password"
                            >
                                <span id="togglePasswordIcon" class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($message)) { ?>
                        <p class="text-red-600 text-sm"><?= htmlspecialchars($message) ?></p>
                    <?php } ?>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-600 bg-transparent">
                            <span class="text-sm text-slate-600 group-hover:text-slate-900 transition-colors">Remember this session</span>
                        </label>
                        <a class="text-sm font-medium text-red-600 hover:underline" href="superadmin_forgot_password.php">Forgot Password?</a>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3.5 rounded-lg transition-all flex items-center justify-center gap-2 shadow-lg hover:shadow-red-600/20 active:scale-[0.98]"
                    >
                        <span class="material-symbols-outlined text-xl">login</span>
                        Sign In as Superadmin
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between text-xs text-slate-400">
                    <span>Encrypted Connection</span>
                    <span>Multi-factor Enabled</span>
                </div>
            </section>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('passwordInput');
        const togglePassword = document.getElementById('togglePassword');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (passwordInput && togglePassword && togglePasswordIcon) {
            togglePassword.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';
                togglePasswordIcon.textContent = isHidden ? 'visibility_off' : 'visibility';
                togglePassword.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        }
    </script>
    <?php echo getBackButtonDetectionScript(); ?>
</body>
</html>