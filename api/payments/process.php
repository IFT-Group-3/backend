<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../middlewares/student.php";
require_once __DIR__ . "/../../config/Database.php";
require_once __DIR__ . "/../../classes/Payment.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

$secretKey = getenv("PAYSTACK_SECRET_KEY");
if (!is_string($secretKey) || $secretKey === "") {
    error_log("PAYSTACK_SECRET_KEY is not configured.");
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "message" => "Payment service is not configured."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$allocationIdValue = is_array($data) ? ($data["allocation_id"] ?? null) : null;
$allocationId = is_int($allocationIdValue) || is_string($allocationIdValue)
    ? filter_var($allocationIdValue, FILTER_VALIDATE_INT)
    : false;

if ($allocationId === false || $allocationId < 1) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid allocation_id is required."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $statement = $db->prepare(
        "SELECT a.allocation_id, a.status, a.expires_at,
                a.expires_at > NOW() AS reservation_active, s.email,
                r.price_per_occupant
         FROM allocations a
         JOIN students s ON s.user_id = a.user_id
         JOIN rooms r ON r.room_id = a.room_id
         WHERE a.allocation_id = :allocation_id
           AND a.user_id = :user_id
         LIMIT 1"
    );
    $statement->execute([
        "allocation_id" => $allocationId,
        "user_id" => $_SESSION["user_id"]
    ]);
    $allocation = $statement->fetch();

    if (
        !$allocation
        || $allocation["status"] !== "Pending"
        || (int) $allocation["reservation_active"] !== 1
    ) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Allocation not found or its 30-minute reservation has expired."
        ]);
        exit;
    }

    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $allocation["price_per_occupant"], $matches)) {
        throw new RuntimeException("Stored room price is invalid.");
    }

    $wholeNaira = (int) $matches[1];
    $fraction = str_pad($matches[2] ?? "", 2, "0");
    $amountKobo = ($wholeNaira * 100) + (int) $fraction;
    if ($amountKobo < 1 || $amountKobo > PHP_INT_MAX) {
        throw new RuntimeException("Stored room price is outside the Paystack-supported range.");
    }

    $pendingPaymentStatement = $db->prepare(
        "SELECT reference, status
         FROM payments
         WHERE allocation_id = :allocation_id
           AND status IN ('Pending', 'Paid')
         LIMIT 1"
    );
    $pendingPaymentStatement->execute([
        "allocation_id" => $allocationId
    ]);
    $existingPayment = $pendingPaymentStatement->fetch();
    if ($existingPayment && $existingPayment["status"] === "Pending") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "A checkout has already been started. Finish it or verify its existing reference.",
            "reference" => $existingPayment["reference"]
        ]);
        exit;
    }
    if ($existingPayment && $existingPayment["status"] === "Paid") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "A payment for this allocation needs manual review before another checkout."
        ]);
        exit;
    }

    $reference = "hostel_" . bin2hex(random_bytes(16));
    $insertPayment = $db->prepare(
        "INSERT INTO payments (allocation_id, reference, amount_kobo, status)
         VALUES (:allocation_id, :reference, :amount_kobo, 'Pending')"
    );
    $insertPayment->execute([
        "allocation_id" => $allocationId,
        "reference" => $reference,
        "amount_kobo" => $amountKobo
    ]);

    $gatewayData = Payment::initialize(
        $secretKey,
        $allocation["email"],
        $amountKobo,
        $reference,
        $allocationId
    );

    if (
        !isset($gatewayData["authorization_url"])
        || !is_string($gatewayData["authorization_url"])
    ) {
        throw new RuntimeException("Paystack did not return a checkout URL.");
    }
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && isset($reference)) {
        try {
            $failedPayment = $db->prepare(
                "UPDATE payments SET status = 'Failed' WHERE reference = :reference"
            );
            $failedPayment->execute([
                "reference" => $reference
            ]);
        } catch (Throwable $updateError) {
            error_log("Could not mark failed Paystack initialization: " . $updateError->getMessage());
        }
    }

    error_log("Paystack initialization failed: " . $error->getMessage());
    http_response_code(502);
    echo json_encode([
        "success" => false,
        "message" => "Unable to initialize payment."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Complete payment before the room reservation expires.",
    "payment" => [
        "reference" => $reference,
        "amount_kobo" => $amountKobo,
        "currency" => "NGN",
        "authorization_url" => $gatewayData["authorization_url"],
        "access_code" => $gatewayData["access_code"] ?? null
    ]
]);