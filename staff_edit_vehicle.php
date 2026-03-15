<?php
session_start();
require_once "db.php";
require_once "log_helper.php";

/* =========================
   ✅ STAFF + ADMIN SAFE REDIRECT
   - Both staff and admin can use this same file
   - Redirects back to the page that submitted the form (return_to)
   - Fallback: role-based
========================= */
$allowedReturn = ['staffvehicle.php', 'vehicleadmin.php'];

$returnTo = $_POST['return_to'] ?? '';
$returnTo = basename((string)$returnTo);

if (!in_array($returnTo, $allowedReturn, true)) {
    $role = strtolower(trim($_SESSION['role'] ?? ''));
    $returnTo = ($role === 'admin') ? 'vehicleadmin.php' : 'staffvehicle.php';
}

/* =========================
   REQUEST METHOD CHECK
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$returnTo}?err=" . urlencode("Invalid request."));
    exit;
}

/* =========================
   SANITIZE INPUTS
========================= */
$vehicle_id        = (int)($_POST['vehicle_id'] ?? 0);
$plateNumber       = strtoupper(trim($_POST['plateNumber'] ?? ''));
$vehicleBrand      = trim($_POST['vehicleBrand'] ?? '');
$vehicleModel      = trim($_POST['vehicleModel'] ?? '');
$vehicleYear_raw   = trim($_POST['vehicleYear'] ?? '');
$engineNumber      = trim($_POST['engineNumber'] ?? '');
$fuelType          = trim($_POST['fuelType'] ?? '');
$transmissiontype  = trim($_POST['transmissiontype'] ?? '');
$color             = trim($_POST['color'] ?? '');
$mileage_raw       = trim($_POST['mileage'] ?? '');

/* =========================
   BASIC VALIDATION
========================= */
if ($vehicle_id <= 0) {
    header("Location: {$returnTo}?err=" . urlencode("Invalid vehicle ID."));
    exit;
}

if ($plateNumber === '' || $vehicleBrand === '' || $vehicleModel === '' || $fuelType === '' || $transmissiontype === '') {
    header("Location: {$returnTo}?err=" . urlencode("Please fill in required fields."));
    exit;
}

/* ✅ Plate format validation */
if (!preg_match('/^[A-Z]{2,3}\s\d{3,5}$/', $plateNumber)) {
    header("Location: {$returnTo}?err=" . urlencode("Invalid plate format. Use: ABC 1234 / AB 1234 / ABC 12345"));
    exit;
}

/* ✅ Validate dropdown values */
$allowedFuel  = ['Gasoline','Diesel','Electric','Hybrid'];
$allowedTrans = ['Manual','Automatic','Hybrid','IMT','CVT','DCT'];

if (!in_array($fuelType, $allowedFuel, true)) {
    header("Location: {$returnTo}?err=" . urlencode("Invalid fuel type selected."));
    exit;
}
if (!in_array($transmissiontype, $allowedTrans, true)) {
    header("Location: {$returnTo}?err=" . urlencode("Invalid transmission selected."));
    exit;
}

/* ✅ Year (NULL if blank) */
$vehicleYear = null;
if ($vehicleYear_raw !== '') {
    $y = (int)$vehicleYear_raw;
    if ($y < 1900 || $y > 2099) {
        header("Location: {$returnTo}?err=" . urlencode("Invalid year."));
        exit;
    }
    $vehicleYear = $y;
}

/* ✅ Mileage (NULL if blank) */
$mileage = null;
if ($mileage_raw !== '') {
    if (!ctype_digit($mileage_raw)) {
        header("Location: {$returnTo}?err=" . urlencode("Mileage must be a whole number."));
        exit;
    }
    $mileage = (int)$mileage_raw;
}

/* ✅ Prevent duplicate plate number */
$chk = $conn->prepare("SELECT vehicle_id FROM vehicleinfo WHERE plateNumber = ? AND vehicle_id <> ? LIMIT 1");
$chk->bind_param("si", $plateNumber, $vehicle_id);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    $chk->close();
    header("Location: {$returnTo}?err=" . urlencode("Plate number already exists."));
    exit;
}
$chk->close();

