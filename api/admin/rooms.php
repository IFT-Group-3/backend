<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../middlewares/admin.php";
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
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid JSON request body is required."
    ]);
    exit;
}

$hostelIdValue = $data["hostel_id"] ?? null;
$buildingCode = $data["building_code"] ?? null;
$floorNumberValue = $data["floor_number"] ?? null;
$roomNumber = $data["room_number"] ?? null;
$maxOccupantsValue = $data["max_occupants"] ?? null;
$priceValue = $data["price_per_occupant"] ?? null;

$hostelId = is_int($hostelIdValue) || is_string($hostelIdValue)
    ? filter_var($hostelIdValue, FILTER_VALIDATE_INT)
    : false;
$maxOccupants = is_int($maxOccupantsValue) || is_string($maxOccupantsValue)
    ? filter_var($maxOccupantsValue, FILTER_VALIDATE_INT)
    : false;
$floorNumber = is_int($floorNumberValue) || is_string($floorNumberValue)
    ? filter_var($floorNumberValue, FILTER_VALIDATE_INT)
    : false;

if (
    $hostelId === false
    || $hostelId < 1
    || !is_string($buildingCode)
    || $floorNumber === false
    || $floorNumber < 0
    || !is_string($roomNumber)
    || !is_string($priceValue)
    || $maxOccupantsValue === null
    || $maxOccupants === false
    || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $priceValue)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Provide valid hostel_id, building_code, floor_number, room_number, max_occupants, and price_per_occupant fields."
    ]);
    exit;
}

$buildingCode = strtoupper(trim($buildingCode));
$roomNumber = trim($roomNumber);
$priceParts = explode(".", $priceValue, 2);
$priceWhole = ltrim($priceParts[0], "0");
$priceFraction = str_pad($priceParts[1] ?? "", 2, "0");
if (
    !preg_match('/^[A-Z0-9]{1,10}$/', $buildingCode)
    || $floorNumber > 1000
    || $roomNumber === ""
    || strlen($roomNumber) > 30
    || $maxOccupants < 1
    || $maxOccupants > 1000
    || ($priceWhole === "" && $priceFraction === "00")
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Building code must be 1-10 letters or digits, floor_number must be 0-1000, room number 1-30 characters, max_occupants 1-1000, and price_per_occupant greater than zero."
    ]);
    exit;
}

$pricePerOccupant = ($priceWhole === "" ? "0" : $priceWhole)
    . "."
    . $priceFraction;

try {
    $database = new Database();
    $db = $database->connect();
    $db->beginTransaction();

    $hostelStatement = $db->prepare(
        "SELECT hostel_id
         FROM hostels
         WHERE hostel_id = :hostel_id
         FOR UPDATE"
    );
    $hostelStatement->execute([
        "hostel_id" => $hostelId
    ]);

    if (!$hostelStatement->fetch()) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Hostel not found."
        ]);
        exit;
    }

    $roomStatement = $db->prepare(
        "INSERT INTO rooms (
            hostel_id, building_code, floor_number, room_number,
            max_occupants, price_per_occupant
         )
         VALUES (
            :hostel_id, :building_code, :floor_number, :room_number,
            :max_occupants, :price_per_occupant
         )"
    );
    $roomStatement->execute([
        "hostel_id" => $hostelId,
        "building_code" => $buildingCode,
        "floor_number" => $floorNumber,
        "room_number" => $roomNumber,
        "max_occupants" => $maxOccupants,
        "price_per_occupant" => $pricePerOccupant
    ]);
    $roomId = (int) $db->lastInsertId();

    $bedStatement = $db->prepare(
        "INSERT INTO bed_spaces (room_id, bed_number)
         VALUES (:room_id, :bed_number)"
    );
    $bedNumbers = [];
    for ($bedIndex = 1; $bedIndex <= $maxOccupants; $bedIndex++) {
        $bedNumber = $buildingCode
            . "-" . $floorNumber
            . "-" . $roomNumber
            . "-BED-" . str_pad((string) $bedIndex, 4, "0", STR_PAD_LEFT);
        $bedStatement->execute([
            "room_id" => $roomId,
            "bed_number" => $bedNumber
        ]);
        $bedNumbers[] = $bedNumber;
    }

    $countStatement = $db->prepare(
        "UPDATE hostels
         SET total_rooms = (
             SELECT COUNT(*)
             FROM rooms
             WHERE hostel_id = :count_hostel_id
         )
         WHERE hostel_id = :hostel_id"
    );
    $countStatement->execute([
        "count_hostel_id" => $hostelId,
        "hostel_id" => $hostelId
    ]);

    $db->commit();
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    if (
        $error instanceof PDOException
        && $error->getCode() === "23000"
        && isset($error->errorInfo[1])
        && (int) $error->errorInfo[1] === 1062
    ) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "That room number already exists in this hostel."
        ]);
        exit;
    }

    error_log("Room creation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to create room."
    ]);
    exit;
}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Room created successfully.",
    "room" => [
        "room_id" => $roomId,
        "hostel_id" => $hostelId,
        "building_code" => $buildingCode,
        "floor_number" => $floorNumber,
        "room_number" => $roomNumber,
        "bed_numbers" => $bedNumbers,
        "max_occupants" => $maxOccupants,
        "price_per_occupant" => $pricePerOccupant,
        "currency" => "NGN"
    ]
]);