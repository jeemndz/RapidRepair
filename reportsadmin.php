<?php
session_start();
include "db.php";

/* =========================
   HELPERS
========================= */
function buildWhere(array $clauses, array $types, array $values){
    $where = "";
    if (!empty($clauses)) $where = " WHERE " . implode(" AND ", $clauses);
    return [$where, implode("", $types), $values];
}

function fetchAllAssoc($result){
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function ymToMonthLabel($ym){ // "YYYY-MM" => "January 2026"
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return $ym;
    $dt = DateTime::createFromFormat('Y-m', $ym);
    return $dt ? $dt->format('F Y') : $ym;
}

function monthStartEnd($ym){
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return [null, null];
    $start = $ym . "-01";
    $end = (new DateTime($start))->modify('last day of this month')->format('Y-m-d');
    return [$start, $end];
}

/* =========================
   AJAX MODE (returns JSON)
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header("Content-Type: application/json; charset=UTF-8");

    $section = $_GET['section'] ?? '';
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
    $status = $_GET['status'] ?? '';

    // monthly generate
    $action = $_GET['action'] ?? '';
    $month  = $_GET['month'] ?? '';

    // normalize
    $from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
    $to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
    $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : '';

    $out = [
        "ok" => true,
        "section" => $section,
        "rows" => [],
        "summary" => [],
        "chart" => [],
        "message" => null
    ];

    try {

        /* =========================
           MONTHLY REPORTS - GENERATE & SAVE (Upsert)
        ========================= */
        if ($section === 'monthly' && $action === 'generate') {
            if (!$month) $month = date("Y-m");

            [$mStart, $mEnd] = monthStartEnd($month);
            if (!$mStart || !$mEnd) {
                echo json_encode(["ok"=>false,"error"=>"Invalid month. Use YYYY-MM."]);
                exit;
            }

            // totalClients (registered this month)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS totalClients
                FROM client_information
                WHERE dateRegistered >= ? AND dateRegistered <= ?
            ");
            $stmt->bind_param("ss", $mStart, $mEnd);
            $stmt->execute();
            $totalClients = (int)($stmt->get_result()->fetch_assoc()['totalClients'] ?? 0);
            $stmt->close();

            // totalAppointments (this month)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS totalAppointments
                FROM appointment
                WHERE appointmentDate >= ? AND appointmentDate <= ?
            ");
            $stmt->bind_param("ss", $mStart, $mEnd);
            $stmt->execute();
            $totalAppointments = (int)($stmt->get_result()->fetch_assoc()['totalAppointments'] ?? 0);
            $stmt->close();

            // totalVehicleServiced (distinct vehicle_id completed this month)
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT vehicle_id) AS totalVehicleServiced
                FROM appointment
                WHERE appointmentDate >= ? AND appointmentDate <= ?
                  AND status = 'Completed'
            ");
            $stmt->bind_param("ss", $mStart, $mEnd);
            $stmt->execute();
            $totalVehicleServiced = (int)($stmt->get_result()->fetch_assoc()['totalVehicleServiced'] ?? 0);
            $stmt->close();

            // totalRevenue (sum amountPaid for Paid payments this month)
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(amountPaid),0) AS totalRevenue
                FROM payments
                WHERE DATE(paymentDate) >= ? AND DATE(paymentDate) <= ?
                  AND paymentStatus = 'Paid'
            ");
            $stmt->bind_param("ss", $mStart, $mEnd);
            $stmt->execute();
            $totalRevenue = (float)($stmt->get_result()->fetch_assoc()['totalRevenue'] ?? 0);
            $stmt->close();

            // totalServicedRendered (completed appointments this month)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS totalServicedRendered
                FROM appointment
                WHERE appointmentDate >= ? AND appointmentDate <= ?
                  AND status = 'Completed'
            ");
            $stmt->bind_param("ss", $mStart, $mEnd);
            $stmt->execute();
            $totalServicedRendered = (int)($stmt->get_result()->fetch_assoc()['totalServicedRendered'] ?? 0);
            $stmt->close();

            // mostAvailedService (top serviceType this month)
            $stmt = $conn->prepare("
                SELECT serviceType, COUNT(*) AS cnt
                FROM appointment
                WHERE appointmentDate >= ? AND appointmentDate <= ?
                GROUP BY serviceType
                ORDER BY cnt DESC
                LIMIT 1
            ");
            $stmt->bind_param("ss", $mStart, $mEnd);
            $stmt->execute();
            $rowTop = $stmt->get_result()->fetch_assoc();
            $mostAvailedService = $rowTop['serviceType'] ?? '';
            $stmt->close();

            $reportMonthLabel = ymToMonthLabel($month); // e.g. "January 2026"
            $generatedBy = $_SESSION['name'] ?? $_SESSION['username'] ?? 'System';
            $remarks = "Auto-generated from live tables";
            $now = date("Y-m-d H:i:s");

            // upsert by reportMonth
            $stmt = $conn->prepare("SELECT reportID FROM monthly_reports WHERE reportMonth = ? LIMIT 1");
            $stmt->bind_param("s", $reportMonthLabel);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing && isset($existing['reportID'])) {
                $rid = (int)$existing['reportID'];
                $stmt = $conn->prepare("
                    UPDATE monthly_reports
                    SET totalClients = ?,
                        totalVehicleServiced = ?,
                        totalAppointments = ?,
                        totalRevenue = ?,
                        totalServicedRendered = ?,
                        mostAvailedService = ?,
                        generatedBy = ?,
                        dategenerated = ?,
                        remarks = ?
                    WHERE reportID = ?
                ");
                $stmt->bind_param(
                    "iiidissssi",
                    $totalClients,
                    $totalVehicleServiced,
                    $totalAppointments,
                    $totalRevenue,
                    $totalServicedRendered,
                    $mostAvailedService,
                    $generatedBy,
                    $now,
                    $remarks,
                    $rid
                );
                $stmt->execute();
                $stmt->close();
                $out["message"] = "Monthly report updated for {$reportMonthLabel}.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO monthly_reports
                    (reportMonth, totalClients, totalVehicleServiced, totalAppointments, totalRevenue, totalServicedRendered,
                     mostAvailedService, generatedBy, dategenerated, remarks)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param(
                    "siiidissss",
                    $reportMonthLabel,
                    $totalClients,
                    $totalVehicleServiced,
                    $totalAppointments,
                    $totalRevenue,
                    $totalServicedRendered,
                    $mostAvailedService,
                    $generatedBy,
                    $now,
                    $remarks
                );
                $stmt->execute();
                $stmt->close();
                $out["message"] = "Monthly report generated for {$reportMonthLabel}.";
            }

            // After generate, return fresh monthly list (same response)
            $section = "monthly";
        }

        /* =========================
           APPOINTMENTS
        ========================= */
        if ($section === 'appointments') {
            $clauses = [];
            $types = [];
            $vals = [];

            if ($from) { $clauses[] = "a.appointmentDate >= ?"; $types[] = "s"; $vals[] = $from; }
            if ($to)   { $clauses[] = "a.appointmentDate <= ?"; $types[] = "s"; $vals[] = $to; }
            if ($status && $status !== 'All') { $clauses[] = "a.status = ?"; $types[] = "s"; $vals[] = $status; }

            [$where, $bindTypes, $bindVals] = buildWhere($clauses, $types, $vals);

            $sql = "
                SELECT
                    a.appointment_id,
                    CONCAT(c.firstName,' ',c.lastName) AS clientName,
                    a.vehicle_id,
                    a.serviceType,
                    a.appointmentDate,
                    a.mechanicAssigned,
                    a.status
                FROM appointment a
                JOIN client_information c ON a.client_id = c.client_id
                $where
                ORDER BY a.appointmentDate DESC, a.appointment_id DESC
            ";

            $stmt = $conn->prepare($sql);
            if ($bindTypes) $stmt->bind_param($bindTypes, ...$bindVals);
            $stmt->execute();
            $rows = fetchAllAssoc($stmt->get_result());
            $stmt->close();

            $sqlS = "SELECT COUNT(*) AS totalAppointments,
                            SUM(status='Completed') AS completedAppointments
                     FROM appointment a $where";
            $stmtS = $conn->prepare($sqlS);
            if ($bindTypes) $stmtS->bind_param($bindTypes, ...$bindVals);
            $stmtS->execute();
            $sum = $stmtS->get_result()->fetch_assoc() ?? [];
            $stmtS->close();

            $sqlP = "SELECT a.status AS label, COUNT(*) AS cnt
                     FROM appointment a $where
                     GROUP BY a.status
                     ORDER BY cnt DESC";
            $stmtP = $conn->prepare($sqlP);
            if ($bindTypes) $stmtP->bind_param($bindTypes, ...$bindVals);
            $stmtP->execute();
            $pieRows = fetchAllAssoc($stmtP->get_result());
            $stmtP->close();

            $sqlL = "SELECT a.appointmentDate AS day, COUNT(*) AS cnt
                     FROM appointment a $where
                     GROUP BY a.appointmentDate
                     ORDER BY a.appointmentDate ASC";
            $stmtL = $conn->prepare($sqlL);
            if ($bindTypes) $stmtL->bind_param($bindTypes, ...$bindVals);
            $stmtL->execute();
            $lineRows = fetchAllAssoc($stmtL->get_result());
            $stmtL->close();

            $out["rows"] = $rows;
            $out["summary"] = $sum;
            $out["chart"] = [
                "pie" => $pieRows,
                "line" => $lineRows
            ];
        }

        /* =========================
           CLIENTS
        ========================= */
        else if ($section === 'clients') {
            $clauses = [];
            $types = [];
            $vals = [];

            if ($from) { $clauses[] = "dateRegistered >= ?"; $types[] = "s"; $vals[] = $from; }
            if ($to)   { $clauses[] = "dateRegistered <= ?"; $types[] = "s"; $vals[] = $to; }

            [$where, $bindTypes, $bindVals] = buildWhere($clauses, $types, $vals);

            $sql = "SELECT client_id, firstName, lastName, contactNumber, email, address, dateRegistered
                    FROM client_information
                    $where
                    ORDER BY dateRegistered DESC, client_id DESC";
            $stmt = $conn->prepare($sql);
            if ($bindTypes) $stmt->bind_param($bindTypes, ...$bindVals);
            $stmt->execute();
            $rows = fetchAllAssoc($stmt->get_result());
            $stmt->close();

            $sqlS = "SELECT COUNT(*) AS totalClients FROM client_information $where";
            $stmtS = $conn->prepare($sqlS);
            if ($bindTypes) $stmtS->bind_param($bindTypes, ...$bindVals);
            $stmtS->execute();
            $sum = $stmtS->get_result()->fetch_assoc() ?? [];
            $stmtS->close();

            $sqlL = "SELECT dateRegistered AS day, COUNT(*) AS cnt
                     FROM client_information $where
                     GROUP BY dateRegistered
                     ORDER BY dateRegistered ASC";
            $stmtL = $conn->prepare($sqlL);
            if ($bindTypes) $stmtL->bind_param($bindTypes, ...$bindVals);
            $stmtL->execute();
            $lineRows = fetchAllAssoc($stmtL->get_result());
            $stmtL->close();

            $out["rows"] = $rows;
            $out["summary"] = $sum;
            $out["chart"] = [
                "line" => $lineRows
            ];
        }

        /* =========================
           PAYMENTS
        ========================= */
        else if ($section === 'payments') {
            $clauses = [];
            $types = [];
            $vals = [];

            if ($from) { $clauses[] = "DATE(p.paymentDate) >= ?"; $types[] = "s"; $vals[] = $from; }
            if ($to)   { $clauses[] = "DATE(p.paymentDate) <= ?"; $types[] = "s"; $vals[] = $to; }
            if ($status && $status !== 'All') { $clauses[] = "p.paymentStatus = ?"; $types[] = "s"; $vals[] = $status; }

            [$where, $bindTypes, $bindVals] = buildWhere($clauses, $types, $vals);

            $sql = "
                SELECT
                    p.payment_id,
                    CONCAT(c.firstName,' ',c.lastName) AS clientName,
                    p.appointment_id,
                    p.paymentAmount,
                    p.amountPaid,
                    p.paymentDate,
                    p.paymentStatus,
                    p.referenceNumber
                FROM payments p
                JOIN client_information c ON p.client_id = c.client_id
                $where
                ORDER BY p.paymentDate DESC, p.payment_id DESC
            ";
            $stmt = $conn->prepare($sql);
            if ($bindTypes) $stmt->bind_param($bindTypes, ...$bindVals);
            $stmt->execute();
            $rows = fetchAllAssoc($stmt->get_result());
            $stmt->close();

            $sqlS = "SELECT SUM(paymentAmount) AS totalBilled,
                            SUM(amountPaid) AS totalCollected
                     FROM payments p $where";
            $stmtS = $conn->prepare($sqlS);
            if ($bindTypes) $stmtS->bind_param($bindTypes, ...$bindVals);
            $stmtS->execute();
            $sum = $stmtS->get_result()->fetch_assoc() ?? [];
            $stmtS->close();

            $sqlP = "SELECT p.paymentStatus AS label, COUNT(*) AS cnt
                     FROM payments p $where
                     GROUP BY p.paymentStatus
                     ORDER BY cnt DESC";
            $stmtP = $conn->prepare($sqlP);
            if ($bindTypes) $stmtP->bind_param($bindTypes, ...$bindVals);
            $stmtP->execute();
            $pieRows = fetchAllAssoc($stmtP->get_result());
            $stmtP->close();

            $sqlL = "SELECT DATE(p.paymentDate) AS day, SUM(p.amountPaid) AS collected
                     FROM payments p $where
                     GROUP BY DATE(p.paymentDate)
                     ORDER BY DATE(p.paymentDate) ASC";
            $stmtL = $conn->prepare($sqlL);
            if ($bindTypes) $stmtL->bind_param($bindTypes, ...$bindVals);
            $stmtL->execute();
            $lineRows = fetchAllAssoc($stmtL->get_result());
            $stmtL->close();

            $out["rows"] = $rows;
            $out["summary"] = $sum;
            $out["chart"] = [
                "pie" => $pieRows,
                "line" => $lineRows
            ];
        }

        /* =========================
           MONTHLY REPORTS - FETCH
        ========================= */
        else if ($section === 'monthly') {
            $clauses = [];
            $types = [];
            $vals = [];

            if ($from) { $clauses[] = "DATE(dategenerated) >= ?"; $types[] = "s"; $vals[] = $from; }
            if ($to)   { $clauses[] = "DATE(dategenerated) <= ?"; $types[] = "s"; $vals[] = $to; }

            [$where, $bindTypes, $bindVals] = buildWhere($clauses, $types, $vals);

            $sql = "
                SELECT
                    reportID,
                    reportMonth,
                    totalClients,
                    totalVehicleServiced,
                    totalAppointments,
                    totalRevenue,
                    totalServicedRendered,
                    mostAvailedService,
                    generatedBy,
                    dategenerated,
                    remarks
                FROM monthly_reports
                $where
                ORDER BY dategenerated DESC, reportID DESC
            ";
            $stmt = $conn->prepare($sql);
            if ($bindTypes) $stmt->bind_param($bindTypes, ...$bindVals);
            $stmt->execute();
            $rows = fetchAllAssoc($stmt->get_result());
            $stmt->close();

            $sqlS = "
                SELECT
                    COUNT(*) AS totalMonths,
                    COALESCE(SUM(totalClients),0) AS sumClients,
                    COALESCE(SUM(totalAppointments),0) AS sumAppointments,
                    COALESCE(SUM(totalVehicleServiced),0) AS sumVehicles,
                    COALESCE(SUM(totalRevenue),0) AS sumRevenue
                FROM monthly_reports
                $where
            ";
            $stmtS = $conn->prepare($sqlS);
            if ($bindTypes) $stmtS->bind_param($bindTypes, ...$bindVals);
            $stmtS->execute();
            $sum = $stmtS->get_result()->fetch_assoc() ?? [];
            $stmtS->close();

            // chart line (from fetched rows)
            $rowsAsc = $rows;
            usort($rowsAsc, function($a,$b){
                $ta = strtotime("1 " . ($a['reportMonth'] ?? ''));
                $tb = strtotime("1 " . ($b['reportMonth'] ?? ''));
                if ($ta && $tb) return $ta <=> $tb;
                return ($a['reportMonth'] ?? '') <=> ($b['reportMonth'] ?? '');
            });

            $lineRevenue = [];
            $lineAppt = [];
            foreach($rowsAsc as $r){
                $label = $r['reportMonth'] ?? '';
                $lineRevenue[] = ["day" => $label, "collected" => (float)($r['totalRevenue'] ?? 0)];
                $lineAppt[]    = ["day" => $label, "cnt" => (int)($r['totalAppointments'] ?? 0)];
            }

            $out["rows"] = $rows;
            $out["summary"] = $sum;
            $out["chart"] = [
                "revenueLine" => $lineRevenue,
                "apptLine" => $lineAppt
            ];
        }

        /* =========================
           OVERALL (summary of monthly_reports)
        ========================= */
        else if ($section === 'overall') {
            $clauses = [];
            $types = [];
            $vals = [];

            if ($from) { $clauses[] = "DATE(dategenerated) >= ?"; $types[] = "s"; $vals[] = $from; }
            if ($to)   { $clauses[] = "DATE(dategenerated) <= ?"; $types[] = "s"; $vals[] = $to; }

            [$where, $bindTypes, $bindVals] = buildWhere($clauses, $types, $vals);

            // summary
            $sqlS = "
                SELECT
                    COUNT(*) AS totalMonths,
                    COALESCE(SUM(totalClients),0) AS totalClients,
                    COALESCE(SUM(totalVehicleServiced),0) AS totalVehicleServiced,
                    COALESCE(SUM(totalAppointments),0) AS totalAppointments,
                    COALESCE(SUM(totalRevenue),0) AS totalRevenue,
                    COALESCE(SUM(totalServicedRendered),0) AS totalServicedRendered,
                    COALESCE(AVG(totalRevenue),0) AS avgRevenue,
                    MAX(totalRevenue) AS maxRevenue,
                    MAX(dategenerated) AS lastGenerated
                FROM monthly_reports
                $where
            ";
            $stmtS = $conn->prepare($sqlS);
            if ($bindTypes) $stmtS->bind_param($bindTypes, ...$bindVals);
            $stmtS->execute();
            $sum = $stmtS->get_result()->fetch_assoc() ?? [];
            $stmtS->close();

            // best month by revenue
            $sqlBest = "
                SELECT reportMonth, totalRevenue
                FROM monthly_reports
                $where
                ORDER BY totalRevenue DESC
                LIMIT 1
            ";
            $stmtB = $conn->prepare($sqlBest);
            if ($bindTypes) $stmtB->bind_param($bindTypes, ...$bindVals);
            $stmtB->execute();
            $best = $stmtB->get_result()->fetch_assoc() ?? [];
            $stmtB->close();

            // chart line: revenue per month (ordered by dategenerated)
            $sqlLine = "
                SELECT reportMonth AS label, totalRevenue AS val
                FROM monthly_reports
                $where
                ORDER BY dategenerated ASC, reportID ASC
            ";
            $stmtL = $conn->prepare($sqlLine);
            if ($bindTypes) $stmtL->bind_param($bindTypes, ...$bindVals);
            $stmtL->execute();
            $lineRows = fetchAllAssoc($stmtL->get_result());
            $stmtL->close();

            // chart pie: mostAvailedService occurrences
            $sqlPie = "
                SELECT mostAvailedService AS label, COUNT(*) AS cnt
                FROM monthly_reports
                $where
                GROUP BY mostAvailedService
                ORDER BY cnt DESC
                LIMIT 8
            ";
            $stmtP = $conn->prepare($sqlPie);
            if ($bindTypes) $stmtP->bind_param($bindTypes, ...$bindVals);
            $stmtP->execute();
            $pieRows = fetchAllAssoc($stmtP->get_result());
            $stmtP->close();

            // latest 10 monthly reports
            $sqlRows = "
                SELECT reportID, reportMonth, totalRevenue, totalAppointments, totalClients, dategenerated
                FROM monthly_reports
                $where
                ORDER BY dategenerated DESC, reportID DESC
                LIMIT 10
            ";
            $stmtR = $conn->prepare($sqlRows);
            if ($bindTypes) $stmtR->bind_param($bindTypes, ...$bindVals);
            $stmtR->execute();
            $rows = fetchAllAssoc($stmtR->get_result());
            $stmtR->close();

            $out["summary"] = $sum;
            $out["summary"]["bestMonth"] = $best["reportMonth"] ?? "";
            $out["summary"]["bestMonthRevenue"] = $best["totalRevenue"] ?? 0;

            $out["rows"] = $rows;
            $out["chart"] = [
                "revenueLine" => $lineRows,
                "servicePie" => $pieRows
            ];
        }

        else {
            $out["ok"] = false;
            $out["error"] = "Invalid section.";
        }

        echo json_encode($out);
        exit;

    } catch (Throwable $e) {
        echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
        exit;
    }
}

