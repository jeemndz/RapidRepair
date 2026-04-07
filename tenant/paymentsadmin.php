<?php
session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/../session_security.php';

if (!isset($_SESSION['tenantID'])) {
    header('Location: tenantlogin.php');
    exit;
}

$tenantID = (int) $_SESSION['tenantID'];



$loginSlug = '';
if (isset($_SESSION['login_slug']) && trim((string) $_SESSION['login_slug']) !== '') {
    $loginSlug = trim((string) $_SESSION['login_slug']);
} elseif (isset($_GET['shop']) && trim((string) $_GET['shop']) !== '') {
    $loginSlug = trim((string) $_GET['shop']);
    $_SESSION['login_slug'] = $loginSlug;
}

if ($loginSlug === '') {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$ownerStmt = mysqli_prepare($conn, "SELECT shopName FROM owners WHERE tenantID = ? AND login_slug = ? LIMIT 1");
mysqli_stmt_bind_param($ownerStmt, 'is', $tenantID, $loginSlug);
mysqli_stmt_execute($ownerStmt);
$ownerResult = mysqli_stmt_get_result($ownerStmt);
$owner = $ownerResult ? mysqli_fetch_assoc($ownerResult) : null;
mysqli_stmt_close($ownerStmt);

if (!$owner) {
    session_unset();
    session_destroy();
    header('Location: tenantlogin.php');
    exit;
}

$_SESSION['login_slug'] = $loginSlug;
$shopQuery = urlencode($loginSlug);

$currentScript = basename($_SERVER['PHP_SELF']);
if (!isset($_GET['shop']) || trim((string) $_GET['shop']) !== $loginSlug) {
    header('Location: ' . $currentScript . '?shop=' . $shopQuery);
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isValidGcashReference(string $value): bool
{
    return (bool) preg_match('/^[A-Za-z0-9]{10,20}$/', $value);
}

function formatPaymentReference(int $paymentId): string
{
    return 'AP-' . str_pad((string) $paymentId, 5, '0', STR_PAD_LEFT);
}

$allowedStatuses = ['All', 'Pending', 'Partial', 'Paid', 'Failed', 'Refunded'];
$allowedMethods = ['All', 'Cash', 'GCash', 'Card', 'Bank Transfer'];
$paymentStatusChoices = ['Pending', 'Partial', 'Paid', 'Failed', 'Refunded'];
$paymentMethodChoices = ['Cash', 'GCash', 'Card', 'Bank Transfer'];

$flashMessage = '';
$flashType = 'success';

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'All'));
$methodFilter = trim((string) ($_GET['method'] ?? 'All'));
$page = max(1, (int) ($_GET['page'] ?? 1));
$rowsPerPage = 10;

$hasRepairJobIdColumn = false;
$maxMonetaryAmount = 99999999.99;
$repairJobColumnCheckSql = "
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'payments'
            AND COLUMN_NAME = 'repair_job_id'
        LIMIT 1
";
$repairJobColumnCheckResult = mysqli_query($conn, $repairJobColumnCheckSql);
if ($repairJobColumnCheckResult && mysqli_fetch_row($repairJobColumnCheckResult)) {
    $hasRepairJobIdColumn = true;
}

$amountSpecSql = "
    SELECT NUMERIC_PRECISION, NUMERIC_SCALE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'paymentAmount'
    LIMIT 1
";
$amountSpecResult = mysqli_query($conn, $amountSpecSql);
if ($amountSpecResult && $amountSpec = mysqli_fetch_assoc($amountSpecResult)) {
    $precision = isset($amountSpec['NUMERIC_PRECISION']) ? (int) $amountSpec['NUMERIC_PRECISION'] : 0;
    $scale = isset($amountSpec['NUMERIC_SCALE']) ? (int) $amountSpec['NUMERIC_SCALE'] : 0;
    if ($precision > 0 && $scale >= 0 && $precision > $scale) {
        $integerDigits = $precision - $scale;
        $maxMonetaryAmount = ((float) pow(10, $integerDigits)) - ((float) pow(10, -$scale));
    }
}

$nextReferencePreview = 'AP-00000';
$nextAutoIncrementSql = "
    SELECT AUTO_INCREMENT
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
    LIMIT 1
