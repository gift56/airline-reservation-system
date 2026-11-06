-- Safe to apply again on MariaDB 10.11.16; existing fares and flights are preserved.
ALTER TABLE flights
    ADD COLUMN IF NOT EXISTS ticket_price DECIMAL(10, 2) NULL AFTER seat_capacity;

DROP TRIGGER IF EXISTS flights_validate_before_insert;
DROP TRIGGER IF EXISTS flights_validate_before_update;

DELIMITER //

CREATE TRIGGER flights_validate_before_insert
BEFORE INSERT ON flights
FOR EACH ROW
BEGIN
    IF NEW.origin_airport_id = NEW.destination_airport_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Origin and destination airports must differ.';
    END IF;

    IF NEW.arrival_at <= NEW.departure_at THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Arrival must be after departure.';
    END IF;

    IF NEW.seat_capacity = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Seat capacity must be positive.';
    END IF;

    IF NEW.ticket_price IS NULL OR NEW.ticket_price <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ticket price must be positive.';
    END IF;
END//

CREATE TRIGGER flights_validate_before_update
BEFORE UPDATE ON flights
FOR EACH ROW
BEGIN
    IF NEW.origin_airport_id = NEW.destination_airport_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Origin and destination airports must differ.';
    END IF;

    IF NEW.arrival_at <= NEW.departure_at THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Arrival must be after departure.';
    END IF;

    IF NEW.seat_capacity = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Seat capacity must be positive.';
    END IF;

    IF NEW.ticket_price IS NULL OR NEW.ticket_price <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ticket price must be positive.';
    END IF;
END//

DELIMITER ;
