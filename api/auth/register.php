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
        "message" => "A valid JSON request body is required.",
        "json_error" => json_last_error_msg()
    ]);
    exit;
}

$email = $data["email"] ?? null;
$password = $data["password"] ?? null;
$matricNumber = $data["matric_number"] ?? null;
$firstName = $data["first_name"] ?? null;
$lastName = $data["last_name"] ?? null;
$phone = $data["phone"] ?? null;
$department = $data["department"] ?? null;
$levelValue = $data["level"] ?? null;
$gender = $data["gender"] ?? null;

if (
    !is_string($email)
    || !is_string($password)
    || !is_string($matricNumber)
    || !is_string($firstName)
    || !is_string($lastName)
    || !is_string($department)
    || !is_string($gender)
    || ($phone !== null && !is_string($phone))
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email, password, matric number, first name, last name, department, level, and gender are required."
    ]);
    exit;
}

$email = strtolower(trim($email));
$matricNumber = trim($matricNumber);
$firstName = trim($firstName);
$lastName = trim($lastName);
$phone = $phone === null ? null : trim($phone);
$department = trim($department);

if (
    filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || strlen($email) > 100
    || strlen($password) < 8
    || strlen($password) > 72
    || $matricNumber === ""
    || strlen($matricNumber) > 30
    || $firstName === ""
    || strlen($firstName) > 50
    || $lastName === ""
    || strlen($lastName) > 50
    || $department === ""
    || strlen($department) > 100
    || ($phone !== null && strlen($phone) > 20)
    || !in_array($gender, ["Male", "Female"], true)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Please provide valid registration details. Passwords must be 8-72 characters and gender must be Male or Female."
    ]);
    exit;
}

$level = is_int($levelValue) || is_string($levelValue)
    ? filter_var($levelValue, FILTER_VALIDATE_INT)
    : false;
if ($level === false || $level < 1 || $level > 2147483647) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Level must be a positive integer."
    ]);
    exit;
}

try {
    $database = new Database();
    $student = new Student($database->connect());
    $userId = $student->register(
        $email,
        $password,
        $matricNumber,
        $firstName,
        $lastName,
        $phone === "" ? null : $phone,
        $department,
        $level,
        $gender
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
