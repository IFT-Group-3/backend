<?php

require_once __DIR__ . "/auth.php";

if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Admin access required."
    ]);
    exit;
}
