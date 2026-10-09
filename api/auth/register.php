<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../config/Database.php";
require_once __DIR__ . "/../../classes/Student.php";

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

$email = $data["email"] ?? null;
$password = $data["password"] ?? null;
$matricNo = $data["matric_no"] ?? null;
$name = $data["name"] ?? null;
$department = $data["department"] ?? null;
$levelValue = $data["level"] ?? null;

if (
    !is_string($email)
    || !is_string($password)
    || !is_string($matricNo)
    || !is_string($name)
    || ($department !== null && !is_string($department))
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email, password, matric number, and name are required as strings."
    ]);
    exit;
}

$email = strtolower(trim($email));
$matricNo = trim($matricNo);
$name = trim($name);
$department = $department === null ? null : trim($department);

if (
    filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || strlen($email) > 100
    || strlen($password) < 8
    || strlen($password) > 72
    || $matricNo === ""
    || strlen($matricNo) > 30
    || $name === ""
    || strlen($name) > 100
    || ($department !== null && strlen($department) > 100)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide valid registration details. Passwords must be 8-72 characters."
    ]);
    exit;
}

$level = null;
if ($levelValue !== null && $levelValue !== "") {
    $validatedLevel = filter_var($levelValue, FILTER_VALIDATE_INT);
    if (
        $validatedLevel === false
        || $validatedLevel < 1
        || $validatedLevel > 2147483647
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Level must be a positive integer."
        ]);
        exit;
    }

    $level = $validatedLevel;
}

try {
    $database = new Database();
    $student = new Student($database->connect());
    $userId = $student->register(
        $email,
        $password,
        $matricNo,
        $name,
        $department === "" ? null : $department,
        $level
    );
} catch (PDOException $error) {
    if (
        $error->getCode() === "23000"
        && isset($error->errorInfo[1])
        && (int) $error->errorInfo[1] === 1062
    ) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "That email or matric number is already registered."
        ]);
        exit;
    }

    error_log("Student registration failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to register student."
    ]);
    exit;
} catch (Throwable $error) {
    error_log("Student registration failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to register student."
    ]);
    exit;
}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Student registered successfully.",
    "user" => [
        "id" => $userId,
        "email" => $email,
        "role" => "student"
    ]
]);
