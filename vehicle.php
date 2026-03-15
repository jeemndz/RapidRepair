<?php
session_start();
require_once "db.php";

// ✅ keep your logic, but make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);

// ✅ If you already store client_id in session, use it.
// ✅ Otherwise, fetch client_id using user_id (more reliable for your setup)
$client_id = (int) ($_SESSION['client_id'] ?? 0);
$clientRow = null;

if ($client_id <= 0 && $user_id > 0) {
    $stmt = $conn->prepare("SELECT client_id, firstName, lastName FROM client_information WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $clientRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($clientRow['client_id'])) {
        $client_id = (int) $clientRow['client_id'];
        $_SESSION['client_id'] = $client_id;
    }
}

// If still no client_id, redirect (no profile yet)
if ($client_id <= 0) {
    header("Location: profile.php");
    exit();
}

// Fetch vehicles for this client
$stmt = $conn->prepare("SELECT * FROM vehicleinfo WHERE client_id = ? ORDER BY vehicle_id DESC");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$vehicles = $stmt->get_result();
$stmt->close();

// Build name for header
$name = $_SESSION['name'] ?? '';
if ($name === '' && !empty($clientRow)) {
    $name = trim(($clientRow['firstName'] ?? '') . ' ' . ($clientRow['lastName'] ?? ''));
}
if ($name === '') $name = 'User';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Vehicles | RapidRepair</title>

    <!-- IMPORTANT: pagelayout.css first -->
    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="vehicle.css">


    <style>
        /* ALERTS */
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .alert-warning button {
            background: #1f3f8b;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 18px;
            cursor: pointer;
        }

        .alert-warning button:hover {
            background: #17306b;
        }

        /* MODAL STYLE */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 18px;
        }

        .modal-content {
            max-width: 500px;
            width: 90%;
            padding: 20px 25px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
            text-align: left;
        }

        .modal-content h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-content .record-row {
            margin-bottom: 12px;
        }

        .modal-content .record-row label {
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
        }

        .modal-content .record-row input,
        .modal-content .record-row select {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .modal-content .btn-primary,
        .modal-content .close-btn,
        .modal-content .cancel {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 18px;
            border-radius: 18px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .modal-content .btn-primary {
            background: #254a91;
            color: #fff;
        }

        .modal-content .btn-primary:hover {
            background: #1e3a73;
        }

        .modal-content .cancel,
        .modal-content .close-btn {
            background: #ccc;
            color: #000;
        }

        .modal-content .cancel:hover,
        .modal-content .close-btn:hover {
            background: #aaa;
        }

        /* ====== VIEW MODAL (2-column + nicer UI) ====== */
        #viewModal .modal-content {
            max-width: 720px;
            width: 92%;
            padding: 22px 26px;
            border-radius: 12px;
        }

        #viewModal h2 {
            margin: 0 0 14px 0;
            font-size: 22px;
            font-weight: 700;
        }

        #viewVehicleDetails {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
            margin-top: 10px;
        }

        #viewVehicleDetails .record-row {
            margin: 0 !important;
            padding: 10px 12px;
            border: 1px solid #eee;
            border-radius: 10px;
            background: #fafafa;
        }

        #viewVehicleDetails .record-row label {
            font-size: 12px;
            font-weight: 700;
            color: #1f3f8b;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin: 0 0 6px 0;
        }

        #viewVehicleDetails .record-row span {
            display: block;
            font-size: 14px;
            color: #111;
            word-break: break-word;
        }

        @media (max-width: 640px) {
            #viewVehicleDetails {
                grid-template-columns: 1fr;
            }
        }

        /* ====== EDIT MODAL (2-column + better UI) ====== */
        #editModal .modal-content {
            max-width: 760px;
            width: 92%;
            padding: 22px 26px;
            border-radius: 12px;
        }

        #editModal h2 {
            margin: 0 0 14px 0;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
        }

        #editVehicleForm {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
        }

        #editVehicleForm .record-row {
            margin: 0 !important;
        }

        #editVehicleForm .record-row label {
            font-size: 12px;
            font-weight: 700;
            color: #1f3f8b;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin: 0 0 6px 0;
        }

        #editVehicleForm .record-row input,
        #editVehicleForm .record-row select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #ddd;
            outline: none;
            background: #fff;
        }

        #editVehicleForm .record-row input:focus,
        #editVehicleForm .record-row select:focus {
            border-color: #1f3f8b;
            box-shadow: 0 0 0 3px rgba(31, 63, 139, .12);
        }

        #editVehicleForm .modal-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 8px;
        }

        #editModal .btn-primary {
            background: #1f3f8b;
            color: #fff;
            padding: 9px 18px;
            border-radius: 22px;
            border: none;
            cursor: pointer;
        }

        #editModal .btn-primary:hover {
            background: #17306b;
        }

        #editModal .cancel {
            background: #d0d0d0;
            color: #111;
            padding: 9px 18px;
            border-radius: 22px;
            border: none;
            cursor: pointer;
        }

        #editModal .cancel:hover {
            background: #bdbdbd;
        }

        @media (max-width: 640px) {
            #editVehicleForm {
                grid-template-columns: 1fr;
            }
        }

        /* ====== REGISTER MODAL (2-column + better UI) ====== */
        #registerModal .modal-content {
            max-width: 760px;
            width: 92%;
            padding: 22px 26px;
            border-radius: 12px;
        }

        #registerModal h2 {
            margin: 0 0 14px 0;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
        }

        #registerModal form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
        }

        #registerModal .record-row {
            margin: 0 !important;
        }

        #registerModal .record-row label {
            font-size: 12px;
            font-weight: 700;
            color: #1f3f8b;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin: 0 0 6px 0;
        }

        #registerModal .record-row input,
        #registerModal .record-row select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #ddd;
            outline: none;
            background: #fff;
        }

        #registerModal .record-row input:focus,
        #registerModal .record-row select:focus {
            border-color: #1f3f8b;
            box-shadow: 0 0 0 3px rgba(31, 63, 139, .12);
        }

        #registerModal .modal-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 8px;
        }

        #registerModal .btn-primary {
            background: #1f3f8b;
            color: #fff;
            padding: 9px 18px;
            border-radius: 22px;
            border: none;
            cursor: pointer;
        }

        #registerModal .btn-primary:hover {
            background: #17306b;
        }

        #registerModal .cancel {
            background: #d0d0d0;
            color: #111;
            padding: 9px 18px;
            border-radius: 22px;
            border: none;
            cursor: pointer;
        }

        #registerModal .cancel:hover {
            background: #bdbdbd;
        }

        @media (max-width: 640px) {
            #registerModal form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- TOPBAR (matches pagelayout.css) -->
    <header class="topbar">
        <div class="logo">
            <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
            <small>Commitment is our Passion</small>
        </div>

        <!-- ✅ UPDATED: real globalSearch input -->
        <div class="search-box">
            <input type="text" id="globalSearch" placeholder="Search vehicles..." autocomplete="off">
            <div id="searchResults" class="search-results" style="display:none;"></div>
        </div>

        <div class="user-info">
            <img src="pictures/user.png" alt="User">
            <div>
                <strong>Welcome!</strong><br>
                <span><?= htmlspecialchars($name) ?></span>
            </div>
        </div>
    </header>

    <div class="layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <ul>
                <li><a href="user_home.php">Home</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li class="active"><a href="vehicle.php">Vehicle</a></li>
                <li><a href="clientreq.php">Booking</a></li>
                <li><a href="payments.php">Payment</a></li>
            </ul>
            <div class="logout">
                <a href="logout.php">Logout</a>
            </div>
        </aside>

        <!-- CONTENT -->
        <main class="content">

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="alert-success">Changes are successfully!</div>
            <?php endif; ?>

            <?php if (!($vehicles && $vehicles->num_rows > 0)): ?>
                <div class="alert-warning">
                    <span>You don’t have any vehicle registered yet.</span>
                    <button type="button" onclick="openRegisterModal()">Register Vehicle</button>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header">
                    <h2>Your Vehicles</h2>
                    <button class="btn-primary" type="button" onclick="openRegisterModal()">Register Vehicle</button>
                </div>

                <!-- ✅ table has an id for filtering -->
                <table id="vehiclesTable">
                    <thead>
                        <tr>
                            <th>Vehicle ID</th>
                            <th>Plate Number</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Year</th>
                            <th>Engine Number</th>
                            <th>Fuel</th>
                            <th>Transmission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($vehicles && $vehicles->num_rows > 0): ?>
                            <?php while ($row = $vehicles->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int) $row['vehicle_id'] ?></td>
                                    <td><?= htmlspecialchars($row['plateNumber']) ?></td>
                                    <td><?= htmlspecialchars($row['vehicleBrand']) ?></td>
                                    <td><?= htmlspecialchars($row['vehicleModel']) ?></td>
                                    <td><?= htmlspecialchars($row['vehicleYear']) ?></td>
                                    <td><?= htmlspecialchars($row['engineNumber']) ?></td>
                                    <td><?= htmlspecialchars($row['fuelType']) ?></td>
                                    <td><?= htmlspecialchars($row['transmissiontype']) ?></td>
                                    <td>
                                        <button type="button" class="btn-primary view-btn"
                                            data-vehicle='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>View</button>

                                        <button type="button" class="btn-primary edit-btn"
                                            data-vehicle='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>Edit</button>

                                        <button type="button" class="btn-primary delete-btn"
                                            data-id="<?= (int) $row['vehicle_id'] ?>">Delete</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center;">No Registered Vehicles</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- REGISTER MODAL -->
    <div class="modal" id="registerModal">
        <div class="modal-content">
            <h2>Register Vehicle</h2>

            <form method="POST" action="register_vehicle.php">
                <div class="record-row">
                    <label>Plate Number:</label>
                    <input name="plateNumber" id="reg_plateNumber" required placeholder="e.g. ABC 1234 / AB 1234 / ABC 12345">
                    <small style="color:#666;font-size:12px;">
                        Format: 2–3 letters + space + 3–5 numbers (ex: ABC 1234)
                    </small>
                </div>

                <div class="record-row">
                    <label>Brand:</label>
                    <select name="vehicleBrand" id="reg_vehicleBrand" required>
                        <option value="">Select Brand</option>
                    </select>
                </div>

                <div class="record-row">
                    <label>Model:</label>
                    <select name="vehicleModel" id="reg_vehicleModel" required disabled>
                        <option value="">Select Model</option>
                    </select>
                </div>

                <div class="record-row">
                    <label>Year:</label>
                    <input name="vehicleYear" id="reg_vehicleYear" type="number" min="1900" max="2099" placeholder="e.g. 2020">
                </div>

                <div class="record-row"><label>Engine Number:</label><input name="engineNumber"></div>

                <div class="record-row">
                    <label>Fuel Type:</label>
                    <select name="fuelType" required>
                        <option value="">Select</option>
                        <option value="Gasoline">Gasoline</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Electric">Electric</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>
                </div>

                <div class="record-row">
                    <label>Transmission:</label>
                    <select name="transmissiontype" required>
                        <option value="">Select</option>
                        <option value="Manual">Manual</option>
                        <option value="Automatic">Automatic</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="IMT">IMT</option>
                        <option value="CVT">CVT</option>
                        <option value="DCT">DCT</option>
                    </select>
                </div>

                <div class="record-row"><label>Color:</label><input name="color"></div>
                <div class="record-row"><label>Mileage:</label><input name="mileage"></div>

                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Save</button>
                    <button type="button" class="cancel" onclick="closeRegisterModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <h2>Vehicle Details</h2>
            <div id="viewVehicleDetails"></div>
            <button type="button" class="close-btn" onclick="closeViewModal()">Close</button>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <h2>Edit Vehicle</h2>
            <form method="POST" action="edit_vehicle.php" id="editVehicleForm">
                <input type="hidden" name="vehicle_id" id="edit_vehicle_id">

                <div class="record-row">
                    <label>Plate Number:</label>
                    <input name="plateNumber" id="edit_plateNumber" required placeholder="e.g. ABC 1234">
                    <small style="color:#666;font-size:12px;">
                        Format: 2–3 letters + space + 3–5 numbers
                    </small>
                </div>

                <div class="record-row">
                    <label>Brand:</label>
                    <select name="vehicleBrand" id="edit_vehicleBrand" required>
                        <option value="">Select Brand</option>
                    </select>
                </div>

                <div class="record-row">
                    <label>Model:</label>
                    <select name="vehicleModel" id="edit_vehicleModel" required disabled>
                        <option value="">Select Model</option>
                    </select>
                </div>

                <div class="record-row"><label>Year:</label><input name="vehicleYear" id="edit_vehicleYear"></div>
                <div class="record-row"><label>Engine Number:</label><input name="engineNumber" id="edit_engineNumber"></div>

                <div class="record-row">
                    <label>Fuel Type:</label>
                    <select name="fuelType" id="edit_fuelType" required>
                        <option value="">Select</option>
                        <option value="Gasoline">Gasoline</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Electric">Electric</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>
                </div>

                <div class="record-row">
                    <label>Transmission:</label>
                    <select name="transmissiontype" id="edit_transmissiontype" required>
                        <option value="">Select</option>
                        <option value="Manual">Manual</option>
                        <option value="Automatic">Automatic</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="IMT">IMT</option>
                        <option value="CVT">CVT</option>
                        <option value="DCT">DCT</option>
                    </select>
                </div>

                <div class="record-row"><label>Color:</label><input name="color" id="edit_color"></div>
                <div class="record-row"><label>Mileage:</label><input name="mileage" id="edit_mileage"></div>

                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Update</button>
                    <button type="button" class="cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRM MODAL -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this vehicle?</p>
            <button type="button" class="btn-primary" id="confirmDelete">Yes, Delete</button>
            <button type="button" class="cancel" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>

    <script>
        // MODAL FUNCTIONS
        const registerModal = document.getElementById('registerModal');
        function openRegisterModal() { registerModal.style.display = 'flex'; }
        function closeRegisterModal() { registerModal.style.display = 'none'; }

        const viewModal = document.getElementById('viewModal');
        const viewDetails = document.getElementById('viewVehicleDetails');

        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const vehicle = JSON.parse(btn.dataset.vehicle);
                viewDetails.innerHTML = '';

                for (const key in vehicle) {
                    const label = key.replace(/_/g, ' ');
                    const value = (vehicle[key] ?? '');
                    viewDetails.innerHTML += `
                        <div class="record-row">
                            <label>${label}:</label>
                            <span>${String(value).replace(/</g,"&lt;").replace(/>/g,"&gt;")}</span>
                        </div>
                    `;
                }

                viewModal.style.display = 'flex';
            });
        });

        function closeViewModal() { viewModal.style.display = 'none'; }

        const editModal = document.getElementById('editModal');

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const vehicle = JSON.parse(btn.dataset.vehicle);

                document.getElementById('edit_vehicle_id').value = vehicle.vehicle_id ?? '';
                document.getElementById('edit_plateNumber').value = vehicle.plateNumber ?? '';
                document.getElementById('edit_vehicleBrand').value = vehicle.vehicleBrand ?? '';
                document.getElementById('edit_vehicleModel').value = vehicle.vehicleModel ?? '';
                document.getElementById('edit_vehicleYear').value = vehicle.vehicleYear ?? '';
                document.getElementById('edit_engineNumber').value = vehicle.engineNumber ?? '';
                document.getElementById('edit_fuelType').value = vehicle.fuelType ?? '';
                document.getElementById('edit_transmissiontype').value = vehicle.transmissiontype ?? '';
                document.getElementById('edit_color').value = vehicle.color ?? '';
                document.getElementById('edit_mileage').value = vehicle.mileage ?? '';

                editModal.style.display = 'flex';
            });
        });

        function closeEditModal() { editModal.style.display = 'none'; }

        let vehicleToDelete = 0;
        const deleteModal = document.getElementById('deleteModal');
        const confirmDelete = document.getElementById('confirmDelete');

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                vehicleToDelete = btn.dataset.id;
                deleteModal.style.display = 'flex';
            });
        });

        function closeDeleteModal() {
            deleteModal.style.display = 'none';
            vehicleToDelete = 0;
        }

        confirmDelete.addEventListener('click', () => {
            if (vehicleToDelete) {
                window.location.href = `delete_vehicle.php?id=${encodeURIComponent(vehicleToDelete)}`;
            }
        });

        // close modals when clicking the backdrop
        window.addEventListener('click', (e) => {
            [registerModal, viewModal, editModal, deleteModal].forEach(m => {
                if (e.target === m) m.style.display = 'none';
            });
        });
    </script>

    <script>
        /* ✅ PH Brands + Models (Hardcoded) */
        const vehicleModels = {
            Toyota: ["Vios", "Wigo", "Raize", "Yaris Cross", "Corolla Altis", "Camry", "Rush", "Avanza", "Innova", "Zenix", "Fortuner", "Hilux", "Land Cruiser", "Alphard"],
            Honda: ["Brio", "City", "Civic", "Accord", "BR-V", "HR-V", "CR-V"],
            Mitsubishi: ["Mirage", "Mirage G4", "Xpander", "Xpander Cross", "Montero Sport", "Strada", "L300"],
            Nissan: ["Almera", "Sentra", "Navara", "Terra", "Patrol", "Urvan"],
            Hyundai: ["Reina", "Accent", "Elantra", "Creta", "Tucson", "Santa Fe", "Stargazer"],
            Ford: ["Ranger", "Everest", "Territory", "Explorer", "Mustang"],
            Suzuki: ["Celerio", "S-Presso", "Swift", "Dzire", "Ertiga", "XL7", "Jimny", "Carry"],
            Kia: ["Picanto", "Soluto", "Stonic", "Seltos", "Sportage", "Sorento", "Carnival"],
            Mazda: ["Mazda2", "Mazda3", "Mazda6", "CX-3", "CX-5", "CX-8", "CX-9", "BT-50"],
            Isuzu: ["D-Max", "mu-X", "Traviz", "N-Series"],
            Subaru: ["XV", "Forester", "Outback", "WRX", "BRZ"],
            Geely: ["GX3 Pro", "Coolray", "Azkarra", "Okavango", "Emgrand"],
            Chery: ["Tiggo 2", "Tiggo 5X", "Tiggo 7 Pro", "Tiggo 8 Pro"],
            MG: ["MG5", "MG GT", "ZS", "HS", "RX5", "Marvel R"],
            GAC: ["GS3", "GS4", "GS8", "Empow", "GN6"],
            BAIC: ["X35", "X55", "X7", "BJ40", "M60"],
            Foton: ["Gratour", "Toano", "View", "Thunder"],
            Peugeot: ["2008", "3008", "5008", "Traveller"],
            BMW: ["1 Series", "3 Series", "5 Series", "X1", "X3", "X5"],
            MercedesBenz: ["A-Class", "C-Class", "E-Class", "GLA", "GLC", "GLE"],
            Audi: ["A3", "A4", "A6", "Q2", "Q3", "Q5", "Q7"],
            Lexus: ["IS", "ES", "NX", "RX", "LX"],
            Volvo: ["S60", "S90", "XC40", "XC60", "XC90"]
        };

        function fillBrandOptions(brandSelect) {
            brandSelect.innerHTML = `<option value="">Select Brand</option>`;
            Object.keys(vehicleModels).sort().forEach(brand => {
                const opt = document.createElement("option");
                opt.value = brand;
                opt.textContent = brand;
                brandSelect.appendChild(opt);
            });
        }

        function fillModelOptions(modelSelect, brand, selectedModel = "") {
            modelSelect.innerHTML = `<option value="">Select Model</option>`;
            modelSelect.disabled = true;

            if (!brand || !vehicleModels[brand]) return;

            vehicleModels[brand].forEach(model => {
                const opt = document.createElement("option");
                opt.value = model;
                opt.textContent = model;
                if (selectedModel && selectedModel === model) opt.selected = true;
                modelSelect.appendChild(opt);
            });

            modelSelect.disabled = false;
        }

        /* ✅ REGISTER MODAL DROPDOWNS */
        const regBrand = document.getElementById("reg_vehicleBrand");
        const regModel = document.getElementById("reg_vehicleModel");
        fillBrandOptions(regBrand);

        regBrand.addEventListener("change", () => {
            fillModelOptions(regModel, regBrand.value);
        });

        /* ✅ EDIT MODAL DROPDOWNS */
        const editBrand = document.getElementById("edit_vehicleBrand");
        const editModel = document.getElementById("edit_vehicleModel");
        fillBrandOptions(editBrand);

        editBrand.addEventListener("change", () => {
            fillModelOptions(editModel, editBrand.value);
        });

        /* ✅ When opening edit modal, auto-fill model list correctly */
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const vehicle = JSON.parse(btn.dataset.vehicle);
                editBrand.value = vehicle.vehicleBrand ?? "";
                fillModelOptions(editModel, editBrand.value, vehicle.vehicleModel ?? "");
            });
        });

        /* ✅ When opening register modal, reset model */
        function resetRegisterDropdowns() {
            regBrand.value = "";
            regModel.innerHTML = `<option value="">Select Model</option>`;
            regModel.disabled = true;
        }

        const oldOpenRegisterModal = window.openRegisterModal;
        window.openRegisterModal = function () {
            resetRegisterDropdowns();
            oldOpenRegisterModal();
        };
    </script>

    <script>
        /* ✅ Mixed PH Plate Formats */
        const plateRegex = /^[A-Z]{2,3}\s\d{3,5}$/;

        /* Auto uppercase */
        ["reg_plateNumber", "edit_plateNumber"].forEach(id => {
            const input = document.getElementById(id);
            if (!input) return;

            input.addEventListener("input", () => {
                input.value = input.value.toUpperCase();
            });

            input.addEventListener("blur", () => {
                if (input.value.trim() && !plateRegex.test(input.value.trim())) {
                    alert("Invalid plate number format.\n\nExamples:\nABC 1234\nAB 1234\nABC 12345");
                    input.focus();
                }
            });
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

                // ignore the "No Registered Vehicles" row (colspan row)
                if (tds.length <= 1) {
                    tr.style.display = q ? "none" : "";
                    return;
                }

                // Search only in first 8 columns (exclude Actions col)
                const cells = Array.from(tds).slice(0, 8);
                const rowText = cells.map(td => td.textContent).join(" ").toLowerCase();

                const match = q === "" || rowText.includes(q);
                tr.style.display = match ? "" : "none";
                if (match) visible++;
            });

            // show "No matching vehicles found" row
            let noRow = tbody.querySelector("tr.__noresults");
            if (q !== "" && visible === 0) {
                if (!noRow) {
                    noRow = document.createElement("tr");
                    noRow.className = "__noresults";
                    noRow.innerHTML = `<td colspan="9" style="text-align:center;color:#777;padding:14px;">No matching vehicles found.</td>`;
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
