<?php
// dashboard_data.php
include "db.php";

$filter = $_GET['filter'] ?? 'today'; // today, weekly, monthly
$today = date("Y-m-d");
$startOfWeek = date("Y-m-d", strtotime("monday this week"));
$startOfMonth = date("Y-m-01");

// =======================
// TOTAL SCHEDULED
// =======================
$whereDate = "";
if ($filter === 'today') {
    $whereDate = "appointmentDate = '$today' AND status IN ('Approved', 'Scheduled')";
} elseif ($filter === 'weekly') {
    $whereDate = "appointmentDate BETWEEN '$startOfWeek' AND '$today' AND status IN ('Approved', 'Scheduled')";
} elseif ($filter === 'monthly') {
    $whereDate = "MONTH(appointmentDate) = MONTH('$today') AND YEAR(appointmentDate) = YEAR('$today') AND status IN ('Approved', 'Scheduled')";
}

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT appointment_ID) AS totalScheduled
    FROM appointment
    WHERE $whereDate OR status = 'Ongoing'
");
$stmt->execute();
$totalScheduled = $stmt->get_result()->fetch_assoc()['totalScheduled'] ?? 0;

// =======================
// ESTIMATES
// =======================
$whereEstimate = "";
if ($filter === 'today') {
    $whereEstimate = "serviceDate = '$today'";
} elseif ($filter === 'weekly') {
    $whereEstimate = "serviceDate BETWEEN '$startOfWeek' AND '$today'";
} elseif ($filter === 'monthly') {
    $whereEstimate = "MONTH(serviceDate) = MONTH('$today') AND YEAR(serviceDate) = YEAR('$today')";
}

$result = $conn->query("
    SELECT SUM(totalCost) AS total_estimates 
    FROM services 
    WHERE status IN ('Completed', 'Ongoing') AND $whereEstimate
");
$estimates = $result->fetch_assoc()['total_estimates'] ?? 0;

// =======================
// Return JSON
// =======================
echo json_encode([
    'totalScheduled' => $totalScheduled,
    'estimates' => number_format($estimates, 2)
]);
?>
