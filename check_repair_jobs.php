<?php
require_once 'db.php';

$result = mysqli_query($conn, 'SELECT repair_job_id, job_order_no, job_status FROM repair_jobs LIMIT 10');

echo "Repair Jobs Sample:\n";
echo "===================\n";
while($row = mysqli_fetch_assoc($result)) {
    echo 'ID: ' . $row['repair_job_id'] . ' | Order No: ' . ($row['job_order_no'] ?: 'NULL/EMPTY') . ' | Status: ' . $row['job_status'] . "\n";
}
?>
