<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../middlewares/student.php";
require_once __DIR__ . "/../../config/Database.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$roomIdValue = is_array($data) ? ($data["room_id"] ?? null) : null;
$roomId = is_int($roomIdValue) || is_string($roomIdValue)
    ? filter_var($roomIdValue, FILTER_VALIDATE_INT)
    : false;

if ($roomId === false || $roomId < 1) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid room_id is required."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $db->beginTransaction();

    $studentStatement = $db->prepare(
        "SELECT user_id, gender
         FROM students
         WHERE user_id = :user_id
         FOR UPDATE"
    );
    $studentStatement->execute([
        "user_id" => $_SESSION["user_id"]
    ]);
    $student = $studentStatement->fetch();

    if (!$student || !in_array($student["gender"], ["Male", "Female"], true)) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "A valid student profile is required to book a room."
        ]);
        exit;
    }

    $roomStatement = $db->prepare(
        "SELECT r.room_id, r.price_per_occupant,
                r.status AS room_status, h.status AS hostel_status,
                h.hostel_type
         FROM rooms r
         JOIN hostels h ON h.hostel_id = r.hostel_id
         WHERE r.room_id = :room_id
         FOR UPDATE"
    );
    $roomStatement->execute([
        "room_id" => $roomId
    ]);
    $room = $roomStatement->fetch();

    if (
        !$room
        || $room["room_status"] !== "Available"
        || $room["hostel_status"] !== "Available"
        || !in_array($room["hostel_type"], [$student["gender"], "Mixed"], true)
    ) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Room not found or unavailable to this student."
        ]);
        exit;
    }

    $periodStatement = $db->query(
        "SELECT start_date, end_date
         FROM allocation_period
         WHERE period_id = 1"
    );
    $period = $periodStatement->fetch();
    if (!$period) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "The admin has not configured the allocation period yet."
        ]);
        exit;
    }

    $existingStatement = $db->prepare(
        "SELECT allocation_id
         FROM allocations
         WHERE user_id = :user_id
           AND (
               (
                   status = 'Confirmed'
                   OR (status = 'Pending' AND expires_at > NOW())
               )
               AND start_date <= :end_date
               AND end_date >= :start_date
           )
         LIMIT 1"
    );
    $existingStatement->execute([
        "user_id" => $_SESSION["user_id"],
        "end_date" => $period["end_date"],
        "start_date" => $period["start_date"]
    ]);
    if ($existingStatement->fetch()) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "You already have an active room allocation or reservation."
        ]);
        exit;
    }

    $bedStatement = $db->prepare(
        "SELECT b.bed_id, b.bed_number
         FROM bed_spaces b
         WHERE b.room_id = :room_id
           AND NOT EXISTS (
               SELECT 1
               FROM allocations a
               WHERE a.bed_id = b.bed_id
                 AND a.start_date <= :end_date
                 AND a.end_date >= :start_date
                 AND (
                     a.status = 'Confirmed'
                     OR (a.status = 'Pending' AND a.expires_at > NOW())
                 )
           )
         ORDER BY b.bed_number
         LIMIT 1
         FOR UPDATE"
    );
    $bedStatement->execute([
        "room_id" => $roomId,
        "end_date" => $period["end_date"],
        "start_date" => $period["start_date"]
    ]);
    $bed = $bedStatement->fetch();

    if (!$bed) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "All beds in this room are allocated for the selected period."
        ]);
        exit;
    }

    $allocationStatement = $db->prepare(
        "INSERT INTO allocations (
            user_id, room_id, bed_id, start_date, end_date, status, expires_at
         )
         VALUES (
            :user_id, :room_id, :bed_id, :start_date, :end_date,
            'Pending', DATE_ADD(NOW(), INTERVAL 30 MINUTE)
         )"
    );
    $allocationStatement->execute([
        "user_id" => $_SESSION["user_id"],
        "room_id" => $roomId,
        "bed_id" => $bed["bed_id"],
        "start_date" => $period["start_date"],
        "end_date" => $period["end_date"]
    ]);
    $allocationId = (int) $db->lastInsertId();
    $db->commit();

    echo json_encode([
        "success" => true,
        "message" => "Room reserved for 30 minutes. Complete Paystack payment to confirm it.",
        "allocation" => [
            "allocation_id" => $allocationId,
            "room_id" => $roomId,
            "bed_id" => (int) $bed["bed_id"],
            "bed_number" => $bed["bed_number"],
            "start_date" => $period["start_date"],
            "end_date" => $period["end_date"],
            "status" => "Pending",
            "expires_in_minutes" => 30,
            "amount" => $room["price_per_occupant"],
            "currency" => "NGN"
        ]
    ]);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Room allocation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to reserve this room."
    ]);
}