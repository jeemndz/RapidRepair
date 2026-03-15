<?php
session_start();
require_once "db.php";

// ✅ basic auth guard (only logged in users)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// OPTIONAL: enable if you want only admin/staff
// if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'], true)) {
//     header("Location: dashboard.php");
//     exit();
// }

// filters
$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$type = trim($_GET['type'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = "";

// search
if ($q !== '') {
    $where[] = "(user_name LIKE ? OR action LIKE ? OR details LIKE ? OR entity_type LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like; $types .= "s";
    $params[] = $like; $types .= "s";
    $params[] = $like; $types .= "s";
    $params[] = $like; $types .= "s";
}
if ($action !== '') {
    $where[] = "action = ?";
    $params[] = $action; $types .= "s";
}
if ($type !== '') {
    $where[] = "entity_type = ?";
    $params[] = $type; $types .= "s";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$countSql = "SELECT COUNT(*) AS total FROM system_logs $whereSql";
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($total / $limit));

// fetch logs
$sql = "
    SELECT log_id, user_name, user_role, action, entity_type, entity_id, details, ip_address, created_at
    FROM system_logs
    $whereSql
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);

// add limit/offset params
$params2 = $params;
$types2  = $types . "ii";
$params2[] = $limit;
$params2[] = $offset;

$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// build action/type dropdown lists
$actionsRes = $conn->query("SELECT DISTINCT action FROM system_logs ORDER BY action ASC");
$actions = $actionsRes ? $actionsRes->fetch_all(MYSQLI_ASSOC) : [];

$typesRes = $conn->query("SELECT DISTINCT entity_type FROM system_logs WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type ASC");
$typesList = $typesRes ? $typesRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>System Logs | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="pagelayout.css">

    <style>
        .logs-wrap{ background:#fff; border:1px solid #eef2f6; border-radius:14px; box-shadow:0 10px 25px rgba(16,24,40,.08); overflow:hidden; }
        .logs-head{ padding:16px 18px; background:linear-gradient(135deg,#071f4a,#2a4485); color:#fff; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .logs-head h1{ margin:0; font-size:18px; font-weight:900; }
        .logs-head p{ margin:3px 0 0; font-size:13px; opacity:.9; }
        .filters{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .filters input, .filters select{ padding:10px 12px; border-radius:12px; border:1px solid rgba(255,255,255,.25); background:rgba(255,255,255,.15); color:#fff; outline:none; }
        .filters input::placeholder{ color:rgba(255,255,255,.85); }
        .filters select option{ color:#111; }
        .filters button{ padding:10px 12px; border-radius:12px; border:none; font-weight:800; cursor:pointer; background:#fff; color:#071f4a; }
        .filters a{ color:#fff; opacity:.9; text-decoration:underline; font-size:13px; }

        .table-wrap{ overflow-x:auto; }
        table{ width:100%; border-collapse:collapse; min-width:980px; }
        th, td{ padding:12px; border-bottom:1px solid #eef2f6; text-align:left; font-size:14px; white-space:nowrap; }
        thead th{ background:#0b2a66; color:#fff; position:sticky; top:0; z-index:1; }
        tbody tr:nth-child(even){ background:#f8fafc; }
        tbody tr:hover{ background:#eef3ff; }

        .badge{ display:inline-block; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:900; border:1px solid transparent; }
        .b-admin{ background:#fdecea; color:#b71c1c; border-color:#f5c6cb; }
        .b-staff{ background:#e8f0fe; color:#1e3a8a; border-color:#c7d2fe; }
        .b-unknown{ background:#f2f4f7; color:#344054; border-color:#eaecf0; }

        .pager{ display:flex; justify-content:space-between; align-items:center; padding:14px 16px; }
        .pager a{ padding:8px 12px; border-radius:10px; text-decoration:none; border:1px solid #e5e7eb; color:#111; font-weight:800; }
        .pager a.disabled{ opacity:.5; pointer-events:none; }
        .muted{ color:#667085; font-size:13px; }
        .empty{ text-align:center; padding:18px; color:#667085; font-weight:800; }

         /* dropdown base */
        .sidebar .dropdown-menu {
            display: none;
            list-style: none;
            padding-left: 12px;
            margin: 6px 0 0 0;
        }

        /* when opened */
        .sidebar .dropdown.open .dropdown-menu {
            display: block;
        }

        /* optional: make the toggle look clickable */
        .sidebar .dropdown-toggle {
            display: block;
            cursor: pointer;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>
    <div></div>
    <div class="user-info">
        <img src="pictures/user.png" alt="User">
        <div>
            <strong>Welcome!</strong><br>
            <span><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
        </div>
    </div>
</header>

<div class="layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboardadmin.php">Dashboard</a></li>
                <li><a href="bookingadmin.php">Bookings</a></li>
                <li><a href="vehicleadmin.php">Vehicles</a></li>
                <li><a href="clientrecordsadmin.php">Client Records</a></li>
                <li><a href="servicesadmin.php">Service & Invoice</a></li>
                <li><a href="reportsadmin.php">Reports</a></li>

                <!-- SETTINGS DROPDOWN -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Settings ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="manage_services.php">Manage Services</a></li>
                        <li><a href="manage_users.php">Manage User Accounts</a></li>
                        <li><a href="backup_restore.php">Back / Restore Data</a></li>
                        <li class="active"><a href="system_logs.php">System Logs</a></li>
                    </ul>
                </li>
            </ul>

            <div class="logout">
                <a href="logout.php">Logout</a>
            </div>
        </aside>

    <main class="content">
        <div class="logs-wrap">
            <div class="logs-head">
                <div>
                    <h1>System Logs</h1>
                    <p>Track who did what, when, and to which record.</p>
                </div>

                <form class="filters" method="GET">
                    <input type="text" name="q" placeholder="Search logs..." value="<?= htmlspecialchars($q) ?>">
                    <select name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $aRow): ?>
                            <?php $aVal = $aRow['action']; ?>
                            <option value="<?= htmlspecialchars($aVal) ?>" <?= ($action === $aVal ? 'selected' : '') ?>>
                                <?= htmlspecialchars($aVal) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="type">
                        <option value="">All Types</option>
                        <?php foreach ($typesList as $tRow): ?>
                            <?php $tVal = $tRow['entity_type']; ?>
                            <option value="<?= htmlspecialchars($tVal) ?>" <?= ($type === $tVal ? 'selected' : '') ?>>
                                <?= htmlspecialchars($tVal) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">Filter</button>
                    <a href="system_logs.php">Reset</a>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$logs): ?>
                            <tr><td colspan="7" class="empty">No logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): ?>
                                <?php
                                    $role = strtolower(trim((string)($row['user_role'] ?? '')));
                                    $roleClass = $role === 'admin' ? 'b-admin' : ($role === 'staff' ? 'b-staff' : 'b-unknown');

                                    $entity = '—';
                                    if (!empty($row['entity_type'])) {
                                        $entity = htmlspecialchars($row['entity_type']) . ($row['entity_id'] ? (' #' . (int)$row['entity_id']) : '');
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    <td><?= htmlspecialchars($row['user_name'] ?? 'Unknown') ?></td>
                                    <td><span class="badge <?= $roleClass ?>"><?= htmlspecialchars($row['user_role'] ?? '—') ?></span></td>
                                    <td><?= htmlspecialchars($row['action']) ?></td>
                                    <td><?= $entity ?></td>
                                    <td><?= htmlspecialchars($row['details'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // build query string for pagination
            $base = $_GET;
            ?>
            <div class="pager">
                <div class="muted">Total: <?= (int)$total ?> logs • Page <?= (int)$page ?> / <?= (int)$totalPages ?></div>
                <div style="display:flex; gap:10px;">
                    <?php
                        $prev = $page - 1;
                        $next = $page + 1;

                        $base['page'] = $prev;
                        $prevUrl = "system_logs.php?" . http_build_query($base);

                        $base['page'] = $next;
                        $nextUrl = "system_logs.php?" . http_build_query($base);
                    ?>
                    <a class="<?= ($page <= 1 ? 'disabled' : '') ?>" href="<?= htmlspecialchars($prevUrl) ?>">Prev</a>
                    <a class="<?= ($page >= $totalPages ? 'disabled' : '') ?>" href="<?= htmlspecialchars($nextUrl) ?>">Next</a>
                </div>
            </div>

        </div>
    </main>
</div>

  <!-- SIDEBAR DROPDOWN TOGGLE -->
    <script>
        document.querySelectorAll(".dropdown-toggle").forEach(toggle => {
            toggle.addEventListener("click", (e) => {
                e.preventDefault();
                const li = toggle.closest(".dropdown");
                li.classList.toggle("open");
            });
        });

        document.addEventListener("click", (e) => {
            const dropdown = document.querySelector(".sidebar .dropdown");
            if (!dropdown) return;
            if (!e.target.closest(".sidebar")) dropdown.classList.remove("open");
        });
    </script>

</body>
</html>
