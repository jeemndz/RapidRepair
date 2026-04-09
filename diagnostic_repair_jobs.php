<?php
require_once 'db.php';

echo "=== REPAIR JOBS DIAGNOSTIC ===\n\n";

// Get total repair jobs
$totalResult = mysqli_query($conn, 'SELECT COUNT(*) as total FROM repair_jobs');
$totalRow = mysqli_fetch_assoc($totalResult);
echo "Total Repair Jobs: " . $totalRow['total'] . "\n\n";

// Check jobs without services
echo "--- Jobs WITHOUT services linked ---\n";
$noServicesResult = mysqli_query($conn, 
    'SELECT DISTINCT rj.repair_job_id, rj.job_order_no, rj.job_status 
     FROM repair_jobs rj
     LEFT JOIN repair_job_services rjs ON rj.repair_job_id = rjs.repair_job_id
     WHERE rjs.repair_job_service_id IS NULL
     LIMIT 10'
);

$noServicesCount = 0;
while($row = mysqli_fetch_assoc($noServicesResult)) {
    $noServicesCount++;
    echo "Job ID: {$row['repair_job_id']}, Order: {$row['job_order_no']}, Status: {$row['job_status']}\n";
}
echo "Total: $noServicesCount\n\n";

// Check jobs with services but missing service records
echo "--- Jobs with service_id that DON'T exist in services table ---\n";
$missingServicesResult = mysqli_query($conn, 
    'SELECT DISTINCT rj.repair_job_id, rj.job_order_no, rjs.service_id
     FROM repair_jobs rj
     JOIN repair_job_services rjs ON rj.repair_job_id = rjs.repair_job_id
     LEFT JOIN services s ON rjs.service_id = s.service_id
     WHERE s.service_id IS NULL
     LIMIT 10'
);

$missingServicesCount = 0;
while($row = mysqli_fetch_assoc($missingServicesResult)) {
    $missingServicesCount++;
    echo "Job ID: {$row['repair_job_id']}, Order: {$row['job_order_no']}, Service ID: {$row['service_id']}\n";
}
echo "Total: $missingServicesCount\n\n";

// Check jobs with NULL grand_total
echo "--- Jobs with NULL or 0 grand_total ---\n";
$nullTotalResult = mysqli_query($conn,
    'SELECT repair_job_id, job_order_no, grand_total, labor_total, parts_total
     FROM repair_jobs
     WHERE grand_total IS NULL OR grand_total = 0
     LIMIT 5'
);

while($row = mysqli_fetch_assoc($nullTotalResult)) {
    echo "Job ID: {$row['repair_job_id']}, Order: {$row['job_order_no']}, Grand: {$row['grand_total']}, Labor: {$row['labor_total']}, Parts: {$row['parts_total']}\n";
}

echo "\nDone!\n";
?>
