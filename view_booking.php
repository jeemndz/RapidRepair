<?php
session_start();
require_once "db.php";

/* =========================
   AUTH: STAFF + ADMIN ONLY
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

// allow only staff/admin
if (!in_array($role, ['staff', 'admin'], true)) {
    http_response_code(403);
    die("Access denied.");
}

/* =========================
   BACK LINK (NO PANEL SWITCH)
   - admin goes back to admin panel page
   - staff goes back to staff panel page
========================= */
$backUrl = ($role === 'admin') ? 'bookingadmin.php' : 'bookings.php'; 
// ✅ change admin_bookings.php to your real admin bookings page filename

/* =========================
   GET BOOKING ID
========================= */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Booking ID is missing.");
}
$booking_id = (int) $_GET['id'];

/* =========================
   FETCH BOOKING DETAILS
========================= */
$stmt = $conn->prepare("
    SELECT 
        a.*,
        CONCAT(c.firstName, ' ', c.lastName) AS client_name,
        v.plateNumber,
        v.vehicleBrand,
        v.vehicleModel,
        v.vehicleYear
    FROM appointment a
    LEFT JOIN client_information c ON c.client_id = a.client_id
    LEFT JOIN vehicleinfo v ON v.vehicle_id = a.vehicle_id
    WHERE a.appointment_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Booking not found.");
}

$booking = $res->fetch_assoc();
$stmt->close();

/* =========================
   HELPERS
========================= */
function badgeClass($status)
{
    $s = strtolower(trim($status ?? ''));
    return match ($s) {
        'pending'   => 'badge pending',
        'approved'  => 'badge approved',
        'scheduled' => 'badge scheduled',
        'ongoing'   => 'badge ongoing',
        'completed' => 'badge completed',
        'cancelled' => 'badge cancelled',
        'rejected'  => 'badge rejected',
        default     => 'badge default'
    };
}

