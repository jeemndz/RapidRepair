<?php
session_start();

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

/* ======================
   DATABASE SETTINGS
====================== */
$dbHost = "127.0.0.1";
$dbUser = "root";
$dbPass = ""; // set if you have one
$dbName = "rapidrepair"; // 🔴 CHANGE THIS

/* ======================
   BACKUP SETTINGS
====================== */
$backupDir = __DIR__ . "/backups";
$mysqlDumpPath = "C:\\xampp\\mysql\\bin\\mysqldump.exe"; // ✅ XAMPP WINDOWS

// Create backup folder if not exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Backup filename
$filename = "backup_" . $dbName . "_" . date("Y-m-d_H-i-s") . ".sql";
$filepath = $backupDir . "/" . $filename;

// Build command
$command = "\"$mysqlDumpPath\" -h $dbHost -u $dbUser";
if ($dbPass !== "") {
    $command .= " -p$dbPass";
}
$command .= " $dbName > \"$filepath\"";

// Execute backup
exec($command, $output, $result);

// Validate backup
if ($result !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
    die("❌ Backup failed. Please check MySQL path or permissions.");
}

// Force file download
header("Content-Type: application/sql");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Length: " . filesize($filepath));

readfile($filepath);
exit;