";
$nextAutoIncrementResult = mysqli_query($conn, $nextAutoIncrementSql);
if ($nextAutoIncrementResult && ($nextAutoIncrementRow = mysqli_fetch_assoc($nextAutoIncrementResult))) {
    $nextPaymentId = isset($nextAutoIncrementRow['AUTO_INCREMENT']) ? (int) $nextAutoIncrementRow['AUTO_INCREMENT'] : 0;
    if ($nextPaymentId > 0) {
        $nextReferencePreview = formatPaymentReference($nextPaymentId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_submit'])) {
    $postUserId = isset($_POST['user_id']) ? max(0, (int) $_POST['user_id']) : 0;
    $rawPaymentAmount = isset($_POST['paymentAmount']) ? str_replace(',', '', trim((string) $_POST['paymentAmount'])) : '';
    $rawAmountPaid = isset($_POST['amountPaid']) ? str_replace(',', '', trim((string) $_POST['amountPaid'])) : '';
    $postPaymentAmount = is_numeric($rawPaymentAmount) ? (float) $rawPaymentAmount : -1.0;
    $postAmountPaid = is_numeric($rawAmountPaid) ? (float) $rawAmountPaid : -1.0;
    $postMethod = trim((string) ($_POST['paymentMethod'] ?? ''));
    $postStatus = trim((string) ($_POST['paymentStatus'] ?? 'Pending'));
    $postPaymentDate = trim((string) ($_POST['paymentDate'] ?? date('Y-m-d')));
    $postReferenceNumber = '';
    $postGcashReference = trim((string) ($_POST['gcashReferenceNumber'] ?? ''));
    $postRemarks = trim((string) ($_POST['remarks'] ?? ''));
    $postRepairJobId = isset($_POST['repair_job_id']) ? max(0, (int) $_POST['repair_job_id']) : 0;

    $validationError = '';

    if ($postUserId <= 0) {
        $validationError = 'Customer is required.';
    } elseif ($postPaymentAmount <= 0) {
        $validationError = 'Payment amount must be greater than 0.';
    } elseif ($postAmountPaid < 0) {
        $validationError = 'Amount paid cannot be negative.';
    } elseif ($postPaymentAmount > $maxMonetaryAmount || $postAmountPaid > $maxMonetaryAmount) {
        $validationError = 'Amount is too large for the payment column limit.';
    } elseif ($postAmountPaid > $postPaymentAmount) {
        $validationError = 'Amount paid cannot exceed payment amount.';
    } elseif (!in_array($postMethod, $paymentMethodChoices, true)) {
        $validationError = 'Invalid payment method.';
    } elseif (!in_array($postStatus, $paymentStatusChoices, true)) {
        $validationError = 'Invalid payment status.';
    } elseif ($postPaymentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $postPaymentDate)) {
        $validationError = 'Invalid payment date.';
    } elseif ($postMethod === 'GCash' && !isValidGcashReference($postGcashReference)) {
        $validationError = 'GCash reference must be 10-20 alphanumeric characters.';
    }

    if ($validationError === '') {
        $userCheckStmt = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE user_id = ? AND tenantID = ? LIMIT 1');
        if ($userCheckStmt) {
            mysqli_stmt_bind_param($userCheckStmt, 'ii', $postUserId, $tenantID);
            mysqli_stmt_execute($userCheckStmt);
            $userCheckResult = mysqli_stmt_get_result($userCheckStmt);
            if (!$userCheckResult || !mysqli_fetch_assoc($userCheckResult)) {
                $validationError = 'Selected customer does not belong to this tenant.';
            }
            mysqli_stmt_close($userCheckStmt);
        } else {
            $validationError = 'Unable to validate customer.';
        }
    }

    if ($validationError === '') {
        $computedBalance = max(0, $postPaymentAmount - $postAmountPaid);
        $insertSql = 'INSERT INTO payments (tenantID, user_id, paymentAmount, amountPaid, balance, paymentMethod, paymentDate, paymentStatus, referenceNumber, gcashReferenceNumber, remarks';
        if ($hasRepairJobIdColumn) {
            $insertSql .= ', repair_job_id';
        }
        $insertSql .= ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
        if ($hasRepairJobIdColumn) {
            $insertSql .= ', ?';
        }
        $insertSql .= ')';

        $insertStmt = mysqli_prepare($conn, $insertSql);
        if ($insertStmt) {
            $referenceNumber = null;
            $gcashReference = $postMethod === 'GCash' ? $postGcashReference : null;
            $remarks = $postRemarks !== '' ? $postRemarks : null;

            if ($hasRepairJobIdColumn) {
                $repairJobIdValue = $postRepairJobId > 0 ? $postRepairJobId : null;
                mysqli_stmt_bind_param(
                    $insertStmt,
                    'iidddssssssi',
                    $tenantID,
                    $postUserId,
                    $postPaymentAmount,
                    $postAmountPaid,
                    $computedBalance,
                    $postMethod,
                    $postPaymentDate,
                    $postStatus,
                    $referenceNumber,
                    $gcashReference,
                    $remarks,
                    $repairJobIdValue
                );
            } else {
                mysqli_stmt_bind_param(
                    $insertStmt,
                    'iidddssssss',
                    $tenantID,
                    $postUserId,
                    $postPaymentAmount,
                    $postAmountPaid,
                    $computedBalance,
                    $postMethod,
                    $postPaymentDate,
                    $postStatus,
                    $referenceNumber,
                    $gcashReference,
                    $remarks
                );
            }

            if (mysqli_stmt_execute($insertStmt)) {
                $newPaymentId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($insertStmt);

                if ($newPaymentId > 0) {
                    $generatedReference = formatPaymentReference($newPaymentId);
                    $referenceUpdateStmt = mysqli_prepare(
                        $conn,
                        'UPDATE payments SET referenceNumber = ? WHERE payment_id = ? AND tenantID = ? LIMIT 1'
                    );
                    if ($referenceUpdateStmt) {
                        mysqli_stmt_bind_param($referenceUpdateStmt, 'sii', $generatedReference, $newPaymentId, $tenantID);
                        mysqli_stmt_execute($referenceUpdateStmt);
                        mysqli_stmt_close($referenceUpdateStmt);
                    }
                }

                header('Location: paymentsadmin.php?shop=' . urlencode($loginSlug) . '&payment_added=1');
                exit;
            }

            $validationError = 'Unable to save payment.';
            mysqli_stmt_close($insertStmt);
        } else {
            $validationError = 'Unable to prepare payment insert query.';
        }
    }

    if ($validationError !== '') {
        $flashMessage = $validationError;
        $flashType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status_submit'])) {
    $postPaymentId = isset($_POST['payment_id']) ? max(0, (int) $_POST['payment_id']) : 0;
    $postStatus = trim((string) ($_POST['paymentStatus'] ?? ''));

    if ($postPaymentId <= 0 || !in_array($postStatus, $paymentStatusChoices, true)) {
        $flashMessage = 'Invalid payment status update request.';
        $flashType = 'error';
    } else {
        $updateStmt = mysqli_prepare($conn, 'UPDATE payments SET paymentStatus = ? WHERE payment_id = ? AND tenantID = ? LIMIT 1');
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'sii', $postStatus, $postPaymentId, $tenantID);
            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);
                header('Location: paymentsadmin.php?shop=' . urlencode($loginSlug) . '&status_updated=1');
                exit;
            }
            mysqli_stmt_close($updateStmt);
        }
        $flashMessage = 'Unable to update payment status.';
        $flashType = 'error';
    }
}

