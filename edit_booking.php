<?php
require_once "db.php";

$id = intval($_GET['id']);

// Update booking status
if (isset($_POST['update'])) {
    $status = $_POST['status'];

    $sql = "UPDATE appointment SET status = ? WHERE appointment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();

    header("Location: bookings.php");
    exit;
}

// Fetch booking details
$result = $conn->query("SELECT * FROM appointment WHERE appointment_id = $id");
$booking = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Booking Status | Rapid Repair</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    /* Modal overlay */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    /* Modal box */
    .modal-box {
        background: #fff;
        padding: 25px 30px;
        border-radius: 10px;
        width: 400px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        text-align: center;
    }

    .modal-box h2 {
        margin-bottom: 20px;
        color: #254a91;
    }

    .modal-box label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        text-align: left;
    }

    .modal-box select {
        width: 100%;
        padding: 8px 10px;
        margin-bottom: 20px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: 16px;
    }

    .modal-actions {
        display: flex;
        justify-content: space-between;
    }

    .modal-actions button,
    .modal-actions a {
        padding: 8px 18px;
        border-radius: 18px;
        border: none;
        cursor: pointer;
        font-weight: bold;
        text-decoration: none;
    }

    .btn-update {
        background-color: #18b718;
        color: #fff;
    }
    .btn-update:hover { background-color: #13a113; }

    .btn-cancel {
        background-color: #ccc;
        color: #333;
    }
    .btn-cancel:hover { background-color: #999; }

</style>
</head>
<body>

<div class="modal-overlay">
    <div class="modal-box">
        <h2>Edit Booking Status</h2>

        <form method="POST">
            <label for="status">Status</label>
            <select name="status" id="status" required>
                <option <?= $booking['status']=="Pending"?"selected":"" ?>>Pending</option>
                <option <?= $booking['status']=="Completed"?"selected":"" ?>>Completed</option>
                <option <?= $booking['status']=="Cancelled"?"selected":"" ?>>Cancelled</option>
                <option <?= $booking['status']=="Apporoved"?"selected":"" ?>>Apporoved</option>
            </select>

            <div class="modal-actions">
                <button type="submit" name="update" class="btn-update"><i class="fa fa-check"></i> Update</button>
                <a href="bookings.php" class="btn-cancel"><i class="fa fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
