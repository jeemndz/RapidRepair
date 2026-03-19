<?php
include "db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

$notice = $_GET['notice'] ?? '';
$noticeTypeClass = '';
$noticeIcon = '';
$noticeTitle = '';
$noticeMessage = '';

switch ($notice) {
    case 'tenant_created_email_sent':
        $noticeTypeClass = 'bg-emerald-500';
        $noticeIcon = 'check_circle';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created and email was sent successfully.';
        break;
    case 'tenant_created_email_failed':
        $noticeTypeClass = 'bg-amber-500';
        $noticeIcon = 'warning';
        $noticeTitle = 'Tenant Created';
        $noticeMessage = 'Tenant was created, but the email could not be sent.';
        break;
    case 'tenant_create_failed':
        $noticeTypeClass = 'bg-red-500';
        $noticeIcon = 'error';
        $noticeTitle = 'Creation Failed';
        $noticeMessage = 'Could not create tenant. Please try again.';
        break;
}

// ✅ Generate unique login slug
function generateSlug($conn, $shopName) {
    $slug = strtolower(trim($shopName));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    $originalSlug = $slug;
    $counter = 1;

    while (true) {
        $check = mysqli_query($conn, "SELECT tenantID FROM owners WHERE login_slug='$slug'");
        if (mysqli_num_rows($check) == 0) break;
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

// HANDLE CREATE TENANT
if (isset($_POST['createTenant'])) {

    $shopName = mysqli_real_escape_string($conn, $_POST['shopName']);
    $shopAddress = mysqli_real_escape_string($conn, $_POST['shopAddress']);
    $ownerName = mysqli_real_escape_string($conn, $_POST['ownerName']);
    $email = trim($_POST['email']);
    $contactNumber = mysqli_real_escape_string($conn, $_POST['contactNumber']);
    $tempPassword = $_POST['tempPassword'];

    // ✅ VALIDATE EMAIL (VERY IMPORTANT)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email address.");
    }

    // ✅ HASH PASSWORD
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

    // ✅ Generate tenant ID
    $getID = mysqli_query($conn, "SELECT tenantID FROM owners ORDER BY tenantID DESC LIMIT 1");

    if (mysqli_num_rows($getID) > 0) {
        $row = mysqli_fetch_assoc($getID);
        $newID = (int)$row['tenantID'] + 1;
    } else {
        $newID = 1;
    }

    $tenantID = str_pad($newID, 3, "0", STR_PAD_LEFT);

    // ✅ Generate slug
    $login_slug = generateSlug($conn, $shopName);

    // ✅ INSERT
    $insert = mysqli_query($conn, "
        INSERT INTO owners 
        (tenantID, ownerName, shopName, login_slug, email, contactNumber, shopAddress, password, first_login, status) 
        VALUES 
        ('$tenantID','$ownerName','$shopName','$login_slug','$email','$contactNumber','$shopAddress','$hashedPassword',1,'Pending')
    ");

    $emailSent = false;

    if ($insert) {

        // ✅ LOGIN LINK
        $baseURL = "https://rapidrepair-gygpcbczgyg0czek.southeastasia-01.azurewebsites.net";
        $loginLink = $baseURL . "/tenantlogin.php?shop=" . urlencode($login_slug);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ekalamosus224@gmail.com';
            $mail->Password = 'zepa ulgt ihei iphw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // ✅ FIX SSL (Azure)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // ✅ CLEAR ANY PREVIOUS RECIPIENTS
            $mail->clearAddresses();

            // ✅ SENDER
            $mail->setFrom('ekalamosus224@gmail.com', 'Rapid Repair Admin');

            // 🔥 DEBUG (REMOVE AFTER TESTING)
            error_log("Sending email to: " . $email);

            // ✅ RECEIVER (THIS IS THE FIX)
            $mail->addAddress($email, $ownerName);

            $mail->isHTML(true);
            $mail->Subject = 'Your Tenant Account - Rapid Repair';

            $mail->Body = "
                <h2>Welcome to Rapid Repair 🚗</h2>
                <p>Your tenant account has been created and is pending approval.</p>

                <p><strong>Shop Name:</strong> {$shopName}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Temporary Password:</strong> {$tempPassword}</p>

                <p><strong>Login Here:</strong><br>
                <a href='{$loginLink}'>{$loginLink}</a></p>

                <p>Status: Pending Approval</p>
                <p>Please log in and change your password immediately.</p>
            ";

            $mail->send();
            $emailSent = true;

        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
        }
    }

    // ✅ REDIRECT
    if ($insert && $emailSent) {
        header("Location: superaddtenants.php?notice=tenant_created_email_sent");
    } elseif ($insert) {
        header("Location: superaddtenants.php?notice=tenant_created_email_failed");
    } else {
        header("Location: superaddtenants.php?notice=tenant_create_failed");
    }

    exit;
}
?>

<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Superadmin - Tenant Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1152d4",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                },
            },
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
    <div class="flex min-h-screen overflow-hidden">

        <!-- Side Navigation -->
        <aside
            class="w-72 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shrink-0">
            <div class="p-6 flex items-center gap-3">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined block text-2xl">directions_car</span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                    Rapid Repair <span class="text-primary">SuperAdmin</span>
                </h2>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1">
                <!-- Tenants -->
                <a href="superadd.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">dashboard</span>
                    <p class="text-sm font-medium">Dashboard</p>
                </a>

                <!-- Tenants -->
                <a href="superaddtenants.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-semibold cursor-pointer hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined">group</span>
                    <p class="text-sm">Tenants</p>
                </a>

                <!-- System Health -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined">analytics</span>
                    <p class="text-sm font-medium">System Health</p>
                </a>

                <!-- Subscriptions -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined">payments</span>
                    <p class="text-sm font-medium">Subscriptions</p>
                </a>

                <!-- Settings -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined">settings</span>
                    <p class="text-sm font-medium">Settings</p>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <!-- Profile -->
                <a href="#"
                    class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                    <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 bg-cover bg-center"
                        style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAA7ZvS0RT24pYl7zsQUKsnC9inrzmoUQVQC8PvdcW5_q4FtMWEC8ZD9Ke8mBa8iRwi4vfG0NbuLhEY9U_mYTQt3gBMRoNS0jNV_aJYQ-QCLtauVwWdyP53SHmFLjb5bQvwjbvvF24yHFp3moy4K6rJ0tVvtMIzdIUNohESEbLUilTPScnQYQQutAW0bzWhFZkGsX1GwwAl_2_9yXjauFnRNg0uTHfeR3lnfDRxLlk9Jo_hIr7N64rr5SWZq57QEfMdbFLkygzUgb-A')">
                    </div>
                    <div class="flex flex-col min-w-0">
                        <h3 class="text-sm font-semibold truncate">Sup</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Superadmin</p>
                    </div>
                </a>

                <!-- Logout -->
                <a href="#"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 p-8 bg-background-light dark:bg-background-dark">
            <!-- Header -->
            <header class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">Tenant Management</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Manage and monitor platform tenants
                        and shop applications.</p>
                </div>
                <button onclick="openModal()"
                    class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg">
                    <span class="material-symbols-outlined">add</span> Add Tenant
                </button>
            </header>

            <!-- Tenant Table -->
            <div
                class="bg-white dark:bg-background-dark rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">
                                <th class="px-6 py-4">Shop Name</th>
                                <th class="px-6 py-4">Owner</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Created Date</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php
                            $query = "SELECT * FROM owners ORDER BY tenantID DESC";
                            $result = mysqli_query($conn, $query);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $statusColor = "emerald";
                                if (strtolower($row['status']) == "inactive")
                                    $statusColor = "red";
                                if (strtolower($row['status']) == "pending")
                                    $statusColor = "amber";
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-4"><?php echo $row['shopName']; ?> (ID:
                                        <?php echo $row['tenantID']; ?>)
                                    </td>
                                    <td class="px-6 py-4"><?php echo $row['ownerName']; ?><br><span
                                            class="text-xs text-slate-500"><?php echo $row['email']; ?></span></td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-1 text-xs font-semibold bg-<?php echo $statusColor; ?>-100 dark:bg-<?php echo $statusColor; ?>-900/30 text-<?php echo $statusColor; ?>-700 dark:text-<?php echo $statusColor; ?>-400 rounded-full">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-slate-400 hover:text-primary transition-colors"><span
                                                class="material-symbols-outlined">more_vert</span></button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Add Tenant Modal -->
            <div id="tenantModal"
                class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-xl rounded-xl shadow-2xl border flex flex-col overflow-hidden">
                    <div class="px-8 py-6 border-b flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold">Create New Tenant</h2>
                            <p class="text-sm text-gray-500">Onboard a new vendor to your platform</p>
                        </div>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-black">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <form method="POST" class="p-8 flex flex-col gap-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Shop Name</label>
                                <input name="shopName" class="border rounded-lg p-3" placeholder="Modern Boutique"
                                    required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Shop Address</label>
                                <input name="shopAddress" class="border rounded-lg p-3" placeholder="123 Main Street"
                                    required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Owner Name</label>
                                <input name="ownerName" class="border rounded-lg p-3" placeholder="Juan Dela Cruz"
                                    required>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Email</label>
                                <input name="email" type="email" class="border rounded-lg p-3"
                                    placeholder="owner@email.com" required>
                            </div>
                            <div class="flex flex-col gap-2 md:col-span-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Contact Number</label>
                                <input name="contactNumber" class="border rounded-lg p-3" placeholder="09123456789">
                            </div>
                            <div class="flex flex-col gap-2 md:col-span-2">
                                <label class="text-xs font-bold uppercase text-gray-500">Temporary Password</label>
                                <input name="tempPassword" type="text" class="border rounded-lg p-3"
                                    placeholder="TempPassword123" required>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <button type="button" onclick="closeModal()"
                                class="flex-1 border rounded-lg py-3">Cancel</button>
                            <button name="createTenant" class="flex-1 bg-primary text-white rounded-lg py-3">Create
                                Tenant</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($noticeTitle !== ''): ?>
                <div id="statusNotification"
                    class="fixed bottom-6 right-6 <?php echo $noticeTypeClass; ?> text-white px-6 py-4 rounded-lg shadow-lg flex items-center gap-3 transform translate-y-20 opacity-0 transition-all duration-500 z-50">
                    <span class="material-symbols-outlined"><?php echo htmlspecialchars($noticeIcon); ?></span>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($noticeTitle); ?></p>
                        <p class="text-sm"><?php echo htmlspecialchars($noticeMessage); ?></p>
                    </div>
                    <button onclick="closeNotification()" class="ml-4 text-white hover:text-gray-200">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            <?php endif; ?>

            <script>
                function openModal() { document.getElementById("tenantModal").classList.remove("hidden"); }
                function closeModal() { document.getElementById("tenantModal").classList.add("hidden"); }
            </script>

            <script>
                function showNotification() {
                    const notif = document.getElementById('statusNotification');
                    if (!notif) return;
                    notif.classList.remove('translate-y-20', 'opacity-0');
                    notif.classList.add('translate-y-0', 'opacity-100');

                    // Auto-hide after 5 seconds
                    setTimeout(() => closeNotification(), 5000);
                }

                function closeNotification() {
                    const notif = document.getElementById('statusNotification');
                    if (!notif) return;
                    notif.classList.add('translate-y-20', 'opacity-0');
                    notif.classList.remove('translate-y-0', 'opacity-100');
                }

                <?php if ($noticeTitle !== ''): ?>
                    window.onload = function() { showNotification(); }
                <?php endif; ?>
            </script>
</body>

</html>