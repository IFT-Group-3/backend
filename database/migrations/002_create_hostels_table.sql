CREATE TABLE hostels (
    hostel_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(100) NOT NULL,
    hostel_type ENUM('Male', 'Female', 'Mixed') NOT NULL,
    location VARCHAR(150) NOT NULL,
    description TEXT,
    total_rooms INT NOT NULL DEFAULT 0,
    status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hostels_hostel_name (hostel_name),
    INDEX idx_hostels_status (status),
    CONSTRAINT chk_hostels_total_rooms CHECK (total_rooms >= 0)
);
