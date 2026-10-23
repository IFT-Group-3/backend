<?php

header("Content-Type: application/json");
require_once __DIR__ . "/utils/cors.php";
handle_api_cors();

$requestPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
if (!is_string($requestPath)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request path."
    ]);
    exit;
}

$scriptDirectory = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
$scriptDirectory = rtrim($scriptDirectory, "/.");
if ($scriptDirectory !== "" && str_starts_with($requestPath, $scriptDirectory . "/")) {
    $requestPath = substr($requestPath, strlen($scriptDirectory));
}

$route = "/" . trim($requestPath, "/");
$routes = [
    "/api/auth/bootstrap-admin" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/auth/bootstrap-admin.php"
    ],
    "/api/auth/register" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/auth/register.php"
    ],
    "/api/auth/admin/register" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/auth/register-admin.php"
    ],
    "/api/auth/login" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/auth/login.php"
    ],
    "/api/auth/logout" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/auth/logout.php"
    ],
    "/api/admin/hostels" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/admin/hostels.php"
    ],
    "/api/admin/dashboard" => [
        "method" => "GET",
        "file" => __DIR__ . "/api/admin/dashboard.php"
    ],
    "/api/admin/rooms" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/admin/rooms.php"
    ],
    "/api/admin/allocation-period" => [
        "method" => "PUT",
        "file" => __DIR__ . "/api/admin/allocation-period.php"
    ],
    "/api/hostels" => [
        "method" => "GET",
        "file" => __DIR__ . "/api/hostels/list.php"
    ],
    "/api/rooms/availability" => [
        "method" => "GET",
        "file" => __DIR__ . "/api/rooms/availability.php"
    ],
    "/api/allocations/create" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/allocations/create.php"
    ],
    "/api/payments/process" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/payments/process.php"
    ],
    "/api/payments/verify" => [
        "method" => "POST",
        "file" => __DIR__ . "/api/payments/verify.php"
    ]
];

if (!isset($routes[$route])) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Route not found."
    ]);
    exit;
}

$endpoint = $routes[$route];
if (($_SERVER["REQUEST_METHOD"] ?? "") !== $endpoint["method"]) {
    header("Allow: " . $endpoint["method"]);
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

require $endpoint["file"];