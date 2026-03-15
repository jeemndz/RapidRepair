<?php
session_start();
require_once "db.php";

/* =========================
   ✅ STAFF + ADMIN REDIRECT TARGET
   - admin -> clientrecordsadmin.php
   - staff -> create_client.php
========================= */
$role = strtolower(trim($_SESSION['role'] ?? 'staff'));
$redirectPage = ($role === 'admin') ? 'clientrecordsadmin.php' : 'create_client.php';

/* ======================
   DELETE CLIENT (SAFE)
====================== */
if (isset($_GET['delete_id'])) {
    $delete_id = (int)($_GET['delete_id'] ?? 0);

    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM client_information WHERE client_id = ? LIMIT 1");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Client record deleted successfully!";
    } else {
        $_SESSION['success'] = "Invalid client ID.";
    }

    header("Location: " . $redirectPage);
    exit;
}

/* ======================
   SAVE CLIENT
====================== */
if (isset($_POST['save'])) {

    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $contact   = trim($_POST['contactNumber'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($firstName === '' || $lastName === '') {
        $_SESSION['success'] = "First name and last name are required.";
        header("Location: " . $redirectPage);
        exit;
    }

    $sql = "INSERT INTO client_information
            (firstName, lastName, contactNumber, email, address, notes)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $firstName, $lastName, $contact, $email, $address, $notes);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Client record successfully created!";
    } else {
        $_SESSION['success'] = "Failed to create client: " . $stmt->error;
    }

    $stmt->close();

    header("Location: " . $redirectPage);
    exit;
}

/* ======================
   FETCH CLIENTS
====================== */
$clients = $conn->query("SELECT * FROM client_information ORDER BY client_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Records | Rapid Repair</title>

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .popup-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #18b718;
            color: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
            font-size: 16px;
            text-align: center;
            z-index: 9999;
            display: none;
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
        <input type="text" id="globalSearch" placeholder="Search clients..." autocomplete="off">
        <div id="searchResults" class="search-results" style="display:none;"></div>
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
    <aside class="sidebar">
        <ul>
            <li><a href="dashboardadmin.php">Dashboard</a></li>
            <li><a href="bookingadmin.php">Bookings</a></li>
            <li><a href="vehicleadmin.php">Vehicles</a></li>
            <li class="active"><a href="clientrecordsadmin.php">Client Records</a></li>
            <li><a href="servicesadmin.php">Service & Invoice</a></li>
            <li><a href="reportsadmin.php">Reports</a></li>

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
                <i class="fa fa-address-book"></i> Client Records
            </div>
            <div class="actions">
                <button class="btn create" type="button" onclick="openModal()">
                    <i class="fa fa-plus"></i> Create
                </button>
                <button class="btn refresh" type="button" onclick="location.reload();">
                    <i class="fa fa-arrows-rotate"></i> Refresh
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="popup-message" id="popupMessage">
                <?= htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="table-container">
            <table id="clientsTable">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($clients && $clients->num_rows > 0): ?>
                    <?php while ($row = $clients->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$row['client_id'] ?></td>
                            <td><?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></td>
                            <td><?= htmlspecialchars($row['contactNumber']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td class="actions-col">
                                <a href="view_client.php?id=<?= (int)$row['client_id'] ?>" title="View">
                                    <i class="fa fa-search"></i>
                                </a>

                                <!-- ✅ FIXED: delete now points to THIS same page (works for admin + staff) -->
                                <a href="<?= htmlspecialchars($redirectPage) ?>?delete_id=<?= (int)$row['client_id'] ?>"
                                   onclick="return confirm('Are you sure you want to delete this client?');"
                                   title="Delete">
                                    <i class="fa fa-ban" style="color:red;"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="__empty">
                        <td colspan="5" style="text-align:center; color:#555;">No client records found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div class="modal" id="clientModal">
    <div class="modal-box">
        <h3>Register Client</h3>
        <form method="POST">
            <input name="firstName" placeholder="First Name" required>
            <input name="lastName" placeholder="Last Name" required>
            <input name="contactNumber" placeholder="Contact Number">
            <input name="email" type="email" placeholder="Email">
            <textarea name="address" placeholder="Address"></textarea>
            <textarea name="notes" placeholder="Notes"></textarea>

            <div class="modal-actions">
                <button type="submit" name="save" class="btn-save">Save</button>
                <button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById("clientModal").style.display = "flex"; }
function closeModal() { document.getElementById("clientModal").style.display = "none"; }

window.addEventListener('DOMContentLoaded', () => {
    const popup = document.getElementById('popupMessage');
    if (popup) {
        popup.style.display = 'block';
        setTimeout(() => { popup.style.display = 'none'; }, 3000);
    }
});
</script>

<script>
(function () {
    const search = document.getElementById("globalSearch");
    const table = document.getElementById("clientsTable");
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
            if (tr.classList.contains("__empty")) {
                tr.style.display = q ? "none" : "";
                return;
            }

            const cells = Array.from(tr.querySelectorAll("td")).slice(0, 4);
            const rowText = cells.map(td => td.textContent).join(" ").toLowerCase();

            const match = (q === "") || rowText.includes(q);
            tr.style.display = match ? "" : "none";
            if (match) visible++;
        });

        let noRow = tbody.querySelector("tr.__noresults");
        if (q !== "" && visible === 0) {
            if (!noRow) {
                noRow = document.createElement("tr");
                noRow.className = "__noresults";
                noRow.innerHTML = `<td colspan="5" style="text-align:center;color:#777;padding:14px;">No matching clients found.</td>`;
                tbody.appendChild(noRow);
            }
            noRow.style.display = "";
        } else {
            if (noRow) noRow.style.display = "none";
        }
    }

    search.addEventListener("input", applyFilter);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && document.activeElement === search) {
            search.value = "";
            applyFilter();
        }
    });

    document.addEventListener("click", (e) => {
        if (!e.target.closest(".search-box")) hideDropdown();
    });

})();
</script>

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
