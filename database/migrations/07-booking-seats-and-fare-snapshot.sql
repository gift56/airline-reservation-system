ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS ticket_price DECIMAL(10, 2) NULL AFTER flight_id;

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
    ) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
