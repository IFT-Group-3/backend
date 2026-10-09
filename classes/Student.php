<?php

class Student
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function register(
        string $email,
        string $password,
        string $matricNo,
        string $name,
        ?string $department,
        ?int $level
    ): int {
        $this->db->beginTransaction();

        try {
            $userStatement = $this->db->prepare(
                "INSERT INTO users (email, password, role)
                 VALUES (:email, :password, 'student')"
            );
            $userStatement->execute([
                "email" => $email,
                "password" => password_hash($password, PASSWORD_DEFAULT)
            ]);

            $userId = (int) $this->db->lastInsertId();
            $studentStatement = $this->db->prepare(
                "INSERT INTO students (user_id, matric_no, name, department, level)
                 VALUES (:user_id, :matric_no, :name, :department, :level)"
            );
            $studentStatement->execute([
                "user_id" => $userId,
                "matric_no" => $matricNo,
                "name" => $name,
                "department" => $department,
                "level" => $level
            ]);

            $this->db->commit();

            return $userId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }
}