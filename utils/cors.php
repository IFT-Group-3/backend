<?php

function handle_api_cors(): void
{
    $allowedOrigin = "http://localhost:8000";
    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";

    if ($origin === $allowedOrigin) {
        header("Access-Control-Allow-Origin: " . $allowedOrigin);
        header("Access-Control-Allow-Credentials: true");
        header("Vary: Origin");
    }

    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "OPTIONS") {
        return;
    }

    if ($origin !== $allowedOrigin) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Origin is not allowed."
        ]);
        exit;
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Max-Age: 600");
    http_response_code(204);
    exit;
}
