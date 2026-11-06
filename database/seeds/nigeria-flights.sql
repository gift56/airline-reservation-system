-- Fictional demonstration airports, schedules, and NGN fares only.
-- Apply database/migrations/06-flight-ticket-price.sql before importing this file.
-- Demo flights use fixed dates so rerunning this seed does not create new occurrences.

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