/* =========================
   ✅ FETCH OLD DATA (for logging details)
========================= */
$oldStmt = $conn->prepare("
    SELECT plateNumber, vehicleBrand, vehicleModel, vehicleYear, engineNumber, fuelType,
           transmissiontype, color, mileage, client_id
    FROM vehicleinfo
    WHERE vehicle_id = ?
    LIMIT 1
");
$oldStmt->bind_param("i", $vehicle_id);
$oldStmt->execute();
$oldRes = $oldStmt->get_result();
$oldRow = $oldRes ? $oldRes->fetch_assoc() : null;
$oldStmt->close();

if (!$oldRow) {
    header("Location: {$returnTo}?err=" . urlencode("Vehicle record not found."));
    exit;
}

/* =========================
   ✅ UPDATE (with color + mileage)
========================= */
$stmt = $conn->prepare("
    UPDATE vehicleinfo
    SET plateNumber = ?,
        vehicleBrand = ?,
        vehicleModel = ?,
        vehicleYear = ?,
        engineNumber = ?,
        fuelType = ?,
        transmissiontype = ?,
        color = ?,
        mileage = ?
    WHERE vehicle_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "sssissssii",
    $plateNumber,
    $vehicleBrand,
    $vehicleModel,
    $vehicleYear,         // int or null (mysqli handles null for 'i' as 0 sometimes depending on setup)
    $engineNumber,
    $fuelType,
    $transmissiontype,
    $color,
    $mileage,             // int or null
    $vehicle_id
);

if (!$stmt->execute()) {
    $err = "Update failed: " . $stmt->error;
    $stmt->close();
    header("Location: {$returnTo}?err=" . urlencode($err));
    exit;
}
$stmt->close();

/* =========================
   ✅ SYSTEM LOGS (after successful update)
========================= */
try {
    $changes = [];

    $oldYear = ($oldRow['vehicleYear'] === null || $oldRow['vehicleYear'] === '') ? null : (int)$oldRow['vehicleYear'];
    $oldMileage = ($oldRow['mileage'] === null || $oldRow['mileage'] === '') ? null : (int)$oldRow['mileage'];

    if (trim((string)$oldRow['plateNumber']) !== $plateNumber) $changes[] = "Plate: {$oldRow['plateNumber']} → {$plateNumber}";
    if (trim((string)$oldRow['vehicleBrand']) !== $vehicleBrand) $changes[] = "Brand: {$oldRow['vehicleBrand']} → {$vehicleBrand}";
    if (trim((string)$oldRow['vehicleModel']) !== $vehicleModel) $changes[] = "Model: {$oldRow['vehicleModel']} → {$vehicleModel}";
    if ($oldYear !== $vehicleYear) $changes[] = "Year: " . ($oldYear ?? "NULL") . " → " . ($vehicleYear ?? "NULL");
    if (trim((string)$oldRow['engineNumber']) !== $engineNumber) $changes[] = "Engine#: {$oldRow['engineNumber']} → {$engineNumber}";
    if (trim((string)$oldRow['fuelType']) !== $fuelType) $changes[] = "Fuel: {$oldRow['fuelType']} → {$fuelType}";
    if (trim((string)$oldRow['transmissiontype']) !== $transmissiontype) $changes[] = "Trans: {$oldRow['transmissiontype']} → {$transmissiontype}";
    if (trim((string)$oldRow['color']) !== $color) $changes[] = "Color: {$oldRow['color']} → {$color}";
    if ($oldMileage !== $mileage) $changes[] = "Mileage: " . ($oldMileage ?? "NULL") . " → " . ($mileage ?? "NULL");

    $changeText = $changes ? implode(" | ", $changes) : "No field changes detected (update submitted).";

    log_event(
        $conn,
        "Update Vehicle",
        "vehicleinfo",
        $vehicle_id,
        "Vehicle #{$vehicle_id} updated. Client #{$oldRow['client_id']} | " . $changeText
    );
} catch (Throwable $e) {
    // do not block the main flow if logging fails
}

/* =========================
   DONE
========================= */
header("Location: {$returnTo}?success=" . urlencode("Vehicle updated successfully!"));
exit;
