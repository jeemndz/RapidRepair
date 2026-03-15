<?php
session_start();

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$name = $_SESSION['fullName'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Backup & Restore | Rapid Repair</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- ✅ layout first -->
<link rel="stylesheet" href="pagelayout.css">

<style>
/* If pagelayout.css already has these, this will just enhance/ensure consistency */
.card{
    background:#fff;
    border-radius:12px;
    padding:18px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
    margin-bottom:16px;
    border:1px solid #eee;
}
.card h3{
    margin:0 0 10px;
}
.form-row{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}
input[type="file"]{
    border:1px solid #ccc;
    padding:10px;
    border-radius:10px;
    background:#fff;
    width:min(520px, 100%);
}
button{
    background:#071f4a;
    color:#fff;
    border:none;
    padding:10px 16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
}
button:hover{
    opacity:.92;
}
.note{
    margin:8px 0 0;
    color:#555;
    font-size:13px;
    line-height:1.4;
}
</style>
</head>

<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>

    <div class="search-box">
        <input type="text" placeholder="Search..." disabled>
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
                    <li><a href="manage_users.php">Manage User Accounts</a></li>
                    <li class="active"><a href="backup_restore.php">Back / Restore Data</a></li>
                    <li><a href="system_logs.php">System Logs</a></li>
                </ul>
            </li>
        </ul>

        <div class="logout">
            <a href="logout.php">Logout</a>
        </div>
    </aside>

    <!-- CONTENT -->
    <main class="content">
        <h1>Backup & Restore Data</h1>

        <div class="card">
            <h3>Backup Database</h3>
            <form action="backup.php" method="POST" class="form-row">
                <button type="submit">Backup Now</button>
            </form>
            <p class="note">
                This will download a backup file of your current database.
            </p>
        </div>

        <div class="card">
            <h3>Restore Database</h3>
            <form action="restore.php" method="POST" enctype="multipart/form-data" class="form-row">
                <input type="file" name="backup_file" accept=".sql,.zip" required>
                <button type="submit" onclick="return confirm('Restore will overwrite current data. Continue?')">
                    Restore
                </button>
            </form>
            <p class="note">
                Upload a valid backup file (.sql or .zip). Restoring may overwrite existing records.
            </p>
        </div>

    </main>

</div>

</body>
</html>
