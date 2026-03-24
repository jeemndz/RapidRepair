<?php
// Backward-compatibility bridge: old endpoint now delegates to the unified
// save/load logic inside logincustom.php.
if (!isset($_GET['action'])) {
    $_GET['action'] = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'save' : 'load';
}

require __DIR__ . '/logincustom.php';
?>
