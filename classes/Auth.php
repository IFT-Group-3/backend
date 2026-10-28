<?php

class Auth
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        require_once __DIR__ . "/../utils/session.php";
        start_app_session();
    }

    public function login(string $email, string $password): array
    {
        $stmt = $this->db->prepare(
            "SELECT user_id, email, password, role
             FROM users
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute([
            "email" => $email
        ]);

        $user = $stmt->fetch();

        if (
            !$user
            || !password_verify($password, $user["password"])
            || !in_array($user["role"], ["student", "admin"], true)
        ) {
            return [
                "success" => false,
                "message" => "Invalid email or password."
            ];
        }

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];

        $profile = [
            "id" => $user["user_id"],
            "email" => $user["email"],
            "role" => $user["role"]
        ];

        if ($user["role"] === "student") {
            $studentStmt = $this->db->prepare(
                "SELECT first_name, last_name, matric_number
                 FROM students
                 WHERE user_id = :user_id
                 LIMIT 1"
            );
            $studentStmt->execute(["user_id" => $user["user_id"]]);
            $student = $studentStmt->fetch();

            if ($student) {
                $profile["name"] = trim($student["first_name"] . " " . $student["last_name"]);
                $profile["matric_no"] = $student["matric_number"];
            }
        }

        return [
            "success" => true,
            "message" => "Login successful.",
            "user" => $profile
        ];
    }

    public function registerAdmin(string $email, string $password): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, password, role)
             VALUES (:email, :password, 'admin')"
        );
        $stmt->execute([
            "email" => $email,
            "password" => password_hash($password, PASSWORD_DEFAULT)
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function bootstrapFirstAdmin(string $email, string $password): ?int
    {
        $lockStatement = $this->db->query(
            "SELECT GET_LOCK('hostel_system_admin_bootstrap', 5)"
        );

        if ((int) $lockStatement->fetchColumn() !== 1) {
            throw new RuntimeException("Could not acquire admin bootstrap lock.");
        }

        try {
            $adminCount = (int) $this->db
                ->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")
                ->fetchColumn();

            if ($adminCount > 0) {
                return null;
            }

            return $this->registerAdmin($email, $password);
        } finally {
            $this->db->query(
                "SELECT RELEASE_LOCK('hostel_system_admin_bootstrap')"
            );
        }
    }

    public static function logout(): void
    {
        require_once __DIR__ . "/../utils/session.php";
        start_app_session();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                "",
                [
                    "expires" => time() - 42000,
                    "path" => $params["path"],
                    "domain" => $params["domain"],
                    "secure" => $params["secure"],
                    "httponly" => $params["httponly"],
                    "samesite" => $params["samesite"] ?? "Lax"
                ]
            );
        }

        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION["user_id"]);
    }

    public function isAdmin(): bool
    {
        return isset($_SESSION["role"])
            && $_SESSION["role"] === "admin";
    }

    public function getUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        if (!isset($_SESSION["email"], $_SESSION["role"])) {
            return null;
        }

        return [
            "id" => $_SESSION["user_id"],
            "email" => $_SESSION["email"],
            "role" => $_SESSION["role"]
        ];
    }
}