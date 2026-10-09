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

$hostelName = $_POST["hostel_name"] ?? null;
$hostelType = $_POST["hostel_type"] ?? null;
$location = $_POST["location"] ?? null;
$description = $_POST["description"] ?? null;
$totalRoomsValue = $_POST["total_rooms"] ?? "0";
$status = $_POST["status"] ?? "Available";

if (
    !is_string($hostelName)
    || !is_string($hostelType)
    || !is_string($location)
    || ($description !== null && !is_string($description))
    || !is_string($totalRoomsValue)
    || !is_string($status)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Hostel name, type, location, room count, and status must be valid form fields."
    ]);
    exit;
}

$hostelName = trim($hostelName);
$hostelType = trim($hostelType);
$location = trim($location);
$description = $description === null ? null : trim($description);
$description = $description === "" ? null : $description;
$totalRoomsValue = $totalRoomsValue === "" ? "0" : $totalRoomsValue;
$totalRooms = filter_var($totalRoomsValue, FILTER_VALIDATE_INT);

if (
    $hostelName === ""
    || strlen($hostelName) > 100
    || !in_array($hostelType, ["Male", "Female", "Mixed"], true)
    || $location === ""
    || strlen($location) > 150
    || $totalRooms === false
    || $totalRooms < 0
    || $totalRooms > 2147483647
    || !in_array($status, ["Available", "Unavailable"], true)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Provide a valid name, hostel type, location, non-negative room count, and status."
    ]);
    exit;
}

$imagePath = null;
$savedImage = null;
$image = $_FILES["image"] ?? null;

if (is_array($image) && ($image["error"] ?? null) === UPLOAD_ERR_NO_FILE) {
    $image = null;
}

if ($image !== null) {
    if (!is_array($image) || !isset($image["error"], $image["tmp_name"], $image["size"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid image upload."
        ]);
        exit;
    }

    if ($image["error"] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Image upload failed."
        ]);
        exit;
    }

    if ($image["size"] > 5 * 1024 * 1024 || !is_uploaded_file($image["tmp_name"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Image must be a valid upload no larger than 5 MB."
        ]);
        exit;
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($image["tmp_name"]);
    $allowedMimeTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Image must be JPEG, PNG, or WebP."
        ]);
        exit;
    }

    $uploadDirectory = __DIR__ . "/../../uploads/hostels";
    if (
        !is_dir($uploadDirectory)
        && !mkdir($uploadDirectory, 0755, true)
        && !is_dir($uploadDirectory)
    ) {
        error_log("Could not create hostel image upload directory.");
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Unable to store hostel image."
        ]);
        exit;
    }

    $fileName = bin2hex(random_bytes(16)) . "." . $allowedMimeTypes[$mimeType];
    $savedImage = $uploadDirectory . "/" . $fileName;
    $imagePath = "uploads/hostels/" . $fileName;

    if (!move_uploaded_file($image["tmp_name"], $savedImage)) {
        error_log("Could not move uploaded hostel image into storage.");
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Unable to store hostel image."
        ]);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->connect();
    $statement = $db->prepare(
        "INSERT INTO hostels (
            hostel_name, hostel_type, location, description, total_rooms,
            status, image_path
         )
         VALUES (
            :hostel_name, :hostel_type, :location, :description, :total_rooms,
            :status, :image_path
         )"
    );
    $statement->execute([
        "hostel_name" => $hostelName,
        "hostel_type" => $hostelType,
        "location" => $location,
        "description" => $description,
        "total_rooms" => $totalRooms,
        "status" => $status,
        "image_path" => $imagePath
    ]);
    $hostelId = (int) $db->lastInsertId();
} catch (Throwable $error) {
    if ($savedImage !== null && is_file($savedImage) && !unlink($savedImage)) {
        error_log("Could not remove hostel image after failed insert: " . $savedImage);
    }

    error_log("Hostel creation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to create hostel."
    ]);
    exit;
}

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Hostel created successfully.",
    "hostel" => [
        "hostel_id" => $hostelId,
        "hostel_name" => $hostelName,
        "hostel_type" => $hostelType,
        "location" => $location,
        "description" => $description,
        "total_rooms" => $totalRooms,
        "status" => $status,
        "image_path" => $imagePath
    ]
]);