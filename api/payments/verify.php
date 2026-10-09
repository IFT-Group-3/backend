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
$reference = is_array($data) ? ($data["reference"] ?? null) : null;
if (!is_string($reference) || !preg_match('/^hostel_[a-f0-9]{32}$/', $reference)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid payment reference is required."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $paymentStatement = $db->prepare(
        "SELECT p.payment_id, p.allocation_id, p.amount_kobo, p.status,
                a.room_id, a.bed_id, a.start_date, a.end_date, b.bed_number,
                a.status AS allocation_status, a.expires_at
         FROM payments p
         JOIN allocations a ON a.allocation_id = p.allocation_id
         JOIN bed_spaces b ON b.bed_id = a.bed_id
         WHERE p.reference = :reference
           AND a.user_id = :user_id
         LIMIT 1"
    );
    $paymentStatement->execute([
        "reference" => $reference,
        "user_id" => $_SESSION["user_id"]
    ]);
    $payment = $paymentStatement->fetch();

    if (!$payment) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Payment reference not found."
        ]);
        exit;
    }

    if ($payment["status"] === "Paid" && $payment["allocation_status"] === "Confirmed") {
        echo json_encode([
            "success" => true,
            "message" => "Payment is verified and the room allocation is confirmed.",
            "allocation_id" => (int) $payment["allocation_id"],
            "bed_id" => (int) $payment["bed_id"],
            "bed_number" => $payment["bed_number"],
            "start_date" => $payment["start_date"],
            "end_date" => $payment["end_date"],
            "status" => "Confirmed"
        ]);
        exit;
    }

    $gatewayData = Payment::verify($secretKey, $reference);
    if (($gatewayData["status"] ?? null) !== "success") {
        $failedPayment = $db->prepare(
            "UPDATE payments SET status = 'Failed' WHERE payment_id = :payment_id"
        );
        $failedPayment->execute([
            "payment_id" => $payment["payment_id"]
        ]);
        http_response_code(402);
        echo json_encode([
            "success" => false,
            "message" => "Paystack did not confirm payment. You can start checkout again."
        ]);
        exit;
    }

    if (
        ($gatewayData["reference"] ?? null) !== $reference
        || ($gatewayData["currency"] ?? null) !== "NGN"
        || (int) ($gatewayData["amount"] ?? 0) !== (int) $payment["amount_kobo"]
    ) {
        $suspectPayment = $db->prepare(
            "UPDATE payments
             SET status = 'Paid', gateway_reference = :gateway_reference
             WHERE payment_id = :payment_id"
        );
        $suspectPayment->execute([
            "gateway_reference" => (string) ($gatewayData["id"] ?? ""),
            "payment_id" => $payment["payment_id"]
        ]);
        error_log("Paystack payment amount or currency mismatch for reference " . $reference);
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Payment succeeded but its reference, amount, or currency did not match. Contact an administrator.",
            "refund_required" => true,
            "reference" => $reference
        ]);
        exit;
    }

    $db->beginTransaction();
    $roomLock = $db->prepare(
        "SELECT room_id
         FROM rooms
         WHERE room_id = :room_id
         FOR UPDATE"
    );
    $roomLock->execute([
        "room_id" => $payment["room_id"]
    ]);
    if ($roomLock->fetchColumn() === false) {
        throw new RuntimeException("Payment room no longer exists.");
    }

    $allocationLock = $db->prepare(
        "SELECT allocation_id, room_id, bed_id, start_date, end_date,
                status, expires_at,
                expires_at > NOW() AS reservation_active
         FROM allocations
         WHERE allocation_id = :allocation_id
         FOR UPDATE"
    );
    $allocationLock->execute([
        "allocation_id" => $payment["allocation_id"]
    ]);
    $allocation = $allocationLock->fetch();
    if (!$allocation) {
        throw new RuntimeException("Payment allocation no longer exists.");
    }

    $markPaymentPaid = $db->prepare(
        "UPDATE payments
         SET status = 'Paid', gateway_reference = :gateway_reference
         WHERE payment_id = :payment_id"
    );
    $markPaymentPaid->execute([
        "gateway_reference" => (string) ($gatewayData["id"] ?? ""),
        "payment_id" => $payment["payment_id"]
    ]);

    if ($allocation["status"] === "Confirmed") {
        $db->commit();
        echo json_encode([
            "success" => true,
            "message" => "Payment is verified and the room allocation is confirmed.",
            "allocation_id" => (int) $allocation["allocation_id"],
            "bed_id" => (int) $allocation["bed_id"],
            "bed_number" => $payment["bed_number"],
            "start_date" => $allocation["start_date"],
            "end_date" => $allocation["end_date"],
            "status" => "Confirmed"
        ]);
        exit;
    }

    if (
        $allocation["status"] !== "Pending"
        || (int) $allocation["reservation_active"] !== 1
    ) {
        if ($allocation["status"] === "Pending") {
            $expireAllocation = $db->prepare(
                "UPDATE allocations SET status = 'Expired' WHERE allocation_id = :allocation_id"
            );
            $expireAllocation->execute([
                "allocation_id" => $allocation["allocation_id"]
            ]);
        }
        $db->commit();

        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Payment succeeded after the reservation expired. The payment requires a refund.",
            "refund_required" => true,
            "reference" => $reference
        ]);
        exit;
    }

    $occupancyStatement = $db->prepare(
        "SELECT COUNT(*)
         FROM allocations
         WHERE bed_id = :bed_id
           AND allocation_id <> :allocation_id
           AND start_date <= :end_date
           AND end_date >= :start_date
           AND (
               status = 'Confirmed'
               OR (status = 'Pending' AND expires_at > NOW())
           )"
    );
    $occupancyStatement->execute([
        "bed_id" => $allocation["bed_id"],
        "allocation_id" => $allocation["allocation_id"],
        "end_date" => $allocation["end_date"],
        "start_date" => $allocation["start_date"]
    ]);

    if ((int) $occupancyStatement->fetchColumn() > 0) {
        $expireAllocation = $db->prepare(
            "UPDATE allocations SET status = 'Expired' WHERE allocation_id = :allocation_id"
        );
        $expireAllocation->execute([
            "allocation_id" => $allocation["allocation_id"]
        ]);
        $db->commit();

        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Payment succeeded but the assigned bed was allocated for overlapping dates. The payment requires a refund.",
            "refund_required" => true,
            "reference" => $reference
        ]);
        exit;
    }

    $confirmAllocation = $db->prepare(
        "UPDATE allocations
         SET status = 'Confirmed', expires_at = NULL, confirmed_at = NOW()
         WHERE allocation_id = :allocation_id"
    );
    $confirmAllocation->execute([
        "allocation_id" => $allocation["allocation_id"]
    ]);
    $db->commit();

    echo json_encode([
        "success" => true,
        "message" => "Payment verified and room allocation confirmed.",
        "allocation_id" => (int) $allocation["allocation_id"],
        "bed_id" => (int) $allocation["bed_id"],
        "bed_number" => $payment["bed_number"],
        "start_date" => $allocation["start_date"],
        "end_date" => $allocation["end_date"],
        "status" => "Confirmed"
    ]);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Payment verification failed: " . $error->getMessage());
    http_response_code(502);
    echo json_encode([
        "success" => false,
        "message" => "Unable to verify payment."
    ]);
}