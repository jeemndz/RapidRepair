<?php
session_start();
require_once "db.php";

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$name = $_SESSION['name'] ?? 'Admin';

$successMsg = '';
$errorMsg = '';

// =========================
// Helpers
// =========================
function clean_service_name(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function clean_money($v): float {
    // allow "1,200.50"
    $v = str_replace(',', '', (string)$v);
    if ($v === '' || !is_numeric($v)) return -1;
    return (float)$v;
}

// =========================
// Handle ADD / EDIT / DELETE
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD
    if ($action === 'add') {
        $service_name = clean_service_name($_POST['service_name'] ?? '');
        $labor_fee    = clean_money($_POST['labor_fee'] ?? '');

        if ($service_name === '') {
            $errorMsg = "Service name is required.";
        } elseif ($labor_fee < 0) {
            $errorMsg = "Service price (labor fee) must be a valid number (0 or higher).";
        } else {
            $stmt = $conn->prepare("INSERT INTO service_types (service_name, labor_fee, is_active, created_at) VALUES (?, ?, 1, NOW())");
            $stmt->bind_param("sd", $service_name, $labor_fee);

            if ($stmt->execute()) {
                $successMsg = "Service added successfully.";
            } else {
                if ($conn->errno === 1062) {
                    $errorMsg = "That service already exists.";
                } else {
                    $errorMsg = "Database error: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }

    // EDIT
    if ($action === 'edit') {
        $service_id   = (int)($_POST['service_id'] ?? 0);
        $service_name = clean_service_name($_POST['service_name'] ?? '');
        $labor_fee    = clean_money($_POST['labor_fee'] ?? '');

        if ($service_id <= 0 || $service_name === '') {
            $errorMsg = "Invalid edit request.";
        } elseif ($labor_fee < 0) {
            $errorMsg = "Service price (labor fee) must be a valid number (0 or higher).";
        } else {
            $stmt = $conn->prepare("UPDATE service_types SET service_name = ?, labor_fee = ? WHERE service_id = ?");
            $stmt->bind_param("sdi", $service_name, $labor_fee, $service_id);

            if ($stmt->execute()) {
                $successMsg = "Service updated successfully.";
            } else {
                if ($conn->errno === 1062) {
                    $errorMsg = "That service name already exists.";
                } else {
                    $errorMsg = "Database error: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }

    // DELETE (soft delete)
    if ($action === 'delete') {
        $service_id = (int)($_POST['service_id'] ?? 0);

        if ($service_id <= 0) {
            $errorMsg = "Invalid delete request.";
        } else {
            $stmt = $conn->prepare("UPDATE service_types SET is_active = 0 WHERE service_id = ?");
            $stmt->bind_param("i", $service_id);

            if ($stmt->execute()) {
                $successMsg = "Service deleted (disabled) successfully.";
            } else {
                $errorMsg = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// =========================
// Fetch services
// =========================
$services = [];
$res = $conn->query("
    SELECT service_id, service_name, labor_fee, is_active
    FROM service_types
    ORDER BY is_active DESC, service_name ASC
");
if ($res) {
    $services = $res->fetch_all(MYSQLI_ASSOC);
}

// For datalist suggestions (active only)
$suggestions = [];
$res2 = $conn->query("SELECT service_name FROM service_types WHERE is_active = 1 ORDER BY service_name ASC");
if ($res2) {
    while ($r = $res2->fetch_assoc()) $suggestions[] = $r['service_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="manage_services.css">

    <style>
        .card{
            background:#fff;
            border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,.08);
            padding:18px;
            margin-bottom:18px;
        }
        .card h3{ margin:0 0 8px; }

        .alert{
            padding:12px 14px;
            border-radius:8px;
            margin-bottom:14px;
            font-size:14px;
        }
        .alert-success{ background:#e6f4ea; border:1px solid #c3e6cb; color:#1e7e34; }
        .alert-error{ background:#fdecea; border:1px solid #f5c6cb; color:#b71c1c; }

        .service-form{
            display:flex;
            gap:10px;
            align-items:center;
            flex-wrap:wrap;
        }
        .service-form input{
            padding:10px 12px;
            border-radius:8px;
            border:1px solid #ccc;
            outline:none;
        }
        .service-form .name{
            width:min(420px, 100%);
        }
        .service-form .price{
            width:160px;
        }

        .service-form button{
            padding:10px 16px;
            border:none;
            border-radius:8px;
            cursor:pointer;
            background:#071f4a;
            color:#fff;
        }
        .service-form button:hover{ opacity:.92; }

        .table-wrapper{ overflow-x:auto; }
        table{ width:100%; border-collapse:collapse; background:#fff; }
        th, td{ padding:10px; border-bottom:1px solid #eee; text-align:left; }
        th{ background:#243f7d; color:#fff; white-space:nowrap; }

        .btn-row{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        .btn{
            border:none;
            border-radius:8px;
            padding:8px 12px;
            cursor:pointer;
            font-size:13px;
        }
        .btn-edit{ background:#254a91; color:#fff; }
        .btn-del{ background:#b30000; color:#fff; }
        .btn-edit:hover, .btn-del:hover{ opacity:.92; }

        .badge{
            display:inline-block;
            padding:4px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:600;
        }
        .badge.active{ background:#e6f4ea; color:#1e7e34; }
        .badge.inactive{ background:#fdecea; color:#b71c1c; }

        .price-cell{ font-weight:600; }
        .muted{ color:#666; font-size:13px; }

        /* EDIT MODAL */
        .modal{
            display:none;
            position:fixed;
            inset:0;
            background:rgba(0,0,0,0.55);
            justify-content:center;
            align-items:center;
            z-index:9999;
        }
        .modal-content{
            background:#fff;
            width:520px;
            max-width:calc(100% - 24px);
            border-radius:12px;
            padding:18px;
        }
        .modal-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            border-bottom:1px solid #eee;
            padding-bottom:10px;
            margin-bottom:12px;
        }
        .close-x{
            border:none;
            background:none;
            font-size:22px;
            cursor:pointer;
        }
        .modal-content label{ display:block; font-weight:600; margin:10px 0 6px; }
        .modal-content input{
            width:100%;
            padding:10px 12px;
            border:1px solid #ccc;
            border-radius:8px;
        }
        .modal-actions{
            display:flex;
            justify-content:flex-end;
            gap:10px;
            margin-top:14px;
        }
        .btn-cancel{ background:#ccc; }
    </style>
</head>

<body>

<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>

    <div class="search-box">
        <input type="text" placeholder="Search services..." id="serviceSearch">
    </div>

    <div class="user-info">
        <img src="pictures/user.png" alt="User">
        <div>
            <strong><?= htmlspecialchars($name) ?></strong><br>
            <span>Admin</span>
        </div>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <ul>
            <li><a href="dashboardadmin.php">Dashboard</a></li>
            <li><a href="bookingadmin.php">Bookings</a></li>
            <li><a href="vehicleadmin.php">Vehicles</a></li>
            <li><a href="clientrecordsadmin.php">Client Records</a></li>
            <li><a href="servicesadmin.php">Service & Invoice</a></li>
            <li><a href="reportsadmin.php">Reports</a></li>

            <li class="dropdown">
                <a href="#" class="dropdown-toggle">Settings ▾</a>
                <ul class="dropdown-menu">
                    <li class="active"><a href="manage_services.php">Manage Services</a></li>
                    <li><a href="manage_users.php">Manage User Accounts</a></li>
                    <li><a href="backup_restore.php">Back / Restore Data</a></li>
                    <li><a href="system_logs.php">System Logs</a></li>
                </ul>
            </li>
        </ul>

        <div class="logout">
            <a href="logout.php">Logout</a>
        </div>
    </aside>

    <main class="content">
        <h1>Manage Services</h1>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <!-- ADD SERVICE -->
        <div class="card">
            <h3>Add New Service</h3>
            <p class="muted">Add a service type + default labor fee (used as suggestion when creating invoices).</p>

            <form method="POST" class="service-form">
                <input type="hidden" name="action" value="add">

                <input
                    class="name"
                    type="text"
                    name="service_name"
                    placeholder="Type a service name (e.g. Engine Tune-up)"
                    list="serviceSuggestions"
                    required
                >

                <datalist id="serviceSuggestions">
                    <?php foreach ($suggestions as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <input
                    class="price"
                    type="number"
                    name="labor_fee"
                    placeholder="Labor fee"
                    min="0"
                    step="0.01"
                    required
                >

                <button type="submit">Add Service</button>
            </form>
        </div>

        <!-- SERVICE LIST -->
        <div class="card">
            <h3>Service List</h3>
            <p class="muted">Edit / Disable services</p>

            <div class="table-wrapper">
                <table id="servicesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service Name</th>
                            <th>Labor Fee</th>
                            <th>Status</th>
                            <th style="width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr><td colspan="5">No services found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($services as $row): ?>
                                <tr>
                                    <td><?= (int)$row['service_id'] ?></td>
                                    <td class="svc-name"><?= htmlspecialchars($row['service_name']) ?></td>
                                    <td class="price-cell">₱<?= number_format((float)$row['labor_fee'], 2) ?></td>
                                    <td>
                                        <?php if ((int)$row['is_active'] === 1): ?>
                                            <span class="badge active">Active</span>
                                        <?php else: ?>
                                            <span class="badge inactive">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-row">
                                            <button
                                                type="button"
                                                class="btn btn-edit"
                                                data-id="<?= (int)$row['service_id'] ?>"
                                                data-name="<?= htmlspecialchars($row['service_name'], ENT_QUOTES) ?>"
                                                data-fee="<?= htmlspecialchars((string)$row['labor_fee'], ENT_QUOTES) ?>"
                                            >Edit</button>

                                            <?php if ((int)$row['is_active'] === 1): ?>
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('Disable this service?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="service_id" value="<?= (int)$row['service_id'] ?>">
                                                    <button class="btn btn-del" type="submit">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color:#888;font-size:13px;">Disabled</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;">Edit Service</h3>
            <button class="close-x" id="editClose">&times;</button>
        </div>

        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="service_id" id="edit_service_id">

            <label>Service Name</label>
            <input type="text" name="service_name" id="edit_service_name" required>

            <label>Labor Fee (₱)</label>
            <input type="number" name="labor_fee" id="edit_labor_fee" min="0" step="0.01" required>

            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" id="editCancel">Cancel</button>
                <button type="submit" class="btn btn-edit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    // Search filter (front-end only)
    const search = document.getElementById('serviceSearch');
    const table = document.getElementById('servicesTable');
    if (search && table) {
        search.addEventListener('input', () => {
            const q = search.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(tr => {
                const name = (tr.querySelector('.svc-name')?.textContent || '').toLowerCase();
                tr.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }

    // Edit modal
    const modal = document.getElementById('editModal');
    const closeBtn = document.getElementById('editClose');
    const cancelBtn = document.getElementById('editCancel');

    const idInput = document.getElementById('edit_service_id');
    const nameInput = document.getElementById('edit_service_name');
    const feeInput  = document.getElementById('edit_labor_fee');

    document.querySelectorAll('.btn-edit[data-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            idInput.value = btn.dataset.id;
            nameInput.value = btn.dataset.name || '';
            feeInput.value = btn.dataset.fee || '0.00';
            modal.style.display = 'flex';
            nameInput.focus();
        });
    });

    function closeModal(){ modal.style.display = 'none'; }
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
})();
</script>

</body>
</html>
