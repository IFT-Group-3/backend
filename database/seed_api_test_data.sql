-- Additive API test data for a database already using the current schema.
-- Safe to run more than once: existing records are kept and duplicate rooms
-- and beds are skipped. This script does not create users or payments.

INSERT INTO hostels (
    hostel_name,
    hostel_type,
    location,
    description,
    total_rooms,
    status
)
SELECT
    'API Test Mixed Hostel',
    'Mixed',
    'API Test Campus',
    'Sample hostel for testing room availability and reservations.',
    2,
    'Available'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM hostels
    WHERE hostel_name = 'API Test Mixed Hostel'
      AND hostel_type = 'Mixed'
      AND location = 'API Test Campus'
);

INSERT IGNORE INTO rooms (
    hostel_id,
    building_code,
    floor_number,
    room_number,
    max_occupants,
    price_per_occupant,
    status
)
SELECT
    h.hostel_id,
    'A',
    0,
    '101',
    4,
    1000.00,
    'Available'
FROM hostels h
WHERE h.hostel_name = 'API Test Mixed Hostel'
  AND h.hostel_type = 'Mixed'
  AND h.location = 'API Test Campus';

INSERT IGNORE INTO rooms (
    hostel_id,
    building_code,
    floor_number,
    room_number,
    max_occupants,
    price_per_occupant,
    status
)
SELECT
    h.hostel_id,
    'A',
    0,
    '102',
    4,
    1000.00,
    'Available'
FROM hostels h
WHERE h.hostel_name = 'API Test Mixed Hostel'
  AND h.hostel_type = 'Mixed'
  AND h.location = 'API Test Campus';

INSERT IGNORE INTO bed_spaces (room_id, bed_number)
SELECT
    r.room_id,
    CONCAT(
        r.building_code,
        '-',
        r.floor_number,
        '-',
        r.room_number,
        '-BED-',
        LPAD(bed_sequence.bed_index, 4, '0')
    )
FROM rooms r
JOIN hostels h ON h.hostel_id = r.hostel_id
CROSS JOIN (
    SELECT 1 AS bed_index
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
) AS bed_sequence
WHERE h.hostel_name = 'API Test Mixed Hostel'
  AND h.hostel_type = 'Mixed'
  AND h.location = 'API Test Campus'
  AND r.building_code = 'A'
  AND r.floor_number = 0
  AND r.room_number IN ('101', '102')
  AND bed_sequence.bed_index <= r.max_occupants;

INSERT INTO allocation_period (period_id, start_date, end_date)
SELECT 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY)
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM allocation_period
    WHERE period_id = 1
);
