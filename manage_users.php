<?php
session_start();
require_once "db.php";

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

define("ADMIN_CONFIRM_PASSWORD", "RREDMS123");

$name = $_SESSION['name'] ?? 'Admin';

$successMsg = '';
$errorMsg = '';

// show messages from update_user_role.php redirect
if (!empty($_GET['ok'])) {
    $successMsg = (string) $_GET['ok'];
}
if (!empty($_GET['err'])) {
    $errorMsg = (string) $_GET['err'];
}

/* =========================
   CREATE USER (admin creates staff/admin)
   - requires hardcoded confirm password
========================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'create_user'
) {

    $fullName = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = trim($_POST['role'] ?? 'staff');

    $newUserPassword = $_POST['password'] ?? '';
    $newUserPassword2 = $_POST['password2'] ?? '';
    $adminConfirmPassword = $_POST['admin_confirm_password'] ?? '';

    // VALIDATION
    if ($fullName === '' || $email === '' || $username === '' || $role === '' || $adminConfirmPassword === '') {
        $errorMsg = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Please enter a valid email address.";
    } elseif (!in_array($role, ['staff', 'admin'], true)) {
        $errorMsg = "Only Staff or Admin accounts can be created here.";
    } elseif ($newUserPassword === '' || $newUserPassword2 === '') {
        $errorMsg = "Please enter and confirm the new user's password.";
    } elseif ($newUserPassword !== $newUserPassword2) {
        $errorMsg = "New user passwords do not match.";
    } elseif (!hash_equals(ADMIN_CONFIRM_PASSWORD, $adminConfirmPassword)) {
        $errorMsg = "Admin confirmation password is incorrect.";
    } else {

        // check username duplicate
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $errorMsg = "Username already exists.";
        } else {
            $stmt->close();

            // check email duplicate
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->close();
                $errorMsg = "Email already exists.";
            } else {
                $stmt->close();

                // insert user
                $hashed = password_hash($newUserPassword, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (fullName, username, email, password, role, dateRegistered)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("sssss", $fullName, $username, $email, $hashed, $role);

                if ($stmt->execute()) {
                    $successMsg = "User created successfully.";
                } else {
                    $errorMsg = "Error creating user: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

/* =========================
   FETCH USERS
========================= */
$userStmt = $conn->prepare("
    SELECT user_id, fullName, email, username, role
    FROM users
    ORDER BY user_id DESC
");
$userStmt->execute();
$userResult = $userStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- layout first -->
    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="users_table.css">

    <style>
        /* cards */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
            padding: 18px;
            margin-bottom: 18px;
        }

        .card h3 {
            margin: 0 0 10px;
        }

        /* alerts */
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin: 0 0 14px;
            font-size: 14px;
        }

        .alert-success {
            background: #e6f4ea;
            border: 1px solid #c3e6cb;
            color: #1e7e34;
        }

        .alert-error {
            background: #fdecea;
            border: 1px solid #f5c6cb;
            color: #b71c1c;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #243f7d;
            color: #fff;
            white-space: nowrap;
        }

        .role {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .role.admin {
            background: #fdecea;
            color: #b71c1c;
        }

        .role.staff {
            background: #e6f4ea;
            color: #1e7e34;
        }

        .role.client {
            background: #eef2ff;
            color: #243f7d;
        }

        /* buttons */
        .btn {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-primary {
            background: #071f4a;
            color: #fff;
        }

        .btn-primary:hover {
            opacity: .92;
        }

        .btn-muted {
            background: #cfcfcf;
        }

        .btn-muted:hover {
            opacity: .92;
        }

        /* modal */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-content {
            background: #fff;
            width: 520px;
            max-width: calc(100% - 24px);
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, .25);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .modal-header h3 {
            margin: 0;
        }

        .close-x {
            border: none;
            background: none;
            font-size: 22px;
            cursor: pointer;
        }

        .modal-form label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            margin-top: 10px;
        }

        .modal-form input,
        .modal-form select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            outline: none;
            margin-top: 6px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }

        /* mini confirmation modal */
        .confirm-text {
            color: #333;
            font-size: 14px;
            line-height: 1.45;
            margin: 8px 0 0;
        }

        /* search */
        .search-box input {
            width: 100%;
        }

        /* role update admin confirm input */
        .admin-confirm-inline {
            width: 160px;
            max-width: 100%;
        }
    </style>
</head>

