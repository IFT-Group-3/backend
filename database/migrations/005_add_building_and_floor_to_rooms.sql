ALTER TABLE rooms
    ADD COLUMN building_code VARCHAR(10) NOT NULL DEFAULT 'A' AFTER hostel_id,
    ADD COLUMN floor_number INT NOT NULL DEFAULT 0 AFTER building_code,
    DROP INDEX uq_rooms_hostel_room_number,
    ADD UNIQUE KEY uq_rooms_hostel_building_floor_room (
        hostel_id, building_code, floor_number, room_number
    ),
    ADD CONSTRAINT chk_rooms_floor_number CHECK (floor_number BETWEEN 0 AND 1000);

ALTER TABLE bed_spaces
    MODIFY bed_number VARCHAR(100) NOT NULL;

UPDATE bed_spaces b
JOIN rooms r ON r.room_id = b.room_id
SET b.bed_number = CONCAT(
    r.building_code,
    '-',
    r.floor_number,
    '-',
    r.room_number,
    '-BED-',
    SUBSTRING_INDEX(b.bed_number, '-BED-', -1)
);
