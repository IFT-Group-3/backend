<?php

class Database
{
    public function connect(): PDO
    {
        try {
            $localConfigPath = __DIR__ . "/database.local.php";
            $localConfig = is_file($localConfigPath)
                ? require $localConfigPath
                : [];

            if (!is_array($localConfig)) {
                throw new RuntimeException("Database config must return an array.");
            }

            $host = getenv("DB_HOST") ?: ($localConfig["host"] ?? "127.0.0.1");
            $dbName = getenv("DB_NAME") ?: ($localConfig["name"] ?? "hostel_system");
            $username = getenv("DB_USER") ?: ($localConfig["user"] ?? "root");
            $password = getenv("DB_PASSWORD");
            if ($password === false) {
                $password = $localConfig["password"] ?? "";
            }

            foreach (["DB_HOST" => $host, "DB_NAME" => $dbName, "DB_USER" => $username] as $name => $value) {
                if (!is_string($value) || trim($value) === "") {
                    throw new RuntimeException($name . " must be a non-empty string.");
                }
            }
            if (!is_string($password)) {
                throw new RuntimeException("DB_PASSWORD must be a string.");
            }

            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            return $pdo;
        } catch (Throwable $error) {
            error_log("Database connection failed: " . $error->getMessage());
            http_response_code(500);
            header("Content-Type: application/json");
            echo json_encode([
                "success" => false,
                "message" => "Database connection failed. Check the server configuration and logs."
            ]);
            exit;
        }
    }
}