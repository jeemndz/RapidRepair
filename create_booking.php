<?php
require_once "db.php";

// Fetch clients for dropdown
$clients = $conn->query("SELECT client_id, firstName, lastName FROM client_information ORDER BY client_id DESC");

// Fetch vehicles for dropdown
$vehicles = $conn->query("SELECT vehicle_id, plateNumber FROM vehicleinfo ORDER BY vehicle_id DESC");

// Fetch Services for dropdown
$serviceTypesStmt = $conn->prepare("
    SELECT service_id, service_name
    FROM service_types
    WHERE is_active = 1
    ORDER BY service_name ASC
");
$serviceTypesStmt->execute();
$serviceTypes = $serviceTypesStmt->get_result();


$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {

    // ✅ Get values (match the form names exactly)
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
    $serviceType = trim($_POST['serviceType'] ?? '');
    $appointmentDate = $_POST['appointmentDate'] ?? '';
    $appointmentTime = $_POST['appointmentTime'] ?? '';
    $mechanicAssigned = $_POST['mechanicAssigned'] ?? 'Unassigned';
    $notes = trim($_POST['notes'] ?? '');

    $status = "Pending";

    // ✅ Required validation
    if ($client_id <= 0 || $vehicle_id <= 0 || $serviceType === '' || $appointmentDate === '' || $appointmentTime === '' || $mechanicAssigned === '') {
        $error = "Please fill in all required fields.";
    } else {

        // ✅ 1) Block past dates & past times today
        $today = date("Y-m-d");
        $nowDateTime = date("Y-m-d H:i");

        $requestedDateTime = $appointmentDate . " " . $appointmentTime;

        if ($appointmentDate < $today) {
            $error = "Appointment date cannot be in the past.";
        } elseif ($requestedDateTime < $nowDateTime) {
            $error = "Appointment time cannot be earlier than the current time.";
        } else {

            // ✅ 2) Business hours: 08:00 to 20:00 (8PM is closing time)
            // To enforce "closing at 8PM", last allowed time is 19:59
            $openTime = "08:00";
            $lastTime = "19:59";

            if ($appointmentTime < $openTime || $appointmentTime > $lastTime) {
                $error = "Booking time must be within business hours (08:00 AM to 08:00 PM).";
            } else {

                // ✅ 3) Prevent double booking (same vehicle + date + time)
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
                    $error = "This vehicle already has a booking on the same date and time.";
                    $check->close();
                } else {
                    $check->close();

                    // ✅ Insert into DB
                    $stmt = $conn->prepare("
                        INSERT INTO appointment
                        (client_id, vehicle_id, serviceType, appointmentDate, appointmentTime, mechanicAssigned, notes, status, dateCreated)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param(
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

                    if ($stmt->execute()) {
                        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                        exit();
                    } else {
                        $error = "Error creating booking: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Success message after redirect
if (isset($_GET['success'])) {
    $success = "Booking created successfully!";
}
?>


<!-- Modal -->
<div id="createBookingModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Booking</h3>
            <button type="button" class="cancel-btn" onclick="closeBookingModal()">&times;</button>
        </div>

        <?php if ($success): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">

            <label>Client*</label>
            <select name="client_id" id="clientSelect" required>
                <option value="">-- Select Client --</option>
                <?php while ($client = $clients->fetch_assoc()): ?>
                    <option value="<?= (int) $client['client_id'] ?>">
                        <?= htmlspecialchars($client['firstName'] . " " . $client['lastName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>


            <label>Vehicle*</label>
            <select name="vehicle_id" id="vehicleSelect" required>
                <option value="">-- Select Vehicle --</option>
            </select>


            <label>Service Type*</label>
            <select name="serviceType" required>
                <option value="">-- Select Service --</option>

                <?php while ($st = $serviceTypes->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($st['service_name']) ?>">
                        <?= htmlspecialchars($st['service_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Appointment Date*</label>
            <input type="date" name="appointmentDate" min="<?= date('Y-m-d'); ?>" required>

            <label>Appointment Time*</label>
            <input type="time" name="appointmentTime" min="08:00" max="19:59" required>

            <label>Mechanic Assigned*</label>
            <select name="mechanicAssigned" required>
                <option value="">--Select Mechanic--</option>
                <option value="Unassigned">Unassigned</option>
                <option value="Mechanic 1">Mechanic 1</option>
                <option value="Mechanic 2">Mechanic 2</option>
                <option value="Mechanic 3">Mechanic 3</option>
            </select>

            <label>Notes</label>
            <textarea name="notes"></textarea>

            <div class="modal-actions">
                <button type="submit" name="create_booking" class="btn create-btn">Create</button>
                <button type="button" class="btn cancel-btn2" onclick="closeBookingModal()">Cancel</button>
            </div>
        </form>

    </div>
</div>

<style>
    /* Modal overlay */
    #createBookingModal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        display: none;
        /* IMPORTANT: stays closed by default */
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    /* modal box */
    .modal-content {
        background: #fff;
        padding: 20px 26px;
        border-radius: 10px;
        width: 420px;
        max-width: calc(100% - 30px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
    }

    /* header */
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e5e5e5;
        margin-bottom: 12px;
        padding-bottom: 10px;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }

    /* close button */
    .cancel-btn {
        background: none;
        border: none;
        font-size: 22px;
        cursor: pointer;
    }

    /* fields */
    .modal-form label {
        display: block;
        margin-top: 10px;
        font-weight: 600;
        font-size: 13px;
    }

    .modal-form input,
    .modal-form select,
    .modal-form textarea {
        width: 100%;
        padding: 9px 10px;
        border-radius: 6px;
        border: 1px solid #cfcfcf;
        margin-top: 5px;
        outline: none;
    }

    .modal-form textarea {
        min-height: 80px;
        resize: vertical;
    }

    /* actions */
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }

    .btn {
        padding: 9px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }

    .create-btn {
        background: #071f4a;
        color: #fff;
    }

    .cancel-btn2 {
        background: #cfcfcf;
    }

    .success-msg {
        color: green;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .error-msg {
        color: #b30000;
        margin-bottom: 10px;
        font-weight: 600;
    }
</style>

<script>
    function openBookingModal() {
        document.getElementById('createBookingModal').style.display = 'flex';
    }
    function closeBookingModal() {
        document.getElementById('createBookingModal').style.display = 'none';
    }

    // close when clicking outside modal box
    document.getElementById('createBookingModal').addEventListener('click', (e) => {
        if (e.target.id === 'createBookingModal') {
            closeBookingModal();
        }
    });
</script>

<script>
function openBookingModal() {
    document.getElementById('createBookingModal').style.display = 'flex';
}
function closeBookingModal() {
    document.getElementById('createBookingModal').style.display = 'none';
}

// close when clicking outside modal box
document.getElementById('createBookingModal').addEventListener('click', (e) => {
    if (e.target.id === 'createBookingModal') {
        closeBookingModal();
    }
});

/* ==========================
   FILTER VEHICLES BY CLIENT
========================== */
const clientSelect  = document.getElementById("clientSelect");
const vehicleSelect = document.getElementById("vehicleSelect");

async function loadClientVehicles(clientId) {
    // Reset dropdown
    vehicleSelect.innerHTML = `<option value="">-- Select Vehicle --</option>`;

    if (!clientId) return;

    try {
        const res = await fetch(`fetch_client_vehicles.php?client_id=${encodeURIComponent(clientId)}`);
        const vehicles = await res.json();

        if (!Array.isArray(vehicles) || vehicles.length === 0) {
            vehicleSelect.innerHTML = `<option value="">No vehicles found for this client</option>`;
            return;
        }

        vehicles.forEach(v => {
            const opt = document.createElement("option");
            opt.value = v.vehicle_id;
            opt.textContent = v.plateNumber;
            vehicleSelect.appendChild(opt);
        });

    } catch (err) {
        console.error(err);
        vehicleSelect.innerHTML = `<option value="">Failed to load vehicles</option>`;
    }
}

clientSelect.addEventListener("change", () => {
    loadClientVehicles(clientSelect.value);
});
</script>