/* =========================
   NORMAL PAGE LOAD
========================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports | Rapid Repair</title>

    <link rel="stylesheet" href="pagelayout.css">
    <link rel="stylesheet" href="reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
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
        <input type="text" placeholder="Search..." disabled>
        <div id="searchResults" class="search-results" style="display:none;"></div>
    </div>

    <div class="user-info">
        <img src="pictures/user.png" alt="User">
        <div>
            <strong>Welcome!</strong><br>
            <span><?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
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
                <li><a href="servicesadmin.php">Service & Invoice</a></li>
                <li class="active"><a href="reportsadmin.php">Reports</a></li>

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
        <h2>Reports</h2>

        <div class="report-tabs">
            <button class="tab-btn active" data-tab="appointments">Appointments</button>
            <button class="tab-btn" data-tab="clients">Clients</button>
            <button class="tab-btn" data-tab="payments">Payments</button>
            <button class="tab-btn" data-tab="monthly">Monthly Reports</button>
            <button class="tab-btn" data-tab="overall">Overall</button>
        </div>

        <!-- ================= APPOINTMENTS ================= -->
        <section class="report-section active" id="tab-appointments">
            <div class="report-toolbar">
                <div>
                    <h3 style="margin:0;">Appointments Report</h3>
                    <small>Filter, visualize, then export to PDF.</small>
                </div>
                <div class="toolbar-actions">
                    <button class="btn btn-filter" data-filter="appointments">Filter</button>
                    <button class="btn btn-pdf" data-pdf="appointments">Generate PDF</button>
                </div>
            </div>

            <div id="pdf-appointments" class="pdf-wrap">
                <h3 class="pdf-title">Appointments Report</h3>
                <p class="pdf-meta" id="pdfmeta-appointments"></p>

                <div class="summary-box" id="sum-appointments"></div>

                <div class="grid-2">
                    <div class="chart-card">
                        <h4>Status Distribution</h4>
                        <canvas id="chart-appt-pie"></canvas>
                    </div>
                    <div class="chart-card">
                        <h4>Appointments per Day</h4>
                        <canvas id="chart-appt-line"></canvas>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Vehicle ID</th>
                            <th>Service Type</th>
                            <th>Mechanic</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody id="tbody-appointments">
                            <tr><td colspan="7">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= CLIENTS ================= -->
        <section class="report-section" id="tab-clients">
            <div class="report-toolbar">
                <div>
                    <h3 style="margin:0;">Client Records Report</h3>
                    <small>View registrations and export report.</small>
                </div>
                <div class="toolbar-actions">
                    <button class="btn btn-filter" data-filter="clients">Filter</button>
                    <button class="btn btn-pdf" data-pdf="clients">Generate PDF</button>
                </div>
            </div>

            <div id="pdf-clients" class="pdf-wrap">
                <h3 class="pdf-title">Client Records Report</h3>
                <p class="pdf-meta" id="pdfmeta-clients"></p>

                <div class="summary-box" id="sum-clients"></div>

                <div class="chart-card" style="margin-bottom:14px;">
                    <h4>Client Registrations per Day</h4>
                    <canvas id="chart-client-line"></canvas>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Date Registered</th>
                        </tr>
                        </thead>
                        <tbody id="tbody-clients">
                            <tr><td colspan="7">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= PAYMENTS ================= -->
        <section class="report-section" id="tab-payments">
            <div class="report-toolbar">
                <div>
                    <h3 style="margin:0;">Payments Report</h3>
                    <small>Track billed vs collected, visualize, export.</small>
                </div>
                <div class="toolbar-actions">
                    <button class="btn btn-filter" data-filter="payments">Filter</button>
                    <button class="btn btn-pdf" data-pdf="payments">Generate PDF</button>
                </div>
            </div>

            <div id="pdf-payments" class="pdf-wrap">
                <h3 class="pdf-title">Payments Report</h3>
                <p class="pdf-meta" id="pdfmeta-payments"></p>

                <div class="summary-box" id="sum-payments"></div>

                <div class="grid-2">
                    <div class="chart-card">
                        <h4>Payment Status Distribution</h4>
                        <canvas id="chart-pay-pie"></canvas>
                    </div>
                    <div class="chart-card">
                        <h4>Collections per Day</h4>
                        <canvas id="chart-pay-line"></canvas>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Client</th>
                            <th>Appointment ID</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Ref #</th>
                        </tr>
                        </thead>
                        <tbody id="tbody-payments">
                            <tr><td colspan="8">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= MONTHLY REPORTS ================= -->
        <section class="report-section" id="tab-monthly">
            <div class="report-toolbar">
                <div>
                    <h3 style="margin:0;">Monthly Reports (Saved)</h3>
                    <small>Rows come from <b>monthly_reports</b>. Generate/update this month anytime.</small>
                </div>
                <div class="toolbar-actions">
                    <button class="btn btn-filter" data-filter="monthly">Filter</button>
                    <button class="btn btn-generate" id="btn-generate-monthly">Generate Current Month</button>
                    <button class="btn btn-pdf" data-pdf="monthly">Generate PDF</button>
                </div>
            </div>

            <div id="pdf-monthly" class="pdf-wrap">
                <h3 class="pdf-title">Monthly Reports</h3>
                <p class="pdf-meta" id="pdfmeta-monthly"></p>

                <div class="summary-box" id="sum-monthly"></div>

                <div class="grid-2">
                    <div class="chart-card">
                        <h4>Revenue per Month</h4>
                        <canvas id="chart-monthly-revenue"></canvas>
                    </div>
                    <div class="chart-card">
                        <h4>Appointments per Month</h4>
                        <canvas id="chart-monthly-appt"></canvas>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Month</th>
                            <th>Total Clients</th>
                            <th>Total Vehicles Serviced</th>
                            <th>Total Appointments</th>
                            <th>Total Revenue</th>
                            <th>Total Services Rendered</th>
                            <th>Most Availed Service</th>
                            <th>Generated By</th>
                            <th>Date Generated</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody id="tbody-monthly">
                            <tr><td colspan="11">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= OVERALL ================= -->
        <section class="report-section" id="tab-overall">
            <div class="report-toolbar">
                <div>
                    <h3 style="margin:0;">Overall Summary (Monthly Reports)</h3>
                    <small>Aggregated totals from <b>monthly_reports</b>.</small>
                </div>
                <div class="toolbar-actions">
                    <button class="btn btn-filter" data-filter="overall">Filter</button>
                    <button class="btn btn-generate" id="btn-generate-overall">Generate Current Month</button>
                    <button class="btn btn-pdf" data-pdf="overall">Generate PDF</button>
                </div>
            </div>

            <div id="pdf-overall" class="pdf-wrap">
                <h3 class="pdf-title">Overall Summary (Monthly Reports)</h3>
                <p class="pdf-meta" id="pdfmeta-overall"></p>

                <div class="summary-box" id="sum-overall"></div>

                <div class="grid-2">
                    <div class="chart-card">
                        <h4>Revenue Trend (per generated month)</h4>
                        <canvas id="chart-overall-revenue"></canvas>
                    </div>
                    <div class="chart-card">
                        <h4>Top Most-Availed Services (monthly winners)</h4>
                        <canvas id="chart-overall-servicepie"></canvas>
                    </div>
                </div>

                <div class="table-container" style="margin-top:14px;">
                    <h4 style="margin:0 0 10px;">Latest 10 Generated Monthly Reports</h4>
                    <table>
                        <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Month</th>
                            <th>Total Revenue</th>
                            <th>Total Appointments</th>
                            <th>Total Clients</th>
                            <th>Date Generated</th>
                        </tr>
                        </thead>
                        <tbody id="tbody-overall">
                            <tr><td colspan="6">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- FILTER MODAL -->
<div class="modal" id="filterModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="filterTitle">Filter</h3>
            <button class="xbtn" id="filterClose">&times;</button>
        </div>

        <form id="filterForm">
            <input type="hidden" id="filterSection" value="appointments">

            <div class="modal-grid">
                <div>
                    <label>From</label>
                    <input type="date" id="filterFrom">
                </div>
                <div>
                    <label>To</label>
                    <input type="date" id="filterTo">
                </div>

                <div id="statusWrap" style="grid-column:1/3;">
                    <label>Status</label>
                    <select id="filterStatus">
                        <option value="All">All</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" id="filterCancel">Cancel</button>
                <button type="submit" class="btn btn-apply">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const statusOptions = {
    appointments: ["All","Pending","Approved","Scheduled","Ongoing","Completed","Cancelled","Invoiced"],
    payments: ["All","Paid","Partial","Unpaid"],
    clients: ["All"],
    monthly: ["All"],
    overall: ["All"]
};

let chartApptPie = null, chartApptLine = null;
let chartClientLine = null;
let chartPayPie = null, chartPayLine = null;
let chartMonthlyRevenue = null, chartMonthlyAppt = null;
let chartOverallRevenue = null, chartOverallServicePie = null;

function money(n){
    const x = Number(n || 0);
    return "₱" + x.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}
function showToast(msg){
    const t = document.getElementById("toast");
    if(!t) return;
    t.textContent = msg;
    t.style.display = "block";
    setTimeout(()=> t.style.display="none", 3200);
}
function setMeta(section, from, to, status){
    const el = document.getElementById("pdfmeta-" + section);
    if(!el) return;
    const parts = [];
    if(from) parts.push("From: " + from);
    if(to) parts.push("To: " + to);
    if(status && status !== "All") parts.push("Status: " + status);
    const meta = parts.length ? parts.join(" | ") : "All records";
    el.textContent = "Filters: " + meta + " | Generated: " + new Date().toLocaleString();
}

function escapeHtml(str){
    return String(str ?? "")
        .replaceAll("&","&amp;")
        .replaceAll("<","&lt;")
        .replaceAll(">","&gt;")
        .replaceAll('"',"&quot;")
        .replaceAll("'","&#039;");
}

/* ===== Status badge class mapping ===== */
function statusClass(status){
  const s = String(status || "").toLowerCase().trim();
  if(s === "completed") return "st-completed";
  if(s === "cancelled") return "st-cancelled";
  if(s === "pending") return "st-pending";
  if(s === "approved" || s === "scheduled" || s === "ongoing") return "st-approved";
  if(s === "invoiced") return "st-invoiced";
  return "st-default";
}
function payClass(status){
  const s = String(status || "").toLowerCase().trim();
  if(s === "paid") return "st-paid";
  if(s === "partial") return "st-partial";
  if(s === "unpaid") return "st-unpaid";
  return "st-default";
}

