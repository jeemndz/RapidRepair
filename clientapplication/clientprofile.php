<?php
session_start();
include __DIR__ . "/../db.php";

if (!isset($_SESSION['client_id'])) {
    header("Location: clientlogin.php");
    exit();
}

$clientID = (int) $_SESSION['client_id'];
$client = null;
$errorMessage = "";
$successMessage = "";

function safeText($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function usersColumnExists($conn, $columnName)
{
    $safeColumn = mysqli_real_escape_string($conn, $columnName);
    $checkSql = "SHOW COLUMNS FROM users LIKE '$safeColumn'";
    $check = mysqli_query($conn, $checkSql);
    return $check && mysqli_num_rows($check) > 0;
}

$hasUpdatedAt = usersColumnExists($conn, 'updated_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateProfile'])) {
    $fullName = trim((string) ($_POST['fullName'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $contactNumber = trim((string) ($_POST['contactNumber'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));

    if ($fullName === '' || $username === '' || $email === '' || $contactNumber === '' || $address === '') {
        $errorMessage = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        $duplicateSql = "SELECT user_id FROM users WHERE (email = ? OR username = ?) AND user_id != ? LIMIT 1";
        $duplicateStmt = mysqli_prepare($conn, $duplicateSql);

        if ($duplicateStmt) {
            mysqli_stmt_bind_param($duplicateStmt, "ssi", $email, $username, $clientID);
            mysqli_stmt_execute($duplicateStmt);
            $duplicateResult = mysqli_stmt_get_result($duplicateStmt);

            if ($duplicateResult && mysqli_num_rows($duplicateResult) > 0) {
                $errorMessage = "Email or username is already used by another account.";
            }

            mysqli_stmt_close($duplicateStmt);
        } else {
            $errorMessage = "Unable to check account details right now.";
        }

        if ($errorMessage === '') {
            if ($hasUpdatedAt) {
                $updateSql = "UPDATE users SET fullName = ?, username = ?, email = ?, contactNumber = ?, address = ?, updated_at = NOW() WHERE user_id = ?";
            } else {
                $updateSql = "UPDATE users SET fullName = ?, username = ?, email = ?, contactNumber = ?, address = ? WHERE user_id = ?";
            }

            $updateStmt = mysqli_prepare($conn, $updateSql);

            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, "sssssi", $fullName, $username, $email, $contactNumber, $address, $clientID);

                if (mysqli_stmt_execute($updateStmt)) {
                    $successMessage = "Profile updated successfully.";
                } else {
                    $errorMessage = "Unable to update profile. Please try again.";
                }

                mysqli_stmt_close($updateStmt);
            } else {
                $errorMessage = "Unable to prepare profile update.";
            }
        }
    }
}

$clientSql = "SELECT user_id, tenantID, fullName, username, address, email, contactNumber, role FROM users WHERE user_id = ? LIMIT 1";
$clientStmt = mysqli_prepare($conn, $clientSql);

if ($clientStmt) {
    mysqli_stmt_bind_param($clientStmt, "i", $clientID);
    mysqli_stmt_execute($clientStmt);
    $clientResult = mysqli_stmt_get_result($clientStmt);

    if ($clientResult && mysqli_num_rows($clientResult) > 0) {
        $client = mysqli_fetch_assoc($clientResult);
    }

    mysqli_stmt_close($clientStmt);
}

if (!$client) {
    session_destroy();
    header("Location: clientlogin.php");
    exit();
}

$shopName = "RapidRepairCo.";
if (!empty($client['tenantID'])) {
    $tenantID = (int) $client['tenantID'];
    $shopSql = "SELECT shopName FROM owners WHERE tenantID = ? LIMIT 1";
    $shopStmt = mysqli_prepare($conn, $shopSql);

    if ($shopStmt) {
        mysqli_stmt_bind_param($shopStmt, "i", $tenantID);
        mysqli_stmt_execute($shopStmt);
        $shopResult = mysqli_stmt_get_result($shopStmt);

        if ($shopResult && mysqli_num_rows($shopResult) > 0) {
            $shop = mysqli_fetch_assoc($shopResult);
            if (!empty($shop['shopName'])) {
                $shopName = $shop['shopName'];
            }
        }

        mysqli_stmt_close($shopStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Client Profile | RapidRepairCo.</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1152d4',
                        surface: '#f6f6f8',
                        outline: '#e2e8f0',
                        muted: '#64748b'
                    },
                    fontFamily: {
                        body: ['Inter']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f6f6f8;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-surface text-slate-900">
    <nav class="fixed top-0 w-full z-50 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-3">
            <a href="clientlanding.php" class="text-xl font-black tracking-tighter text-primary">RapidRepairCo.</a>

            <div class="hidden md:flex items-center gap-8">
                <a href="clientlanding.php#features" class="font-medium text-sm text-slate-600 hover:text-primary transition-colors">Features</a>
                <a href="clientlanding.php#pricing" class="font-medium text-sm text-slate-600 hover:text-primary transition-colors">Pricing</a>
                <a href="clientlanding.php#about" class="font-medium text-sm text-slate-600 hover:text-primary transition-colors">About</a>
                <a href="clientlanding.php#support" class="font-medium text-sm text-slate-600 hover:text-primary transition-colors">Support</a>
            </div>

            <div class="relative group">
                <button class="w-10 h-10 inline-flex items-center justify-center rounded-full border border-primary/30 text-primary hover:bg-primary/5 transition-all">
                    <span class="material-symbols-outlined">account_circle</span>
                </button>

                <div class="absolute right-0 mt-2 w-44 bg-white border border-outline rounded-xl shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 overflow-hidden">
                    <a href="clientprofile.php" class="flex items-center gap-2 px-4 py-3 text-sm bg-slate-50 text-primary font-semibold">
                        <span class="material-symbols-outlined text-[18px]">person</span>
                        Profile
                    </a>

                    <a href="../logout/logout.php?redirect=clientlanding.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-all">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-16 px-6">
        <div class="max-w-6xl mx-auto">
            <div class="mb-8">
                <p class="text-xs font-black uppercase tracking-widest text-primary mb-2">Client Account</p>
                <h1 class="text-4xl font-black tracking-tight">My Profile</h1>
                <p class="text-sm text-slate-500 mt-2">Manage your personal details.</p>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">
                    <?php echo safeText($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    <?php echo safeText($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <aside class="lg:col-span-1">
                    <div class="bg-white border border-outline rounded-2xl shadow-sm p-6 sticky top-24">
                        <div class="w-20 h-20 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-5">
                            <span class="material-symbols-outlined text-5xl">account_circle</span>
                        </div>

                        <h2 class="text-xl font-black tracking-tight">
                            <?php echo safeText($client['fullName']); ?>
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">
                            @<?php echo safeText($client['username']); ?>
                        </p>

                        <div class="mt-6 space-y-4 text-sm">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary text-[20px]">storefront</span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Registered Shop</p>
                                    <p class="font-semibold text-slate-700"><?php echo safeText($shopName); ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary text-[20px]">mail</span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Email</p>
                                    <p class="font-semibold text-slate-700 break-all"><?php echo safeText($client['email']); ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary text-[20px]">call</span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Contact Number</p>
                                    <p class="font-semibold text-slate-700"><?php echo safeText($client['contactNumber']); ?></p>
                                </div>
                            </div>
                        </div>

                        <a href="../logout/logout.php?redirect=clientlanding.php" class="mt-6 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 text-red-600 hover:bg-red-50 py-3 text-sm font-bold transition-all">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                            Logout
                        </a>
                    </div>
                </aside>

                <section class="lg:col-span-2">
                    <div class="bg-white border border-outline rounded-2xl shadow-sm p-6 md:p-8">
                        <div class="flex items-center justify-between gap-4 mb-8">
                            <div>
                                <h2 class="text-2xl font-black tracking-tight">Personal Details</h2>
                                <p class="text-sm text-slate-500 mt-1">Update your name, username, contact number, and address.</p>
                            </div>
                            <span class="hidden md:inline-flex items-center gap-2 rounded-full bg-primary/10 text-primary px-4 py-2 text-xs font-bold">
                                <span class="material-symbols-outlined text-[16px]">verified_user</span>
                                Client
                            </span>
                        </div>

                        <form method="post" action="" class="space-y-6">
                            <input type="hidden" name="updateProfile" value="1" />

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Full Name</label>
                                    <input type="text" name="fullName" required value="<?php echo safeText($client['fullName']); ?>" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:border-primary focus:ring-primary text-sm px-4 py-3" />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Username</label>
                                    <input type="text" name="username" required value="<?php echo safeText($client['username']); ?>" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:border-primary focus:ring-primary text-sm px-4 py-3" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Email Address</label>
                                    <input type="email" name="email" required value="<?php echo safeText($client['email']); ?>" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:border-primary focus:ring-primary text-sm px-4 py-3" />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Contact Number</label>
                                    <input type="tel" name="contactNumber" required value="<?php echo safeText($client['contactNumber']); ?>" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:border-primary focus:ring-primary text-sm px-4 py-3" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Address</label>
                                <textarea name="address" required rows="4" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:border-primary focus:ring-primary text-sm px-4 py-3 resize-none"><?php echo safeText($client['address']); ?></textarea>
                            </div>

                            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
                                <a href="clientlanding.php" class="inline-flex items-center justify-center rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 px-5 py-3 text-sm font-bold transition-all">
                                    Back to Home
                                </a>

                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary text-white hover:bg-primary/90 px-5 py-3 text-sm font-bold transition-all">
                                    <span class="material-symbols-outlined text-[18px]">save</span>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>

</html>
