<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../middlewares/admin.php";
require_once __DIR__ . "/../../config/Database.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "PUT") {
    header("Allow: PUT");
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$startDate = is_array($data) ? ($data["start_date"] ?? null) : null;
$endDate = is_array($data) ? ($data["end_date"] ?? null) : null;

$validDate = static function ($value): bool {
    if (!is_string($value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    return $date !== false && $date->format("Y-m-d") === $value;
};

if (
    !$validDate($startDate)
    || !$validDate($endDate)
    || $endDate < $startDate
    || $startDate < date("Y-m-d")
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Provide start_date and end_date as YYYY-MM-DD, with a start date today or later and an end date on or after it."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $statement = $db->prepare(
        "INSERT INTO allocation_period (period_id, start_date, end_date)
         VALUES (1, :start_date, :end_date)
         ON DUPLICATE KEY UPDATE
             start_date = VALUES(start_date),
             end_date = VALUES(end_date)"
    );
    $statement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Allocation period saved.",
        "allocation_period" => [
            "start_date" => $startDate,
            "end_date" => $endDate
        ]
    ]);
} catch (Throwable $error) {
    error_log("Allocation period update failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to save the allocation period."
    ]);
}