async function loadReport(section, from="", to="", status="All"){
    const url = new URL(window.location.href);
    url.searchParams.set("ajax","1");
    url.searchParams.set("section", section);

    if(from) url.searchParams.set("from", from); else url.searchParams.delete("from");
    if(to) url.searchParams.set("to", to); else url.searchParams.delete("to");
    if(status && status !== "All") url.searchParams.set("status", status); else url.searchParams.delete("status");

    const res = await fetch(url.toString());
    const data = await res.json();

    if(!data.ok){
        alert(data.error || "Failed loading report");
        return;
    }
    if(data.message) showToast(data.message);

    setMeta(section, from, to, status);

    if(section === "appointments"){
        const s = data.summary || {};
        document.getElementById("sum-appointments").innerHTML = `
            <strong>Total Appointments:</strong> ${Number(s.totalAppointments||0)} &nbsp; | &nbsp;
            <strong>Completed:</strong> ${Number(s.completedAppointments||0)}
        `;

        const tb = document.getElementById("tbody-appointments");
        if(!data.rows.length){
            tb.innerHTML = `<tr><td colspan="7">No records found</td></tr>`;
        } else {
            tb.innerHTML = data.rows.map(r => `
                <tr>
                    <td>${r.appointment_id}</td>
                    <td>${escapeHtml(r.appointmentDate)}</td>
                    <td>${escapeHtml(r.clientName)}</td>
                    <td>${r.vehicle_id}</td>
                    <td>${escapeHtml(r.serviceType)}</td>
                    <td>${escapeHtml(r.mechanicAssigned || "")}</td>
                    <td>
                      <span class="status-badge ${statusClass(r.status)}">
                        ${escapeHtml(r.status || "")}
                      </span>
                    </td>
                </tr>
            `).join("");
        }

        renderPie("chart-appt-pie", data.chart?.pie || [], chartApptPie, (c)=> chartApptPie=c);
        renderLine("chart-appt-line",
            (data.chart?.line || []).map(x=>({x:x.day,y:Number(x.cnt||0)})),
            chartApptLine, (c)=> chartApptLine=c, "Count");
    }

    if(section === "clients"){
        const s = data.summary || {};
        document.getElementById("sum-clients").innerHTML = `
            <strong>Total Clients:</strong> ${Number(s.totalClients||0)}
        `;
        const tb = document.getElementById("tbody-clients");
        tb.innerHTML = data.rows.length
            ? data.rows.map(r => `
                <tr>
                    <td>${r.client_id}</td>
                    <td>${escapeHtml(r.firstName)}</td>
                    <td>${escapeHtml(r.lastName)}</td>
                    <td>${escapeHtml(r.contactNumber || "")}</td>
                    <td>${escapeHtml(r.email || "")}</td>
                    <td>${escapeHtml(r.address || "")}</td>
                    <td>${escapeHtml(r.dateRegistered || "")}</td>
                </tr>
            `).join("")
            : `<tr><td colspan="7">No records found</td></tr>`;

        renderLine("chart-client-line",
            (data.chart?.line || []).map(x=>({x:x.day,y:Number(x.cnt||0)})),
            chartClientLine, (c)=> chartClientLine=c, "Clients");
    }

    if(section === "payments"){
        const s = data.summary || {};
        document.getElementById("sum-payments").innerHTML = `
            <strong>Total Billed:</strong> ${money(s.totalBilled||0)} &nbsp; | &nbsp;
            <strong>Total Collected:</strong> ${money(s.totalCollected||0)}
        `;
        const tb = document.getElementById("tbody-payments");
        tb.innerHTML = data.rows.length
            ? data.rows.map(r => `
                <tr>
                    <td>${r.payment_id}</td>
                    <td>${escapeHtml(r.clientName)}</td>
                    <td>${r.appointment_id}</td>
                    <td>${money(r.paymentAmount)}</td>
                    <td>${money(r.amountPaid)}</td>
                    <td>${escapeHtml(r.paymentDate)}</td>
                    <td>
                      <span class="status-badge ${payClass(r.paymentStatus)}">
                        ${escapeHtml(r.paymentStatus || "")}
                      </span>
                    </td>
                    <td>${escapeHtml(r.referenceNumber || "")}</td>
                </tr>
            `).join("")
            : `<tr><td colspan="8">No records found</td></tr>`;

        renderPie("chart-pay-pie", data.chart?.pie || [], chartPayPie, (c)=> chartPayPie=c);
        renderLine("chart-pay-line",
            (data.chart?.line || []).map(x=>({x:x.day,y:Number(x.collected||0)})),
            chartPayLine, (c)=> chartPayLine=c, "Collected");
    }

    if(section === "monthly"){
        const s = data.summary || {};
        document.getElementById("sum-monthly").innerHTML = `
            <strong>Months Shown:</strong> ${Number(s.totalMonths||0)} &nbsp; | &nbsp;
            <strong>Sum Clients:</strong> ${Number(s.sumClients||0)} &nbsp; | &nbsp;
            <strong>Sum Appointments:</strong> ${Number(s.sumAppointments||0)} &nbsp; | &nbsp;
            <strong>Sum Vehicles:</strong> ${Number(s.sumVehicles||0)} &nbsp; | &nbsp;
            <strong>Sum Revenue:</strong> ${money(s.sumRevenue||0)}
        `;

        const tb = document.getElementById("tbody-monthly");
        tb.innerHTML = data.rows.length
            ? data.rows.map(r => `
                <tr>
                    <td>${r.reportID}</td>
                    <td>${escapeHtml(r.reportMonth)}</td>
                    <td>${Number(r.totalClients||0)}</td>
                    <td>${Number(r.totalVehicleServiced||0)}</td>
                    <td>${Number(r.totalAppointments||0)}</td>
                    <td>${money(r.totalRevenue||0)}</td>
                    <td>${Number(r.totalServicedRendered||0)}</td>
                    <td>${escapeHtml(r.mostAvailedService || "")}</td>
                    <td>${escapeHtml(r.generatedBy || "")}</td>
                    <td>${escapeHtml(r.dategenerated || "")}</td>
                    <td>${escapeHtml(r.remarks || "")}</td>
                </tr>
            `).join("")
            : `<tr><td colspan="11">No records found</td></tr>`;

        renderLine("chart-monthly-revenue",
            (data.chart?.revenueLine || []).map(x=>({x:x.day,y:Number(x.collected||0)})),
            chartMonthlyRevenue, (c)=> chartMonthlyRevenue=c, "Revenue");
        renderLine("chart-monthly-appt",
            (data.chart?.apptLine || []).map(x=>({x:x.day,y:Number(x.cnt||0)})),
            chartMonthlyAppt, (c)=> chartMonthlyAppt=c, "Appointments");
    }

    if(section === "overall"){
        const s = data.summary || {};
        document.getElementById("sum-overall").innerHTML = `
            <strong>Months:</strong> ${Number(s.totalMonths||0)} &nbsp; | &nbsp;
            <strong>Total Revenue:</strong> ${money(s.totalRevenue||0)} &nbsp; | &nbsp;
            <strong>Avg Revenue/Month:</strong> ${money(s.avgRevenue||0)}<br><br>
            <strong>Total Clients:</strong> ${Number(s.totalClients||0)} &nbsp; | &nbsp;
            <strong>Total Vehicles Serviced:</strong> ${Number(s.totalVehicleServiced||0)} &nbsp; | &nbsp;
            <strong>Total Appointments:</strong> ${Number(s.totalAppointments||0)} &nbsp; | &nbsp;
            <strong>Total Services Rendered:</strong> ${Number(s.totalServicedRendered||0)}<br><br>
            <strong>Best Month:</strong> ${escapeHtml(s.bestMonth||"")} (${money(s.bestMonthRevenue||0)}) &nbsp; | &nbsp;
            <strong>Last Generated:</strong> ${escapeHtml(s.lastGenerated||"")}
        `;

        const tb = document.getElementById("tbody-overall");
        tb.innerHTML = data.rows.length
            ? data.rows.map(r => `
                <tr>
                    <td>${r.reportID}</td>
                    <td>${escapeHtml(r.reportMonth)}</td>
                    <td>${money(r.totalRevenue||0)}</td>
                    <td>${Number(r.totalAppointments||0)}</td>
                    <td>${Number(r.totalClients||0)}</td>
                    <td>${escapeHtml(r.dategenerated||"")}</td>
                </tr>
            `).join("")
            : `<tr><td colspan="6">No records found</td></tr>`;

        renderLine("chart-overall-revenue",
            (data.chart?.revenueLine || []).map(x=>({x:x.label,y:Number(x.val||0)})),
            chartOverallRevenue, (c)=> chartOverallRevenue=c, "Revenue");
        renderPie("chart-overall-servicepie",
            data.chart?.servicePie || [],
            chartOverallServicePie, (c)=> chartOverallServicePie=c);
    }
}

