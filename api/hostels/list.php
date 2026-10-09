<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../middlewares/student.php";
require_once __DIR__ . "/../../config/Database.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();

    $studentStatement = $db->prepare(
        "SELECT gender
         FROM students
         WHERE user_id = :user_id
         LIMIT 1"
    );
    $studentStatement->execute([
        "user_id" => $_SESSION["user_id"]
    ]);
    $gender = $studentStatement->fetchColumn();

    if (!in_array($gender, ["Male", "Female"], true)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "A valid student profile is required to view hostels."
        ]);
        exit;
    }

    $hostelStatement = $db->prepare(
        "SELECT hostel_id, hostel_name, hostel_type, location, description,
                total_rooms, status, image_path, created_at
         FROM hostels
         WHERE status = 'Available'
           AND (hostel_type = :gender OR hostel_type = 'Mixed')
         ORDER BY hostel_name"
    );
    $hostelStatement->execute([
        "gender" => $gender
    ]);

    echo json_encode([
        "success" => true,
        "hostels" => $hostelStatement->fetchAll()
    ]);
} catch (Throwable $error) {
    error_log("Hostel listing failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load available hostels."
    ]);
}