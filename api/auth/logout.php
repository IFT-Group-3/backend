<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../classes/Auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

Auth::logout();

echo json_encode([
    "success" => true,
    "message" => "Logout successful."
]);
