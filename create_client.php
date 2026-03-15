<?php
session_start();
require_once "db.php";

// ======================
// DELETE CLIENT (SAFE)
// ======================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt = $conn->prepare("DELETE FROM client_information WHERE client_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();

    header("Location: create_client.php");
    exit;
}

// ======================
// SAVE CLIENT
// ======================
if (isset($_POST['save'])) {
    $sql = "INSERT INTO client_information 
            (firstName, lastName, contactNumber, email, address, notes)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssss",
        $_POST['firstName'],
        $_POST['lastName'],
        $_POST['contactNumber'],
        $_POST['email'],
        $_POST['address'],
        $_POST['notes']
    );
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Client record successfully created!";
    header("Location: create_client.php");
    exit;
}

// ======================
// FETCH CLIENTS
// ======================
$clients = $conn->query("SELECT * FROM client_information ORDER BY client_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Records | Rapid Repair</title>

    <!-- SAME layout css for all panels -->
    <link rel="stylesheet" href="pagelayout.css">

    <!-- Page css only -->
    <link rel="stylesheet" href="client.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* Centered success popup */
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
    </style>
</head>

<body>

<!-- TOPBAR (MATCHES pagelayout.css) -->
<header class="topbar">
    <div class="logo">
        <img src="rapidlogo.png" alt="Rapid Repair Logo" class="logo-img">
        <small>Commitment is our Passion</small>
    </div>

    <!-- GLOBAL SEARCH (NOW: TABLE FILTER ONLY) -->
    <div class="search-box">
        <input type="text" id="globalSearch" placeholder="Search clients..." autocomplete="off">
        <!-- keep this div if your pagelayout.css expects it; we will hide/disable it -->
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
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="bookings.php">Bookings</a></li>
            <li><a href="staffvehicle.php">Vehicles</a></li>
            <li class="active"><a href="create_client.php">Client Records</a></li>
            <li><a href="services.php">Service & Invoice</a></li>
            <li><a href="reports.php">Reports</a></li>
        </ul>
        <div class="logout">
            <a href="logout.php">Logout</a>
        </div>
    </aside>

    <!-- CONTENT -->
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

        <!-- POPUP MESSAGE -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="popup-message" id="popupMessage">
                <?= htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- CLIENT TABLE -->
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
                                <a href="create_client.php?delete_id=<?= (int)$row['client_id'] ?>"
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

<!-- MODAL -->
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

// Centered popup display
window.addEventListener('DOMContentLoaded', () => {
    const popup = document.getElementById('popupMessage');
    if (popup) {
        popup.style.display = 'block';
        setTimeout(() => { popup.style.display = 'none'; }, 3000);
    }
});
</script>

<!-- GLOBAL SEARCH: FILTERS ONLY THIS TABLE (NO AJAX, NO LINKS) -->
<script>
(function () {
    const search = document.getElementById("globalSearch");
    const table = document.getElementById("clientsTable");
    const resultsBox = document.getElementById("searchResults"); // we keep it but disable it

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
            // ignore "no records found" placeholder row if you have it
            if (tr.classList.contains("__empty")) {
                tr.style.display = q ? "none" : "";
                return;
            }

            // IMPORTANT: Exclude last column (Action) from searching
            const cells = Array.from(tr.querySelectorAll("td")).slice(0, 4); // ID, Name, Contact, Email
            const rowText = cells.map(td => td.textContent).join(" ").toLowerCase();

            const match = (q === "") || rowText.includes(q);
            tr.style.display = match ? "" : "none";
            if (match) visible++;
        });

        // Show "No matching results" row
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

    // filter as user types
    search.addEventListener("input", applyFilter);

    // ESC clears search and shows all rows
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && document.activeElement === search) {
            search.value = "";
            applyFilter();
        }
    });

    // extra safety: never show dropdown
    document.addEventListener("click", (e) => {
        if (!e.target.closest(".search-box")) hideDropdown();
    });

})();
</script>

</body>
</html>
