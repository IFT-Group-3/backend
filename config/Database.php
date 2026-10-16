<?php

class Database
{
    private string $host = "sql208.iceiy.com";
    private string $dbName = "icei_43194901_hostel_systems";
    private string $username = "icei_43194901";
    private string $password = "qwerty1290.";

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