function renderPie(canvasId, rows, existingChart, setChart){
    const labels = rows.map(r=>r.label);
    const values = rows.map(r=>Number(r.cnt||0));
    const ctx = document.getElementById(canvasId);
    if(!ctx) return;
    if(existingChart) existingChart.destroy();
    const chart = new Chart(ctx, {
        type: 'pie',
        data: { labels, datasets: [{ data: values }] },
        options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
    });
    setChart(chart);
}
function renderLine(canvasId, points, existingChart, setChart, labelName){
    const labels = points.map(p=>p.x);
    const values = points.map(p=>p.y);
    const ctx = document.getElementById(canvasId);
    if(!ctx) return;
    if(existingChart) existingChart.destroy();
    const chart = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{ label: labelName, data: values, tension: 0.25 }] },
        options: { responsive:true, plugins:{ legend:{ display:true } }, scales:{ y:{ beginAtZero:true } } }
    });
    setChart(chart);
}

/* Tabs */
document.querySelectorAll(".tab-btn").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        document.querySelectorAll(".tab-btn").forEach(b=>b.classList.remove("active"));
        btn.classList.add("active");
        const tab = btn.dataset.tab;
        document.querySelectorAll(".report-section").forEach(sec=>sec.classList.remove("active"));
        document.getElementById("tab-" + tab).classList.add("active");
    });
});

