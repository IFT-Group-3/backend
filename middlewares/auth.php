<?php

require_once __DIR__ . "/../utils/session.php";

header("Content-Type: application/json");
start_app_session();

if (
    !isset($_SESSION["user_id"], $_SESSION["email"], $_SESSION["role"])
    || !in_array($_SESSION["role"], ["student", "admin"], true)
) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Authentication required."
    ]);
    exit;
}