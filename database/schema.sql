CREATE TABLE IF NOT EXISTS
    users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(254) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM ('passenger', 'admin') NOT NULL DEFAULT 'passenger',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_email (email)
    ) ENGINE = InnoDB DEFAULT CHARACTER
SET
    = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS
    airports (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        iata_code CHAR(3) NOT NULL,
        name VARCHAR(150) NOT NULL,
        city VARCHAR(100) NOT NULL,
        country VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_airports_iata_code (iata_code)
    ) ENGINE = InnoDB DEFAULT CHARACTER
SET
    = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS
    flights (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        flight_number VARCHAR(10) NOT NULL,
        origin_airport_id BIGINT UNSIGNED NOT NULL,
        destination_airport_id BIGINT UNSIGNED NOT NULL,
        departure_at DATETIME NOT NULL,
        arrival_at DATETIME NOT NULL,
        seat_capacity SMALLINT UNSIGNED NOT NULL,
        ticket_price DECIMAL(10, 2) NULL,
        status ENUM ('scheduled', 'delayed', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_flights_number_departure (flight_number, departure_at),
        KEY idx_flights_origin_departure_status (origin_airport_id, departure_at, status),
        KEY idx_flights_destination_departure (destination_airport_id, departure_at),
        CONSTRAINT fk_flights_origin_airport FOREIGN KEY (origin_airport_id) REFERENCES airports (id) ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_flights_destination_airport FOREIGN KEY (destination_airport_id) REFERENCES airports (id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE = InnoDB DEFAULT CHARACTER
SET
    = utf8mb4 COLLATE = utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS
    bookings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_reference CHAR(24) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        flight_id BIGINT UNSIGNED NOT NULL,
        ticket_price DECIMAL(10, 2) NULL,
        status ENUM ('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_bookings_reference (booking_reference),
        KEY idx_bookings_user_status (user_id, status),
        KEY idx_bookings_flight_status (flight_id, status),
        CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_bookings_flight FOREIGN KEY (flight_id) REFERENCES flights (id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE = InnoDB DEFAULT CHARACTER
SET
    = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS ticket_price DECIMAL(10, 2) NULL AFTER flight_id;

CREATE TABLE IF NOT EXISTS
    passengers_manifest (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id BIGINT UNSIGNED NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_manifest_booking (booking_id),
        CONSTRAINT fk_manifest_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARACTER
SET
    = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS
    booking_seat_assignments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        flight_id BIGINT UNSIGNED NOT NULL,
        booking_id BIGINT UNSIGNED NOT NULL,
        manifest_id BIGINT UNSIGNED NOT NULL,
        seat_label VARCHAR(8) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_booking_seat_flight_label (flight_id, seat_label),
        UNIQUE KEY uq_booking_seat_manifest (manifest_id),
        KEY idx_booking_seat_booking (booking_id),
        CONSTRAINT fk_booking_seat_flight FOREIGN KEY (flight_id) REFERENCES flights (id) ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_booking_seat_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_booking_seat_manifest FOREIGN KEY (manifest_id) REFERENCES passengers_manifest (id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARACTER
SET
    = utf8mb4 COLLATE = utf8mb4_unicode_ci;