$vehicleLabel = trim(
    ($booking['vehicleBrand'] ?? '') . ' ' .
    ($booking['vehicleModel'] ?? '') .
    (!empty($booking['plateNumber']) ? ' (' . $booking['plateNumber'] . ')' : '')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Booking | Rapid Repair</title>

<link rel="stylesheet" href="pagelayout.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    body{
        background: #f3f5f9;
        font-family: Arial, sans-serif;
    }

    .wrap{
        max-width: 880px;
        margin: 40px auto;
        padding: 0 18px;
    }

    .card{
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 18px 35px rgba(0,0,0,.10);
        overflow: hidden;
        border: 1px solid #eef1f6;
    }

    .card-header{
        background: linear-gradient(135deg, #071f4a, #254a91);
        color: #fff;
        padding: 22px 24px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 14px;
    }

    .title{
        display:flex;
        align-items:center;
        gap: 12px;
    }

    .title i{
        font-size: 20px;
        opacity: .9;
    }

    .title h2{
        margin:0;
        font-size: 20px;
        letter-spacing: .2px;
    }

    .sub{
        font-size: 12px;
        opacity: .9;
        margin-top: 4px;
    }

    .badge{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding: 8px 12px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 12px;
        background: rgba(255,255,255,.18);
        border: 1px solid rgba(255,255,255,.25);
        white-space: nowrap;
    }

    .badge::before{
        content:"";
        width:8px;
        height:8px;
        border-radius:50%;
        background: #fff;
        opacity: .95;
    }

    .badge.pending::before   { background:#fbbf24; }
    .badge.approved::before  { background:#22c55e; }
    .badge.scheduled::before { background:#38bdf8; }
    .badge.ongoing::before   { background:#a78bfa; }
    .badge.completed::before { background:#34d399; }
    .badge.cancelled::before { background:#fb7185; }
    .badge.rejected::before  { background:#ef4444; }
    .badge.default::before   { background:#e5e7eb; }

    .card-body{
        padding: 22px 24px 8px;
    }

    .grid{
        display:grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 18px;
    }

    @media (max-width: 680px){
        .grid{ grid-template-columns: 1fr; }
    }

    .field{
        border: 1px solid #eef1f6;
        background: #fbfcff;
        border-radius: 12px;
        padding: 14px 14px;
    }

    .label{
        font-size: 12px;
        font-weight: 700;
        color: #415a77;
        margin-bottom: 6px;
        display:flex;
        align-items:center;
        gap: 8px;
    }

    .value{
        font-size: 14px;
        color: #111827;
        font-weight: 600;
        word-break: break-word;
    }

    .mono{
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-weight: 700;
        letter-spacing: .3px;
    }

    .card-footer{
        padding: 16px 24px 22px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap: 12px;
        border-top: 1px solid #eef1f6;
        background: #ffffff;
        flex-wrap: wrap;
    }

    .btn{
        display:inline-flex;
        align-items:center;
        gap:10px;
        padding: 10px 16px;
        border-radius: 999px;
        text-decoration:none;
        font-weight: 700;
        border: 1px solid transparent;
        cursor:pointer;
        transition: .15s ease;
    }

    .btn-back{
        background: #071f4a;
        color:#fff;
    }
    .btn-back:hover{ opacity:.92; }

    .btn-print{
        background:#fff;
        color:#071f4a;
        border-color:#dbe3f1;
    }
    .btn-print:hover{
        background:#f3f7ff;
    }

    .hint{
        font-size: 12px;
        color:#6b7280;
    }

    @media print{
        .btn-print, .btn-back, .hint{ display:none !important; }
        body{ background:#fff; }
        .wrap{ margin:0; max-width:none; }
        .card{ box-shadow:none; border:none; }
        .card-header{ background:#fff; color:#111; border-bottom:1px solid #e5e7eb; }
        .badge{ background:#fff; border:1px solid #e5e7eb; color:#111; }
    }
</style>
</head>

<body>

<div class="wrap">
    <div class="card">
        <div class="card-header">
            <div class="title">
                <i class="fa-solid fa-calendar-check"></i>
                <div>
                    <h2>Booking Details</h2>
                    <div class="sub">Booking ID: <span class="mono">#<?= (int)$booking['appointment_id'] ?></span></div>
                </div>
            </div>

            <span class="<?= badgeClass($booking['status'] ?? '') ?>">
                <?= htmlspecialchars($booking['status'] ?? 'Pending') ?>
            </span>
        </div>

        <div class="card-body">
            <div class="grid">
                <div class="field">
                    <div class="label"><i class="fa-regular fa-user"></i> Client</div>
                    <div class="value"><?= htmlspecialchars($booking['client_name'] ?: ('Client #' . (int)$booking['client_id'])) ?></div>
                </div>

                <div class="field">
                    <div class="label"><i class="fa-solid fa-car"></i> Vehicle</div>
                    <div class="value"><?= htmlspecialchars($vehicleLabel ?: ('Vehicle #' . (int)$booking['vehicle_id'])) ?></div>
                </div>

                <div class="field">
                    <div class="label"><i class="fa-regular fa-calendar"></i> Appointment Date</div>
                    <div class="value"><?= htmlspecialchars($booking['appointmentDate'] ?? '-') ?></div>
                </div>

                <div class="field">
                    <div class="label"><i class="fa-regular fa-clock"></i> Appointment Time</div>
                    <div class="value"><?= htmlspecialchars($booking['appointmentTime'] ?? '-') ?></div>
                </div>

                <div class="field">
                    <div class="label"><i class="fa-solid fa-screwdriver-wrench"></i> Service Type</div>
                    <div class="value"><?= htmlspecialchars($booking['serviceType'] ?? '-') ?></div>
                </div>

                <div class="field">
                    <div class="label"><i class="fa-solid fa-user-gear"></i> Mechanic Assigned</div>
                    <div class="value"><?= htmlspecialchars($booking['mechanicAssigned'] ?? 'Unassigned') ?></div>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <div class="label"><i class="fa-regular fa-note-sticky"></i> Notes</div>
                    <div class="value">
                        <?= !empty($booking['notes'])
                            ? nl2br(htmlspecialchars($booking['notes']))
                            : "<span style='color:#6b7280;font-weight:600;'>No notes.</span>" ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-back">
                <i class="fa fa-arrow-left"></i> Back
            </a>

            <div style="display:flex; gap:10px; align-items:center;">
                <button class="btn btn-print" type="button" onclick="window.print();">
                    <i class="fa-solid fa-print"></i> Print
                </button>
                <span class="hint">Tip: Use Print to save as PDF.</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
