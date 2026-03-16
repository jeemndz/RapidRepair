<?php

header("Content-Type: application/json");
include "db.php";

$email = $_POST['email'];
$password = $_POST['password'];

$query = "SELECT * FROM users WHERE email=?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s",$email);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){

    if(password_verify($password,$row['password'])){
        echo json_encode([
            "status"=>"success",
            "user_id"=>$row['user_id'],
            "name"=>$row['name']
        ]);
    }else{
        echo json_encode(["status"=>"error","message"=>"Invalid password"]);
    }

}else{
    echo json_encode(["status"=>"error","message"=>"User not found"]);
}

?>