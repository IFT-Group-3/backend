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
    $db->exec("SET SESSION group_concat_max_len = 65535");

    $studentStatement = $db->prepare(
        "SELECT gender FROM students WHERE user_id = :user_id LIMIT 1"
    );
    $studentStatement->execute([
        "user_id" => $_SESSION["user_id"]
    ]);
    $gender = $studentStatement->fetchColumn();

    if (!in_array($gender, ["Male", "Female"], true)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "A valid student profile is required to view rooms."
        ]);
        exit;
    }

    $periodStatement = $db->query(
        "SELECT start_date, end_date FROM allocation_period WHERE period_id = 1"
    );
    $period = $periodStatement->fetch();
    if (!$period) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "The admin has not configured the allocation period yet."
        ]);
        exit;
    }

    $statement = $db->prepare(
        "SELECT r.room_id, r.hostel_id, h.hostel_name, h.hostel_type,
                r.building_code, r.floor_number,
                r.room_number, r.max_occupants, r.price_per_occupant,
                'NGN' AS currency,
                COUNT(b.bed_id) AS total_beds,
                SUM(CASE WHEN a.allocation_id IS NOT NULL THEN 1 ELSE 0 END) AS occupied_spaces,
                COUNT(b.bed_id) - SUM(CASE WHEN a.allocation_id IS NOT NULL THEN 1 ELSE 0 END) AS available_spaces,
                GROUP_CONCAT(
                    CASE WHEN a.allocation_id IS NULL THEN b.bed_number END
                    ORDER BY b.bed_number SEPARATOR ','
                ) AS available_bed_numbers
         FROM rooms r
         JOIN hostels h ON h.hostel_id = r.hostel_id
         JOIN bed_spaces b ON b.room_id = r.room_id
         LEFT JOIN allocations a
           ON a.bed_id = b.bed_id
          AND a.start_date <= :end_date
          AND a.end_date >= :start_date
          AND (
              a.status = 'Confirmed'
              OR (a.status = 'Pending' AND a.expires_at > NOW())
          )
         WHERE r.status = 'Available'
           AND h.status = 'Available'
           AND (h.hostel_type = :gender OR h.hostel_type = 'Mixed')
         GROUP BY r.room_id, r.hostel_id, h.hostel_name, h.hostel_type,
                  r.building_code, r.floor_number,
                  r.room_number, r.max_occupants, r.price_per_occupant
         HAVING available_spaces > 0
         ORDER BY h.hostel_name, r.building_code, r.floor_number, r.room_number"
    );
    $statement->execute([
        "gender" => $gender,
        "end_date" => $period["end_date"],
        "start_date" => $period["start_date"]
    ]);

    $rooms = $statement->fetchAll();
    foreach ($rooms as &$room) {
        $room["room_id"] = (int) $room["room_id"];
        $room["hostel_id"] = (int) $room["hostel_id"];
        $room["floor_number"] = (int) $room["floor_number"];
        $room["max_occupants"] = (int) $room["max_occupants"];
        $room["total_beds"] = (int) $room["total_beds"];
        $room["occupied_spaces"] = (int) $room["occupied_spaces"];
        $room["available_spaces"] = (int) $room["available_spaces"];
        $room["available_bed_numbers"] = $room["available_bed_numbers"] === null
            ? []
            : explode(",", $room["available_bed_numbers"]);
    }
    unset($room);

    echo json_encode([
        "success" => true,
        "allocation_period" => $period,
        "rooms" => $rooms
    ]);
} catch (Throwable $error) {
    error_log("Room availability lookup failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load available rooms."
    ]);
}