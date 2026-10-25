<?php

require_once __DIR__ . "/env.php";

class Database
{
    public function connect(): PDO
    {
        $host = get_env("DB_HOST", "127.0.0.1");
        $dbName = get_env("DB_NAME", "hostel_system");
        $username = get_env("DB_USERNAME", "root");
        $password = get_env("DB_PASSWORD", "");

        if (
            !is_string($host)
            || trim($host) === ""
            || !is_string($dbName)
            || trim($dbName) === ""
            || !is_string($username)
            || trim($username) === ""
            || !is_string($password)
        ) {
            throw new RuntimeException("Database environment variables are invalid.");
        }

        try {
            return new PDO(
                "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $error) {
            error_log("Database connection failed: " . $error->getMessage());
            throw new RuntimeException("Database connection failed.", 0, $error);
        }
    }
}
