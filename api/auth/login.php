<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../config/Database.php";
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

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid JSON request body is required."
    ]);
    exit;
}

$email = $data["email"] ?? "";
$password = $data["password"] ?? "";

if (
    !is_string($email)
    || trim($email) === ""
    || !is_string($password)
    || $password === ""
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

$email = trim($email);

$database = new Database();
$db = $database->connect();

$auth = new Auth($db);

$result = $auth->login($email, $password);

if (!$result["success"]) {
    http_response_code(401);
}

echo json_encode($result);