if (isset($_GET['payment_added']) && (string) $_GET['payment_added'] === '1') {
    $flashMessage = 'Payment has been added successfully.';
    $flashType = 'success';
}
if (isset($_GET['status_updated']) && (string) $_GET['status_updated'] === '1') {
    $flashMessage = 'Payment status has been updated.';
    $flashType = 'success';
}

if (isset($_GET['print_receipt'])) {
    $printPaymentId = max(0, (int) $_GET['print_receipt']);
    if ($printPaymentId > 0) {
        $receiptSql = "SELECT
                p.payment_id,
                p.paymentDate,
                p.paymentAmount,
                p.amountPaid,
                p.balance,
                p.paymentMethod,
                p.paymentStatus,
                p.referenceNumber,
                p.gcashReferenceNumber,
                p.remarks,
                COALESCE(u.fullName, CONCAT('User #', p.user_id)) AS customer_name,
                COALESCE(u.address, '') AS customer_address,
                COALESCE(u.contactNumber, '') AS customer_contact,
                COALESCE(u.email, '') AS customer_email,
                COALESCE(v.brand, '') AS vehicle_brand,
                COALESCE(v.model, '') AS vehicle_model,
                COALESCE(v.year_model, '') AS vehicle_year,
                COALESCE(v.mileage_km, '') AS vehicle_mileage
            FROM payments p
            LEFT JOIN users u ON u.user_id = p.user_id
            LEFT JOIN vehicleinformation v ON v.vehicle_id = (
                SELECT vi2.vehicle_id
                FROM vehicleinformation vi2
                WHERE vi2.tenantID = p.tenantID AND vi2.user_id = p.user_id
                ORDER BY vi2.date_added DESC, vi2.vehicle_id DESC
                LIMIT 1
            )
            WHERE p.payment_id = ? AND p.tenantID = ?
            LIMIT 1";

        $receiptStmt = mysqli_prepare($conn, $receiptSql);
        $receiptRow = null;
        if ($receiptStmt) {
            mysqli_stmt_bind_param($receiptStmt, 'ii', $printPaymentId, $tenantID);
            mysqli_stmt_execute($receiptStmt);
            $receiptResult = mysqli_stmt_get_result($receiptStmt);
            if ($receiptResult) {
                $receiptRow = mysqli_fetch_assoc($receiptResult) ?: null;
            }
            mysqli_stmt_close($receiptStmt);
        }

        if ($receiptRow) {
            $serviceTotal = (float) $receiptRow['paymentAmount'];
            $paidAmount = (float) $receiptRow['amountPaid'];
            $balanceAmount = (float) $receiptRow['balance'];
            header('Content-Type: text/html; charset=utf-8');
            ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo h($receiptRow['payment_id']); ?></title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f4f6;color:#1f2937;margin:0;padding:24px}
        .sheet{max-width:900px;margin:0 auto;background:#fff;padding:28px;border:1px solid #d1d5db}
        .row{display:flex;justify-content:space-between;gap:24px}
        .muted{color:#4b5563}
        .title{font-size:28px;font-weight:700;letter-spacing:0.06em}
        .section{margin-top:14px;border-top:2px solid #1f2937;padding-top:8px}
        table{width:100%;border-collapse:collapse;margin-top:8px}
        th,td{padding:8px;border-bottom:1px solid #d1d5db;text-align:left}
        th{background:#e5e7eb;font-size:12px;text-transform:uppercase;letter-spacing:.06em}
        .right{text-align:right}
        .totals{margin-top:18px;width:320px;margin-left:auto}
        .totals td{border:none;padding:4px 0}
        .print-bar{max-width:900px;margin:0 auto 12px;text-align:right}
        .btn{padding:10px 14px;border:1px solid #374151;background:#111827;color:#fff;cursor:pointer}
        @media print {.print-bar{display:none} body{background:#fff;padding:0} .sheet{border:none}}
    </style>
</head>
<body>
    <div class="print-bar"><button class="btn" onclick="window.print()">Print Receipt</button></div>
    <div class="sheet">
        <div class="row">
            <div>
                <div class="title"><?php echo h($owner['shopName']); ?></div>
                <div class="muted">RapidRepair Service Receipt</div>
            </div>
            <div>
                <div><strong>Receipt NO.</strong> <?php echo h($receiptRow['payment_id']); ?></div>
                <div><strong>Date</strong> <?php echo h(date('M d, Y', strtotime((string) $receiptRow['paymentDate']))); ?></div>
                <div><strong>Customer</strong> <?php echo h($receiptRow['customer_name']); ?></div>
            </div>
        </div>

        <div class="section row">
            <div style="width:50%">
                <strong>Bill To</strong>
                <div><?php echo h($receiptRow['customer_name']); ?></div>
                <div><?php echo h($receiptRow['customer_address']); ?></div>
                <div><?php echo h($receiptRow['customer_contact']); ?></div>
                <div><?php echo h($receiptRow['customer_email']); ?></div>
            </div>
            <div style="width:50%">
                <strong>Vehicle Information</strong>
                <div><?php echo h(trim($receiptRow['vehicle_brand'] . ' ' . $receiptRow['vehicle_model'])); ?></div>
                <div>Year: <?php echo h($receiptRow['vehicle_year'] ?: 'N/A'); ?></div>
                <div>Mileage: <?php echo h($receiptRow['vehicle_mileage'] !== '' ? number_format((float) $receiptRow['vehicle_mileage']) . ' km' : 'N/A'); ?></div>
            </div>
        </div>

        <div class="section">
            <table>
                <thead><tr><th>Service Description</th><th>Note</th><th class="right">Amount</th><th class="right">Total</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Payment #<?php echo h($receiptRow['payment_id']); ?> (<?php echo h($receiptRow['paymentMethod']); ?>)</td>
                        <td><?php echo h($receiptRow['remarks'] ?: 'N/A'); ?></td>
                        <td class="right">PHP <?php echo number_format($serviceTotal, 2); ?></td>
                        <td class="right"><strong>PHP <?php echo number_format($serviceTotal, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <table class="totals">
            <tr><td><strong>Total Service</strong></td><td class="right"><strong>PHP <?php echo number_format($serviceTotal, 2); ?></strong></td></tr>
            <tr><td>Amount Paid</td><td class="right">PHP <?php echo number_format($paidAmount, 2); ?></td></tr>
            <tr><td>Balance</td><td class="right">PHP <?php echo number_format($balanceAmount, 2); ?></td></tr>
            <tr><td>Status</td><td class="right"><?php echo h($receiptRow['paymentStatus']); ?></td></tr>
            <tr><td>Reference</td><td class="right"><?php echo h($receiptRow['gcashReferenceNumber'] ?: ($receiptRow['referenceNumber'] ?: 'N/A')); ?></td></tr>
        </table>
    </div>
</body>
</html>
            <?php
            exit;
        }
    }
}

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}
if (!in_array($methodFilter, $allowedMethods, true)) {
    $methodFilter = 'All';
}

$customers = [];
$customerSql = "SELECT user_id, fullName FROM users WHERE tenantID = $tenantID AND role = 'client' ORDER BY fullName ASC";
$customerResult = mysqli_query($conn, $customerSql);
if ($customerResult) {
    while ($customer = mysqli_fetch_assoc($customerResult)) {
        $customers[] = $customer;
    }
}

$addPaymentForm = [
    'user_id' => isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0,
    'repair_job_id' => isset($_POST['repair_job_id']) ? (int) $_POST['repair_job_id'] : 0,
    'paymentAmount' => isset($_POST['paymentAmount']) ? (string) $_POST['paymentAmount'] : '',
    'amountPaid' => isset($_POST['amountPaid']) ? (string) $_POST['amountPaid'] : '',
    'paymentMethod' => isset($_POST['paymentMethod']) ? (string) $_POST['paymentMethod'] : 'Cash',
    'paymentStatus' => isset($_POST['paymentStatus']) ? (string) $_POST['paymentStatus'] : 'Pending',
    'paymentDate' => isset($_POST['paymentDate']) ? (string) $_POST['paymentDate'] : date('Y-m-d'),
    'referenceNumber' => '',
    'gcashReferenceNumber' => isset($_POST['gcashReferenceNumber']) ? (string) $_POST['gcashReferenceNumber'] : '',
    'remarks' => isset($_POST['remarks']) ? (string) $_POST['remarks'] : '',
];
$showAddPaymentModal = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_submit']) && $flashType === 'error';

$where = ["p.tenantID = $tenantID"];

if ($statusFilter !== 'All') {
    $safeStatus = mysqli_real_escape_string($conn, $statusFilter);
    $where[] = "p.paymentStatus = '$safeStatus'";
}

if ($methodFilter !== 'All') {
    $safeMethod = mysqli_real_escape_string($conn, $methodFilter);
    $where[] = "p.paymentMethod = '$safeMethod'";
}

if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $searchClauses = [
        "CAST(p.payment_id AS CHAR) LIKE '%$safeSearch%'",
        "COALESCE(u.fullName, '') LIKE '%$safeSearch%'",
        "COALESCE(p.referenceNumber, '') LIKE '%$safeSearch%'",
        "COALESCE(p.gcashReferenceNumber, '') LIKE '%$safeSearch%'",
        "COALESCE(p.remarks, '') LIKE '%$safeSearch%'",
    ];
    if ($hasRepairJobIdColumn) {
        $searchClauses[] = "CAST(p.repair_job_id AS CHAR) LIKE '%$safeSearch%'";
    }
    $where[] = "(
        " . implode("\n        OR ", $searchClauses) . "
    )";
}

$whereSql = implode(' AND ', $where);

$statsSql = "
    SELECT
        COALESCE(SUM(CASE WHEN p.paymentStatus IN ('Paid', 'Partial') THEN p.amountPaid ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN p.paymentStatus = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_invoices,
        COALESCE(SUM(CASE WHEN YEARWEEK(DATE(p.paymentDate), 1) = YEARWEEK(CURDATE(), 1) AND p.paymentStatus IN ('Paid', 'Partial') THEN p.amountPaid ELSE 0 END), 0) AS paid_this_week,
        COALESCE(SUM(CASE WHEN p.paymentStatus = 'Failed' THEN 1 ELSE 0 END), 0) AS failed_count
    FROM payments p
    WHERE p.tenantID = $tenantID
";
$statsResult = mysqli_query($conn, $statsSql);
$statsRow = $statsResult ? mysqli_fetch_assoc($statsResult) : [];

$totalRevenue = (float) ($statsRow['total_revenue'] ?? 0);
$pendingInvoices = (int) ($statsRow['pending_invoices'] ?? 0);
$paidThisWeek = (float) ($statsRow['paid_this_week'] ?? 0);
$failedCount = (int) ($statsRow['failed_count'] ?? 0);

$totalRowsSql = "
    SELECT COUNT(*) AS total_rows
    FROM payments p
    LEFT JOIN users u ON u.user_id = p.user_id
    WHERE $whereSql
";
$totalRowsResult = mysqli_query($conn, $totalRowsSql);
$totalRows = (int) (($totalRowsResult ? mysqli_fetch_assoc($totalRowsResult)['total_rows'] : 0) ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $rowsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $rowsPerPage;

$paymentsSql = "
    SELECT
        p.payment_id,
        p.tenantID,
        p.user_id,
        " . ($hasRepairJobIdColumn ? "p.repair_job_id" : "NULL") . " AS repair_job_id,
        p.paymentAmount,
        p.amountPaid,
        p.balance,
        p.paymentMethod,
        p.paymentDate,
        p.paymentStatus,
        p.referenceNumber,
        p.gcashReferenceNumber,
        p.remarks,
        p.created_at,
        p.updated_at,
        COALESCE(u.fullName, CONCAT('User #', p.user_id)) AS customer_name
    FROM payments p
    LEFT JOIN users u ON u.user_id = p.user_id
    WHERE $whereSql
    ORDER BY p.paymentDate DESC, p.payment_id DESC
    LIMIT $offset, $rowsPerPage
";
$payments = [];
$paymentsResult = mysqli_query($conn, $paymentsSql);
if ($paymentsResult) {
    while ($row = mysqli_fetch_assoc($paymentsResult)) {
        $payments[] = $row;
    }
}

$recentActivitySql = "
    SELECT
        p.payment_id,
        p.paymentDate,
        p.paymentStatus,
        p.amountPaid,
        p.paymentAmount,
        COALESCE(u.fullName, CONCAT('User #', p.user_id)) AS customer_name
    FROM payments p
    LEFT JOIN users u ON u.user_id = p.user_id
    WHERE p.tenantID = $tenantID
    ORDER BY p.paymentDate DESC, p.payment_id DESC
    LIMIT 5
";
$recentActivities = [];
$recentActivityResult = mysqli_query($conn, $recentActivitySql);
if ($recentActivityResult) {
    while ($row = mysqli_fetch_assoc($recentActivityResult)) {
        $recentActivities[] = $row;
    }
}

$monthlyRevenueSql = "
    SELECT
        DATE_FORMAT(paymentDate, '%b') AS month_label,
        MONTH(paymentDate) AS month_num,
        YEAR(paymentDate) AS year_num,
        COALESCE(SUM(amountPaid), 0) AS month_revenue
    FROM payments
    WHERE tenantID = $tenantID
      AND paymentDate >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
      AND paymentStatus IN ('Paid', 'Partial')
    GROUP BY YEAR(paymentDate), MONTH(paymentDate), DATE_FORMAT(paymentDate, '%b')
    ORDER BY year_num ASC, month_num ASC
";
$monthlyRevenueData = [];
$monthlyRevenueResult = mysqli_query($conn, $monthlyRevenueSql);
if ($monthlyRevenueResult) {
    while ($row = mysqli_fetch_assoc($monthlyRevenueResult)) {
        $monthlyRevenueData[] = $row;
    }
}

$monthMap = [];
foreach ($monthlyRevenueData as $row) {
    $monthMap[$row['year_num'] . '-' . $row['month_num']] = [
        'label' => $row['month_label'],
        'value' => (float) $row['month_revenue']
    ];
}

$chartLabels = [];
$chartValues = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i month");
    $y = date('Y', $ts);
    $m = date('n', $ts);
    $key = $y . '-' . $m;
    $chartLabels[] = date('M', $ts);
    $chartValues[] = isset($monthMap[$key]) ? $monthMap[$key]['value'] : 0;
}

$maxChartValue = max($chartValues);
if ($maxChartValue <= 0) {
    $maxChartValue = 1;
}

function paymentStatusBadgeClass($status)
{
    switch ($status) {
        case 'Paid':
            return 'text-green-700 bg-green-50';
        case 'Partial':
            return 'text-amber-700 bg-amber-50';
        case 'Pending':
            return 'text-blue-700 bg-blue-50';
        case 'Failed':
            return 'text-red-700 bg-red-50';
        case 'Refunded':
            return 'text-slate-700 bg-slate-100';
        default:
            return 'text-slate-700 bg-slate-100';
    }
}

function activityDotClass($status)
{
    switch ($status) {
        case 'Paid':
            return 'bg-green-500';
        case 'Partial':
            return 'bg-amber-500';
        case 'Pending':
            return 'bg-blue-500';
        case 'Failed':
            return 'bg-red-500';
        case 'Refunded':
            return 'bg-slate-400';
        default:
            return 'bg-slate-400';
    }
}

function buildPageUrl($pageNumber, $shopQuery, $search, $statusFilter, $methodFilter)
{
    $params = [
        'shop' => $shopQuery,
        'page' => $pageNumber
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($statusFilter !== 'All') {
        $params['status'] = $statusFilter;
    }
    if ($methodFilter !== 'All') {
        $params['method'] = $methodFilter;
    }

    return 'paymentsadmin.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Payment Management | <?php echo h($owner['shopName']); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface": "#f6f6f8",
                        "surface-variant": "#f1f5f9",
                        "background": "#f6f6f8",
                        "outline": "#e2e8f0",
                        "primary": "#1152d4",
                        "primary-container": "#eef2ff",
                        "error": "#ef4444",
                        "error-container": "#fee2e2",
                        "tertiary": "#f59e0b",
                        "tertiary-container": "#fef3c7",
                        "on-surface": "#0f172a",
                        "on-surface-variant": "#64748b"
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-background text-on-surface antialiased">
    <aside class="fixed inset-y-0 left-0 w-64 flex flex-col border-r border-slate-200 bg-white overflow-y-auto z-50">
        <div class="p-6 flex-1">
            <div class="flex items-center gap-3 mb-8">
                <div class="bg-primary rounded-lg p-2 text-white">
                    <span class="material-symbols-outlined">directions_car</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-none"><?php echo h($owner['shopName']); ?></h1>
                    <p class="text-xs text-slate-500 mt-1">Repair Management</p>
                </div>
            </div>
            <nav class="space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="dashboardadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="repairjobsadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">build</span>
                    Repair Jobs
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="vehicleadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">directions_car</span>
                    Vehicles
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="appointmentadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">event</span>
                    Appointments
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="reportsadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">description</span>
                    Reports
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="inventoryadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                    Inventory
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                    href="customeradmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                    Customers
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary font-medium"
                    href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                    Payments
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        href="settingsadmin.php?shop=<?php echo $shopQuery; ?>">
                        <span class="material-symbols-outlined text-[22px]">settings</span>
                        Settings
                    </a>
                </div>
            </nav>
        </div>
        <div class="mt-auto w-full p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-slate-500">person</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate"><?php echo h($owner['shopName']); ?></p>
                    <p class="text-xs text-slate-500 truncate">Shop Manager</p>
                </div>
                <form id="logoutForm" method="post" action="../logout/logout.php" class="inline">
                    <input type="hidden" name="action" value="confirm" />
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>" />
                    <button class="text-slate-400 hover:text-error transition-colors" type="submit">
                        <span class="material-symbols-outlined text-xl">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="ml-64 min-h-screen bg-background">
        <header
            class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md flex items-center justify-between px-8 h-16">
            <div class="flex items-center gap-6">
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Payments Management</h2>
                <form method="get" class="relative hidden lg:block">
                    <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                    <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">
                    <input type="hidden" name="method" value="<?php echo h($methodFilter); ?>">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="bg-surface-variant border-none rounded-lg pl-10 pr-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary/20"
                        placeholder="Search payments..." type="text" name="search" value="<?php echo h($search); ?>">
                </form>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-slate-500 hover:text-primary transition-all">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
                <div class="h-8 w-px bg-slate-200 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-bold text-on-surface"><?php echo h($owner['shopName']); ?></p>
                        <p class="text-[10px] text-slate-500 uppercase font-semibold">Slug: <?php echo h($loginSlug); ?>
                        </p>
                    </div>
                    <div
                        class="h-10 w-10 rounded-full border-2 border-primary/20 bg-slate-200 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-500">person</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <h2 class="text-3xl font-black text-on-surface tracking-tight">Payments & Invoices</h2>
                    <p class="text-on-surface-variant mt-1">Comprehensive overview of shop revenue and billing cycles.
                    </p>
                </div>
                <div class="flex space-x-3">
                    <button id="openAddPaymentModal" type="button"
                        class="flex items-center px-4 py-2 bg-primary text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition-all">
                        <span class="material-symbols-outlined text-sm mr-2">add</span>
                        Add Payment
                    </button>
                    <a href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>"
                        class="flex items-center px-4 py-2 bg-white border border-outline text-slate-600 text-sm font-bold rounded-lg hover:bg-surface transition-all">
                        <span class="material-symbols-outlined text-sm mr-2">refresh</span>
                        Refresh
                    </a>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
                <div class="mb-6 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $flashType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'; ?>">
                    <?php echo h($flashMessage); ?>
                </div>
            <?php endif; ?>

            <form method="get"
                class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
                <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Search</label>
                    <input type="text" name="search" value="<?php echo h($search); ?>"
                        class="rounded-lg border-slate-300 text-sm w-64" placeholder="Customer, ref, payment id">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
                    <select name="status" class="rounded-lg border-slate-300 text-sm">
                        <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Method</label>
                    <select name="method" class="rounded-lg border-slate-300 text-sm">
                        <?php foreach ($allowedMethods as $method): ?>
                            <option value="<?php echo h($method); ?>" <?php echo $methodFilter === $method ? 'selected' : ''; ?>><?php echo h($method); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="px-4 py-2 bg-primary text-white text-sm font-bold rounded-lg">Apply Filters</button>
                <a href="paymentsadmin.php?shop=<?php echo $shopQuery; ?>"
                    class="px-4 py-2 bg-slate-100 text-slate-700 text-sm font-bold rounded-lg">Reset</a>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-primary-container rounded-lg">
                            <span class="material-symbols-outlined text-primary"
                                style="font-variation-settings:'FILL' 1;">account_balance_wallet</span>
                        </div>
                    </div>
                    <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Revenue</p>
                    <h3 class="text-2xl font-bold text-on-surface mt-1">PHP
                        <?php echo number_format($totalRevenue, 2); ?></h3>
                </div>

                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-tertiary-container rounded-lg">
                            <span class="material-symbols-outlined text-tertiary"
                                style="font-variation-settings:'FILL' 1;">pending_actions</span>
                        </div>
                    </div>
                    <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Pending Invoices
                    </p>
                    <h3 class="text-2xl font-bold text-on-surface mt-1"><?php echo number_format($pendingInvoices); ?>
                    </h3>
                </div>

                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-blue-50 rounded-lg">
                            <span class="material-symbols-outlined text-primary"
                                style="font-variation-settings:'FILL' 1;">event_available</span>
                        </div>
                    </div>
                    <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Paid This Week</p>
                    <h3 class="text-2xl font-bold text-on-surface mt-1">PHP
                        <?php echo number_format($paidThisWeek, 2); ?></h3>
                </div>

                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-error-container rounded-lg">
                            <span class="material-symbols-outlined text-error"
                                style="font-variation-settings:'FILL' 1;">warning</span>
                        </div>
                    </div>
                    <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Failed Payments
                    </p>
                    <h3 class="text-2xl font-bold text-on-surface mt-1"><?php echo number_format($failedCount); ?></h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6 overflow-hidden">
                    <div class="flex items-center justify-between mb-8">
                        <h4 class="text-lg font-bold text-on-surface">Revenue Over Time</h4>
                        <span class="text-xs font-bold bg-surface-variant px-3 py-1 rounded-lg">Last 6 Months</span>
                    </div>

                    <div class="relative h-64 w-full mt-4 flex items-end justify-between space-x-2">
                        <?php foreach ($chartValues as $index => $value): ?>
                            <?php
                            $heightPercent = ($value / $maxChartValue) * 100;
                            $heightPercent = max(8, $heightPercent);
                            ?>
                            <div class="flex-1 flex flex-col justify-end items-center h-full">
                                <div class="w-full bg-slate-100 rounded-t-lg relative overflow-hidden"
                                    style="height: <?php echo number_format($heightPercent, 2); ?>%;">
                                    <div class="absolute bottom-0 w-full bg-primary/70 hover:bg-primary transition-all"
                                        style="height: 100%;"></div>
                                </div>
                                <span
                                    class="mt-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"><?php echo h($chartLabels[$index]); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col">
                    <div class="p-6 border-b border-slate-100">
                        <h4 class="text-lg font-bold text-on-surface">Recent Billing Activity</h4>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <?php if (count($recentActivities) === 0): ?>
                            <p class="text-sm text-slate-500">No recent payment activity found.</p>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="flex space-x-4">
                                    <div
                                        class="mt-1 w-2 h-2 rounded-full <?php echo activityDotClass($activity['paymentStatus']); ?> shrink-0">
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-on-surface leading-none">
                                            <?php echo h($activity['paymentStatus']); ?> Payment
                                        </p>
                                        <p class="text-xs text-on-surface-variant mt-1">
                                            Payment #<?php echo h($activity['payment_id']); ?> -
                                            <?php echo h($activity['customer_name']); ?>
                                        </p>
                                        <p class="text-xs text-on-surface-variant mt-1">
                                            Amount Paid: PHP <?php echo number_format((float) $activity['amountPaid'], 2); ?>
                                        </p>
                                        <span
                                            class="text-[10px] font-semibold text-slate-400 mt-2 block uppercase tracking-tight">
                                            <?php echo h(date('M d, Y h:i A', strtotime((string) $activity['paymentDate']))); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h4 class="text-lg font-bold text-on-surface">Recent Transactions</h4>
                    <div class="text-xs text-slate-500 font-medium">
                        <?php echo number_format($totalRows); ?> total records
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface">
                            <tr>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Payment ID</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Customer</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Repair Job</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-right">
                                    Amount</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-right">
                                    Paid</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-right">
                                    Balance</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Method</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Status</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Date</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Reference</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-right">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($payments) === 0): ?>
                                <tr>
                                    <td colspan="11" class="px-6 py-10 text-center text-sm text-slate-500">
                                        No payments found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <?php
                                    $reference = trim((string) ($payment['referenceNumber'] ?? ''));
                                    $gcashRef = trim((string) ($payment['gcashReferenceNumber'] ?? ''));
                                    $displayReference = $gcashRef !== '' ? $gcashRef : ($reference !== '' ? $reference : 'N/A');
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-bold text-on-surface">
                                            #PAY-<?php echo h($payment['payment_id']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-on-surface">
                                                <?php echo h($payment['customer_name']); ?></div>
                                            <div class="text-xs text-slate-500">User ID: <?php echo h($payment['user_id']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-on-surface-variant">
                                            #JOB-<?php echo h($payment['repair_job_id'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-on-surface text-right">
                                            PHP <?php echo number_format((float) $payment['paymentAmount'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-green-700 text-right">
                                            PHP <?php echo number_format((float) $payment['amountPaid'], 2); ?>
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm font-bold text-right <?php echo ((float) $payment['balance'] > 0) ? 'text-amber-700' : 'text-slate-700'; ?>">
                                            PHP <?php echo number_format((float) $payment['balance'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-on-surface-variant">
                                            <?php echo h($payment['paymentMethod']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span
                                                class="px-3 py-1 text-[11px] font-bold rounded-full <?php echo paymentStatusBadgeClass($payment['paymentStatus']); ?>">
                                                <?php echo h($payment['paymentStatus']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-on-surface-variant">
                                            <?php echo h(date('M d, Y', strtotime((string) $payment['paymentDate']))); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-on-surface-variant max-w-[180px] truncate"
                                            title="<?php echo h($displayReference); ?>">
                                            <?php echo h($displayReference); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <a href="paymentsadmin.php?shop=<?php echo h($shopQuery); ?>&print_receipt=<?php echo (int) $payment['payment_id']; ?>"
                                                    class="px-3 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">Receipt</a>
                                                <button
                                                    type="button"
                                                    class="openStatusModal px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 border border-slate-200 rounded-lg hover:bg-slate-200"
                                                    data-payment-id="<?php echo (int) $payment['payment_id']; ?>"
                                                    data-current-status="<?php echo h($payment['paymentStatus']); ?>">Edit Status</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-on-surface-variant font-medium">
                        Showing <?php echo $totalRows > 0 ? ($offset + 1) : 0; ?> to
                        <?php echo min($offset + $rowsPerPage, $totalRows); ?> of <?php echo $totalRows; ?> transactions
                    </p>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant transition-colors"
                                href="<?php echo h(buildPageUrl($page - 1, $loginSlug, $search, $statusFilter, $methodFilter)); ?>">
                                <span class="material-symbols-outlined text-sm">chevron_left</span>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary text-white text-xs font-bold"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant text-xs font-bold transition-colors"
                                    href="<?php echo h(buildPageUrl($i, $loginSlug, $search, $statusFilter, $methodFilter)); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-variant text-on-surface-variant transition-colors"
                                href="<?php echo h(buildPageUrl($page + 1, $loginSlug, $search, $statusFilter, $methodFilter)); ?>">
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="addPaymentModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-3xl rounded-xl bg-white border border-slate-200 shadow-xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                        <h3 class="text-lg font-bold text-on-surface">Add Payment</h3>
                        <button type="button" class="closeAddPaymentModal text-slate-500 hover:text-slate-700"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <form method="post" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4" id="addPaymentForm">
                        <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Customer *</label>
                            <select name="user_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <option value="">Select customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo (int) $customer['user_id']; ?>" <?php echo $addPaymentForm['user_id'] === (int) $customer['user_id'] ? 'selected' : ''; ?>><?php echo h($customer['fullName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($hasRepairJobIdColumn): ?>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Repair Job ID</label>
                                <input type="number" min="0" name="repair_job_id" value="<?php echo h($addPaymentForm['repair_job_id']); ?>" class="w-full rounded-lg border-slate-300 text-sm">
                            </div>
                        <?php endif; ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Payment Amount *</label>
                            <input type="number" step="0.01" min="0.01" name="paymentAmount" value="<?php echo h($addPaymentForm['paymentAmount']); ?>" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Amount Paid *</label>
                            <input type="number" step="0.01" min="0" name="amountPaid" value="<?php echo h($addPaymentForm['amountPaid']); ?>" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Payment Method *</label>
                            <select name="paymentMethod" id="paymentMethodField" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <?php foreach ($paymentMethodChoices as $method): ?>
                                    <option value="<?php echo h($method); ?>" <?php echo $addPaymentForm['paymentMethod'] === $method ? 'selected' : ''; ?>><?php echo h($method); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Payment Status *</label>
                            <select name="paymentStatus" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <?php foreach ($paymentStatusChoices as $status): ?>
                                    <option value="<?php echo h($status); ?>" <?php echo $addPaymentForm['paymentStatus'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Payment Date *</label>
                            <input type="date" name="paymentDate" value="<?php echo h($addPaymentForm['paymentDate']); ?>" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Reference Number</label>
                            <input type="text" value="<?php echo h($nextReferencePreview); ?>" class="w-full rounded-lg border-slate-300 text-sm bg-slate-100 text-slate-600 font-semibold" readonly>
                            <p class="text-[11px] text-slate-500 mt-1">Auto-generated when payment is saved.</p>
                        </div>
                        <div id="gcashReferenceWrap">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">GCash Reference</label>
                            <input type="text" name="gcashReferenceNumber" id="gcashReferenceField" value="<?php echo h($addPaymentForm['gcashReferenceNumber']); ?>" class="w-full rounded-lg border-slate-300 text-sm" placeholder="10-20 alphanumeric">
                            <p class="text-[11px] text-slate-500 mt-1">Required for GCash payments.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Remarks</label>
                            <textarea name="remarks" rows="3" class="w-full rounded-lg border-slate-300 text-sm"><?php echo h($addPaymentForm['remarks']); ?></textarea>
                        </div>
                        <div class="md:col-span-2 flex justify-end gap-2 pt-2">
                            <button type="button" class="closeAddPaymentModal px-4 py-2 text-sm font-bold bg-slate-100 text-slate-700 rounded-lg">Cancel</button>
                            <button type="submit" name="add_payment_submit" value="1" class="px-4 py-2 text-sm font-bold bg-primary text-white rounded-lg">Save Payment</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="statusModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl bg-white border border-slate-200 shadow-xl">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                        <h3 class="text-lg font-bold text-on-surface">Edit Payment Status</h3>
                        <button type="button" id="closeStatusModal" class="text-slate-500 hover:text-slate-700"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <form method="post" class="p-6 space-y-4">
                        <input type="hidden" name="shop" value="<?php echo h($loginSlug); ?>">
                        <input type="hidden" name="payment_id" id="statusPaymentId" value="0">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
                            <select name="paymentStatus" id="statusValueField" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <?php foreach ($paymentStatusChoices as $status): ?>
                                    <option value="<?php echo h($status); ?>"><?php echo h($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" id="cancelStatusModal" class="px-4 py-2 text-sm font-bold bg-slate-100 text-slate-700 rounded-lg">Cancel</button>
                            <button type="submit" name="update_payment_status_submit" value="1" class="px-4 py-2 text-sm font-bold bg-primary text-white rounded-lg">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>

<script>
    const addPaymentModal = document.getElementById('addPaymentModal');
    const openAddPaymentModal = document.getElementById('openAddPaymentModal');
    const closeAddPaymentButtons = document.querySelectorAll('.closeAddPaymentModal');
    const paymentMethodField = document.getElementById('paymentMethodField');
    const gcashReferenceWrap = document.getElementById('gcashReferenceWrap');
    const gcashReferenceField = document.getElementById('gcashReferenceField');
    const addPaymentForm = document.getElementById('addPaymentForm');

    const statusModal = document.getElementById('statusModal');
    const statusPaymentId = document.getElementById('statusPaymentId');
    const statusValueField = document.getElementById('statusValueField');
    const closeStatusModal = document.getElementById('closeStatusModal');
    const cancelStatusModal = document.getElementById('cancelStatusModal');
    const openStatusButtons = document.querySelectorAll('.openStatusModal');

    function toggleGcashField() {
        if (!paymentMethodField || !gcashReferenceWrap || !gcashReferenceField) {
            return;
        }
        const isGcash = paymentMethodField.value === 'GCash';
        gcashReferenceWrap.style.display = isGcash ? '' : 'none';
        gcashReferenceField.required = isGcash;
    }

    if (openAddPaymentModal && addPaymentModal) {
        openAddPaymentModal.addEventListener('click', () => {
            addPaymentModal.classList.remove('hidden');
            addPaymentModal.classList.add('flex');
        });
    }

    closeAddPaymentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            addPaymentModal.classList.add('hidden');
            addPaymentModal.classList.remove('flex');
        });
    });

    if (paymentMethodField) {
        paymentMethodField.addEventListener('change', toggleGcashField);
        toggleGcashField();
    }

    if (addPaymentForm) {
        addPaymentForm.addEventListener('submit', (event) => {
            if (paymentMethodField && paymentMethodField.value === 'GCash') {
                const ref = (gcashReferenceField.value || '').trim();
                if (!/^[A-Za-z0-9]{10,20}$/.test(ref)) {
                    event.preventDefault();
                    alert('GCash reference must be 10-20 alphanumeric characters.');
                    gcashReferenceField.focus();
                }
            }
        });
    }

    openStatusButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!statusModal || !statusPaymentId || !statusValueField) {
                return;
            }
            statusPaymentId.value = button.dataset.paymentId || '0';
            statusValueField.value = button.dataset.currentStatus || 'Pending';
            statusModal.classList.remove('hidden');
            statusModal.classList.add('flex');
        });
    });

    function hideStatusModal() {
        if (!statusModal) {
            return;
        }
        statusModal.classList.add('hidden');
        statusModal.classList.remove('flex');
    }

    if (closeStatusModal) {
        closeStatusModal.addEventListener('click', hideStatusModal);
    }
    if (cancelStatusModal) {
        cancelStatusModal.addEventListener('click', hideStatusModal);
    }

    <?php if ($showAddPaymentModal): ?>
    if (addPaymentModal) {
        addPaymentModal.classList.remove('hidden');
        addPaymentModal.classList.add('flex');
    }
    <?php endif; ?>
</script>

</html>