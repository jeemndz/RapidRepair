<?php
ob_start();
session_start();
require_once "db.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Booking List | Rapid Repair</title>

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="bookings.css">
    <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
      referrerpolicy="no-referrer" />

    <style>
        .table-container+.table-container {
            margin-top: 40px;
        }

        .table-container h3 {
            margin-bottom: 10px;
            color: #254a91;
        }

        #searchResults {
            display: none !important;
        }

        /* Make edit button look like icon link */
        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            margin: 0;
        }

        .icon-btn i {
            color: #1f3b77;
        }

        .icon-btn:hover i {
            opacity: .8;
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
            <img src="pictures/User.png" alt="User">
            <div>
                <strong>Welcome!</strong><br>
                <span><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
            </div>
        </div>
    </header>

  <div class="layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <ul>
                <li><a href="dashboardadmin.php">Dashboard</a></li>
                <li class="active"><a href="bookingadmin.php">Bookings</a></li>
                <li><a href="vehicleadmin.php">Vehicles</a></li>
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


        <main class="content">
            <div class="content-header">
                <div class="title">
                    <h2>Booking List</h2>
                </div>

                <div class="actions">
                    <button class="btn create" type="button"><i class="fa fa-plus"></i> Create</button>
                    <button class="btn view" type="button"><i class="fa fa-rotate-right"></i> Refresh</button>
                </div>
            </div>

            <!-- TABLE 1: Pending -->
            <div class="table-container">
                <h3>Pending Bookings</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Appointment Date</th>
                            <th>Client</th>
                            <th>Vehicle</th>
                            <th>Service Type</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pendingTableBody"></tbody>
                </table>
            </div>

            <!-- TABLE 2: Ongoing / Approved -->
            <div class="table-container">
                <h3>Ongoing / Approved Bookings</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Appointment Date</th>
                            <th>Client</th>
                            <th>Vehicle</th>
                            <th>Service Type</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ongoingTableBody"></tbody>
                </table>
            </div>

            <!-- TABLE 3: Completed / Cancelled -->
            <div class="table-container">
                <h3>Completed / Cancelled Bookings</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Appointment Date</th>
                            <th>Client</th>
                            <th>Vehicle</th>
                            <th>Service Type</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="doneTableBody"></tbody>
                </table>
            </div>

        </main>
    </div>

    <?php include 'create_booking.php'; ?>

    <!-- ============================
     EDIT BOOKING MODAL (FULL EDIT)
============================ -->
    <div id="editBookingModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999; align-items:center; justify-content:center;">
        <div
            style="background:#fff; width:520px; max-width:calc(100% - 30px); border-radius:12px; padding:22px; box-shadow:0 20px 40px rgba(0,0,0,.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h2 style="margin:0; color:#1d3f8b;">Edit Booking</h2>
                <button type="button" id="closeEditBookingModal"
                    style="background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
            </div>

            <div id="editBookingMsg" style="margin-bottom:10px; font-weight:600;"></div>

            <form id="editBookingForm">
                <input type="hidden" name="appointment_id" id="eb_id">

                <label style="font-weight:600;font-size:13px;">Status*</label>
                <select name="status" id="eb_status" required
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin:6px 0 10px;">
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

                <label style="font-weight:600;font-size:13px;">Service Type*</label>
                <input type="text" name="serviceType" id="eb_serviceType" required
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin:6px 0 10px;">

                <label style="font-weight:600;font-size:13px;">Appointment Date*</label>
                <input type="date" name="appointmentDate" id="eb_date" required min="<?= date('Y-m-d'); ?>"
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin:6px 0 10px;">

                <label style="font-weight:600;font-size:13px;">Appointment Time*</label>
                <input type="time" name="appointmentTime" id="eb_time" required min="08:00" max="19:59"
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin:6px 0 10px;">

                <label style="font-weight:600;font-size:13px;">Mechanic Assigned*</label>
                <select name="mechanicAssigned" id="eb_mechanic" required
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin:6px 0 10px;">
                    <option value="Unassigned">Unassigned</option>
                    <option value="Mechanic 1">Mechanic 1</option>
                    <option value="Mechanic 2">Mechanic 2</option>
                    <option value="Mechanic 3">Mechanic 3</option>
                </select>

                <label style="font-weight:600;font-size:13px;">Notes</label>
                <textarea name="notes" id="eb_notes"
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;min-height:80px;"></textarea>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
                    <button type="button" id="cancelEditBooking"
                        style="background:#e5e7eb;border:none;padding:10px 18px;border-radius:999px;cursor:pointer;">Cancel</button>

                    <button type="submit"
                        style="background:#16a34a;color:#fff;border:none;padding:10px 18px;border-radius:999px;cursor:pointer;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ===========================
           REFRESH 3 TABLES
        =========================== */
        function refreshTables() {
            const ts = Date.now();

            fetch('fetch_appointments.php?type=pending&_=' + ts)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('pendingTableBody').innerHTML = html;
                    filterBookingsTables();
                })
                .catch(() => {
                    document.getElementById('pendingTableBody').innerHTML =
                        "<tr><td colspan='8'>Failed to load pending bookings.</td></tr>";
                });

            fetch('fetch_appointments.php?type=ongoing&_=' + ts)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('ongoingTableBody').innerHTML = html;
                    filterBookingsTables();
                })
                .catch(() => {
                    document.getElementById('ongoingTableBody').innerHTML =
                        "<tr><td colspan='8'>Failed to load ongoing bookings.</td></tr>";
                });

            fetch('fetch_appointments.php?type=done&_=' + ts)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('doneTableBody').innerHTML = html;
                    filterBookingsTables();
                })
                .catch(() => {
                    document.getElementById('doneTableBody').innerHTML =
                        "<tr><td colspan='8'>Failed to load completed/cancelled bookings.</td></tr>";
                });
        }

        refreshTables();
        document.querySelector('.btn.view').addEventListener('click', refreshTables);
        setInterval(refreshTables, 10000);

        /* ===========================
           CREATE BOOKING MODAL OPEN/CLOSE
        =========================== */
        const createBtn = document.querySelector('.btn.create');
        const createModal = document.getElementById('createBookingModal');

        if (createBtn && createModal) {
            createBtn.addEventListener('click', () => {
                createModal.style.display = 'flex';
            });

            document.querySelectorAll('#createBookingModal .cancel-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    createModal.style.display = 'none';
                });
            });

            createModal.addEventListener('click', (e) => {
                if (e.target === createModal) createModal.style.display = 'none';
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') createModal.style.display = 'none';
            });
        }

        /* ===========================
           SEARCH FILTER (3 TABLES)
        =========================== */
        const searchInput = document.getElementById("globalSearch");

        function filterOneTbody(tbody, query) {
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll("tr")).filter(r => !r.classList.contains("__noresults"));
            let visible = 0;

            rows.forEach(tr => {
                const rowText = tr.textContent.toLowerCase();
                const match = query === "" || rowText.includes(query);
                tr.style.display = match ? "" : "none";
                if (match) visible++;
            });

            let noRow = tbody.querySelector("tr.__noresults");
            if (query !== "" && visible === 0) {
                if (!noRow) {
                    noRow = document.createElement("tr");
                    noRow.className = "__noresults";
                    noRow.innerHTML = `<td colspan="8" style="text-align:center;color:#777;padding:14px;">No matching bookings found.</td>`;
                    tbody.appendChild(noRow);
                }
                noRow.style.display = "";
            } else {
                if (noRow) noRow.style.display = "none";
            }
        }

        function filterBookingsTables() {
            const q = (searchInput?.value || "").toLowerCase().trim();
            filterOneTbody(document.getElementById("pendingTableBody"), q);
            filterOneTbody(document.getElementById("ongoingTableBody"), q);
            filterOneTbody(document.getElementById("doneTableBody"), q);
        }

        if (searchInput) searchInput.addEventListener("input", filterBookingsTables);

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && document.activeElement === searchInput) {
                searchInput.value = "";
                filterBookingsTables();
            }
        });

        /* ===========================
           EDIT MODAL OPEN/LOAD/SAVE
        =========================== */
        const editBookingModal = document.getElementById("editBookingModal");
        const closeEditBookingModal = document.getElementById("closeEditBookingModal");
        const cancelEditBooking = document.getElementById("cancelEditBooking");
        const editBookingMsg = document.getElementById("editBookingMsg");
        const editBookingForm = document.getElementById("editBookingForm");

        const eb_id = document.getElementById("eb_id");
        const eb_status = document.getElementById("eb_status");
        const eb_serviceType = document.getElementById("eb_serviceType");
        const eb_date = document.getElementById("eb_date");
        const eb_time = document.getElementById("eb_time");
        const eb_mechanic = document.getElementById("eb_mechanic");
        const eb_notes = document.getElementById("eb_notes");

        function openEditBookingModal() { editBookingModal.style.display = "flex"; }
        function closeEditBooking() { editBookingModal.style.display = "none"; }

        closeEditBookingModal.addEventListener("click", closeEditBooking);
        cancelEditBooking.addEventListener("click", closeEditBooking);
        editBookingModal.addEventListener("click", (e) => {
            if (e.target === editBookingModal) closeEditBooking();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") closeEditBooking();
        });

        // Click pencil -> load details from fetch_appointments row dataset
        document.addEventListener("click", async (e) => {
            const btn = e.target.closest(".edit-booking-btn");
            if (!btn) return;

            // Data comes from fetch_appointments.php row buttons
            eb_id.value = btn.dataset.id || "";
            eb_status.value = btn.dataset.status || "Pending";
            eb_serviceType.value = btn.dataset.service || "";
            eb_date.value = btn.dataset.date || "";
            eb_time.value = btn.dataset.time || "";
            eb_mechanic.value = btn.dataset.mechanic || "Unassigned";
            eb_notes.value = btn.dataset.notes || "";

            editBookingMsg.textContent = "";
            openEditBookingModal();
        });

        // Save update
        editBookingForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            editBookingMsg.textContent = "Updating...";
            editBookingMsg.style.color = "#111";

            const fd = new FormData(editBookingForm);

            try {
                const res = await fetch("update_booking_status.php", {
                    method: "POST",
                    body: fd
                });
                const text = await res.text();

                if (!res.ok || text.trim() !== "OK") {
                    editBookingMsg.textContent = text || "Update failed.";
                    editBookingMsg.style.color = "#b30000";
                    return;
                }

                editBookingMsg.textContent = "Updated!";
                editBookingMsg.style.color = "green";

                setTimeout(() => {
                    closeEditBooking();
                    refreshTables();
                }, 250);

            } catch (err) {
                console.error(err);
                editBookingMsg.textContent = "Network error.";
                editBookingMsg.style.color = "#b30000";
            }
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

</body>

</html>

<?php ob_end_flush(); ?>