<?php
session_start();
require_once "db.php";

$action = $_GET['action'] ?? '';
$vehicle_id = $_GET['id'] ?? 0;
$client_id = $_SESSION['client_id'] ?? 0;

if (!$vehicle_id) {
    header("Location: vehicle.php");
    exit();
}

// DELETE
if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM vehicleinfo WHERE vehicle_id=? AND client_id=?");
    $stmt->bind_param("ii", $vehicle_id, $client_id);
    $stmt->execute();
    header("Location: vehicle.php?deleted=1");
    exit();
}

// Fetch vehicle (for view/edit)
$stmt = $conn->prepare("SELECT * FROM vehicleinfo WHERE vehicle_id=? AND client_id=?");
$stmt->bind_param("ii", $vehicle_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();

if (!$vehicle && $action !== 'delete') {
    header("Location: vehicle.php?error=1");
    exit();
}

// UPDATE
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $plateNumber = $_POST['plateNumber'] ?? '';
    $vehicleBrand = $_POST['vehicleBrand'] ?? '';
    $vehicleModel = $_POST['vehicleModel'] ?? '';
    $vehicleYear = $_POST['vehicleYear'] ?? '';
    $engineNumber = $_POST['engineNumber'] ?? '';
    $fuelType = $_POST['fuelType'] ?? '';
    $transmissiontype = $_POST['transmissiontype'] ?? '';
    $color = $_POST['color'] ?? '';
    $mileage = $_POST['mileage'] ?? '';

    $stmt = $conn->prepare("UPDATE vehicleinfo SET plateNumber=?, vehicleBrand=?, vehicleModel=?, vehicleYear=?, engineNumber=?, fuelType=?, transmissiontype=?, color=?, mileage=? WHERE vehicle_id=? AND client_id=?");
    $stmt->bind_param(
        "ssssssssiii",
        $plateNumber,
        $vehicleBrand,
        $vehicleModel,
        $vehicleYear,
        $engineNumber,
        $fuelType,
        $transmissiontype,
        $color,
        $mileage,
        $vehicle_id,
        $client_id
    );
    $stmt->execute();

    header("Location: vehicle.php?success=2");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>
        <?php
        if ($action === 'view')
            echo "View Vehicle";
        elseif ($action === 'edit')
            echo "Edit Vehicle";
        ?>
    </title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="user.css">
</head>

<body>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="brand logo">
            <img src="rapidlogo.png">
            <small>Commitment is our Passion</small>
        </div>

        <div class="search-box">
            <input type="text" placeholder="Search...">
        </div>

        <div class="user">
            <img src="user.png">
            <div>
                <strong>Welcome!</strong><br>
                <span><?= $_SESSION['name'] ?? 'User' ?></span>
            </div>
        </div>
    </div>

    <div class="layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboardadmin.php">Dashboard</a></li>
                <li><a href="bookingadmin.php">Bookings</a></li>
                <li class="active"><a href="vehicleadmin.php">Vehicles</a></li>
                <li><a href="clientrecordsadmin.php">Client Records</a></li>
                <li><a href="servicesadmin.php">Services</a></li>
                <li><a href="reportsadmin.php">Reports</a></li>

                <!-- SETTINGS DROPDOWN -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Settings ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="manage_services.php">Manage Services</a></li>
                        <li><a href="manage_users.php">Manage User Accounts</a></li>
                        <li><a href="backup_restore.php">Back / Restore Data</a></li>
                    </ul>
                </li>
            </ul>

            <div class="logout">
                <a href="logout.php">Logout</a>
            </div>
        </aside>

        <!-- CONTENT -->
        <main class="content">
            <?php if ($action === 'view'): ?>
                <h2>Vehicle Details</h2>
                <ul>
                    <li><strong>Plate Number:</strong> <?= htmlspecialchars($vehicle['plateNumber']) ?></li>
                    <li><strong>Brand:</strong> <?= htmlspecialchars($vehicle['vehicleBrand']) ?></li>
                    <li><strong>Model:</strong> <?= htmlspecialchars($vehicle['vehicleModel']) ?></li>
                    <li><strong>Year:</strong> <?= htmlspecialchars($vehicle['vehicleYear']) ?></li>
                    <li><strong>Engine Number:</strong> <?= htmlspecialchars($vehicle['engineNumber']) ?></li>
                    <li><strong>Fuel Type:</strong> <?= htmlspecialchars($vehicle['fuelType']) ?></li>
                    <li><strong>Transmission:</strong> <?= htmlspecialchars($vehicle['transmissiontype']) ?></li>
                    <li><strong>Color:</strong> <?= htmlspecialchars($vehicle['color']) ?></li>
                    <li><strong>Mileage:</strong> <?= htmlspecialchars($vehicle['mileage']) ?></li>
                </ul>
                <a href="vehicle.php" class="btn-primary">Back to Vehicles</a>

            <?php elseif ($action === 'edit'): ?>
                <h2>Edit Vehicle</h2>
                <form method="POST" class="vehicle-form">
                    <input name="plateNumber" value="<?= htmlspecialchars($vehicle['plateNumber']) ?>"
                        placeholder="Plate Number" required>
                    <input name="vehicleBrand" value="<?= htmlspecialchars($vehicle['vehicleBrand']) ?>"
                        placeholder="Brand">
                    <input name="vehicleModel" value="<?= htmlspecialchars($vehicle['vehicleModel']) ?>"
                        placeholder="Model">
                    <input name="vehicleYear" value="<?= htmlspecialchars($vehicle['vehicleYear']) ?>" placeholder="Year">
                    <input name="engineNumber" value="<?= htmlspecialchars($vehicle['engineNumber']) ?>"
                        placeholder="Engine Number">

                    <select name="fuelType" required>
                        <option value="">Select Fuel Type</option>
                        <option value="Gasoline" <?= $vehicle['fuelType'] == 'Gasoline' ? 'selected' : '' ?>>Gasoline</option>
                        <option value="Diesel" <?= $vehicle['fuelType'] == 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                        <option value="Electric" <?= $vehicle['fuelType'] == 'Electric' ? 'selected' : '' ?>>Electric</option>
                        <option value="Hybrid" <?= $vehicle['fuelType'] == 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                    </select>

                    <select name="transmissiontype" required>
                        <option value="">Select Transmission</option>
                        <option value="Manual" <?= $vehicle['transmissiontype'] == 'Manual' ? 'selected' : '' ?>>Manualoption>
                        <option value="Automatic" <?= $vehicle['transmissiontype'] == 'Automatic' ? 'selected' : '' ?>>Automatic</option>
                        <option value="Hybrid" <?= $vehicle['transmissiontype'] == 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        <option value="IMT" <?= $vehicle['transmissiontype'] == 'IMT' ? 'selected' : '' ?>>IMT</option>
                        <option value="CVT" <?= $vehicle['transmissiontype'] == 'CVT' ? 'selected' : '' ?>>CVT</option>
                        <option value="DCT" <?= $vehicle['transmissiontype'] == 'DCT' ? 'selected' : '' ?>>DCT</option>
                    </select>

                    <input name="color" value="<?= htmlspecialchars($vehicle['color']) ?>" placeholder="Color">
                    <input name="mileage" value="<?= htmlspecialchars($vehicle['mileage']) ?>" placeholder="Mileage">

                    <button type="submit" class="btn-primary">Update Vehicle</button>
                    <a href="vehicle.php" class="btn-cancel">Cancel</a>
                </form>
            <?php endif; ?>
        </main>
    </div>

</body>

</html>