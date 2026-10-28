<?php

require_once __DIR__ . "/../config/env.php";

/**
 * Origins allowed to call the API with credentials.
 *
 * Localhost development origins are always allowed. Add deployed frontend
 * origins (comma separated, no trailing slash) with CORS_ALLOWED_ORIGINS, for
 * example: CORS_ALLOWED_ORIGINS=https://roommate.netlify.app
 * A frontend served from the same origin as the API needs no entry.
 */
function allowed_cors_origins(): array
{
    $origins = [
        "http://localhost:8000",
        "http://127.0.0.1:8000",
        "http://localhost",
        "http://127.0.0.1"
    ];

    $configured = get_env("CORS_ALLOWED_ORIGINS", "");
    foreach (explode(",", (string) $configured) as $origin) {
        $origin = rtrim(trim($origin), "/");
        if ($origin !== "") {
            $origins[] = $origin;
        }
    }

    return $origins;
}

function handle_api_cors(): void
{
    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";
    $isAllowed = $origin !== "" && in_array($origin, allowed_cors_origins(), true);

    if ($isAllowed) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
        header("Vary: Origin");
    }

    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "OPTIONS") {
        return;
    }

    if (!$isAllowed) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Origin is not allowed."
        ]);
        exit;
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Accept");
    header("Access-Control-Max-Age: 600");
    http_response_code(204);
    exit;
}
