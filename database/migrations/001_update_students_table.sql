-- Destructive development migration: removes student accounts and profiles.
-- Admin accounts are not affected.
DELETE FROM users
WHERE role = 'student';

ALTER TABLE students
    DROP COLUMN matric_no,
    DROP COLUMN name,
    MODIFY department VARCHAR(100) NOT NULL,
    MODIFY level INT NOT NULL,
    ADD COLUMN matric_number VARCHAR(30) NOT NULL UNIQUE AFTER user_id,
    ADD COLUMN first_name VARCHAR(50) NOT NULL AFTER matric_number,
    ADD COLUMN last_name VARCHAR(50) NOT NULL AFTER first_name,
    ADD COLUMN email VARCHAR(100) NOT NULL UNIQUE AFTER last_name,
    ADD COLUMN phone VARCHAR(20) NULL AFTER email,
    ADD COLUMN gender ENUM('Male', 'Female') NOT NULL AFTER level,
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER gender;
