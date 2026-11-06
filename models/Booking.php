<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class Booking
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *     id: string,
     *     flight_number: string,
     *     departure_at: string,
     *     arrival_at: string,
     *     ticket_price: string,
     *     remaining_seats: string,
     *     origin_iata_code: string,
     *     origin_city: string,
     *     destination_iata_code: string,
     *     destination_city: string
     * }|null
     */
    public function findBookableFlight(int $flightId): ?array
    {
        if ($flightId < 1) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT f.id, f.flight_number, f.departure_at, f.arrival_at, f.ticket_price,
                    f.seat_capacity - COALESCE(occupancy.occupied_seats, 0) AS remaining_seats,
                    origin.iata_code AS origin_iata_code, origin.city AS origin_city,
                    destination.iata_code AS destination_iata_code,
                    destination.city AS destination_city
             FROM flights f
             INNER JOIN airports origin ON origin.id = f.origin_airport_id
             INNER JOIN airports destination ON destination.id = f.destination_airport_id
             LEFT JOIN (
                 SELECT bookings.flight_id,
                        SUM(GREATEST(COALESCE(manifest.passenger_count, 0), 1)) AS occupied_seats
                 FROM bookings
                 LEFT JOIN (
                     SELECT booking_id, COUNT(*) AS passenger_count
                     FROM passengers_manifest
                     GROUP BY booking_id
                 ) manifest ON manifest.booking_id = bookings.id
                 WHERE bookings.status IN (\'pending\', \'confirmed\')
                 GROUP BY bookings.flight_id
             ) occupancy ON occupancy.flight_id = f.id
             WHERE f.id = :flight_id
               AND f.departure_at > NOW()
               AND f.status = \'scheduled\'
               AND f.ticket_price > 0
               AND f.seat_capacity > COALESCE(occupancy.occupied_seats, 0)'
        );
        $statement->execute(['flight_id' => $flightId]);
        $flight = $statement->fetch();

        if (!is_array($flight)) {
            return null;
        }

        return [
            'id' => (string) $flight['id'],
            'flight_number' => (string) $flight['flight_number'],
            'departure_at' => (string) $flight['departure_at'],
            'arrival_at' => (string) $flight['arrival_at'],
            'ticket_price' => (string) $flight['ticket_price'],
            'remaining_seats' => (string) $flight['remaining_seats'],
            'origin_iata_code' => (string) $flight['origin_iata_code'],
            'origin_city' => (string) $flight['origin_city'],
            'destination_iata_code' => (string) $flight['destination_iata_code'],
            'destination_city' => (string) $flight['destination_city'],
        ];
    }

    /**
     * @param list<array{first_name: string, last_name: string}> $travelers
     *
     * @return array{booking_reference: string, ticket_price: string}
     */
    public function createPendingBooking(string $userId, int $flightId, array $travelers): array
    {
        if (preg_match('/\A[1-9][0-9]{0,19}\z/', $userId) !== 1 || $flightId < 1) {
            throw new InvalidArgumentException('Booking account or flight is invalid.');
        }

        if (count($travelers) < 1 || count($travelers) > 6) {
            throw new InvalidArgumentException('A booking must include between one and six travelers.');
        }

        $validatedTravelers = [];
        foreach ($travelers as $traveler) {
            if (
                !is_array($traveler)
                || !isset($traveler['first_name'], $traveler['last_name'])
                || !is_string($traveler['first_name'])
                || !is_string($traveler['last_name'])
            ) {
                throw new InvalidArgumentException('Each traveler must have a first and last name.');
            }

            $firstName = trim($traveler['first_name']);
            $lastName = trim($traveler['last_name']);
            if (
                preg_match('/\A[^\r\n\0]{1,100}\z/u', $firstName) !== 1
                || preg_match('/\A[^\r\n\0]{1,100}\z/u', $lastName) !== 1
            ) {
                throw new InvalidArgumentException('Traveler names must be between one and 100 characters.');
            }

            $validatedTravelers[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        }

        $this->pdo->beginTransaction();

        try {
            $flightStatement = $this->pdo->prepare(
                'SELECT f.id, f.seat_capacity, f.departure_at, f.ticket_price, f.status
                 FROM flights f
                 WHERE f.id = :flight_id
                   AND f.departure_at > NOW()
                 FOR UPDATE'
            );
            $flightStatement->execute(['flight_id' => $flightId]);
            $flight = $flightStatement->fetch();

            if (
                !is_array($flight)
                || $flight['status'] !== 'scheduled'
                || $flight['ticket_price'] === null
                || (float) $flight['ticket_price'] <= 0
            ) {
                throw new DomainException('This flight is no longer available to book.');
            }
            $ticketPrice = (string) $flight['ticket_price'];

            $occupancyStatement = $this->pdo->prepare(
                'SELECT COALESCE(
                    SUM(GREATEST(COALESCE(manifest.passenger_count, 0), 1)),
                    0
                 )
                 FROM bookings
                 LEFT JOIN (
                     SELECT booking_id, COUNT(*) AS passenger_count
                     FROM passengers_manifest
                     GROUP BY booking_id
                 ) manifest ON manifest.booking_id = bookings.id
                 WHERE bookings.flight_id = :flight_id
                   AND bookings.status IN (\'pending\', \'confirmed\')'
            );
            $occupancyStatement->execute(['flight_id' => $flightId]);
            $occupiedSeats = (int) $occupancyStatement->fetchColumn();
            $remainingSeats = (int) $flight['seat_capacity'] - $occupiedSeats;

            if ($remainingSeats < count($validatedTravelers)) {
                throw new DomainException(
                    $remainingSeats > 0
                        ? 'There are not enough seats for everyone in your party. Choose fewer travelers or another flight.'
                        : 'This flight no longer has seats available.'
                );
            }

            $bookingReference = bin2hex(random_bytes(12));
            $insertBooking = $this->pdo->prepare(
                'INSERT INTO bookings (booking_reference, user_id, flight_id, status)
                 VALUES (:booking_reference, :user_id, :flight_id, \'pending\')'
            );
            $insertBooking->execute([
                'booking_reference' => $bookingReference,
                'user_id' => $userId,
                'flight_id' => $flightId,
            ]);

            $bookingId = (int) $this->pdo->lastInsertId();
            if ($bookingId < 1) {
                throw new \RuntimeException('The booking record was not created.');
            }

            $insertTraveler = $this->pdo->prepare(
                'INSERT INTO passengers_manifest (booking_id, first_name, last_name)
                 VALUES (:booking_id, :first_name, :last_name)'
            );
            foreach ($validatedTravelers as $traveler) {
                $insertTraveler->execute([
                    'booking_id' => $bookingId,
                    'first_name' => $traveler['first_name'],
                    'last_name' => $traveler['last_name'],
                ]);
            }

            $this->pdo->commit();

            return [
                'booking_reference' => $bookingReference,
                'ticket_price' => $ticketPrice,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
