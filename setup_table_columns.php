<?php
include 'db.php';

$sql = "ALTER TABLE document_extractions ADD COLUMN IF NOT EXISTS (
    shop_name VARCHAR(255) NULL,
    home_address TEXT NULL,
    business_address TEXT NULL,
    or_number VARCHAR(100) NULL,
    tin_number VARCHAR(20) NULL,
    branch_code VARCHAR(20) NULL,
    tin_issuance_date DATE NULL,
    id_number VARCHAR(50) NULL
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Table columns added/verified successfully!\n\n";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "\n";
}

// Show table structure
echo "Current document_extractions table structure:\n";
$result = mysqli_query($conn, 'DESCRIBE document_extractions');
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Table does not exist or error: " . mysqli_error($conn) . "\n";
}
?>
