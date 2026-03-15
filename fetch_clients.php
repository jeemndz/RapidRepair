<?php
include "db.php";

$clients = $conn->query("SELECT * FROM client_information ORDER BY client_id DESC");

if($clients->num_rows > 0){
    while($row = $clients->fetch_assoc()){
        echo "<tr>
                <td>{$row['client_id']}</td>
                <td>{$row['firstName']} {$row['lastName']}</td>
                <td>{$row['contactNumber']}</td>
                <td>{$row['email']}</td>
                <td class='actions-col'>
                    <i class='fa fa-search'></i>
                    <i class='fa fa-ban'></i>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5'>No clients found.</td></tr>";
}
?>
