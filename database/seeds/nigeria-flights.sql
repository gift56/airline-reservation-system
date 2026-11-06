-- Fictional demonstration airports, schedules, and NGN fares only.
-- Apply database/migrations/06-flight-ticket-price.sql before importing this file.
-- Rolling demo schedules are dated relative to CURRENT_DATE so they are usable
-- for near-term searches. Re-running on the same day does not duplicate them.

INSERT INTO airports (iata_code, name, city, country)
SELECT seed_row.iata_code, seed_row.name, seed_row.city, 'Nigeria'
FROM (
    SELECT 'LOS' AS iata_code, 'Murtala Muhammed International Airport' AS name, 'Lagos' AS city
    UNION ALL SELECT 'ABV', 'Nnamdi Azikiwe International Airport', 'Abuja'
    UNION ALL SELECT 'PHC', 'Port Harcourt International Airport', 'Port Harcourt'
    UNION ALL SELECT 'KAN', 'Mallam Aminu Kano International Airport', 'Kano'
    UNION ALL SELECT 'ENU', 'Akanu Ibiam International Airport', 'Enugu'
    UNION ALL SELECT 'CBQ', 'Margaret Ekpo International Airport', 'Calabar'
) AS seed_row
WHERE NOT EXISTS (
    SELECT 1
    FROM airports current_row
    WHERE current_row.iata_code = seed_row.iata_code
);

INSERT INTO flights (
    flight_number,
    origin_airport_id,
    destination_airport_id,
    departure_at,
    arrival_at,
    seat_capacity,
    ticket_price
)
SELECT
    seed_row.flight_number,
    origin.id,
    destination.id,
    seed_row.departure_at,
    seed_row.arrival_at,
    seed_row.seat_capacity,
    seed_row.ticket_price
FROM (
    SELECT 'DM101' AS flight_number, 'LOS' AS origin_code, 'ABV' AS destination_code,
        '2030-02-01 07:00:00' AS departure_at, '2030-02-01 08:15:00' AS arrival_at,
        150 AS seat_capacity, 98500.00 AS ticket_price
    UNION ALL SELECT 'DM102', 'ABV', 'LOS', '2030-02-01 09:00:00', '2030-02-01 10:15:00', 150, 102000.00
    UNION ALL SELECT 'DM103', 'LOS', 'PHC', '2030-02-01 08:30:00', '2030-02-01 09:45:00', 120, 87500.00
    UNION ALL SELECT 'DM104', 'PHC', 'LOS', '2030-02-01 11:00:00', '2030-02-01 12:15:00', 120, 91000.00
    UNION ALL SELECT 'DM105', 'LOS', 'KAN', '2030-02-01 10:00:00', '2030-02-01 11:40:00', 140, 118000.00
    UNION ALL SELECT 'DM106', 'KAN', 'LOS', '2030-02-01 13:00:00', '2030-02-01 14:40:00', 140, 121500.00
    UNION ALL SELECT 'DM107', 'ABV', 'ENU', '2030-02-01 12:00:00', '2030-02-01 13:10:00', 100, 72000.00
    UNION ALL SELECT 'DM108', 'ENU', 'ABV', '2030-02-01 15:00:00', '2030-02-01 16:10:00', 100, 74500.00
    UNION ALL SELECT 'DM109', 'LOS', 'CBQ', '2030-02-02 07:30:00', '2030-02-02 09:00:00', 110, 89500.00
    UNION ALL SELECT 'DM110', 'CBQ', 'LOS', '2030-02-02 10:30:00', '2030-02-02 12:00:00', 110, 92500.00
    UNION ALL SELECT 'DM111', 'ABV', 'PHC', '2030-02-02 13:00:00', '2030-02-02 14:25:00', 130, 94000.00
    UNION ALL SELECT 'DM112', 'PHC', 'ABV', '2030-02-02 16:00:00', '2030-02-02 17:25:00', 130, 97000.00
) AS seed_row
INNER JOIN airports origin ON origin.iata_code = seed_row.origin_code
INNER JOIN airports destination ON destination.iata_code = seed_row.destination_code
WHERE NOT EXISTS (
    SELECT 1
    FROM flights current_row
    WHERE current_row.flight_number = seed_row.flight_number
      AND current_row.departure_at = seed_row.departure_at
);

INSERT INTO flights (
    flight_number,
    origin_airport_id,
    destination_airport_id,
    departure_at,
    arrival_at,
    seat_capacity,
    ticket_price
)
SELECT
    schedule.flight_number,
    origin.id,
    destination.id,
    CAST(CONCAT(DATE_ADD(CURRENT_DATE, INTERVAL calendar.day_offset DAY), ' ', schedule.departure_time) AS DATETIME),
    CAST(CONCAT(DATE_ADD(CURRENT_DATE, INTERVAL calendar.day_offset DAY), ' ', schedule.arrival_time) AS DATETIME),
    schedule.seat_capacity,
    schedule.ticket_price
FROM (
    SELECT 1 AS day_offset
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
    UNION ALL SELECT 13
    UNION ALL SELECT 14
) AS calendar
CROSS JOIN (
    SELECT 'DM201' AS flight_number, 'LOS' AS origin_code, 'ABV' AS destination_code,
        '06:45:00' AS departure_time, '08:00:00' AS arrival_time, 150 AS seat_capacity, 98500.00 AS ticket_price
    UNION ALL SELECT 'DM202', 'ABV', 'LOS', '09:30:00', '10:45:00', 150, 102000.00
    UNION ALL SELECT 'DM203', 'LOS', 'PHC', '08:15:00', '09:30:00', 120, 87500.00
    UNION ALL SELECT 'DM204', 'PHC', 'LOS', '11:20:00', '12:35:00', 120, 91000.00
    UNION ALL SELECT 'DM205', 'LOS', 'KAN', '10:10:00', '11:50:00', 140, 118000.00
    UNION ALL SELECT 'DM206', 'KAN', 'LOS', '13:15:00', '14:55:00', 140, 121500.00
    UNION ALL SELECT 'DM207', 'ABV', 'ENU', '12:05:00', '13:15:00', 100, 72000.00
    UNION ALL SELECT 'DM208', 'ENU', 'ABV', '15:10:00', '16:20:00', 100, 74500.00
    UNION ALL SELECT 'DM209', 'LOS', 'CBQ', '07:35:00', '09:05:00', 110, 89500.00
    UNION ALL SELECT 'DM210', 'CBQ', 'LOS', '10:40:00', '12:10:00', 110, 92500.00
    UNION ALL SELECT 'DM211', 'ABV', 'PHC', '13:05:00', '14:30:00', 130, 94000.00
    UNION ALL SELECT 'DM212', 'PHC', 'ABV', '16:10:00', '17:35:00', 130, 97000.00
) AS schedule
INNER JOIN airports origin ON origin.iata_code = schedule.origin_code
INNER JOIN airports destination ON destination.iata_code = schedule.destination_code
WHERE NOT EXISTS (
    SELECT 1
    FROM flights current_row
    WHERE current_row.flight_number = schedule.flight_number
      AND current_row.departure_at = CAST(
          CONCAT(DATE_ADD(CURRENT_DATE, INTERVAL calendar.day_offset DAY), ' ', schedule.departure_time)
          AS DATETIME
      )
);
