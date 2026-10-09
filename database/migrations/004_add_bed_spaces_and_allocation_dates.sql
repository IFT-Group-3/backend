-- Existing allocations have no reliable bed number or date range; this
-- development migration clears those allocations and their payment records.
DELETE FROM payments;
DELETE FROM allocations;

CREATE TABLE bed_spaces (
    bed_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    bed_number VARCHAR(60) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bed_spaces_room_number (room_id, bed_number),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON DELETE CASCADE
);

INSERT INTO bed_spaces (room_id, bed_number)
SELECT
    rooms.room_id,
    CONCAT(rooms.room_number, '-BED-', LPAD(bed_sequence.bed_index, 4, '0'))
FROM rooms
JOIN (
    SELECT units.digit + tens.digit * 10 + hundreds.digit * 100 + 1 AS bed_index
    FROM (
        SELECT 0 AS digit UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
        SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
        SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    ) AS units
    CROSS JOIN (
        SELECT 0 AS digit UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
        SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
        SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    ) AS tens
    CROSS JOIN (
        SELECT 0 AS digit UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
        SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
        SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    ) AS hundreds
) AS bed_sequence ON bed_sequence.bed_index <= rooms.max_occupants;

CREATE TABLE allocation_period (
    period_id TINYINT PRIMARY KEY,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_allocation_period_singleton CHECK (period_id = 1),
    CONSTRAINT chk_allocation_period_dates CHECK (end_date >= start_date)
);

ALTER TABLE allocations
    ADD COLUMN bed_id INT NOT NULL AFTER room_id,
    ADD COLUMN start_date DATE NOT NULL AFTER bed_id,
    ADD COLUMN end_date DATE NOT NULL AFTER start_date,
    ADD INDEX idx_allocations_room_id (room_id),
    DROP INDEX idx_allocations_room_status_expiry,
    ADD INDEX idx_allocations_bed_dates_status (bed_id, start_date, end_date, status),
    ADD CONSTRAINT fk_allocations_bed_space
        FOREIGN KEY (bed_id) REFERENCES bed_spaces(bed_id)
        ON DELETE CASCADE;
