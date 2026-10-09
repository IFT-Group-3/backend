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
        string $matricNumber,
        string $firstName,
        string $lastName,
        ?string $phone,
        string $department,
        int $level,
        string $gender
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
                "INSERT INTO students (
                    user_id, matric_number, first_name, last_name, email,
                    phone, department, level, gender
                 )
                 VALUES (
                    :user_id, :matric_number, :first_name, :last_name, :email,
                    :phone, :department, :level, :gender
                 )"
            );
            $studentStatement->execute([
                "user_id" => $userId,
                "matric_number" => $matricNumber,
                "first_name" => $firstName,
                "last_name" => $lastName,
                "email" => $email,
                "phone" => $phone,
                "department" => $department,
                "level" => $level,
                "gender" => $gender
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