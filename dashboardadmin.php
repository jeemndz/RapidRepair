<?php
session_start();
require_once "db.php";

/*
    range options:
    - today
    - week  (last 7 days including today)
    - month (current month)
*/
$range = $_GET['range'] ?? 'today';
$range = in_array($range, ['today','week','month'], true) ? $range : 'today';

$today = date("Y-m-d");

// =======================
// DATE RANGE SETUP
// =======================
if ($range === 'today') {
    $dateFrom = $today;
    $dateTo   = $today;
    $rangeLabel = "Today";
} elseif ($range === 'week') {
    $dateFrom = date("Y-m-d", strtotime("-6 days")); // last 7 days
    $dateTo   = $today;
    $rangeLabel = "Weekly";
} else { // month
    $dateFrom = date("Y-m-01"); // first day of current month
    $dateTo   = date("Y-m-t");  // last day of current month
    $rangeLabel = "Monthly";
}

// =======================
// TOTAL SCHEDULED (Fixed parentheses)
// - counts appointments within date range
// - includes Ongoing in the same date range
// =======================
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT appointment_id) AS totalScheduled
    FROM appointment
    WHERE appointmentDate BETWEEN ? AND ?
      AND status IN ('Approved','Scheduled','Pending','Completed','Cancelled','Ongoing')
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$totalScheduled = (int)($stmt->get_result()->fetch_assoc()['totalScheduled'] ?? 0);
$stmt->close();

// =======================
// ESTIMATES (FIXED)
// - Uses DATE(serviceDate) (works for DATETIME too)
// - Includes your actual invoice/service statuses
// =======================
$estimateStatuses = [
    'Ready for payment',
    'Invoiced',
    'Ongoing',
    'Completed',
    'Paid'
];
$placeholders = implode(",", array_fill(0, count($estimateStatuses), "?"));
$types = "ss" . str_repeat("s", count($estimateStatuses));

$sqlEst = "
    SELECT COALESCE(SUM(totalCost),0) AS total_estimates
    FROM services
    WHERE DATE(serviceDate) BETWEEN ? AND ?
      AND status IN ($placeholders)
";

$stmt = $conn->prepare($sqlEst);
$params = array_merge([$dateFrom, $dateTo], $estimateStatuses);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$estimates = (float)($stmt->get_result()->fetch_assoc()['total_estimates'] ?? 0);
$stmt->close();

// =======================
// SALES (Filtered) - CARD TOTAL
// =======================
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amountPaid),0) AS total_sales
    FROM payments
    WHERE paymentStatus = 'Paid'
      AND DATE(paymentDate) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$sales = (float)($stmt->get_result()->fetch_assoc()['total_sales'] ?? 0);
$stmt->close();

// =======================
// TOTAL CLIENTS (not filtered)
// =======================
$result = $conn->query("SELECT COUNT(client_id) AS total_clients FROM client_information");
$clients = (int)($result->fetch_assoc()['total_clients'] ?? 0);

// =======================
// CHART DATA (by day in range)
// =======================
$labels = [];

// build labels depending on range
if ($range === 'today') {
    $labels = [$today];
} else {
    $start = new DateTime($dateFrom);
    $end   = new DateTime($dateTo);
    $end->modify('+1 day'); // include last day

    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    foreach ($period as $dt) {
        $labels[] = $dt->format("Y-m-d");
    }
}

// prepare maps with 0 values
$salesMap = array_fill_keys($labels, 0);
$estMap   = array_fill_keys($labels, 0);

// Sales grouped by date
$stmt = $conn->prepare("
    SELECT DATE(paymentDate) AS d, COALESCE(SUM(amountPaid),0) AS total
    FROM payments
    WHERE paymentStatus='Paid'
      AND DATE(paymentDate) BETWEEN ? AND ?
    GROUP BY DATE(paymentDate)
    ORDER BY d ASC
");
$stmt->bind_param("ss", $dateFrom, $dateTo);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $d = $row['d'];
    if (isset($salesMap[$d])) $salesMap[$d] = (float)$row['total'];
}
$stmt->close();

// Estimates grouped by date (FIXED: DATE(serviceDate) + status list)
$sqlEstLine = "
    SELECT DATE(serviceDate) AS d, COALESCE(SUM(totalCost),0) AS total
    FROM services
    WHERE DATE(serviceDate) BETWEEN ? AND ?
      AND status IN ($placeholders)
    GROUP BY DATE(serviceDate)
    ORDER BY d ASC
";
$stmt = $conn->prepare($sqlEstLine);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $d = $row['d'];
    if (isset($estMap[$d])) $estMap[$d] = (float)$row['total'];
}
$stmt->close();

