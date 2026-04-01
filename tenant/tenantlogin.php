<?php
session_start();
include __DIR__ . "/../db.php";

$error = "";

// Get the requested shop from URL parameter or POST data
$requestedShop = isset($_GET['shop']) ? trim($_GET['shop']) : '';
if (!$requestedShop && isset($_POST['shop'])) {
    $requestedShop = trim($_POST['shop']);
}

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
$welcome_subtext = $customization['welcome_subtext'] ?? 'The all-in-one platform for modern automotive service centers to manage scheduling, inventory, and customer relations.';
$hero_image_path = $customization['hero_image_path'] ?? '';
$hero_image = $hero_image_path !== '' ? '../pictures/' . $hero_image_path : 'https://lh3.googleusercontent.com/aida-public/AB6AXuDSvLJ3cZ6ER79yp4o0Y6WzI13dqdVNHhZHyLZ4Kme87pJYEmODEmNSRjQ0g63jOoVZm4UaDpyBha6ec962kjUuNBIniN-rnrETo8k-FO4-O39ZFYyuu6p97SuzraheAFkzXxwABqt3ur6ZemstwDJC3DK8JRm5f8I_Wg39e4nQFobYSlTPUeKHAi9IREjo2PztGF8l1xTOkR0Thn92ufrXf2K5DCTcgO9BDNrLqPYjloFAqFRHq3Wug_cHDUq7vyyX-0hUWfzOyqxn';

if (isset($_POST['login'])) {

    $loginInput = isset($_POST['login_input']) ? trim($_POST['login_input']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $requestedShop = strtolower(trim($requestedShop));

    if ($requestedShop === '') {
        $error = "Invalid login link. Please use your shop's login page.";
    } elseif ($loginInput === '') {
        $error = "Enter your username or email.";
    } else {
        // Validate login within the exact shop context to block cross-tenant access.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM owners 
             WHERE login_slug = ? 
             AND (email = ? OR username = ?)
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "sss", $requestedShop, $loginInput, $loginInput);
        mysqli_stmt_execute($stmt);
        $query = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($query);

        if ($user) {

            // Check if this is the first login
            if (isset($user['first_login']) && $user['first_login'] == 1) {
                // First login: compare plain text password
                if ($password === $user['password']) {
                    $_SESSION['tenantID'] = $user['tenantID'];
                    $_SESSION['shopName'] = $user['shopName'];
                    $_SESSION['login_slug'] = isset($user['login_slug']) ? $user['login_slug'] : '';

                    // --- Tenant login logging (new ENUM schema) ---
                    $tenantID = (int) $user['tenantID'];
                    $user_id = $tenantID;
                    $user_name = $user['shopName'];
                    $user_role = 'admin'; // Default to admin for tenant login
                    $actionLog = 'LOGIN';
                    $entity_type = 'tenant';
                    $entity_id = $tenantID;
                    $details = 'Tenant logged in (first login)';
                    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
                    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                    if ($tenantID) {
                        $stmt = $conn->prepare("INSERT INTO system_logs (tenantID, user_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param('iissssisss', $tenantID, $user_id, $user_name, $user_role, $actionLog, $entity_type, $entity_id, $details, $ip_address, $user_agent);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // First-time owners must update their temporary password
                    header("Location: changetemppass.php");
                    exit;
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                // Subsequent logins: use hashed password verification
                if (password_verify($password, $user['password'])) {
                    $_SESSION['tenantID'] = $user['tenantID'];
                    $_SESSION['shopName'] = $user['shopName'];
                    $_SESSION['login_slug'] = isset($user['login_slug']) ? $user['login_slug'] : '';

                    // --- Tenant login logging (new ENUM schema) ---
                    $tenantID = (int) $user['tenantID'];
                    $user_id = $tenantID;
                    $user_name = $user['shopName'];
                    $user_role = 'admin'; // Default to admin for tenant login
                    $actionLog = 'LOGIN';
                    $entity_type = 'tenant';
                    $entity_id = $tenantID;
                    $details = 'Tenant logged in';
                    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_USER'] : '';
                    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                    if ($tenantID) {
                        $stmt = $conn->prepare("INSERT INTO system_logs (tenantID, user_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param('iissssisss', $tenantID, $user_id, $user_name, $user_role, $actionLog, $entity_type, $entity_id, $details, $ip_address, $user_agent);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Returning owners proceed directly to dashboard
                    header("Location: dashboardadmin.php");
                    exit;
                } else {
                    $error = "Incorrect password.";
                }
            }

        } else {
            $error = "This username or email is not authorized for this shop login page.";
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />

    <title>RapidRepair - Partner Login</title>

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
                        <?php echo htmlspecialchars($welcome_heading); ?>
                    </h1>

                    <p class="text-slate-300 text-lg">
                        <?php echo htmlspecialchars($welcome_subtext); ?>
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
                        <?php echo htmlspecialchars($shop_name); ?> Login
                    </h2>

                    <p class="text-slate-600">
                        Access your shop's dashboard and management tools.
                    </p>

                </div>

                <?php if ($error != "") { ?>
                    <div class="mb-4 text-red-500 text-sm font-semibold">
                        <?php echo $error; ?>
                    </div>
                <?php } ?>
                <form method="POST" class="space-y-5">
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
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="text-sm font-semibold text-slate-700">Password</label>
                            <a class="text-xs font-bold text-primary hover:underline" href="#">
                                Forgot password?
                            </a>
                        </div>

                        <div
                            class="flex items-stretch rounded-xl overflow-hidden border border-slate-200 focus-within:border-primary transition-colors bg-slate-50">

                            <div class="flex items-center justify-center bg-slate-100 px-3 border-r border-slate-200">
                                <span class="material-symbols-outlined text-gray-custom text-xl">lock</span>
                            </div>

                            <input id="passwordInput" name="password"
                                class="w-full border-none text-slate-900 px-4 py-3 focus:ring-0 text-sm bg-transparent"
                                placeholder="••••••••" type="password" required />

                            <button type="button" id="togglePassword"
                                class="flex items-center justify-center bg-slate-100 px-3 border-l border-slate-200 text-slate-500 hover:text-primary transition-colors"
                                aria-label="Show password">
                                <span id="togglePasswordIcon"
                                    class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div class="pt-4">

                        <button name="login"
                            class="w-full h-12 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl primary-glow transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">

                            <span>Sign In to Dashboard</span>

                            <span class="material-symbols-outlined text-lg">
                                arrow_forward
                            </span>

                        </button>

                    </div>

                </form>

                <div class="mt-8 text-center">

                    <p class="text-sm text-slate-600">

                        New partner shop?

                        <a class="text-primary font-bold hover:underline" href="#">
                            Start your 14-day free trial
                        </a>

                    </p>

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

    <a href="logincustom.php">

        <button
            class="fixed bottom-8 right-8 z-50 w-14 h-14 bg-primary text-white rounded-full flex items-center justify-center primary-glow hover:scale-110 active:scale-95 transition-all shadow-lg cursor-pointer">

            <span class="material-symbols-outlined text-3xl">
                edit
            </span>

        </button>

    </a>

    <script>
    const passwordInput = document.getElementById('passwordInput');
    const togglePassword = document.getElementById('togglePassword');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');

    if (passwordInput && togglePassword && togglePasswordIcon) {
        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';

            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            togglePasswordIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
            togglePassword.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    }
</script>

</body>

</html>