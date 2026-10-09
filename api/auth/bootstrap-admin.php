<?php

header("Content-Type: application/json");

$remoteAddress = $_SERVER["REMOTE_ADDR"] ?? "";
if (!in_array($remoteAddress, ["127.0.0.1", "::1"], true)) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Admin bootstrap is only available from this server."
    ]);
    exit;
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

require_once __DIR__ . "/../../config/Database.php";
require_once __DIR__ . "/../../classes/Auth.php";

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid JSON request body is required."
    ]);
    exit;
}

$email = $data["email"] ?? null;
$password = $data["password"] ?? null;
if (!is_string($email) || !is_string($password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required as strings."
    ]);
    exit;
}

$email = strtolower(trim($email));
if (
    filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || strlen($email) > 100
    || strlen($password) < 8
    || strlen($password) > 72
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Provide a valid email and a password of 8-72 characters."
    ]);
    exit;
}

try {
    $database = new Database();
    $auth = new Auth($database->connect());
    $userId = $auth->bootstrapFirstAdmin($email, $password);
} catch (PDOException $error) {
    if (
        $error->getCode() === "23000"
        && isset($error->errorInfo[1])
        && (int) $error->errorInfo[1] === 1062
    ) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "That email address is already registered."
        ]);
        exit;
    }

    error_log("Admin bootstrap failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to create the first admin."
    ]);
    exit;
} catch (Throwable $error) {
    error_log("Admin bootstrap failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to create the first admin."
    ]);
    exit;
}

if ($userId === null) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Admin bootstrap is closed because an admin already exists."
    ]);
    exit;
}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "First admin created. Admin bootstrap is now closed.",
    "user" => [
        "id" => $userId,
        "email" => $email,
        "role" => "admin"
    ]
]);
