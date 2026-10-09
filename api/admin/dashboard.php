<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../middlewares/admin.php";
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

$pageValue = $_GET["page"] ?? "1";
$perPageValue = $_GET["per_page"] ?? "50";
$page = is_string($pageValue) ? filter_var($pageValue, FILTER_VALIDATE_INT) : false;
$perPage = is_string($perPageValue) ? filter_var($perPageValue, FILTER_VALIDATE_INT) : false;

if (
    $page === false
    || $page < 1
    || $page > 1000000
    || $perPage === false
    || $perPage < 1
    || $perPage > 100
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "page must be positive and per_page must be between 1 and 100."
    ]);
    exit;
}

$offset = ($page - 1) * $perPage;

try {
    $database = new Database();
    $db = $database->connect();

    $periodStatement = $db->query(
        "SELECT start_date, end_date, updated_at
         FROM allocation_period
         WHERE period_id = 1"
    );
    $period = $periodStatement->fetch() ?: null;

    $summaryStatement = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'student') AS students,
            (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admins,
            (SELECT COUNT(*) FROM hostels) AS hostels,
            (SELECT COUNT(*) FROM rooms) AS rooms,
            (SELECT COUNT(*) FROM bed_spaces) AS bed_spaces,
            (SELECT COUNT(*) FROM allocations WHERE status = 'Confirmed') AS confirmed_allocations,
            (SELECT COUNT(*) FROM allocations
             WHERE status = 'Pending' AND expires_at > NOW()) AS active_reservations,
            (SELECT COUNT(*) FROM allocations
             WHERE status = 'Pending' AND expires_at <= NOW()) AS expired_pending_allocations,
            (SELECT COUNT(*) FROM payments WHERE status = 'Paid') AS paid_payments,
            (SELECT COUNT(*) FROM payments WHERE status = 'Pending') AS pending_payments,
            (SELECT COUNT(*) FROM payments WHERE status = 'Failed') AS failed_payments,
            (SELECT COALESCE(SUM(amount_kobo), 0)
             FROM payments WHERE status = 'Paid') AS total_paid_kobo"
    );
    $summary = $summaryStatement->fetch();
    foreach ($summary as $key => $value) {
        $summary[$key] = (int) $value;
    }
    $summary["total_paid_ngn"] = number_format(
        $summary["total_paid_kobo"] / 100,
        2,
        ".",
        ""
    );
    $summary["currency"] = "NGN";

    $countQuery = static function (PDO $db, string $table): int {
        $allowedTables = ["hostels", "rooms", "bed_spaces", "students", "allocations", "payments"];
        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException("Unsupported dashboard table.");
        }

        return (int) $db->query("SELECT COUNT(*) FROM " . $table)->fetchColumn();
    };

    $fetchPage = static function (
        PDO $db,
        string $sql,
        int $limit,
        int $offset
    ): array {
        return $db->query($sql . " LIMIT " . $limit . " OFFSET " . $offset)->fetchAll();
    };

    $hostels = $fetchPage(
        $db,
        "SELECT hostel_id, hostel_name, hostel_type, location, description,
                total_rooms, status, image_path, created_at
         FROM hostels
         ORDER BY hostel_name, hostel_id",
        $perPage,
        $offset
    );

    $admins = $fetchPage(
        $db,
        "SELECT user_id, email, created_at
         FROM users
         WHERE role = 'admin'
         ORDER BY email, user_id",
        $perPage,
        $offset
    );

    $rooms = $fetchPage(
        $db,
        "SELECT r.room_id, r.hostel_id, h.hostel_name, h.hostel_type,
                r.building_code, r.floor_number, r.room_number,
                r.max_occupants, r.price_per_occupant, r.status, r.created_at,
                COUNT(b.bed_id) AS total_beds,
                COALESCE(SUM(CASE WHEN a.allocation_id IS NOT NULL THEN 1 ELSE 0 END), 0)
                    AS occupied_beds,
                COUNT(b.bed_id) - COALESCE(
                    SUM(CASE WHEN a.allocation_id IS NOT NULL THEN 1 ELSE 0 END), 0
                ) AS available_beds
         FROM rooms r
         JOIN hostels h ON h.hostel_id = r.hostel_id
         LEFT JOIN bed_spaces b ON b.room_id = r.room_id
         LEFT JOIN allocations a
           ON a.bed_id = b.bed_id
          AND a.start_date <= COALESCE(
              (SELECT end_date FROM allocation_period WHERE period_id = 1),
              '1000-01-01'
          )
          AND a.end_date >= COALESCE(
              (SELECT start_date FROM allocation_period WHERE period_id = 1),
              '9999-12-31'
          )
          AND (
              a.status = 'Confirmed'
              OR (a.status = 'Pending' AND a.expires_at > NOW())
          )
         GROUP BY r.room_id, r.hostel_id, h.hostel_name, h.hostel_type,
                  r.building_code, r.floor_number, r.room_number,
                  r.max_occupants, r.price_per_occupant, r.status, r.created_at
         ORDER BY h.hostel_name, r.building_code, r.floor_number, r.room_number",
        $perPage,
        $offset
    );

    $beds = $fetchPage(
        $db,
        "SELECT b.bed_id, b.bed_number, b.room_id, h.hostel_id,
                h.hostel_name, r.building_code, r.floor_number, r.room_number,
                a.allocation_id, a.status AS allocation_status,
                CASE
                    WHEN a.status = 'Pending' AND a.expires_at <= NOW() THEN 'Expired'
                    ELSE a.status
                END AS current_status,
                a.start_date, a.end_date, a.expires_at,
                s.student_id, s.matric_number, s.first_name, s.last_name,
                s.email AS student_email
         FROM bed_spaces b
         JOIN rooms r ON r.room_id = b.room_id
         JOIN hostels h ON h.hostel_id = r.hostel_id
         LEFT JOIN allocations a
           ON a.bed_id = b.bed_id
          AND a.start_date <= COALESCE(
              (SELECT end_date FROM allocation_period WHERE period_id = 1),
              '1000-01-01'
          )
          AND a.end_date >= COALESCE(
              (SELECT start_date FROM allocation_period WHERE period_id = 1),
              '9999-12-31'
          )
          AND (
              a.status = 'Confirmed'
              OR (a.status = 'Pending' AND a.expires_at > NOW())
          )
         LEFT JOIN students s ON s.user_id = a.user_id
         ORDER BY h.hostel_name, r.building_code, r.floor_number,
                  r.room_number, b.bed_number",
        $perPage,
        $offset
    );

    $students = $fetchPage(
        $db,
        "SELECT s.student_id, s.user_id, s.matric_number, s.first_name,
                s.last_name, s.email, s.phone, s.department, s.level,
                s.gender, s.created_at
         FROM students s
         ORDER BY s.last_name, s.first_name, s.student_id",
        $perPage,
        $offset
    );

    $allocations = $fetchPage(
        $db,
        "SELECT a.allocation_id, a.user_id, s.matric_number, s.first_name,
                s.last_name, s.email AS student_email, a.room_id, h.hostel_name,
                r.building_code, r.floor_number, r.room_number,
                a.bed_id, b.bed_number, a.start_date, a.end_date, a.status,
                a.expires_at, a.created_at, a.confirmed_at,
                CASE
                    WHEN a.status = 'Pending' AND a.expires_at <= NOW() THEN 'Expired'
                    ELSE a.status
                END AS current_status
         FROM allocations a
         JOIN students s ON s.user_id = a.user_id
         JOIN rooms r ON r.room_id = a.room_id
         JOIN hostels h ON h.hostel_id = r.hostel_id
         JOIN bed_spaces b ON b.bed_id = a.bed_id
         ORDER BY a.created_at DESC, a.allocation_id DESC",
        $perPage,
        $offset
    );

    $payments = $fetchPage(
        $db,
        "SELECT p.payment_id, p.allocation_id, p.reference,
                p.amount_kobo, p.status, p.gateway_reference,
                p.created_at, p.updated_at,
                s.matric_number, s.first_name, s.last_name, s.email AS student_email,
                h.hostel_name, r.building_code, r.floor_number, r.room_number,
                b.bed_number, a.start_date, a.end_date
         FROM payments p
         JOIN allocations a ON a.allocation_id = p.allocation_id
         JOIN students s ON s.user_id = a.user_id
         JOIN rooms r ON r.room_id = a.room_id
         JOIN hostels h ON h.hostel_id = r.hostel_id
         JOIN bed_spaces b ON b.bed_id = a.bed_id
         ORDER BY p.created_at DESC, p.payment_id DESC",
        $perPage,
        $offset
    );

    foreach ($rooms as &$room) {
        foreach (["room_id", "hostel_id", "floor_number", "max_occupants", "total_beds", "occupied_beds", "available_beds"] as $key) {
            $room[$key] = (int) $room[$key];
        }
    }
    unset($room);

    foreach ($allocations as &$allocation) {
        foreach (["allocation_id", "user_id", "room_id", "bed_id"] as $key) {
            $allocation[$key] = (int) $allocation[$key];
        }
    }
    unset($allocation);

    foreach ($admins as &$admin) {
        $admin["user_id"] = (int) $admin["user_id"];
    }
    unset($admin);

    foreach ($payments as &$payment) {
        $payment["payment_id"] = (int) $payment["payment_id"];
        $payment["allocation_id"] = (int) $payment["allocation_id"];
        $payment["amount_kobo"] = (int) $payment["amount_kobo"];
        $payment["amount_ngn"] = number_format($payment["amount_kobo"] / 100, 2, ".", "");
    }
    unset($payment);

    $collectionTotals = [];
    foreach (["hostels", "rooms", "bed_spaces", "students", "allocations", "payments"] as $collection) {
        $collectionTotals[$collection] = $countQuery($db, $collection);
    }
    $collectionTotals["admins"] = $summary["admins"];

    foreach ($beds as &$bed) {
        $bed["bed_id"] = (int) $bed["bed_id"];
        $bed["room_id"] = (int) $bed["room_id"];
        $bed["hostel_id"] = (int) $bed["hostel_id"];
        $bed["floor_number"] = (int) $bed["floor_number"];
        $bed["allocation_id"] = $bed["allocation_id"] === null
            ? null
            : (int) $bed["allocation_id"];
        $bed["student_id"] = $bed["student_id"] === null
            ? null
            : (int) $bed["student_id"];
        $bed["status"] = $bed["current_status"] ?? "Available";
        unset($bed["current_status"]);
    }
    unset($bed);

    echo json_encode([
        "success" => true,
        "allocation_period" => $period,
        "summary" => $summary,
        "pagination" => [
            "page" => $page,
            "per_page" => $perPage,
            "totals" => $collectionTotals
        ],
        "hostels" => $hostels,
        "rooms" => $rooms,
        "beds" => $beds,
        "students" => $students,
        "admins" => $admins,
        "allocations" => $allocations,
        "payments" => $payments
    ]);
} catch (Throwable $error) {
    error_log("Admin dashboard retrieval failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load the admin dashboard."
    ]);
}
