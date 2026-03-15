<?php
session_start();
require_once "db.php";

/* =========================
   AUTH: allow staff, admin, client
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));
$allowedRoles = ['admin', 'staff', 'client'];

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    exit("Access denied.");
}

/* =========================
   GET CLIENT ID
========================= */
$client_id = (int)($_GET['id'] ?? 0);
if ($client_id <= 0) {
    exit("Client ID is missing or invalid.");
}

/* =========================
   CLIENT SAFETY RULE:
   client can only view their own client record
========================= */
if ($role === 'client') {
    $user_id = (int)$_SESSION['user_id'];

    $own = $conn->prepare("SELECT client_id FROM client_information WHERE user_id = ? LIMIT 1");
    $own->bind_param("i", $user_id);
    $own->execute();
    $ownRes = $own->get_result()->fetch_assoc();
    $own->close();

    $myClientId = (int)($ownRes['client_id'] ?? 0);

    if ($myClientId <= 0) {
        http_response_code(403);
        exit("No client record linked to your account.");
    }

    if ($client_id !== $myClientId) {
        http_response_code(403);
        exit("Access denied. You can only view your own profile.");
    }
}

/* =========================
   BACK LINK: use RETURN param (prevents panel switching)
   Example link from list page:
   view_client.php?id=5&return=create_client.php
========================= */
$return = trim($_GET['return'] ?? '');

// allow only safe local pages (no http://, no //, no javascript:)
$backLink = "javascript:history.back()";
if ($return !== '' && preg_match('/^[a-zA-Z0-9_\-\/\.]+\.php(\?.*)?$/', $return)) {
    $backLink = $return;
}

/* =========================
   FETCH CLIENT
========================= */
$stmt = $conn->prepare("SELECT * FROM client_information WHERE client_id = ? LIMIT 1");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    exit("Client not found.");
}

$client = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Client | Rapid Repair</title>
<link rel="stylesheet" href="pagelayout.css">
<link rel="stylesheet" href="client.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    .view-wrap{ max-width:780px; margin:30px auto; padding:0 12px; }
    .view-card{ background:#fff; border:1px solid #eef1f6; border-radius:16px; box-shadow:0 18px 30px rgba(0,0,0,.08); overflow:hidden; }
    .view-head{ background:linear-gradient(135deg,#071f4a,#254a91); color:#fff; padding:18px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .view-head h2{ margin:0; font-size:18px; font-weight:900; letter-spacing:.2px; display:flex; align-items:center; gap:10px; }
    .view-body{ padding:18px 20px 16px; }
    .kv{ display:grid; grid-template-columns:180px 1fr; gap:10px 14px; padding:10px 0; border-bottom:1px solid #eef1f6; align-items:start; }
    .kv:last-child{ border-bottom:none; }
    .k{ color:#6b7280; font-weight:800; font-size:13px; }
    .v{ color:#111827; font-weight:700; font-size:14px; word-break:break-word; }
    .pill{ display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; border:1px solid #dbe6ff; background:#f3f7ff; color:#1e40af; font-weight:900; font-size:12px; white-space:nowrap; }
    .view-actions{ padding:16px 20px 18px; display:flex; justify-content:flex-start; gap:10px; background:#fafcff; border-top:1px solid #eef1f6; }
    .back-btn{ display:inline-flex; align-items:center; gap:10px; padding:10px 16px; border-radius:999px; text-decoration:none; font-weight:900; background:linear-gradient(135deg,#254a91,#071f4a); color:#fff; box-shadow:0 10px 18px rgba(0,0,0,.12); transition:opacity .12s ease, transform .12s ease; }
    .back-btn:hover{ opacity:.92; }
    .back-btn:active{ transform:translateY(1px); }
    @media (max-width:600px){ .kv{ grid-template-columns:1fr; } .k{ font-size:12px; } }
</style>
</head>
<body>

<div class="view-wrap">
    <div class="view-card">

        <div class="view-head">
            <h2><i class="fa-solid fa-user"></i> Client Details</h2>
            <span class="pill"><i class="fa-solid fa-id-card"></i> ID: <?= (int)$client['client_id'] ?></span>
        </div>

        <div class="view-body">
            <div class="kv">
                <div class="k">Full Name</div>
                <div class="v"><?= htmlspecialchars(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? '')) ?></div>
            </div>

            <div class="kv">
                <div class="k">Contact Number</div>
                <div class="v"><?= htmlspecialchars($client['contactNumber'] ?? '-') ?></div>
            </div>

            <div class="kv">
                <div class="k">Email</div>
                <div class="v"><?= htmlspecialchars($client['email'] ?? '-') ?></div>
            </div>

            <div class="kv">
                <div class="k">Address</div>
                <div class="v"><?= nl2br(htmlspecialchars($client['address'] ?? '-')) ?></div>
            </div>

            <div class="kv">
                <div class="k">Notes</div>
                <div class="v"><?= nl2br(htmlspecialchars($client['notes'] ?? '-')) ?></div>
            </div>
        </div>

        <div class="view-actions">
            <a href="<?= htmlspecialchars($backLink) ?>" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>

    </div>
</div>

</body>
</html>