/* Filter Modal */
const modal = document.getElementById("filterModal");
const filterTitle = document.getElementById("filterTitle");
const filterSection = document.getElementById("filterSection");
const filterFrom = document.getElementById("filterFrom");
const filterTo = document.getElementById("filterTo");
const filterStatus = document.getElementById("filterStatus");
const statusWrap = document.getElementById("statusWrap");

function openFilter(section){
    filterSection.value = section;
    filterTitle.textContent =
        section === "appointments" ? "Filter Appointments" :
        section === "clients" ? "Filter Clients" :
        section === "payments" ? "Filter Payments" :
        section === "monthly" ? "Filter Monthly Reports" :
        "Filter Overall Summary";

    if(section === "clients" || section === "monthly" || section === "overall"){
        statusWrap.style.display = "none";
    } else {
        statusWrap.style.display = "block";
        filterStatus.innerHTML = (statusOptions[section] || ["All"])
            .map(s=>`<option value="${s}">${s}</option>`).join("");
    }
    modal.style.display = "flex";
}
function closeFilter(){ modal.style.display = "none"; }

document.querySelectorAll("[data-filter]").forEach(btn=>{
    btn.addEventListener("click", ()=> openFilter(btn.dataset.filter));
});
document.getElementById("filterClose").addEventListener("click", closeFilter);
document.getElementById("filterCancel").addEventListener("click", closeFilter);
modal.addEventListener("click", (e)=>{ if(e.target===modal) closeFilter(); });

