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

    // The student's current bed: a confirmed one, or a pending one that is still inside its 30 minute hold.
    $statement = $db->prepare(
        "SELECT a.allocation_id, a.status, a.start_date, a.end_date, a.expires_at,
                TIMESTAMPDIFF(SECOND, NOW(), a.expires_at) AS seconds_left,
                b.bed_number, r.room_number, r.building_code, r.floor_number,
                r.price_per_occupant, h.hostel_name
         FROM allocations a
         JOIN bed_spaces b ON b.bed_id = a.bed_id
         JOIN rooms r ON r.room_id = a.room_id
         JOIN hostels h ON h.hostel_id = r.hostel_id
         WHERE a.user_id = :user_id
           AND (
               a.status = 'Confirmed'
               OR (a.status = 'Pending' AND a.expires_at > NOW())
           )
         ORDER BY (a.status = 'Confirmed') DESC, a.allocation_id DESC
         LIMIT 1"
    );
    $statement->execute([
        "user_id" => $_SESSION["user_id"]
    ]);
    $row = $statement->fetch();

    if (!$row) {
        echo json_encode([
            "success" => true,
            "allocation" => null,
            "payment" => null
        ]);
        exit;
    }

    $paymentStatement = $db->prepare(
        "SELECT reference, status
         FROM payments
         WHERE allocation_id = :allocation_id
         ORDER BY payment_id DESC
         LIMIT 1"
    );
    $paymentStatement->execute([
        "allocation_id" => $row["allocation_id"]
    ]);
    $payment = $paymentStatement->fetch();

    $isPending = $row["status"] === "Pending";

    echo json_encode([
        "success" => true,
        "allocation" => [
            "allocation_id" => (int) $row["allocation_id"],
            "status" => $row["status"],
            "hostel_name" => $row["hostel_name"],
            "room_number" => $row["room_number"],
            "building_code" => $row["building_code"],
            "floor_number" => (int) $row["floor_number"],
            "bed_number" => $row["bed_number"],
            "start_date" => $row["start_date"],
            "end_date" => $row["end_date"],
            "amount" => $row["price_per_occupant"],
            "currency" => "NGN",
            "seconds_left" => $isPending ? max(0, (int) $row["seconds_left"]) : null
        ],
        "payment" => $payment ? [
            "reference" => $payment["reference"],
            "status" => $payment["status"]
        ] : null
    ]);
} catch (Throwable $error) {
    error_log("Current allocation lookup failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load your reservation."
    ]);
}
