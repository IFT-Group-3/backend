CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_number VARCHAR(30) NOT NULL,
    max_occupants INT NOT NULL,
    price_per_occupant DECIMAL(10, 2) NOT NULL,
    status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rooms_hostel_room_number (hostel_id, room_number),
    INDEX idx_rooms_status (status),
    CONSTRAINT chk_rooms_max_occupants CHECK (max_occupants BETWEEN 1 AND 1000),
    CONSTRAINT chk_rooms_price CHECK (price_per_occupant > 0),
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id)
        ON DELETE CASCADE
);

CREATE TABLE allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Cancelled', 'Expired') NOT NULL DEFAULT 'Pending',
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME,
    INDEX idx_allocations_room_status_expiry (room_id, status, expires_at),
    INDEX idx_allocations_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON DELETE CASCADE
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    allocation_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,
    amount_kobo BIGINT UNSIGNED NOT NULL,
    status ENUM('Pending', 'Paid', 'Failed') NOT NULL DEFAULT 'Pending',
    gateway_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_allocation_status (allocation_id, status),
    FOREIGN KEY (allocation_id) REFERENCES allocations(allocation_id)
        ON DELETE CASCADE
);