document.getElementById("filterForm").addEventListener("submit", async (e)=>{
    e.preventDefault();
    const sec = filterSection.value;
    const from = filterFrom.value;
    const to = filterTo.value;
    const status = (sec === "clients" || sec === "monthly" || sec === "overall") ? "All" : (filterStatus.value || "All");
    await loadReport(sec, from, to, status);
    closeFilter();
});

/* Generate Current Month (Monthly tab) */
document.getElementById("btn-generate-monthly").addEventListener("click", async ()=>{
    const url = new URL(window.location.href);
    url.searchParams.set("ajax","1");
    url.searchParams.set("section","monthly");
    url.searchParams.set("action","generate");
    const res = await fetch(url.toString());
    const data = await res.json();
    if(!data.ok){ alert(data.error || "Failed generating monthly report"); return; }
    if(data.message) showToast(data.message);
    await loadReport("monthly");
    await loadReport("overall");
});

/* Generate Current Month (Overall tab) */
document.getElementById("btn-generate-overall").addEventListener("click", async ()=>{
    const url = new URL(window.location.href);
    url.searchParams.set("ajax","1");
    url.searchParams.set("section","monthly");
    url.searchParams.set("action","generate");
    const res = await fetch(url.toString());
    const data = await res.json();
    if(!data.ok){ alert(data.error || "Failed generating monthly report"); return; }
    if(data.message) showToast(data.message);
    await loadReport("monthly");
    await loadReport("overall");
});

/* PDF Export */
document.querySelectorAll("[data-pdf]").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        const sec = btn.dataset.pdf;
        const wrap = document.getElementById("pdf-" + sec);
        if(!wrap) return;

        wrap.querySelectorAll(".pdf-title, .pdf-meta").forEach(el => el.style.display = "block");

        const opt = {
            margin:       0.4,
            filename:     `RapidRepair-${sec}-Report.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'A3', orientation: 'landscape' }
        };

        html2pdf().set(opt).from(wrap).save().then(()=>{
            wrap.querySelectorAll(".pdf-title, .pdf-meta").forEach(el => el.style.display = "none");
        });
    });
});

/* Initial Load */
(async ()=>{
    await loadReport("appointments");
    await loadReport("clients");
    await loadReport("payments");
    await loadReport("monthly");
    await loadReport("overall");
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
