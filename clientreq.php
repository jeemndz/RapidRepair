<?php
session_start();
require "db.php";
require_once "log_helper.php";

/* ================= AUTH CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

/* ================= GET CLIENT ID ================= */
$clientStmt = $conn->prepare("SELECT client_id FROM client_information WHERE user_id = ? LIMIT 1");
$clientStmt->bind_param("i", $user_id);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();

if ($clientResult->num_rows === 0) {
    die("Client record not found.");
}
$client_id = (int) $clientResult->fetch_assoc()['client_id'];
$clientStmt->close();

/* ================= FETCH VEHICLES ================= */
$vehicleStmt = $conn->prepare("
    SELECT vehicle_id, plateNumber
    FROM vehicleinfo
    WHERE client_id = ? AND status = 'Active'
    ORDER BY plateNumber ASC
");
$vehicleStmt->bind_param("i", $client_id);
$vehicleStmt->execute();
$vehicles = $vehicleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$vehicleStmt->close();

/* ================= FETCH SERVICE TYPES ================= */
$serviceTypesStmt = $conn->prepare("
    SELECT service_id, service_name
    FROM service_types
    WHERE is_active = 1
    ORDER BY service_name ASC
");
$serviceTypesStmt->execute();
$serviceTypes = $serviceTypesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$serviceTypesStmt->close();

/* ================= CREATE BOOKING ================= */
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {

    $vehicle_id       = (int)($_POST['vehicle_id'] ?? 0);
    $serviceType      = trim($_POST['serviceType'] ?? '');
    $appointmentDate  = $_POST['appointmentDate'] ?? '';
    $appointmentTime  = $_POST['appointmentTime'] ?? '';
    $mechanicAssigned = $_POST['mechanicAssigned'] ?? 'Unassigned';
    $notes            = trim($_POST['notes'] ?? '');
    $status           = "Pending";

    if ($vehicle_id <= 0 || $serviceType === '' || $appointmentDate === '' || $appointmentTime === '') {
        $errorMsg = "Please fill in all required fields.";
    } else {

        // ✅ Validate serviceType exists in service_types (active)
        $svcChk = $conn->prepare("SELECT service_id FROM service_types WHERE service_name = ? AND is_active = 1 LIMIT 1");
        $svcChk->bind_param("s", $serviceType);
        $svcChk->execute();
        $svcChk->store_result();

        if ($svcChk->num_rows === 0) {
            $errorMsg = "Selected service is not valid. Please refresh and try again.";
            $svcChk->close();
        } else {
            $svcChk->close();

            // ✅ Block past date/time
            $requestedTs = strtotime($appointmentDate . " " . $appointmentTime);
            if ($requestedTs === false) {
                $errorMsg = "Invalid appointment date/time.";
            } else {
                $nowTs = time();
                if ($requestedTs < $nowTs) {
                    $errorMsg = "Appointment date/time cannot be in the past.";
                } else {

                    // ✅ Business hours 08:00 to 20:00 (allow 20:00 exactly)
                    $openTs  = strtotime($appointmentDate . " 08:00");
                    $closeTs = strtotime($appointmentDate . " 20:00");

                    if ($requestedTs < $openTs || $requestedTs > $closeTs) {
                        $errorMsg = "Booking time must be within business hours (08:00 AM to 08:00 PM).";
                    } else {

                        // ✅ Prevent double booking (same vehicle + same date + same time)
                        $check = $conn->prepare("
                            SELECT appointment_id
                            FROM appointment
                            WHERE vehicle_id = ?
                              AND appointmentDate = ?
                              AND appointmentTime = ?
                              AND status IN ('Pending','Approved','Scheduled','Ongoing')
                            LIMIT 1
                        ");
                        $check->bind_param("iss", $vehicle_id, $appointmentDate, $appointmentTime);
                        $check->execute();
                        $check->store_result();

                        if ($check->num_rows > 0) {
                            $errorMsg = "This vehicle already has a booking on the same date and time.";
                            $check->close();
                        } else {
                            $check->close();

                            // ✅ Insert booking
                            $insertStmt = $conn->prepare("
                                INSERT INTO appointment
                                (client_id, vehicle_id, serviceType, appointmentDate, appointmentTime, mechanicAssigned, notes, status, dateCreated)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $insertStmt->bind_param(
                                "iissssss",
                                $client_id,
                                $vehicle_id,
                                $serviceType,
                                $appointmentDate,
                                $appointmentTime,
                                $mechanicAssigned,
                                $notes,
                                $status
                            );

                            if ($insertStmt->execute()) {

                                $newAppointmentId = (int)$insertStmt->insert_id;

                                // ✅ Get plate number for better log details
                                $p = $conn->prepare("SELECT plateNumber FROM vehicleinfo WHERE vehicle_id=? AND client_id=? LIMIT 1");
                                $p->bind_param("ii", $vehicle_id, $client_id);
                                $p->execute();
                                $plateRow = $p->get_result()->fetch_assoc();
                                $p->close();

                                $plate = $plateRow['plateNumber'] ?? ("#".$vehicle_id);

                                // ✅ SYSTEM LOG
                                log_event(
                                    $conn,
                                    "Create Booking",
                                    "appointment",
                                    $newAppointmentId,
                                    "Client requested booking: Vehicle {$plate} | Service {$serviceType} | {$appointmentDate} {$appointmentTime} | Mechanic {$mechanicAssigned} | Notes: " . ($notes !== '' ? $notes : '—')
                                );

                                $insertStmt->close();
                                header("Location: clientreq.php?success=1");
                                exit;

                            } else {
                                $errorMsg = "Failed to create booking: " . $insertStmt->error;
                                $insertStmt->close();
                            }
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $successMsg = "Booking has been requested!";
}

/* ================= FETCH CLIENT BOOKINGS ================= */
$bookingStmt = $conn->prepare("
    SELECT 
        a.appointmentDate,
        a.appointmentTime,
        a.serviceType,
        a.status,
        a.mechanicAssigned,
        v.plateNumber
    FROM appointment a
    JOIN vehicleinfo v ON a.vehicle_id = v.vehicle_id
    WHERE a.client_id = ?
    ORDER BY a.dateCreated DESC
");
$bookingStmt->bind_param("i", $client_id);
$bookingStmt->execute();
$bookings = $bookingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bookingStmt->close();

/* status badge class helper */
function statusBadgeClass($status)
{
    $s = strtolower(trim((string) $status));
    return match ($s) {
        'pending'   => 'st-pending',
        'approved'  => 'st-approved',
        'scheduled' => 'st-approved',
        'ongoing'   => 'st-ongoing',
        'completed' => 'st-completed',
        'cancelled' => 'st-cancelled',
        'invoiced'  => 'st-invoiced',
        default     => 'st-default'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking | Rapid Repair</title>
    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="clientbooking.css">
</head>
<body>

<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>

    <!-- 🔍 SEARCH BAR -->
    <div class="search-box">
        <input
            type="text"
            id="globalSearch"
            placeholder="Search bookings, vehicles, services..."
            autocomplete="off"
        >
        <div id="searchResults" class="search-results"></div>
    </div>

    <div class="user-info">
        <img src="pictures/user.png" alt="User">
        <div>
            <strong>Welcome!</strong><br>
            <span><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
        </div>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <ul>
            <li><a href="user_home.php">Home</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="vehicle.php">Vehicle</a></li>
            <li class="active"><a href="clientreq.php">Booking</a></li>
            <li><a href="payments.php">Payment</a></li>
        </ul>
        <div class="logout"><a href="logout.php">Logout</a></div>
    </aside>

    <main class="content">

        <div class="page-head">
            <div>
                <h1 class="page-title">Booking</h1>
                <p class="page-sub">Request an appointment and track its status.</p>
            </div>

            <div class="page-actions">
                <button type="button" class="btn-primary" onclick="openModal()">
                    + Create Booking
                </button>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <section class="table-card">
            <div class="table-card-head">
                <h2>Your Booking Requests</h2>
                <span class="muted">Most recent first</span>
            </div>

            <div class="table-wrap">
                <table class="booking-table" id="vehiclesTable">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr><td colspan="6" class="empty">No bookings found</td></tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><?= htmlspecialchars($b['plateNumber']) ?></td>
                                    <td><?= htmlspecialchars($b['serviceType']) ?></td>
                                    <td><?= htmlspecialchars($b['appointmentDate']) ?></td>
                                    <td><?= htmlspecialchars($b['appointmentTime']) ?></td>
                                    <td><?= htmlspecialchars($b['mechanicAssigned']) ?></td>
                                    <td>
                                        <span class="status-badge <?= statusBadgeClass($b['status']) ?>">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<!-- ================= MODAL ================= -->
<div id="bookingModal" class="modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-label="Create Booking">
        <div class="modal-header">
            <div>
                <h3>Create Booking</h3>
                <p class="muted">Fill out the form to request an appointment.</p>
            </div>
            <button type="button" class="icon-close" onclick="closeModal()" aria-label="Close">&times;</button>
        </div>

        <form method="POST">
            <div class="form-grid">
                <div>
                    <label>Vehicle <span class="req">*</span></label>
                    <select name="vehicle_id" required>
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= (int)$v['vehicle_id'] ?>">
                                <?= htmlspecialchars($v['plateNumber']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Service Type <span class="req">*</span></label>
                    <select name="serviceType" required>
                        <option value="">Select Service</option>
                        <?php foreach ($serviceTypes as $st): ?>
                            <option value="<?= htmlspecialchars($st['service_name']) ?>">
                                <?= htmlspecialchars($st['service_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Mechanic Assigned</label>
                    <select name="mechanicAssigned">
                        <option value="Unassigned">Unassigned</option>
                        <option value="Mechanic 1">Mechanic 1</option>
                        <option value="Mechanic 2">Mechanic 2</option>
                        <option value="Mechanic 3">Mechanic 3</option>
                    </select>
                </div>

                <div>
                    <label>Date <span class="req">*</span></label>
                    <input type="date" name="appointmentDate" min="<?= date('Y-m-d'); ?>" required>
                </div>

                <div>
                    <label>Time <span class="req">*</span></label>
                    <input type="time" name="appointmentTime" min="08:00" max="20:00" required>
                    <small class="hint">Business hours: 08:00 AM – 08:00 PM</small>
                </div>

                <div style="grid-column:1/-1;">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Optional notes..."></textarea>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" name="create_booking" class="btn-primary">Save Booking</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    const m = document.getElementById("bookingModal");
    m.style.display = "flex";
    m.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
}
function closeModal() {
    const m = document.getElementById("bookingModal");
    m.style.display = "none";
    m.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
}
document.getElementById("bookingModal").addEventListener("click", (e) => {
    if (e.target.id === "bookingModal") closeModal();
});
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeModal();
});
</script>

<!-- ✅ GLOBAL SEARCH = FILTER THIS TABLE ONLY -->
<script>
(function () {
    const search = document.getElementById("globalSearch");
    const table = document.getElementById("vehiclesTable");
    const resultsBox = document.getElementById("searchResults"); // exists for layout

    if (!search || !table) return;

    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    function hideDropdown() {
        if (!resultsBox) return;
        resultsBox.style.display = "none";
        resultsBox.innerHTML = "";
    }

    function applyFilter() {
        const q = search.value.toLowerCase().trim();
        hideDropdown();

        const rows = Array.from(tbody.querySelectorAll("tr"));
        let visible = 0;

        rows.forEach(tr => {
            const tds = tr.querySelectorAll("td");

            // ignore the "No bookings found" row
            if (tds.length <= 1) {
                tr.style.display = q ? "none" : "";
                return;
            }

            // search all displayed columns
            const rowText = Array.from(tds).map(td => td.textContent).join(" ").toLowerCase();
            const match = q === "" || rowText.includes(q);
            tr.style.display = match ? "" : "none";
            if (match) visible++;
        });

        // show "No matching results" row
        let noRow = tbody.querySelector("tr.__noresults");
        if (q !== "" && visible === 0) {
            if (!noRow) {
                noRow = document.createElement("tr");
                noRow.className = "__noresults";
                noRow.innerHTML = `<td colspan="6" style="text-align:center;color:#777;padding:14px;">No matching bookings found.</td>`;
                tbody.appendChild(noRow);
            }
            noRow.style.display = "";
        } else {
            if (noRow) noRow.style.display = "none";
        }
    }

    search.addEventListener("input", applyFilter);

    // ESC clears search
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && document.activeElement === search) {
            search.value = "";
            applyFilter();
        }
    });
})();
</script>

</body>
</html>
