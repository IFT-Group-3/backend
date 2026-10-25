<?php

class Database
{
    private string $host;
    private string $dbName;
    private string $username;
    private string $password;

    public function __construct()
    {
        require_once __DIR__ . "/env.php";

        $this->host = environment_value("DB_HOST", "127.0.0.1");
        $this->dbName = environment_value("DB_NAME", "hostel_system");
        $this->username = environment_value("DB_USERNAME", "root");
        $this->password = environment_value("DB_PASSWORD", "");
    }

    public function connect(): PDO
    {
        try {
            $pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4",
                $this->username,
                $this->password
            );

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;

        } catch (PDOException $e) {
            die("Database connection failed.");
        }
    }
}