<?php

require_once __DIR__ . "/auth.php";

if ($_SESSION["role"] !== "student") {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Student access required."
    ]);
    exit;
}
