<?php
require_once "db.php";

$type = $_GET['type'] ?? 'pending';

/* ==========================
   JOIN PAYMENT (1 ROW / APPT)
========================== */
$paymentsJoin = "
    LEFT JOIN (
        SELECT appointment_id, MAX(paymentStatus) AS paymentStatus
        FROM payments
        GROUP BY appointment_id
    ) p ON p.appointment_id = a.appointment_id
";

/* ==========================
   MAIN QUERY WITH JOINS
========================== */
$select = "
    SELECT 
        a.*,

        -- client
        CONCAT(c.firstName, ' ', c.lastName) AS client_name,

        -- vehicle
        v.vehicleBrand,
        v.vehicleModel,
        v.plateNumber,

        -- computed status
        CASE
            WHEN p.paymentStatus = 'Paid' THEN 'Completed'
            WHEN a.status IS NULL OR a.status = '' THEN 'Pending'
            ELSE a.status
        END AS display_status

    FROM appointment a
    LEFT JOIN client_information c ON c.client_id = a.client_id
    LEFT JOIN vehicleinfo v ON v.vehicle_id = a.vehicle_id
    $paymentsJoin
";

/* ==========================
   FILTER BY TABLE TYPE
========================== */
if ($type === 'pending') {

    $sql = $select . "
        WHERE
            (a.status = 'Pending' OR a.status IS NULL OR a.status = '')
            AND (p.paymentStatus IS NULL OR p.paymentStatus <> 'Paid')
        ORDER BY a.appointmentDate ASC, a.appointmentTime ASC
    ";

} elseif ($type === 'ongoing') {

    $sql = $select . "
        WHERE
            a.status IN ('Approved','Scheduled','Ongoing')
            AND (p.paymentStatus IS NULL OR p.paymentStatus <> 'Paid')
        ORDER BY a.appointmentDate ASC, a.appointmentTime ASC
    ";

} else { // done

    $sql = $select . "
        WHERE
            p.paymentStatus = 'Paid'
            OR a.status IN ('Completed','Cancelled')
        ORDER BY a.appointmentDate DESC, a.appointmentTime DESC
    ";
}

$res = mysqli_query($conn, $sql);

if (!$res) {
    echo "<tr><td colspan='8'>Query error.</td></tr>";
    exit;
}

if (mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {

        $status = $row['display_status'] ?? 'Pending';

        // badge class (lowercase)
        $badgeClass = "status-" . strtolower(trim($status));

        // dataset-safe values for edit modal
        $ds_service  = htmlspecialchars($row['serviceType'] ?? '', ENT_QUOTES);
        $ds_mechanic = htmlspecialchars($row['mechanicAssigned'] ?? '', ENT_QUOTES);
        $ds_notes    = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES);
        $ds_status   = htmlspecialchars($row['status'] ?? 'Pending', ENT_QUOTES); // raw DB status for edit
        $ds_date     = htmlspecialchars($row['appointmentDate'] ?? '', ENT_QUOTES);
        $ds_time     = htmlspecialchars($row['appointmentTime'] ?? '', ENT_QUOTES);

        // readable vehicle label
        $vehicleLabel = trim(
            ($row['vehicleBrand'] ?? '') . " " .
            ($row['vehicleModel'] ?? '') .
            (!empty($row['plateNumber']) ? " ({$row['plateNumber']})" : "")
        );

        echo "<tr>
            <td>{$row['appointment_id']}</td>
            <td>{$row['appointmentDate']} {$row['appointmentTime']}</td>
            <td>" . htmlspecialchars($row['client_name'] ?? 'Unknown') . "</td>
            <td>" . htmlspecialchars($vehicleLabel ?: 'Unknown') . "</td>
            <td>" . htmlspecialchars($row['serviceType'] ?? '') . "</td>
            <td>" . (!empty($row['mechanicAssigned']) ? htmlspecialchars($row['mechanicAssigned']) : '-') . "</td>

            <td>
                <span class='status-badge {$badgeClass}'>" . htmlspecialchars($status) . "</span>
            </td>

            <td class='actions-col'>
                <a href='view_booking.php?id={$row['appointment_id']}' title='View'>
                    <i class='fa-solid fa-eye'></i>
                </a>

                <button type='button'
                    class='icon-btn edit-booking-btn'
                    title='Edit'
                    data-id='{$row['appointment_id']}'
                    data-status='{$ds_status}'
                    data-service='{$ds_service}'
                    data-date='{$ds_date}'
                    data-time='{$ds_time}'
                    data-mechanic='{$ds_mechanic}'
                    data-notes='{$ds_notes}'
                >
                    <i class='fa-solid fa-pen-to-square'></i>
                </button>";

        if ($type === 'pending') {
            echo "<a href='delete_booking.php?id={$row['appointment_id']}'
                    onclick=\"return confirm('Are you sure you want to delete this booking?');\"
                    title='Delete'>
                    <i class='fa-solid fa-trash'></i>
                  </a>";
        }

        echo "</td></tr>";
    }
} else {
    echo "<tr><td colspan='8'>No bookings found.</td></tr>";
}
?>