$salesData = array_values($salesMap);
$estimatesData = array_values($estMap);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Rapid Repair</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="dashboard.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <style>

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
            <span><?= htmlspecialchars($_SESSION['name'] ?? 'Office Staff') ?></span>
        </div>
    </div>
</header>

  <div class="layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <ul>
                <li class="active"><a href="dashboardadmin.php">Dashboard</a></li>
                <li><a href="bookingadmin.php">Bookings</a></li>
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
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Dashboard</h1>
                <p class="dash-subtitle">Quick overview of shop performance (<?= htmlspecialchars($rangeLabel) ?>).</p>
            </div>

            <div class="filters">
                <a class="filter-btn <?= ($range==='today') ? 'active' : '' ?>" href="dashboardadmin.php?range=today">Today</a>
                <a class="filter-btn <?= ($range==='week') ? 'active' : '' ?>" href="dashboardadmin.php?range=week">Weekly</a>
                <a class="filter-btn <?= ($range==='month') ? 'active' : '' ?>" href="dashboardadmin.php?range=month">Monthly</a>
            </div>
        </div>

        <div class="cards">
            <div class="card blue">
                <div class="card-top">
                    <span>Total Scheduled</span>
                    <small><?= htmlspecialchars($rangeLabel) ?></small>
                </div>
                <h2><?= (int)$totalScheduled; ?></h2>
                <div class="card-foot">Appointments in selected range</div>
            </div>

            <div class="card red">
                <div class="card-top">
                    <span>Estimates</span>
                    <small><?= htmlspecialchars($rangeLabel) ?></small>
                </div>
                <h2>₱<?= number_format((float)$estimates, 2); ?></h2>
                <div class="card-foot">Invoices/services in range</div>
            </div>

            <div class="card green">
                <div class="card-top">
                    <span>Sales</span>
                    <small><?= htmlspecialchars($rangeLabel) ?></small>
                </div>
                <h2>₱<?= number_format((float)$sales, 2); ?></h2>
                <div class="card-foot">Paid payments only</div>
            </div>

            <div class="card yellow">
                <div class="card-top">
                    <span>Total Clients</span>
                    <small>All time</small>
                </div>
                <h2><?= (int)$clients; ?></h2>
                <div class="card-foot">Registered clients</div>
            </div>
        </div>

        <div class="charts">
            <div class="chart">
                <div class="chart-head">
                    <h3>Sales (<?= htmlspecialchars($rangeLabel) ?>)</h3>
                    <span class="chart-note">Daily totals</span>
                </div>
                <canvas id="salesChart"></canvas>
            </div>

            <div class="chart">
                <div class="chart-head">
                    <h3>Estimates (<?= htmlspecialchars($rangeLabel) ?>)</h3>
                    <span class="chart-note">Daily totals</span>
                </div>
                <canvas id="estimatesChart"></canvas>
            </div>
        </div>
    </main>
</div>

<script>
// Charts
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels); ?>,
        datasets: [{
            label: 'Sales',
            data: <?= json_encode($salesData); ?>,
            borderWidth: 2,
            fill: true,
            tension: 0.25
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('estimatesChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels); ?>,
        datasets: [{
            label: 'Estimates',
            data: <?= json_encode($estimatesData); ?>,
            borderWidth: 2,
            fill: true,
            tension: 0.25
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<!-- GLOBAL SEARCH (AJAX) -->
<script>
const input = document.getElementById("globalSearch");
const resultsBox = document.getElementById("searchResults");

let t;
if (input && resultsBox) {
    input.addEventListener("input", () => {
        clearTimeout(t);
        const q = input.value.trim();

        if (q.length < 2) {
            resultsBox.style.display = "none";
            resultsBox.innerHTML = "";
            return;
        }

        t = setTimeout(async () => {
            try {
                const res = await fetch("search.php?q=" + encodeURIComponent(q), { cache: "no-store" });
                const data = await res.json();

                if (!Array.isArray(data) || data.length === 0) {
                    resultsBox.innerHTML = `<div class="sr-empty">No results found</div>`;
                    resultsBox.style.display = "block";
                    return;
                }

                resultsBox.innerHTML = data.map(item => `
                    <a class="sr-item" href="${item.url}">
                        <div class="sr-title">${item.title}</div>
                        <div class="sr-sub">${item.subtitle}</div>
                    </a>
                `).join("");

                resultsBox.style.display = "block";
            } catch (err) {
                resultsBox.innerHTML = `<div class="sr-empty">Search failed. Try again.</div>`;
                resultsBox.style.display = "block";
            }
        }, 250);
    });

    document.addEventListener("click", (e) => {
        if (!e.target.closest(".search-box")) {
            resultsBox.style.display = "none";
        }
    });
}
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
