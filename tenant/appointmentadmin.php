<?php
session_start();
include __DIR__ . '/../db.php';

if (!isset($_SESSION['tenantID']) || !isset($_SESSION['login_slug'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];
$loginSlug = (string) $_SESSION['login_slug'];

$ownerStmt = mysqli_prepare($conn, "SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1");
mysqli_stmt_bind_param($ownerStmt, 'is', $tenantID, $loginSlug);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$owner = $ownerResult ? mysqli_fetch_assoc($ownerResult) : null;
mysqli_stmt_close($ownerStmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

if (empty($_SESSION['appointment_csrf'])) {
    $_SESSION['appointment_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['appointment_csrf'];

$allowedStatuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
$actionMessage = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $postedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $appointmentID = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
    $newStatus = isset($_POST['status']) ? (string) $_POST['status'] : '';

    if (!hash_equals($csrfToken, $postedToken)) {
        $actionError = 'Invalid request token. Please refresh and try again.';
    } elseif ($appointmentID <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        $actionError = 'Invalid status update request.';
    } else {
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ? AND tenantID = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($updateStmt, 'sii', $newStatus, $appointmentID, $tenantID);
        mysqli_stmt_execute($updateStmt);

        if (mysqli_stmt_affected_rows($updateStmt) >= 0) {
            $actionMessage = 'Appointment status updated successfully.';
        } else {
            $actionError = 'Unable to update appointment status right now.';
        }
        mysqli_stmt_close($updateStmt);
    }
}

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'Pending';
if (!in_array($statusFilter, array_merge(['All'], $allowedStatuses), true)) {
    $statusFilter = 'Pending';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$todayLoad = 0;
$todayCompleted = 0;
$pendingCount = 0;
$weekCount = 0;
$nextAvailable = 'No upcoming schedule';

$statsSql = "
    SELECT
        SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) AS today_load,
        SUM(CASE WHEN appointment_date = CURDATE() AND status = 'Completed' THEN 1 ELSE 0 END) AS today_completed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS weekly_count
    FROM appointments
    WHERE tenantID = $tenantID
";
$statsResult = mysqli_query($conn, $statsSql);
if ($statsResult && ($statsRow = mysqli_fetch_assoc($statsResult))) {
    $todayLoad = (int) ($statsRow['today_load'] ?? 0);
    $todayCompleted = (int) ($statsRow['today_completed'] ?? 0);
    $pendingCount = (int) ($statsRow['pending_count'] ?? 0);
    $weekCount = (int) ($statsRow['weekly_count'] ?? 0);
}

$nextSql = "
    SELECT appointment_date, appointment_time
    FROM appointments
    WHERE tenantID = $tenantID
      AND status IN ('Pending', 'Confirmed', 'In Progress')
      AND (appointment_date > CURDATE() OR (appointment_date = CURDATE() AND appointment_time >= CURTIME()))
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 1
";
$nextResult = mysqli_query($conn, $nextSql);
if ($nextResult && ($nextRow = mysqli_fetch_assoc($nextResult))) {
    $nextAvailable = date('M d, Y', strtotime((string) $nextRow['appointment_date'])) . ' ' . date('h:i A', strtotime((string) $nextRow['appointment_time']));
}

$whereParts = ["a.tenantID = $tenantID"];
if ($statusFilter !== 'All') {
    $safeStatus = mysqli_real_escape_string($conn, $statusFilter);
    $whereParts[] = "a.status = '$safeStatus'";
}
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $whereParts[] = "(
        u.fullName LIKE '%$safeSearch%'
        OR CONCAT(IFNULL(v.year_model, ''), ' ', IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) LIKE '%$safeSearch%'
        OR IFNULL(v.plate_number, '') LIKE '%$safeSearch%'
        OR IFNULL(s.service_name, '') LIKE '%$safeSearch%'
        OR IFNULL(a.notes, '') LIKE '%$safeSearch%'
    )";
}

$appointmentsSql = "
    SELECT
        a.appointment_id,
        a.user_id,
        a.vehicle_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
        COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
        v.year_model,
        v.brand,
        v.model,
        v.plate_number,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No service linked') AS requested_services
    FROM appointments a
    LEFT JOIN users u ON u.user_id = a.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
    LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id
    LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
    WHERE " . implode(' AND ', $whereParts) . "
    GROUP BY
        a.appointment_id,
        a.user_id,
        a.vehicle_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.total_amount,
        u.fullName,
        v.year_model,
        v.brand,
        v.model,
        v.plate_number
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 200
";

$appointments = [];
$listResult = mysqli_query($conn, $appointmentsSql);
if ($listResult) {
    while ($row = mysqli_fetch_assoc($listResult)) {
        $appointments[] = $row;
    }
}

$upcomingSql = "
    SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        COALESCE(u.fullName, CONCAT('User #', a.user_id)) AS customer_name,
        CONCAT(IFNULL(v.brand, ''), ' ', IFNULL(v.model, '')) AS vehicle_name,
        COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'No service linked') AS requested_services
    FROM appointments a
    LEFT JOIN users u ON u.user_id = a.user_id
    LEFT JOIN vehicleinformation v ON v.vehicle_id = a.vehicle_id AND v.tenantID = a.tenantID
    LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id
    LEFT JOIN services s ON s.service_id = aps.service_id AND s.tenantID = a.tenantID
    WHERE a.tenantID = $tenantID
      AND a.status IN ('Confirmed', 'In Progress')
      AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
    GROUP BY
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        u.fullName,
        a.user_id,
        v.brand,
        v.model
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";

$upcomingAppointments = [];
$upcomingResult = mysqli_query($conn, $upcomingSql);
if ($upcomingResult) {
    while ($row = mysqli_fetch_assoc($upcomingResult)) {
        $upcomingAppointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo h($owner['shopName']); ?> | Appointment Management</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>

<body class="bg-slate-50 text-slate-900 antialiased flex">
    <aside class="w-64 flex-shrink-0 border-r border-slate-200 bg-white h-screen sticky top-0 flex flex-col overflow-y-auto">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-blue-600 rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($owner['shopName']); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1 text-sm">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="dashboardadmin.php"><span class="material-symbols-outlined text-[22px]">dashboard</span>Dashboard</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="repairjobsadmin.php"><span class="material-symbols-outlined text-[22px]">build</span>Repair Jobs</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="vehicleadmin.php"><span class="material-symbols-outlined text-[22px]">directions_car</span>Vehicles</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-blue-50 text-blue-700 font-semibold" href="appointmentadmin.php"><span class="material-symbols-outlined text-[22px]">event</span>Appointments</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="reportsadmin.php"><span class="material-symbols-outlined text-[22px]">description</span>Reports</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="inventoryadmin.php"><span class="material-symbols-outlined text-[22px]">inventory_2</span>Inventory</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="customeradmin.php"><span class="material-symbols-outlined text-[22px]">group</span>Customers</a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="paymentsadmin.php"><span class="material-symbols-outlined text-[22px]">payments</span>Payments</a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100" href="settingsadmin.php"><span class="material-symbols-outlined text-[22px]">settings</span>Settings</a>
                </div>
            </nav>
        </div>
        <div class="p-4 border-t border-slate-200">
            <form method="post" action="../logout/logout.php">
                <button type="submit" class="w-full flex items-center justify-center gap-2 text-slate-500 hover:text-red-600">
                    <span class="material-symbols-outlined">logout</span>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 min-h-screen">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur px-8 h-16 flex items-center justify-between">
            <h2 class="text-lg font-black tracking-tight">Appointment Management</h2>
            <span class="text-xs text-slate-500">Shop login slug: <?php echo h($loginSlug); ?></span>
        </header>

        <div class="p-8 space-y-6">
            <?php if ($actionMessage !== ''): ?>
                <div class="rounded-lg border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm font-medium"><?php echo h($actionMessage); ?></div>
            <?php endif; ?>
            <?php if ($actionError !== ''): ?>
                <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm font-medium"><?php echo h($actionError); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase">Today's Load</p>
                    <p class="text-2xl font-black mt-2"><?php echo h($todayLoad); ?></p>
                    <p class="text-xs text-slate-400 mt-1">Appointments for today</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase">Completed Today</p>
                    <p class="text-2xl font-black mt-2"><?php echo h($todayCompleted); ?></p>
                    <p class="text-xs text-slate-400 mt-1">Finished service visits</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase">Pending Bookings</p>
                    <p class="text-2xl font-black mt-2 text-blue-700"><?php echo h($pendingCount); ?></p>
                    <p class="text-xs text-slate-400 mt-1">Need action</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-500 uppercase">Next Available</p>
                    <p class="text-sm font-bold mt-2"><?php echo h($nextAvailable); ?></p>
                    <p class="text-xs text-slate-400 mt-1">This week total: <?php echo h($weekCount); ?></p>
                </div>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div class="md:col-span-2">
                            <label class="text-xs font-bold uppercase text-slate-500">Search</label>
                            <input name="search" value="<?php echo h($search); ?>" placeholder="Customer, vehicle, plate, service, notes" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500">Status</label>
                            <select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <?php foreach (array_merge(['All'], $allowedStatuses) as $status): ?>
                                    <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold" type="submit">Apply</button>
                            <a href="appointmentadmin.php" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-600">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Vehicle</th>
                                <th class="px-5 py-3">Services</th>
                                <th class="px-5 py-3">Date / Time</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($appointments) === 0): ?>
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-slate-500">No appointments found for this filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $row): ?>
                                    <?php
                                        $vehicleText = trim(((string) $row['year_model']) . ' ' . ((string) $row['brand']) . ' ' . ((string) $row['model']));
                                        if ($vehicleText === '') {
                                            $vehicleText = 'Vehicle #' . (int) $row['vehicle_id'];
                                        }
                                        $plate = trim((string) ($row['plate_number'] ?? ''));
                                        $status = (string) ($row['status'] ?? 'Pending');
                                        $badge = 'bg-slate-100 text-slate-700';
                                        if ($status === 'Pending') {
                                            $badge = 'bg-amber-100 text-amber-700';
                                        } elseif ($status === 'Confirmed') {
                                            $badge = 'bg-blue-100 text-blue-700';
                                        } elseif ($status === 'In Progress') {
                                            $badge = 'bg-indigo-100 text-indigo-700';
                                        } elseif ($status === 'Completed') {
                                            $badge = 'bg-green-100 text-green-700';
                                        } elseif ($status === 'Cancelled') {
                                            $badge = 'bg-red-100 text-red-700';
                                        }
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-4">
                                            <div class="font-semibold text-slate-900"><?php echo h($row['customer_name']); ?></div>
                                            <div class="text-xs text-slate-500">Appointment #<?php echo h($row['appointment_id']); ?></div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-slate-700"><?php echo h($vehicleText); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo $plate !== '' ? h($plate) : 'No plate'; ?></div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-700 max-w-xs"><?php echo h($row['requested_services']); ?></td>
                                        <td class="px-5 py-4">
                                            <div class="font-semibold"><?php echo h(date('M d, Y', strtotime((string) $row['appointment_date']))); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $row['appointment_time']))); ?></div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-bold <?php echo h($badge); ?>"><?php echo h($status); ?></span>
                                        </td>
                                        <td class="px-5 py-4 font-semibold">
                                            <?php
                                                $amount = $row['total_amount'];
                                                echo $amount !== null ? '$' . number_format((float) $amount, 2) : 'N/A';
                                            ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <form method="post" class="flex items-center gap-2 justify-end">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>" />
                                                <input type="hidden" name="appointment_id" value="<?php echo h($row['appointment_id']); ?>" />
                                                <input type="hidden" name="update_status" value="1" />
                                                <select name="status" class="rounded-lg border-slate-300 text-xs">
                                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                                        <option value="<?php echo h($statusOption); ?>" <?php echo $status === $statusOption ? 'selected' : ''; ?>><?php echo h($statusOption); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-semibold">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold">Upcoming Confirmed Queue</h3>
                    <span class="text-xs text-slate-500">Next 5 appointments</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php if (count($upcomingAppointments) === 0): ?>
                        <div class="px-5 py-8 text-sm text-slate-500">No confirmed appointments in queue.</div>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $item): ?>
                            <div class="px-5 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-slate-900"><?php echo h($item['customer_name']); ?> - <?php echo h(trim((string) $item['vehicle_name'])); ?></div>
                                        <div class="text-xs text-slate-500 mt-1"><?php echo h($item['requested_services']); ?></div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs font-bold text-blue-700"><?php echo h(date('M d, Y', strtotime((string) $item['appointment_date']))); ?></div>
                                        <div class="text-xs text-slate-500"><?php echo h(date('h:i A', strtotime((string) $item['appointment_time']))); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</body>

</html>
