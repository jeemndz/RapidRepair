<?php
session_start();
include "db.php";
$currentPage = basename($_SERVER['PHP_SELF']);

/* =========================
   FETCH ACTIVE SERVICE TYPES (Dropdown + Default Labor Fee)
   Table: service_types(service_id, service_name, labor_fee, is_active, created_at)
========================= */
$serviceTypes = [];
$serviceTypesRes = $conn->query("
    SELECT service_id, service_name, labor_fee
    FROM service_types
    WHERE is_active = 1
    ORDER BY service_name ASC
");
if ($serviceTypesRes) {
    $serviceTypes = $serviceTypesRes->fetch_all(MYSQLI_ASSOC);
}

/* =========================
   HANDLE INVOICE CREATION (posts back to THIS file)
========================= */
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['serviceCategory'])) {

    $client_id = (int) ($_POST['client_id'] ?? 0);
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
    $mechanicAssigned = trim($_POST['mechanicAssigned'] ?? '');
    $serviceCategory = trim($_POST['serviceCategory'] ?? '');
    $serviceDescription = trim($_POST['serviceDescription'] ?? '');
    $partsUsed = trim($_POST['partsUsed'] ?? '');
    $laborFee = (float) ($_POST['laborFee'] ?? 0);
    $partsCost = (float) ($_POST['partsCost'] ?? 0);
    $totalCost = (float) ($_POST['totalCost'] ?? 0);
    $serviceDate = $_POST['serviceDate'] ?? date("Y-m-d");
    $status = trim($_POST['status'] ?? 'Ready for payment');

    // Basic validation
    if ($client_id <= 0 || $appointment_id <= 0 || $vehicle_id <= 0 || $serviceCategory === '') {
        $errorMsg = "Invalid invoice request. Please try again.";
    } else {
        // Insert invoice into services table
        $stmt = $conn->prepare("
            INSERT INTO services
            (client_id, appointment_id, vehicle_id, serviceCategory, serviceDescription, partsUsed, laborFee, partsCost, totalCost, serviceDate, mechanicAssigned, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiisssdddsss",
            $client_id,
            $appointment_id,
            $vehicle_id,
            $serviceCategory,
            $serviceDescription,
            $partsUsed,
            $laborFee,
            $partsCost,
            $totalCost,
            $serviceDate,
            $mechanicAssigned,
            $status
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Update the appointment status to 'Invoiced'
            $update = $conn->prepare("UPDATE appointment SET status='Invoiced' WHERE appointment_id=?");
            $update->bind_param("i", $appointment_id);
            $update->execute();
            $update->close();

            header("Location: servicesadmin.php?success=1");
            exit;
        } else {
            $errorMsg = "Error creating invoice: " . $stmt->error;
            $stmt->close();
        }
    }
}

if (isset($_GET['success'])) {
    $successMsg = "Invoice created successfully!";
}

/* =========================
   FETCH COMPLETED APPOINTMENTS (NOT YET INVOICED)
========================= */
$appointmentsSql = "
    SELECT
        a.appointment_id,
        a.appointmentDate,
        a.vehicle_id,
        a.serviceType,
        a.mechanicAssigned,
        c.firstName,
        c.lastName,
        c.client_id,
        v.plateNumber
    FROM appointment a
    LEFT JOIN client_information c ON a.client_id = c.client_id
    LEFT JOIN services s ON a.appointment_id = s.appointment_id
    LEFT JOIN vehicleinfo v ON a.vehicle_id = v.vehicle_id
    WHERE a.status='Completed' AND s.appointment_id IS NULL
    ORDER BY a.appointmentDate DESC, a.appointment_id DESC
";
$appointments = $conn->query($appointmentsSql);

/* =========================
   FETCH COMPLETED SERVICES / INVOICES
========================= */
$servicesSql = "
    SELECT s.service_id, s.appointment_id, s.client_id, s.vehicle_id, s.serviceCategory, s.partsUsed,
           s.laborFee, s.partsCost, s.totalCost, s.mechanicAssigned, s.status, s.serviceDate,
           c.firstName, c.lastName
    FROM services s
    LEFT JOIN client_information c ON s.client_id = c.client_id
    ORDER BY s.serviceDate DESC
";
$services = $conn->query($servicesSql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Services | Rapid Repair</title>

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="services.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .table-wrapper {
            overflow-x: auto;
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #3498db;
            color: #fff;
        }

        tr:hover {
            background: #f1f8ff;
        }

        .status-completed {
            color: green;
            font-weight: bold;
        }

        .status-paid {
            color: blue;
            font-weight: bold;
        }

        .btn-action {
            background: #2ecc71;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-action:hover {
            background: #27ae60;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 520px;
            max-width: calc(100% - 30px);
        }

        .close {
            float: right;
            cursor: pointer;
            font-size: 24px;
            font-weight: bold;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .form-grid label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-grid input,
        .form-grid select,
        .form-grid textarea {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .modal-actions {
            margin-top: 15px;
            text-align: right;
        }

        .modal-actions button {
            padding: 8px 14px;
            border: none;
            background: #071f4a;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
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

        .popup-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fdecea;
            color: #b71c1c;
            border: 1px solid #f5c6cb;
            padding: 14px 18px;
            border-radius: 6px;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            display: none;
            z-index: 10000;
            max-width: 360px;
            line-height: 1.4;
        }

        .popup-alert.show {
            display: block;
            animation: fadeOut 4s forwards;
        }

        @keyframes fadeOut {
            0% {
                opacity: 1
            }

            80% {
                opacity: 1
            }

            100% {
                opacity: 0
            }
        }

        /* dropdown base */
        .sidebar .dropdown-menu {
            display: none;
            list-style: none;
            padding-left: 12px;
            margin: 6px 0 0 0;
        }

        /* when opened */
        .sidebar .dropdown.open .dropdown-menu {
            display: block;
        }

        /* optional: make the toggle look clickable */
        .sidebar .dropdown-toggle {
            display: block;
            cursor: pointer;
        }

        .table-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0 8px;
        }

        .sort-select {
            padding: 8px 10px;
            border: 1px solid #d0d6e2;
            border-radius: 8px;
            outline: none;
        }

        .sort-select:focus {
            border-color: #3498db;
        }

        /* dropdown base */
        .sidebar .dropdown-menu {
            display: none;
            list-style: none;
            padding-left: 12px;
            margin: 6px 0 0 0;
        }

        /* when opened */
        .sidebar .dropdown.open .dropdown-menu {
            display: block;
        }

        /* optional: make the toggle look clickable */
        .sidebar .dropdown-toggle {
            display: block;
            cursor: pointer;
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
            <input type="text" id="globalSearch" placeholder="Search..." autocomplete="off">
            <div id="searchResults" class="search-results"></div>
        </div>

        <div class="user-info">
            <img src="pictures/user.png" alt="User">
            <div>
                <strong>Welcome!</strong><br>
                <span><?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></span>
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
                <li class="active"><a href="servicesadmin.php">Service & Invoice</a></li>
                <li><a href="reportsadmin.php">Reports</a></li>

                <!-- SETTINGS DROPDOWN -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Settings ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="manage_services.php">Manage Services</a></li>
                        <li><a href="manage_users.php">Manage User Accounts</a></li>
                        <li><a href="backup_restore.php">Back / Restore Data</a></li>
                        <li><a href="system_logs.php">System Logs</a></li>
                    </ul>
                </li>
            </ul>

            <div class="logout">
                <a href="logout.php">Logout</a>
            </div>
        </aside>


        <div class="content">

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="section-title">Completed Appointments / Ready for Invoice</div>

            <div class="table-tools">
                <label for="sortAppointments"><strong>Sort:</strong></label>
                <select id="sortAppointments" class="sort-select">
                    <option value="latest">Latest (Newest first)</option>
                    <option value="oldest">Oldest (Oldest first)</option>
                </select>
            </div>

            <div class="table-card">
                <table id="appointmentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Vehicle</th>
                            <th>Service Type</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                            <th class="actions-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments && $appointments->num_rows > 0): ?>
                            <?php while ($row = $appointments->fetch_assoc()): ?>
                                <?php
                                $clientName = trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''));
                                $plate = $row['plateNumber'] ?? '';
                                $vehicleLabel = $plate ? ("#" . (int) $row['vehicle_id'] . " • " . $plate) : ("#" . (int) $row['vehicle_id']);
                                ?>
                                <tr>
                                    <td><?= (int) $row['appointment_id'] ?></td>
                                    <td><?= htmlspecialchars($row['appointmentDate']) ?></td>
                                    <td><?= htmlspecialchars($clientName ?: '—') ?></td>
                                    <td><?= htmlspecialchars($vehicleLabel) ?></td>
                                    <td><?= htmlspecialchars($row['serviceType']) ?></td>
                                    <td><?= htmlspecialchars($row['mechanicAssigned'] ?: '—') ?></td>
                                    <td>
                                        <span class="badge badge-completed">Completed</span>
                                    </td>
                                    <td class="actions-col">
                                        <button type="button" class="btn-action btn-green create-invoice-btn"
                                            data-client-id="<?= (int) $row['client_id'] ?>"
                                            data-appointment-id="<?= (int) $row['appointment_id'] ?>"
                                            data-vehicle-id="<?= (int) $row['vehicle_id'] ?>"
                                            data-mechanic="<?= htmlspecialchars($row['mechanicAssigned'], ENT_QUOTES) ?>"
                                            data-client-name="<?= htmlspecialchars($clientName, ENT_QUOTES) ?>"
                                            data-plate="<?= htmlspecialchars($plate, ENT_QUOTES) ?>"
                                            data-service-type="<?= htmlspecialchars($row['serviceType'], ENT_QUOTES) ?>"
                                            data-appointment-date="<?= htmlspecialchars($row['appointmentDate'], ENT_QUOTES) ?>">
                                            <i class="fa-solid fa-file-invoice"></i> Create Invoice
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty">No completed appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="section-title">Completed Services / Invoices</div>

            <div class="table-tools">
                <label for="sortInvoices"><strong>Sort:</strong></label>
                <select id="sortInvoices" class="sort-select">
                    <option value="latest">Latest (Newest first)</option>
                    <option value="oldest">Oldest (Oldest first)</option>
                </select>
            </div>

            <div class="table-card">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Vehicle</th>
                            <th>Service Date</th>
                            <th>Service Category</th>
                            <th>Parts Used</th>
                            <th>Labor Fee</th>
                            <th>Parts Cost</th>
                            <th>Total Cost</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                            <th class="actions-col">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($services && $services->num_rows > 0): ?>
                            <?php while ($row = $services->fetch_assoc()): ?>
                                <?php
                                $clientName = trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''));
                                $st = strtolower(trim($row['status'] ?? ''));
                                $badgeClass = 'badge-default';
                                $badgeText = $row['status'] ?: '—';

                                if ($st === 'paid') {
                                    $badgeClass = 'badge-paid';
                                } elseif ($st === 'ready for payment') {
                                    $badgeClass = 'badge-ready';
                                } elseif ($st === 'cancelled') {
                                    $badgeClass = 'badge-cancelled';
                                } elseif ($st === 'completed') {
                                    $badgeClass = 'badge-completed';
                                }
                                ?>
                                <tr data-service-id="<?= (int) $row['service_id'] ?>"
                                    data-service-date="<?= htmlspecialchars($row['serviceDate'] ?? '', ENT_QUOTES) ?>">
                                    <td><?= htmlspecialchars($clientName ?: '—') ?></td>
                                    <td><?= "#" . (int) $row['vehicle_id'] ?></td>

                                    <!-- ✅ NEW: Service Date -->
                                    <td><?= htmlspecialchars($row['serviceDate'] ?? '—') ?></td>

                                    <td><?= htmlspecialchars($row['serviceCategory']) ?></td>
                                    <td><?= htmlspecialchars($row['partsUsed'] ?: '—') ?></td>
                                    <td>₱<?= number_format((float) $row['laborFee'], 2) ?></td>
                                    <td>₱<?= number_format((float) $row['partsCost'], 2) ?></td>
                                    <td><strong>₱<?= number_format((float) $row['totalCost'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($row['mechanicAssigned'] ?: '—') ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span></td>

                                    <td class="actions-col">
                                        <?php if (trim($row['status']) != 'Paid'): ?>
                                            <button type="button" class="btn-action btn-blue record-payment-btn"
                                                data-service-id="<?= (int) $row['service_id'] ?>"
                                                data-client-id="<?= (int) $row['client_id'] ?>"
                                                data-appointment-id="<?= (int) $row['appointment_id'] ?>"
                                                data-vehicle-id="<?= (int) $row['vehicle_id'] ?>"
                                                data-total="<?= htmlspecialchars($row['totalCost'], ENT_QUOTES) ?>">
                                                <i class="fa-solid fa-cash-register"></i> Record Payment
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-paid">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="empty">No services found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>


        <!-- POPUP ALERT -->
        <div id="payWarn" class="popup-alert"><span id="payWarnText"></span></div>

        <!-- PAYMENT MODAL -->
        <div id="paymentModal" class="modal">
            <div class="modal-content">
                <div class="modal-head">
                    <h2>Record Payment</h2>
                    <button class="close" type="button" id="paymentClose">&times;</button>
                </div>

                <form method="POST" action="process_payment.php" id="paymentForm">
                    <input type="hidden" name="service_id" id="payment_service_id">
                    <input type="hidden" name="client_id" id="payment_client_id">
                    <input type="hidden" name="appointment_id" id="payment_appointment_id">
                    <div class="form-grid">
                        <div>
                            <label>Vehicle ID</label>
                            <input type="text" id="payment_vehicle_id" readonly>
                        </div>
                        <div>
                            <label>Total Amount</label>
                            <input type="number" id="payment_total" readonly>
                        </div>
                        <div>
                            <label>Amount Paid</label>
                            <input type="number" name="amountPaid" id="amountPaid" step="0.01" min="0" required>
                        </div>
                        <div>
                            <label>Balance</label>
                            <input type="number" id="balance" name="balance" readonly>
                        </div>
                        <div>
                            <label>Payment Method</label>
                            <select name="paymentMethod" required>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>
                        <div>
                            <label>Remarks</label>
                            <textarea name="remarks"></textarea>
                        </div>
                        <div>
                            <label>Reference Number</label>
                            <input type="text" name="referenceNumber" id="referenceNumber" readonly>
                        </div>
                    </div>
                    <div class="modal-actions"><button type="submit">Save Payment</button></div>
                </form>
            </div>
        </div>

        <!-- CREATE INVOICE MODAL -->
        <div id="invoiceModal" class="modal">
            <div class="modal-content">
                <div class="modal-head">
                    <h2>Create Invoice</h2>
                    <button class="close" type="button" id="invoiceClose">&times;</button>
                </div>


                <form method="POST" action="">
                    <input type="hidden" name="client_id" id="invoice_client_id">
                    <input type="hidden" name="appointment_id" id="invoice_appointment_id">
                    <input type="hidden" name="vehicle_id" id="invoice_vehicle_id">
                    <input type="hidden" name="mechanicAssigned" id="invoice_mechanic">

                    <div class="form-grid">
                        <div>
                            <label>Client Name</label>
                            <input type="text" id="invoice_client_name" readonly>
                        </div>
                        <div>
                            <label>Plate Number</label>
                            <input type="text" id="invoice_plate_number" readonly>
                        </div>

                        <div>
                            <label>Service Category</label>
                            <select name="serviceCategory" id="invoice_serviceCategory" required>
                                <option value="">Select Category</option>
                                <?php foreach ($serviceTypes as $st): ?>
                                    <option value="<?= htmlspecialchars($st['service_name']) ?>"
                                        data-fee="<?= htmlspecialchars((string) $st['labor_fee']) ?>">
                                        <?= htmlspecialchars($st['service_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service Description</label>
                            <textarea name="serviceDescription"></textarea>
                        </div>

                        <div>
                            <label>Parts Used</label>
                            <textarea name="partsUsed"></textarea>
                        </div>

                        <div>
                            <label>Labor Fee</label>
                            <input type="number" name="laborFee" id="laborFee" step="0.01" min="0" required>
                        </div>

                        <div>
                            <label>Parts Cost</label>
                            <input type="number" name="partsCost" id="partsCost" step="0.01" min="0" required>
                        </div>

                        <div>
                            <label>Total Cost</label>
                            <input type="number" name="totalCost" id="totalCost" step="0.01" readonly required>
                        </div>

                        <div>
                            <label>Service Date</label>
                            <input type="date" name="serviceDate" id="invoice_serviceDate" required>
                        </div>

                        <div>
                            <label>Status</label>
                            <select name="status" required>
                                <option value="Ready for payment">Ready for payment</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="submit">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {

                // ========= helpers =========
                const warnBox = document.getElementById('payWarn');
                const warnText = document.getElementById('payWarnText');

                function showWarn(msg) {
                    if (!warnBox || !warnText) {
                        alert(msg);
                        return;
                    }
                    warnText.textContent = msg;
                    warnBox.classList.add('show');
                    setTimeout(() => warnBox.classList.remove('show'), 4000);
                }

                function toMoney(n) {
                    const x = Number(n);
                    if (Number.isNaN(x)) return "0.00";
                    return x.toFixed(2);
                }

                // ===== PAYMENT MODAL =====
                const paymentModal = document.getElementById('paymentModal');
                const paymentClose = document.getElementById('paymentClose');
                const paymentForm = document.getElementById('paymentForm');

                const amountPaidInput = document.getElementById('amountPaid');
                const totalInput = document.getElementById('payment_total');
                const balanceInput = document.getElementById('balance');
                const referenceInput = document.getElementById('referenceNumber');

                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.record-payment-btn');
                    if (!btn) return;

                    document.getElementById('payment_service_id').value = btn.dataset.serviceId;
                    document.getElementById('payment_client_id').value = btn.dataset.clientId;
                    document.getElementById('payment_appointment_id').value = btn.dataset.appointmentId;
                    document.getElementById('payment_vehicle_id').value = btn.dataset.vehicleId;

                    const total = parseFloat(btn.dataset.total) || 0;
                    totalInput.value = toMoney(total);

                    amountPaidInput.min = "0";
                    amountPaidInput.max = toMoney(total);

                    amountPaidInput.value = "";
                    balanceInput.value = toMoney(total);

                    referenceInput.value = 'RR-' + String(btn.dataset.serviceId).padStart(5, '0');

                    paymentModal.style.display = 'flex';
                    setTimeout(() => amountPaidInput.focus(), 50);
                });

                amountPaidInput.addEventListener('input', () => {
                    const total = parseFloat(totalInput.value) || 0;
                    const paid = parseFloat(amountPaidInput.value);

                    if (amountPaidInput.value === '' || Number.isNaN(paid)) {
                        balanceInput.value = toMoney(total);
                        return;
                    }
                    balanceInput.value = toMoney(total - paid);
                });

                amountPaidInput.addEventListener('blur', () => {
                    const total = parseFloat(totalInput.value) || 0;
                    let paid = parseFloat(amountPaidInput.value);

                    if (amountPaidInput.value === '' || Number.isNaN(paid)) {
                        amountPaidInput.value = '';
                        balanceInput.value = toMoney(total);
                        return;
                    }

                    if (paid < 0) {
                        showWarn("Amount paid cannot be negative.");
                        paid = 0;
                    }

                    if (paid > total) {
                        showWarn("Amount paid cannot exceed the total amount.");
                        paid = total;
                    }

                    if (paid !== total) {
                        showWarn("Exact payment required. Please enter exactly ₱" + toMoney(total));
                    }

                    amountPaidInput.value = toMoney(paid);
                    balanceInput.value = toMoney(total - paid);
                });

                if (paymentForm) {
                    paymentForm.addEventListener('submit', (e) => {
                        const total = parseFloat(totalInput.value) || 0;
                        const paid = parseFloat(amountPaidInput.value);

                        if (amountPaidInput.value === '' || Number.isNaN(paid)) {
                            e.preventDefault();
                            showWarn("Please enter an amount paid.");
                            amountPaidInput.focus();
                            return;
                        }

                        if (paid < 0) {
                            e.preventDefault();
                            showWarn("Amount paid cannot be negative.");
                            amountPaidInput.focus();
                            return;
                        }

                        if (paid > total) {
                            e.preventDefault();
                            showWarn("Amount paid cannot exceed the total amount.");
                            amountPaidInput.focus();
                            return;
                        }

                        if (paid !== total) {
                            e.preventDefault();
                            showWarn("Exact payment required. Please enter exactly ₱" + toMoney(total));
                            amountPaidInput.focus();
                            return;
                        }
                    });
                }

                if (paymentClose) {
                    paymentClose.addEventListener('click', () => paymentModal.style.display = 'none');
                }
                window.addEventListener('click', (e) => {
                    if (e.target === paymentModal) paymentModal.style.display = 'none';
                });

                // ===== INVOICE MODAL =====
                const invoiceModal = document.getElementById('invoiceModal');
                const invoiceClose = document.getElementById('invoiceClose');

                const laborInput = document.getElementById('laborFee');
                const partsInput = document.getElementById('partsCost');
                const totalInvoiceInput = document.getElementById('totalCost');

                const clientNameInput = document.getElementById('invoice_client_name');
                const plateInput = document.getElementById('invoice_plate_number');
                const serviceCategorySelect = document.getElementById('invoice_serviceCategory');
                const serviceDateInput = document.getElementById('invoice_serviceDate');

                function recalcInvoiceTotal() {
                    const labor = parseFloat(laborInput.value) || 0;
                    const parts = parseFloat(partsInput.value) || 0;
                    totalInvoiceInput.value = toMoney(labor + parts);
                }
                laborInput?.addEventListener('input', recalcInvoiceTotal);
                partsInput?.addEventListener('input', recalcInvoiceTotal);

                serviceCategorySelect?.addEventListener('change', () => {
                    const opt = serviceCategorySelect.selectedOptions[0];
                    const fee = opt ? (parseFloat(opt.dataset.fee) || 0) : 0;
                    laborInput.value = fee ? fee.toFixed(2) : '';
                    recalcInvoiceTotal();
                });

                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.create-invoice-btn');
                    if (!btn) return;

                    document.getElementById('invoice_client_id').value = btn.dataset.clientId || '';
                    document.getElementById('invoice_appointment_id').value = btn.dataset.appointmentId || '';
                    document.getElementById('invoice_vehicle_id').value = btn.dataset.vehicleId || '';
                    document.getElementById('invoice_mechanic').value = btn.dataset.mechanic || '';

                    if (clientNameInput) clientNameInput.value = btn.dataset.clientName || '';
                    if (plateInput) plateInput.value = btn.dataset.plate || '';

                    const serviceType = (btn.dataset.serviceType || '').trim();

                    let matchedOption = null;
                    Array.from(serviceCategorySelect.options).forEach(opt => {
                        if ((opt.value || '').trim().toLowerCase() === serviceType.toLowerCase()) {
                            matchedOption = opt;
                        }
                    });

                    if (matchedOption) {
                        matchedOption.selected = true;
                        const fee = parseFloat(matchedOption.dataset.fee) || 0;
                        laborInput.value = fee ? fee.toFixed(2) : '';
                    } else {
                        serviceCategorySelect.value = '';
                        laborInput.value = '';
                    }

                    partsInput.value = '0.00';
                    recalcInvoiceTotal();

                    if (serviceDateInput) {
                        const apptDate = btn.dataset.appointmentDate || '';
                        if (apptDate) serviceDateInput.value = apptDate;
                    }

                    invoiceModal.style.display = 'flex';
                });

                invoiceClose?.addEventListener('click', () => invoiceModal.style.display = 'none');
                window.addEventListener('click', (e) => {
                    if (e.target === invoiceModal) invoiceModal.style.display = 'none';
                });

            });
        </script>

        <!-- SIDEBAR DROPDOWN TOGGLE -->
        <script>
            document.querySelectorAll(".dropdown-toggle").forEach(toggle => {
                toggle.addEventListener("click", (e) => {
                    e.preventDefault();
                    const li = toggle.closest(".dropdown");
                    li.classList.toggle("open");
                });
            });

            document.addEventListener("click", (e) => {
                const dropdown = document.querySelector(".sidebar .dropdown");
                if (!dropdown) return;
                if (!e.target.closest(".sidebar")) dropdown.classList.remove("open");
            });
        </script>

        <!-- TABLE FILTER SEARCH (NO AJAX, NO REDIRECTS) -->
        <script>
            (function () {
                const search = document.getElementById("globalSearch");
                const resultsBox = document.getElementById("searchResults");

                const tables = [
                    document.getElementById("appointmentsTable"),
                    document.getElementById("invoicesTable")
                ];

                if (!search) return;

                function hideDropdown() {
                    if (!resultsBox) return;
                    resultsBox.style.display = "none";
                    resultsBox.innerHTML = "";
                }

                function applyFilter() {
                    const q = search.value.toLowerCase().trim();
                    hideDropdown();

                    tables.forEach(table => {
                        if (!table) return;

                        const tbody = table.querySelector("tbody");
                        if (!tbody) return;

                        const rows = Array.from(tbody.querySelectorAll("tr"));
                        let visible = 0;

                        rows.forEach(tr => {
                            // hide any previous "no results" row during recalculation
                            if (tr.classList.contains("__noresults")) return;

                            const rowText = tr.textContent.toLowerCase();
                            const match = (q === "") || rowText.includes(q);

                            tr.style.display = match ? "" : "none";
                            if (match) visible++;
                        });

                        // Remove old noresults row if any
                        const oldNo = tbody.querySelector("tr.__noresults");
                        if (oldNo) oldNo.remove();

                        // Add noresults row if needed
                        if (q !== "" && visible === 0) {
                            const colCount = table.querySelectorAll("thead th").length || 1;
                            const noRow = document.createElement("tr");
                            noRow.className = "__noresults";
                            noRow.innerHTML = `<td colspan="${colCount}" style="text-align:center;color:#777;padding:14px;">No matching records found.</td>`;
                            tbody.appendChild(noRow);
                        }
                    });
                }

                search.addEventListener("input", applyFilter);

                document.addEventListener("keydown", (e) => {
                    if (e.key === "Escape" && document.activeElement === search) {
                        search.value = "";
                        applyFilter();
                    }
                    if (e.key === "Escape") hideDropdown();
                });

                document.addEventListener("click", (e) => {
                    if (!e.target.closest(".search-box")) hideDropdown();
                });

            })();

        </script>

        <script>
            (function () {

                // ---------- helpers ----------
                function parseDateLoose(str) {
                    // expects yyyy-mm-dd (works with your data)
                    // fallback to Date parse
                    const s = (str || "").trim();
                    if (!s) return 0;
                    const t = Date.parse(s);
                    return isNaN(t) ? 0 : t;
                }

                function parseMoney(str) {
                    const s = (str || "").replace(/[^\d.-]/g, "");
                    const n = parseFloat(s);
                    return isNaN(n) ? 0 : n;
                }

                function sortTableRows(table, mode, config) {
                    if (!table) return;
                    const tbody = table.querySelector("tbody");
                    if (!tbody) return;

                    // keep "no results" row at bottom if exists
                    const noRow = tbody.querySelector("tr.__noresults");
                    if (noRow) noRow.remove();

                    const rows = Array.from(tbody.querySelectorAll("tr"))
                        .filter(tr => !tr.classList.contains("__noresults"));

                    // ignore empty placeholder row (your "No services found.")
                    // keep it untouched if it's the only row
                    if (rows.length <= 1) {
                        if (noRow) tbody.appendChild(noRow);
                        return;
                    }

                    const dir = (mode === "oldest") ? 1 : -1;

                    rows.sort((a, b) => {
                        const av = config.getValue(a, mode);
                        const bv = config.getValue(b, mode);

                        if (av < bv) return -1 * dir;
                        if (av > bv) return 1 * dir;
                        return 0;
                    });

                    rows.forEach(r => tbody.appendChild(r));
                    if (noRow) tbody.appendChild(noRow);
                }

                // ---------- Appointments sorting ----------
                const appointmentsTable = document.getElementById("appointmentsTable");
                const sortAppointments = document.getElementById("sortAppointments");

                const apptConfig = {
                    // columns: ID(0), Date(1), ...
                    getValue: (tr, mode) => {
                        const tds = tr.querySelectorAll("td");
                        if (!tds || tds.length < 2) return 0;

                        const id = parseInt((tds[0].textContent || "").trim(), 10) || 0;
                        const dateVal = parseDateLoose(tds[1].textContent);

                        // "recent" = by ID (useful when dates are equal)
                        if (mode === "recent") return id;
                        return dateVal;
                    }
                };

                if (sortAppointments) {
                    // default sort latest on load
                    sortTableRows(appointmentsTable, "latest", apptConfig);

                    sortAppointments.addEventListener("change", () => {
                        sortTableRows(appointmentsTable, sortAppointments.value, apptConfig);
                    });
                }

                // ---------- Invoices sorting ----------
                const invoicesTable = document.getElementById("invoicesTable");
                const sortInvoices = document.getElementById("sortInvoices");

                // Since your invoices table doesn't show serviceDate/service_id,
                // we'll sort by:
                // - "recent": try service_id via data attribute if you add it, else by Total Cost
                // - latest/oldest: by Total Cost fallback (or Service Date if you add the column)
                const invConfig = {
                    // columns now: Client(0), Vehicle(1), Service(2), Parts(3), Labor(4), PartsCost(5), Total(6), ...
                    getValue: (tr, mode) => {
                        const tds = tr.querySelectorAll("td");
                        if (!tds || tds.length < 7) return 0;

                        const totalCost = parseMoney(tds[6].textContent);

                        // If later you add a Service Date column, adjust here.
                        // If you add data-service-date / data-service-id attrs, we can use them too.
                        const serviceIdAttr = tr.getAttribute("data-service-id");
                        const serviceId = serviceIdAttr ? (parseInt(serviceIdAttr, 10) || 0) : 0;

                        if (mode === "recent") {
                            return serviceId || totalCost;
                        }
                        return totalCost;
                    }
                };

                if (sortInvoices) {
                    sortTableRows(invoicesTable, "latest", invConfig);

                    sortInvoices.addEventListener("change", () => {
                        sortTableRows(invoicesTable, sortInvoices.value, invConfig);
                    });
                }

            })();
        </script>

</body>

</html>