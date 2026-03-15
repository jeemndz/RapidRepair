<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

/* ================= AUTH CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ✅ Role guard */
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'admin'], true)) {
    header("Location: login.php");
    exit();
}

/* ✅ Redirect destination per role */
$redirectPage = ($role === 'admin') ? "vehicleadmin.php" : "staffvehicle.php";

/* ================= INPUT ================= */
$vehicle_id = (int)($_GET['id'] ?? 0);

if ($vehicle_id <= 0) {
    header("Location: {$redirectPage}?err=" . urlencode("Missing vehicle ID."));
    exit();
}

/* ================= FETCH VEHICLE (for logging) ================= */
$fetch = $conn->prepare("
    SELECT vehicle_id, plateNumber, vehicleBrand, vehicleModel, client_id
    FROM vehicleinfo
    WHERE vehicle_id = ?
    LIMIT 1
");
if (!$fetch) {
    header("Location: {$redirectPage}?err=" . urlencode("Server error (prepare failed)."));
    exit();
}

$fetch->bind_param("i", $vehicle_id);
$fetch->execute();
$vehicle = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$vehicle) {
    header("Location: {$redirectPage}?err=" . urlencode("Vehicle not found."));
    exit();
}

/* ================= DELETE VEHICLE ================= */
$stmt = $conn->prepare("DELETE FROM vehicleinfo WHERE vehicle_id = ? LIMIT 1");
if (!$stmt) {
    header("Location: {$redirectPage}?err=" . urlencode("Server error (prepare failed)."));
    exit();
}

$stmt->bind_param("i", $vehicle_id);

if ($stmt->execute()) {
    $stmt->close();

    /* ✅ SYSTEM LOG (FIXED $_SESSION + better details) */
    $plate = $vehicle['plateNumber'] ?? '—';
    $brand = $vehicle['vehicleBrand'] ?? '—';
    $model = $vehicle['vehicleModel'] ?? '—';
    $cid   = (int)($vehicle['client_id'] ?? 0);

    log_event(
        $conn,
        "Delete Vehicle",
        "vehicleinfo",
        $vehicle_id,
        "Vehicle deleted by role={$role} | ID={$vehicle_id} | Plate={$plate} | Vehicle={$brand} {$model} | ClientID={$cid}"
    );

    header("Location: {$redirectPage}?success=" . urlencode("Vehicle deleted successfully."));
    exit();
}

/* ================= ERROR ================= */
$err = $stmt->error ?: "Delete failed";
$stmt->close();

header("Location: {$redirectPage}?err=" . urlencode($err));
exit();
