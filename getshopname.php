<?php
header('Content-Type: application/json');

$tenantID = isset($_GET['tenantID']) ? intval($_GET['tenantID']) : 0;

if (!$tenantID) {
    echo json_encode(['success' => false, 'message' => 'Missing tenantID']);
    exit;
}

try {
    // Include your database connection
    include 'db.php';
    
    $query = "SELECT shopName, shopAddress FROM owners WHERE tenantID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $tenantID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'shopName' => $row['shopName'] ?? 'Elite Auto Downtown',
            'shopAddress' => $row['shopAddress'] ?? '2.4 miles away • Open until 7 PM'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tenant not found',
            'shopName' => 'Elite Auto Downtown'
        ]);
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>