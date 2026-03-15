<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once "db.php";

// ---- CONFIG ----
$dbHost = "127.0.0.1";
$dbUser = "root";
$dbPass = "";                // set if you have password
$dbName = "rapid_repair_db"; // <-- change this
$maxSize = 50 * 1024 * 1024; // 50MB

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    die("No file uploaded or upload error.");
}

$tmpPath = $_FILES['backup_file']['tmp_name'];
$origName = $_FILES['backup_file']['name'];

if (pathinfo($origName, PATHINFO_EXTENSION) !== "sql") {
    die("Invalid file type. Please upload a .sql file.");
}

if ($_FILES['backup_file']['size'] > $maxSize) {
    die("File too large. Max 50MB.");
}

// Optional: basic check to prevent random uploads
$head = file_get_contents($tmpPath, false, null, 0, 2048);
if ($head === false || stripos($head, "CREATE") === false) {
    // not perfect, but helps
    die("This doesn't look like a valid SQL backup.");
}

// Restore using mysql CLI
$mysql = "mysql";
$mysqlBin = getenv("MYSQL_PATH");
if ($mysqlBin) $mysql = $mysqlBin;

$passPart = $dbPass !== "" ? "-p" . escapeshellarg($dbPass) : "";
$command = escapeshellcmd($mysql) .
    " --host=" . escapeshellarg($dbHost) .
    " --user=" . escapeshellarg($dbUser) .
    " $passPart " .
    escapeshellarg($dbName) .
    " < " . escapeshellarg($tmpPath);

$output = [];
$returnVar = 0;
exec($command . " 2>&1", $output, $returnVar);

if ($returnVar !== 0) {
    die("Restore failed.<br><pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>");
}

header("Location: backup_restore.php?restored=1");
exit;
