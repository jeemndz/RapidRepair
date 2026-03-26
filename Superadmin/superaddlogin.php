<?php
// superaddlogin.php
session_start();
require_once __DIR__ . "/../db.php"; // your database connection file

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
<html class="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Superadmin Login | AutoFix Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
        
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-zinc-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-6xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[620px]">
            <!-- Left Branding Container -->
            <section class="bg-gradient-to-br from-black via-zinc-900 to-red-900 text-white p-10 lg:p-14 flex flex-col justify-center items-center text-center">
                <img src="../pictures/RRlogo2.png" alt="Rapid Repair logo" class="w-44 md:w-56 h-auto object-contain mb-8 mx-auto">
                <h1 class="text-3xl md:text-4xl font-bold leading-tight">Rapid Repair 
                    Super Admin Portal</h1>
                <p class="mt-3 text-slate-200 text-base md:text-lg max-w-md mx-auto">Car Repair Shop Management System</p>
                <p class="mt-8 text-sm text-red-100/90 max-w-md mx-auto">Welcome back. Sign in to manage tenants, reports, and subscriptions.</p>
            </section>

            <!-- Right Login Container -->
            <section class="p-8 md:p-12 flex flex-col justify-center">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Log In</h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-2">Please enter your credentials to access the SuperAdmin dashboard.</p>
                </div>

                <form class="space-y-5" method="POST" action="">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">Email address</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
                            <input type="email" name="email" id="email" placeholder="admin@gmail.com" required
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="password">Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock</span>
                            <input type="password" name="password" id="password" placeholder="Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢" required
                                class="w-full pl-10 pr-12 py-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400">
                        </div>
                    </div>

                    <?php if(!empty($message)) { ?>
                        <p class="text-red-600 text-sm"><?= htmlspecialchars($message) ?></p>
                    <?php } ?>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" class="rounded border-slate-300 dark:border-slate-700 text-primary focus:ring-primary bg-transparent">
                            <span class="text-sm text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-slate-200 transition-colors">Remember this session</span>
                        </label>
                        <a class="text-sm font-medium text-primary hover:underline" href="#">Forgot Password?</a>
                    </div>

                    <button type="submit"
                        class="w-full bg-navy-deep hover:bg-slate-800 text-white font-bold py-3.5 rounded-lg transition-all flex items-center justify-center gap-2 shadow-lg hover:shadow-navy-deep/20 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-xl">login</span>
                        Sign In as Superadmin
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs text-slate-400">
                    <span>Encrypted Connection</span>
                    <span>Multi-factor Enabled</span>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
