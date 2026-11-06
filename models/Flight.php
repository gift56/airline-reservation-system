<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use PDO;

final class Flight
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array{id: string, iata_code: string, name: string, city: string, country: string}>
     */
    public function listAirports(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, iata_code, name, city, country FROM airports ORDER BY city, iata_code'
        );

        return array_map(
            static fn (array $airport): array => [
                'id' => (string) $airport['id'],
                'iata_code' => (string) $airport['iata_code'],
                'name' => (string) $airport['name'],
                'city' => (string) $airport['city'],
                'country' => (string) $airport['country'],
            ],
            $statement->fetchAll()
        );
    }

    public function createAirport(string $iataCode, string $name, string $city, string $country): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO airports (iata_code, name, city, country)
             VALUES (:iata_code, :name, :city, :country)'
        );
        $statement->execute([
            'iata_code' => $iataCode,
            'name' => $name,
            'city' => $city,
            'country' => $country,
        ]);
    }

    /**
     * @return array{id: string, iata_code: string, name: string, city: string, country: string}|null
     */
    public function findAirportById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, iata_code, name, city, country
             FROM airports
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $airport = $statement->fetch();

        if (!is_array($airport)) {
            return null;
        }

        return [
            'id' => (string) $airport['id'],
            'iata_code' => (string) $airport['iata_code'],
            'name' => (string) $airport['name'],
            'city' => (string) $airport['city'],
            'country' => (string) $airport['country'],
        ];
    }

    public function updateAirport(
        int $id,
        string $iataCode,
        string $name,
        string $city,
        string $country
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE airports
             SET iata_code = :iata_code,
                 name = :name,
                 city = :city,
                 country = :country
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'iata_code' => $iataCode,
            'name' => $name,
            'city' => $city,
            'country' => $country,
        ]);

        return $statement->rowCount() > 0 || $this->findAirportById($id) !== null;
    }

    /**
     * @return 'deleted'|'not_found'|'bookings_exist'
     */
    public function deleteAirport(int $id): string
    {
        $this->pdo->beginTransaction();

        try {
            $airportStatement = $this->pdo->prepare(
                'SELECT id FROM airports WHERE id = :id FOR UPDATE'
            );
            $airportStatement->execute(['id' => $id]);
            if ($airportStatement->fetchColumn() === false) {
                $this->pdo->commit();

                return 'not_found';
            }

            $flightStatement = $this->pdo->prepare(
                'SELECT id
                 FROM flights
                 WHERE origin_airport_id = :origin_id
                    OR destination_airport_id = :destination_id
                 FOR UPDATE'
            );
            $flightStatement->execute([
                'origin_id' => $id,
                'destination_id' => $id,
            ]);
            while ($flightStatement->fetchColumn() !== false) {
            }

            $bookingStatement = $this->pdo->prepare(
                'SELECT bookings.id
                 FROM bookings
                 INNER JOIN flights ON flights.id = bookings.flight_id
                 WHERE flights.origin_airport_id = :origin_id
                    OR flights.destination_airport_id = :destination_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $bookingStatement->execute([
                'origin_id' => $id,
                'destination_id' => $id,
            ]);
            if ($bookingStatement->fetchColumn() !== false) {
                $this->pdo->rollBack();

                return 'bookings_exist';
            }

            $deleteFlights = $this->pdo->prepare(
                'DELETE FROM flights
                 WHERE origin_airport_id = :origin_id
                    OR destination_airport_id = :destination_id'
            );
            $deleteFlights->execute([
                'origin_id' => $id,
                'destination_id' => $id,
            ]);

            $deleteAirport = $this->pdo->prepare('DELETE FROM airports WHERE id = :id');
            $deleteAirport->execute(['id' => $id]);
            if ($deleteAirport->rowCount() !== 1) {
                throw new \RuntimeException('Airport deletion did not remove the requested record.');
            }

            $this->pdo->commit();

            return 'deleted';
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return int */
    public function countFlights(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM flights')->fetchColumn();
    }

    /**
     * @return list<array{
     *     id: string,
     *     flight_number: string,
     *     origin_airport_id: string,
     *     destination_airport_id: string,
     *     departure_at: string,
     *     arrival_at: string,
     *     seat_capacity: string,
     *     ticket_price: string|null,
     *     origin_iata_code: string,
     *     origin_city: string,
     *     destination_iata_code: string,
     *     destination_city: string
     * }>
     */
    public function listFlights(int $limit, int $offset): array
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Flight pagination bounds are invalid.');
        }

        $statement = $this->pdo->prepare(
            'SELECT f.id, f.flight_number, f.origin_airport_id, f.destination_airport_id,
                    f.departure_at, f.arrival_at, f.seat_capacity, f.ticket_price,
                    origin.iata_code AS origin_iata_code, origin.city AS origin_city,
                    destination.iata_code AS destination_iata_code, destination.city AS destination_city
             FROM flights f
             INNER JOIN airports origin ON origin.id = f.origin_airport_id
             INNER JOIN airports destination ON destination.id = f.destination_airport_id
             ORDER BY f.departure_at, f.flight_number'
             . ' LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $flight): array => [
                'id' => (string) $flight['id'],
                'flight_number' => (string) $flight['flight_number'],
                'origin_airport_id' => (string) $flight['origin_airport_id'],
                'destination_airport_id' => (string) $flight['destination_airport_id'],
                'departure_at' => (string) $flight['departure_at'],
                'arrival_at' => (string) $flight['arrival_at'],
                'seat_capacity' => (string) $flight['seat_capacity'],
                'ticket_price' => $flight['ticket_price'] === null ? null : (string) $flight['ticket_price'],
                'origin_iata_code' => (string) $flight['origin_iata_code'],
                'origin_city' => (string) $flight['origin_city'],
                'destination_iata_code' => (string) $flight['destination_iata_code'],
                'destination_city' => (string) $flight['destination_city'],
            ],
            $statement->fetchAll()
        );
    }

    /**
     * @return array{
     *     id: string,
     *     flight_number: string,
     *     origin_airport_id: string,
     *     destination_airport_id: string,
     *     departure_at: string,
     *     arrival_at: string,
     *     seat_capacity: string,
     *     ticket_price: string|null
     * }|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, flight_number, origin_airport_id, destination_airport_id,
                    departure_at, arrival_at, seat_capacity, ticket_price
             FROM flights
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $flight = $statement->fetch();

        if (!is_array($flight)) {
            return null;
        }

        return [
            'id' => (string) $flight['id'],
            'flight_number' => (string) $flight['flight_number'],
            'origin_airport_id' => (string) $flight['origin_airport_id'],
            'destination_airport_id' => (string) $flight['destination_airport_id'],
            'departure_at' => (string) $flight['departure_at'],
            'arrival_at' => (string) $flight['arrival_at'],
            'seat_capacity' => (string) $flight['seat_capacity'],
            'ticket_price' => $flight['ticket_price'] === null ? null : (string) $flight['ticket_price'],
        ];
    }

    public function create(
        string $flightNumber,
        int $originAirportId,
        int $destinationAirportId,
        string $departureAt,
        string $arrivalAt,
        int $seatCapacity,
        string $ticketPrice
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO flights
                (flight_number, origin_airport_id, destination_airport_id, departure_at, arrival_at, seat_capacity, ticket_price)
             VALUES
                (:flight_number, :origin_airport_id, :destination_airport_id, :departure_at, :arrival_at, :seat_capacity, :ticket_price)'
        );
        $statement->execute([
            'flight_number' => $flightNumber,
            'origin_airport_id' => $originAirportId,
            'destination_airport_id' => $destinationAirportId,
            'departure_at' => $departureAt,
            'arrival_at' => $arrivalAt,
            'seat_capacity' => $seatCapacity,
            'ticket_price' => $ticketPrice,
        ]);
    }

    public function update(
        int $id,
        string $flightNumber,
        int $originAirportId,
        int $destinationAirportId,
        string $departureAt,
        string $arrivalAt,
        int $seatCapacity,
        string $ticketPrice
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE flights
             SET flight_number = :flight_number,
                 origin_airport_id = :origin_airport_id,
                 destination_airport_id = :destination_airport_id,
                 departure_at = :departure_at,
                 arrival_at = :arrival_at,
                 seat_capacity = :seat_capacity,
                 ticket_price = :ticket_price
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'flight_number' => $flightNumber,
            'origin_airport_id' => $originAirportId,
            'destination_airport_id' => $destinationAirportId,
            'departure_at' => $departureAt,
            'arrival_at' => $arrivalAt,
            'seat_capacity' => $seatCapacity,
            'ticket_price' => $ticketPrice,
        ]);

        if ($statement->rowCount() > 0) {
            return true;
        }

        return $this->findById($id) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM flights WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