<body>

    <header class="topbar">
        <div class="logo">
            <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
            <small>Commitment is our Passion</small>
        </div>

        <div class="search-box">
            <input type="text" placeholder="Search users..." id="userSearch" autocomplete="off">
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
        <!-- SIDEBAR -->
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
                        <li><a href="manage_services.php">Manage Services</a></li>
                        <li class="active"><a href="manage_users.php">Manage User Accounts</a></li>
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
            <h1>Manage User Accounts</h1>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;">Create User</h3>
                        <p style="margin:6px 0 0;color:#666;">Create a Staff or Admin account (with password).</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="openCreateModal">+ Create User</button>
                </div>
            </div>

            <div class="card">
                <h3>User List</h3>
                <p style="margin-top:0;color:#666;">View / change role (requires admin confirmation password)</p>

                <div class="table-wrapper">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th style="width:340px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($userResult->num_rows > 0): ?>
                                <?php while ($row = $userResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= (int) $row['user_id'] ?></td>
                                        <td class="u-name"><?= htmlspecialchars($row['fullName']) ?></td>
                                        <td class="u-email"><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="u-username"><?= htmlspecialchars($row['username']) ?></td>
                                        <td>
                                            <span class="role <?= htmlspecialchars($row['role']) ?>">
                                                <?= ucfirst($row['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['role'] !== 'admin'): ?>
                                                <form action="update_user_role.php" method="POST"
                                                    style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                                    <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">

                                                    <select name="role" required>
                                                        <option value="client" <?= $row['role'] === 'client' ? 'selected' : ''; ?>>Client</option>
                                                        <option value="staff" <?= $row['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                        <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    </select>

                                                    <input class="admin-confirm-inline" type="password" name="admin_confirm_password"
                                                        placeholder="Admin password" required>

                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                </form>
                                            <?php else: ?>
                                                <em>Protected</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- ================= CREATE USER MODAL ================= -->
    <div class="modal" id="createUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Staff/Admin Account</h3>
                <button class="close-x" type="button" id="closeCreateModal">&times;</button>
            </div>

            <form method="POST" class="modal-form" id="createUserForm" autocomplete="off">
                <input type="hidden" name="action" value="create_user">

                <label>Full Name *</label>
                <input type="text" name="fullname" id="cu_fullname" required>

                <label>Email *</label>
                <input type="email" name="email" id="cu_email" required>

                <label>Username *</label>
                <input type="text" name="username" id="cu_username" required>

                <label>Role *</label>
                <select name="role" id="cu_role" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>

                <label>New User Password *</label>
                <input type="password" name="password" id="cu_password" required minlength="6">

                <label>Confirm New User Password *</label>
                <input type="password" name="password2" id="cu_password2" required minlength="6">

                <label>Admin Confirmation Password *</label>
                <input type="password" name="admin_confirm_password" id="cu_admin_confirm" required
                    placeholder="Enter RREDMS123">

                <div class="modal-actions">
                    <button type="button" class="btn btn-muted" id="cancelCreateModal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="openConfirmModal">Continue</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= CONFIRMATION MODAL ================= -->
    <div class="modal" id="confirmCreateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Creation</h3>
                <button class="close-x" type="button" id="closeConfirmModal">&times;</button>
            </div>

            <p class="confirm-text" id="confirmSummary"></p>
            <p class="confirm-text" style="color:#666;margin-top:10px;">
                If the admin confirmation password is correct, this Staff/Admin account will be created.
            </p>

            <div class="modal-actions">
                <button type="button" class="btn btn-muted" id="backToForm">Back</button>
                <button type="button" class="btn btn-primary" id="confirmSubmit">Yes, Create</button>
            </div>
        </div>
    </div>

    <script>
        (function () {

            // Search filter (front-end)
            const search = document.getElementById('userSearch');
            const table = document.getElementById('usersTable');
            if (search && table) {
                search.addEventListener('input', () => {
                    const q = search.value.toLowerCase().trim();
                    table.querySelectorAll('tbody tr').forEach(tr => {
                        const name = (tr.querySelector('.u-name')?.textContent || '').toLowerCase();
                        const email = (tr.querySelector('.u-email')?.textContent || '').toLowerCase();
                        const username = (tr.querySelector('.u-username')?.textContent || '').toLowerCase();
                        tr.style.display = (name.includes(q) || email.includes(q) || username.includes(q)) ? '' : 'none';
                    });
                });
            }

            // Modal helpers
            const createModal = document.getElementById('createUserModal');
            const confirmModal = document.getElementById('confirmCreateModal');

            const openCreate = document.getElementById('openCreateModal');
            const closeCreate = document.getElementById('closeCreateModal');
            const cancelCreate = document.getElementById('cancelCreateModal');

            const openConfirm = document.getElementById('openConfirmModal');
            const closeConfirm = document.getElementById('closeConfirmModal');
            const backToForm = document.getElementById('backToForm');
            const confirmSubmit = document.getElementById('confirmSubmit');

            const form = document.getElementById('createUserForm');
            const summary = document.getElementById('confirmSummary');

            function show(el) { el.style.display = 'flex'; }
            function hide(el) { el.style.display = 'none'; }

            openCreate?.addEventListener('click', () => {
                show(createModal);
                setTimeout(() => document.getElementById('cu_fullname')?.focus(), 50);
            });

            closeCreate?.addEventListener('click', () => hide(createModal));
            cancelCreate?.addEventListener('click', () => hide(createModal));

            // close modals on outside click
            window.addEventListener('click', (e) => {
                if (e.target === createModal) hide(createModal);
                if (e.target === confirmModal) hide(confirmModal);
            });

            // Validate + open confirmation
            openConfirm?.addEventListener('click', () => {
                const fullName = document.getElementById('cu_fullname')?.value.trim();
                const email = document.getElementById('cu_email')?.value.trim();
                const username = document.getElementById('cu_username')?.value.trim();
                const role = document.getElementById('cu_role')?.value;
                const p1 = document.getElementById('cu_password')?.value;
                const p2 = document.getElementById('cu_password2')?.value;
                const adminPw = document.getElementById('cu_admin_confirm')?.value;

                if (!fullName || !email || !username || !role || !p1 || !p2 || !adminPw) {
                    alert("Please complete all required fields.");
                    return;
                }
                if (p1 !== p2) {
                    alert("New user passwords do not match.");
                    return;
                }
                if (role !== 'staff' && role !== 'admin') {
                    alert("Invalid role selected.");
                    return;
                }

                summary.textContent = `Create account: ${fullName} (${username}) as ${role.toUpperCase()} with email ${email}?`;
                hide(createModal);
                show(confirmModal);
            });

            backToForm?.addEventListener('click', () => {
                hide(confirmModal);
                show(createModal);
            });

            closeConfirm?.addEventListener('click', () => hide(confirmModal));

            confirmSubmit?.addEventListener('click', () => {
                form.submit();
            });

        })();
    </script>

</body>

</html>
