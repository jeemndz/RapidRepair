<?php
session_start();
require_once "db.php";
require_once "log_helper.php"; // ✅ add this

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$client = null;
$vehicles = [];

/* =========================
   ✅ SYSTEM LOGS (Client view)
   Log once per session to avoid spam
========================= */
if (!isset($_SESSION['logged_view_profile'])) {
    log_event(
        $conn,
        "View Profile",
        "client_profile",
        null,
        "Client opened profile page"
    );
    $_SESSION['logged_view_profile'] = true;
}

if ($user_id) {
    // Fetch client info
    $stmt = $conn->prepare("
        SELECT 
            client_id,
            firstName,
            lastName,
            contactNumber,
            email,
            address,
            notes
        FROM client_information
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch vehicles (only if client exists)
    if (!empty($client['client_id'])) {
        $client_id = (int)$client['client_id'];

        $stmt = $conn->prepare("
            SELECT plateNumber, vehicleBrand, vehicleModel
            FROM vehicleinfo
            WHERE client_id = ?
            ORDER BY plateNumber ASC
        ");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Full name for header
$fullName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
if ($fullName === '') $fullName = 'User';

// Incomplete?
$isIncomplete = ($client && (empty($client['contactNumber']) || empty($client['email']) || empty($client['address'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile | RapidRepair</title>

    <!-- IMPORTANT: pagelayout.css first -->
    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="user.css">
</head>

<body>

<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>

    <div class="search-box">
        <input type="text" placeholder="Search..." autocomplete="off">
        <div class="search-results" style="display:none;"></div>
    </div>

    <div class="user-info">
        <img src="pictures/user.png" alt="User">
        <div>
            <strong>Welcome!</strong><br>
            <span><?= htmlspecialchars($fullName) ?></span>
        </div>
    </div>
</header>

<div class="layout">

    <aside class="sidebar">
        <ul>
            <li><a href="user_home.php">Home</a></li>
            <li class="active"><a href="profile.php">Profile</a></li>
            <li><a href="vehicle.php">Vehicle</a></li>
            <li><a href="clientreq.php">Booking</a></li>
            <li><a href="payments.php">Payment</a></li>
        </ul>

        <div class="logout">
            <a href="logout.php">Logout</a>
        </div>
    </aside>

    <main class="content">

        <!-- Page header -->
        <div class="page-head">
            <div>
                <h1 class="page-title">My Profile</h1>
                <p class="page-subtitle">View your personal details and registered vehicles.</p>
            </div>
            <div class="page-actions">
                <button class="btn-outline" type="button" onclick="openRegister()">
                    <?= $isIncomplete ? 'Complete Profile' : 'Edit Profile' ?>
                </button>
            </div>
        </div>

        <?php if ($isIncomplete): ?>
            <div class="alert-warning">
                <div class="alert-left">
                    <div class="alert-icon">!</div>
                    <div>
                        <div class="alert-title">Profile incomplete</div>
                        <div class="alert-text">Please complete your registration to continue using all features.</div>
                    </div>
                </div>
                <button type="button" class="btn-primary" onclick="openRegister()">Complete Now</button>
            </div>
        <?php endif; ?>

        <div class="profile-grid">

            <!-- PROFILE CARD -->
            <div class="card profile-card">
                <div class="card-head">
                    <div class="avatar-wrap">
                        <img src="pictures/user.png" class="avatar" alt="Avatar">
                    </div>
                    <div>
                        <h2 class="profile-name">
                            <?= htmlspecialchars(($client['firstName'] ?? 'First Name') . ' ' . ($client['lastName'] ?? 'Last Name')) ?>
                        </h2>
                        <p class="profile-meta">Account details</p>
                    </div>
                </div>

                <div class="info-list">
                    <div class="info-row">
                        <span class="info-label">Contact Number</span>
                        <span class="info-value"><?= htmlspecialchars($client['contactNumber'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($client['email'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($client['address'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Notes</span>
                        <span class="info-value"><?= htmlspecialchars($client['notes'] ?? '-') ?></span>
                    </div>
                </div>
            </div>

            <!-- VEHICLE CARD -->
            <div class="card vehicle-card">
                <div class="table-header">
                    <h3 class="section-title-sm">Owned Vehicles</h3>
                    <span class="badge"><?= count($vehicles) ?> total</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Plate Number</th>
                                <th>Vehicle Brand</th>
                                <th>Vehicle Model</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($vehicles)): ?>
                                <?php foreach ($vehicles as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['plateNumber']) ?></td>
                                        <td><?= htmlspecialchars($v['vehicleBrand']) ?></td>
                                        <td><?= htmlspecialchars($v['vehicleModel']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="empty-cell">No vehicles registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="hint">Tip: You can add vehicles in the “Vehicle” tab.</p>
            </div>

        </div>
    </main>
</div>

<!-- COMPLETE PROFILE MODAL -->
<div class="modal" id="registerModal" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-head">
            <h2><?= $isIncomplete ? 'Complete Registration' : 'Update Profile' ?></h2>
            <button type="button" class="modal-close" onclick="closeRegister()" aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="register_client.php">
            <div class="two-col">
                <div class="field">
                    <label>First Name</label>
                    <input type="text" value="<?= htmlspecialchars($client['firstName'] ?? '') ?>" readonly>
                </div>
                <div class="field">
                    <label>Last Name</label>
                    <input type="text" value="<?= htmlspecialchars($client['lastName'] ?? '') ?>" readonly>
                </div>
            </div>

            <div class="field">
                <label>Contact Number</label>
                <input type="text" name="contactNumber" placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($client['contactNumber'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($client['email'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label>Address</label>
                <textarea name="address" placeholder="Complete address" required><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label>Notes (optional)</label>
                <textarea name="notes" placeholder="Optional notes..."><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn-primary">Save</button>
                <button type="button" class="btn-muted" onclick="closeRegister()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRegister() {
    const m = document.getElementById("registerModal");
    m.style.display = "flex";
    m.setAttribute("aria-hidden", "false");
}
function closeRegister() {
    const m = document.getElementById("registerModal");
    m.style.display = "none";
    m.setAttribute("aria-hidden", "true");
}

// Close on outside click
document.addEventListener("click", function(e){
    const modal = document.getElementById("registerModal");
    if (modal.style.display === "flex" && e.target === modal) closeRegister();
});

// Close on ESC
document.addEventListener("keydown", function(e){
    if (e.key === "Escape") closeRegister();
});
</script>

</body>
</html>
