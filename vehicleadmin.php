<?php
session_start();
require_once "db.php";

// (Optional) Staff-only guard (uncomment if you have roles)
// if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['staff','admin'])) {
//     header("Location: login.php");
//     exit();
// }

$query = "
SELECT 
    v.vehicle_id,
    v.plateNumber,
    v.vehicleBrand,
    v.vehicleModel,
    v.vehicleYear,
    v.engineNumber,
    v.fuelType,
    v.color,
    v.mileage,
    v.transmissiontype,
    c.firstName,
    c.lastName
FROM vehicleinfo v
LEFT JOIN client_information c ON v.client_id = c.client_id
LEFT JOIN users u ON c.user_id = u.user_id
ORDER BY v.dateAdded DESC
";

$result = $conn->query($query);
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registered Vehicles | Staff</title>

    <!-- Layout CSS (same on all pages) -->
    <link rel="stylesheet" href="pagelayout.css">

    <!-- Page-specific CSS (table only) -->
    <link rel="stylesheet" href="staffvehicle.css">

    <!-- Minimal modal styles (kept here so it works even if staffvehicle.css has none) -->
    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 16px;
        }

        .modal-content {
            max-width: 760px;
            width: 92%;
            padding: 22px 26px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .2);
        }

        .modal-content h2 {
            margin: 0 0 14px 0;
            text-align: center;
        }

        .record-row {
            margin: 0 0 12px 0;
        }

        .record-row label {
            font-weight: 700;
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: #1f3f8b;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .record-row input,
        .record-row select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #ddd;
            outline: none;
        }

        .record-row input:focus,
        .record-row select:focus {
            border-color: #1f3f8b;
            box-shadow: 0 0 0 3px rgba(31, 63, 139, .12);
        }

        .record-row span {
            display: block;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #eee;
            background: #fafafa;
            font-size: 14px;
            color: #111;
            word-break: break-word;
        }

        .small-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #666;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-primary {
            background: #1f3f8b;
            color: #fff;
            padding: 9px 18px;
            border-radius: 22px;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #17306b;
        }

        .cancel,
        .close-btn {
            background: #d0d0d0;
            color: #111;
            padding: 9px 18px;
            border-radius: 22px;
            border: none;
            cursor: pointer;
        }

        .cancel:hover,
        .close-btn:hover {
            background: #bdbdbd;
        }

        /* View details grid */
        #viewVehicleDetails {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
            margin-top: 10px;
        }

        @media (max-width:640px) {
            #viewVehicleDetails {
                grid-template-columns: 1fr;
            }
        }

        /* Edit form grid */
        #editVehicleForm {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 18px;
        }

        #editVehicleForm .modal-actions {
            grid-column: 1 / -1;
        }

        @media (max-width:640px) {
            #editVehicleForm {
                grid-template-columns: 1fr;
            }
        }

        /* ✅ Hide dropdown box (we’re not using AJAX search here) */
        #searchResults {
            display: none !important;
        }

        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
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

    <!-- TOP BAR -->
    <header class="topbar">
        <div class="logo">
            <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
            <small>Commitment is our Passion</small>
        </div>

        <!-- ✅ TABLE-ONLY SEARCH INPUT -->
        <div class="search-box">
            <input type="text" id="globalSearch" placeholder="Search vehicles..." autocomplete="off">
            <div id="searchResults" class="search-results"></div>
        </div>

        <div class="user-info">
            <img src="pictures/user.png" alt="User">
            <div>
                <strong>Welcome!</strong><br>
                <span><?= htmlspecialchars($_SESSION['name'] ?? 'Office Staff') ?></span>
            </div>
        </div>
    </header>

    <div class="layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboardadmin.php">Dashboard</a></li>
                <li><a href="bookingadmin.php">Bookings</a></li>
                <li class="active"><a href="vehicleadmin.php">Vehicles</a></li>
                <li><a href="clientrecordsadmin.php">Client Records</a></li>
                <li><a href="servicesadmin.php">Service & Invoice</a></li>
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

        <!-- CONTENT -->
        <main class="content">
            <div class="table-card">
                <h2>Vehicles Registered</h2>

                <!-- ✅ added id="vehiclesTable" -->
                <table id="vehiclesTable">
                    <thead>
                        <tr>
                            <th>Vehicle ID</th>
                            <th>Owner</th>
                            <th>Plate Number</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Year</th>
                            <th>Engine No.</th>
                            <th>Fuel Type</th>
                            <th>Transmission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int) $row['vehicle_id'] ?></td>
                                    <td><?= htmlspecialchars(($row['firstName'] ?? '') . " " . ($row['lastName'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($row['plateNumber'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['vehicleBrand'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['vehicleModel'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['vehicleYear'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['engineNumber'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['fuelType'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['transmissiontype'] ?? '') ?></td>

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
                                <td colspan="10" class="empty">No Registered Vehicles</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
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

            <!-- Change action file name if needed -->
            <form method="POST" action="staff_edit_vehicle.php" id="editVehicleForm">
                <input type="hidden" name="vehicle_id" id="edit_vehicle_id">

                <div class="record-row">
                    <label>Plate Number:</label>
                    <input name="plateNumber" id="edit_plateNumber" required
                        placeholder="e.g. ABC 1234 / AB 1234 / ABC 12345">
                    <small class="small-hint">Format: 2–3 letters + space + 3–5 numbers</small>
                </div>

                <div class="record-row">
                    <label>Brand:</label>
                    <input name="vehicleBrand" id="edit_vehicleBrand" required>
                </div>

                <div class="record-row">
                    <label>Model:</label>
                    <input name="vehicleModel" id="edit_vehicleModel" required>
                </div>

                <div class="record-row">
                    <label>Color:</label>
                    <input name="color" id="edit_color" placeholder="e.g. White">
                </div>

                <div class="record-row">
                    <label>Mileage:</label>
                    <input name="mileage" id="edit_mileage" type="number" min="0" placeholder="e.g. 45000">
                </div>


                <div class="record-row">
                    <label>Year:</label>
                    <input name="vehicleYear" id="edit_vehicleYear" type="number" min="1900" max="2099"
                        placeholder="e.g. 2020">
                </div>

                <div class="record-row">
                    <label>Engine Number:</label>
                    <input name="engineNumber" id="edit_engineNumber">
                </div>

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

                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Update</button>
                    <button type="button" class="cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRM MODAL -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width:520px;">
            <h2>Confirm Delete</h2>
            <p style="margin:0 0 14px;color:#333;">Are you sure you want to delete this vehicle?</p>
            <div class="modal-actions" style="justify-content:center;">
                <button type="button" class="btn-primary" id="confirmDelete">Yes, Delete</button>
                <button type="button" class="cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        /* ===== ACTION MODALS ===== */
        const viewModal = document.getElementById('viewModal');
        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        const viewDetails = document.getElementById('viewVehicleDetails');

        function closeViewModal() { viewModal.style.display = 'none'; }
        function closeEditModal() { editModal.style.display = 'none'; }
        function closeDeleteModal() { deleteModal.style.display = 'none'; vehicleToDelete = 0; }

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
                            <span>${String(value).replace(/</g, "&lt;").replace(/>/g, "&gt;")}</span>
                        </div>
                    `;
                }

                viewModal.style.display = 'flex';
            });
        });

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

        /* plate format validation (mixed PH formats) */
        const plateRegex = /^[A-Z]{2,3}\s\d{3,5}$/;
        const plateInput = document.getElementById("edit_plateNumber");
        if (plateInput) {
            plateInput.addEventListener("input", () => {
                plateInput.value = plateInput.value.toUpperCase();
            });
            plateInput.addEventListener("blur", () => {
                const v = plateInput.value.trim();
                if (v && !plateRegex.test(v)) {
                    alert("Invalid plate format.\nExamples:\nABC 1234\nAB 1234\nABC 12345");
                    plateInput.focus();
                }
            });
        }

        /* delete */
        let vehicleToDelete = 0;
        const confirmDelete = document.getElementById('confirmDelete');

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                vehicleToDelete = btn.dataset.id;
                deleteModal.style.display = 'flex';
            });
        });

        confirmDelete.addEventListener('click', () => {
            if (vehicleToDelete) {
                window.location.href = `staff_delete_vehicle.php?id=${encodeURIComponent(vehicleToDelete)}`;
            }
        });

        /* close when clicking backdrop */
        window.addEventListener('click', (e) => {
            [viewModal, editModal, deleteModal].forEach(m => {
                if (e.target === m) m.style.display = 'none';
            });
        });
    </script>

    <!-- ✅ TABLE-ONLY SEARCH SCRIPT (NO AJAX) -->
    <script>
        (function () {
            const search = document.getElementById("globalSearch");
            const table = document.getElementById("vehiclesTable");
            const resultsBox = document.getElementById("searchResults");

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
                    // ignore "No Registered Vehicles" row
                    if (tr.querySelector("td.empty")) {
                        tr.style.display = q ? "none" : "";
                        return;
                    }

                    // Search only in first 9 columns (exclude Actions column)
                    const cells = Array.from(tr.querySelectorAll("td")).slice(0, 9);
                    const rowText = cells.map(td => td.textContent).join(" ").toLowerCase();

                    const match = q === "" || rowText.includes(q);
                    tr.style.display = match ? "" : "none";
                    if (match) visible++;
                });

                // Show "No matching results" row when none found
                let noRow = tbody.querySelector("tr.__noresults");
                if (q !== "" && visible === 0) {
                    if (!noRow) {
                        noRow = document.createElement("tr");
                        noRow.className = "__noresults";
                        noRow.innerHTML = `<td colspan="10" class="empty">No matching vehicles found.</td>`;
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

            // Hide dropdown always (extra safety)
            document.addEventListener("click", (e) => {
                if (!e.target.closest(".search-box")) hideDropdown();
            });
        })();
    </script>

    <!-- SUCCESS POPUP -->
    <div class="modal" id="successModal">
        <div class="modal-content" style="max-width:420px;text-align:center;">
            <h2 style="color:#1f3f8b;">✅ Success</h2>
            <p id="successMessage" style="margin:12px 0 18px;"></p>
            <button class="btn-primary" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>


    <script>
        function closeSuccessModal() {
            document.getElementById("successModal").style.display = "none";
            // Clean URL (remove ?success=)
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        (function () {
            const params = new URLSearchParams(window.location.search);
            if (params.has("success")) {
                const msg = params.get("success");
                document.getElementById("successMessage").textContent = msg;
                document.getElementById("successModal").style.display = "flex";
            }
        })();
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

</body>

</html>