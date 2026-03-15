<?php
session_start();
include "db.php";

// Check if tenant is logged in and temp password exists
if (!isset($_SESSION['tenantID']) || !isset($_SESSION['temp_pass']) || !isset($_SESSION['verification_code'])) {
    header("Location: tenantlogin.php");
    exit;
}

$tenantID = $_SESSION['tenantID'];
$error = "";

// Handle code verification
if (isset($_POST['verify'])) {
    $input_code = $_POST['verification_code'];

    if ($input_code == $_SESSION['verification_code']) {
        // Code is correct, update password in database
        $hashed_password = $_SESSION['temp_pass'];
        $update = mysqli_query($conn, "UPDATE owners SET password='$hashed_password', first_login=0 WHERE tenantID='$tenantID'");

        if ($update) {
            // Clear temp session variables
            unset($_SESSION['temp_pass']);
            unset($_SESSION['verification_code']);

            // Redirect to dashboard
            header("Location: dashboardadmin.php");
            exit;
        } else {
            $error = "Failed to update password. Try again.";
        }
    } else {
        $error = "Invalid verification code. Please check your email.";
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify Temporary Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "navy-dark": "#0f172a",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen">
    <div class="flex flex-col min-h-screen w-full">
        <!-- Top Navigation -->
        <header class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-dark px-6 md:px-10 py-3 sticky top-0 z-10">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center size-10 bg-primary rounded-lg text-white">
                    <span class="material-symbols-outlined">car_repair</span>
                </div>
                <h2 class="text-slate-900 dark:text-white text-lg font-bold">AutoFix Portal</h2>
            </div>
        </header>

        <!-- Main -->
        <main class="flex-1 flex flex-col items-center justify-center p-6 md:p-12">
            <div class="w-full max-w-2xl bg-white dark:bg-navy-dark rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="relative h-32 w-full bg-primary/10 flex flex-col justify-center px-8 border-b border-slate-100 dark:border-slate-800 overflow-hidden">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Verify Your New Password</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Enter the verification code sent to your email.</p>
                    <div class="absolute right-8 hidden sm:block">
                        <span class="material-symbols-outlined text-6xl text-primary/20">lock</span>
                    </div>
                </div>

                <div class="p-8">
                    <?php if ($error): ?>
                        <div class="mb-4 text-red-500 font-semibold"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <!-- Verification Code -->
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Verification Code</label>
                            <div class="relative flex items-center">
                                <span class="absolute left-4 material-symbols-outlined text-slate-400">confirmation_number</span>
                                <input type="text" name="verification_code" placeholder="Enter the code" class="w-full pl-12 pr-4 h-12 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" required/>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex flex-col sm:flex-row items-center gap-4 pt-4">
                            <button type="submit" name="verify" class="w-full sm:flex-1 h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-lg flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">verified</span> Verify
                            </button>
                            <a href="changetemppass.php" class="w-full sm:w-32 h-12 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-lg flex items-center justify-center">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>