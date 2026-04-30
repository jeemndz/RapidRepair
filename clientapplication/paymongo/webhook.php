<?php
header("Content-Type: application/json");

$payload = file_get_contents("php://input");

file_put_contents(
    __DIR__ . "/webhook_test.log",
    "[" . date("Y-m-d H:i:s") . "] " . $payload . PHP_EOL,
    FILE_APPEND
);

http_response_code(200);
echo json_encode(["status" => "received"]);
exit;