<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class Booking
{
    private const SEATS_PER_ROW = 6;
    private const SEAT_PAGE_SIZE = 60;

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
     *     seat_capacity: string,
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
            'SELECT f.id, f.flight_number, f.departure_at, f.arrival_at, f.ticket_price, f.seat_capacity,
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
            'seat_capacity' => (string) $flight['seat_capacity'],
            'remaining_seats' => (string) $flight['remaining_seats'],
            'origin_iata_code' => (string) $flight['origin_iata_code'],
            'origin_city' => (string) $flight['origin_city'],
            'destination_iata_code' => (string) $flight['destination_iata_code'],
            'destination_city' => (string) $flight['destination_city'],
        ];
    }

    /**
     * @return array{
     *     capacity: int,
     *     remaining_seats: int,
     *     page: int,
     *     page_count: int,
     *     seats: list<array{label: string, available: bool}>
     * }|null
     */
    public function getSeatMap(int $flightId, int $page = 0): ?array
    {
        if ($page < 0) {
            throw new InvalidArgumentException('Seat-map page is invalid.');
        }

        $flight = $this->findBookableFlight($flightId);
        if ($flight === null) {
            return null;
        }

        $capacity = (int) $flight['seat_capacity'];
        $remainingSeats = (int) $flight['remaining_seats'];
        $pageCount = max(1, (int) ceil($capacity / self::SEAT_PAGE_SIZE));
        if ($page >= $pageCount) {
            throw new InvalidArgumentException('Seat-map page is invalid.');
        }

        $assignedLabels = $this->getActiveSeatLabels($flightId);
        $assigned = array_fill_keys($assignedLabels, true);
        $legacyCount = max(0, $capacity - $remainingSeats - count($assignedLabels));
        $legacyBlocked = $this->getLegacyBlockedLabels($capacity, $legacyCount, $assigned);
        $start = $page * self::SEAT_PAGE_SIZE;
        $end = min($capacity, $start + self::SEAT_PAGE_SIZE);
        $seats = [];

        for ($index = $start; $index < $end; $index++) {
            $label = self::seatLabel($index);
            $seats[] = [
                'label' => $label,
                'available' => !isset($assigned[$label]) && !isset($legacyBlocked[$label]),
            ];
        }

        return [
            'capacity' => $capacity,
            'remaining_seats' => $remainingSeats,
            'page' => $page,
            'page_count' => $pageCount,
            'seats' => $seats,
        ];
    }

    /**
     * @param list<string> $seatLabels
     */
    public function areSeatsAvailable(int $flightId, array $seatLabels): bool
    {
        $flight = $this->findBookableFlight($flightId);
        if ($flight === null || $seatLabels === [] || count(array_unique($seatLabels)) !== count($seatLabels)) {
            return false;
        }

        if (count($seatLabels) > (int) $flight['remaining_seats']) {
            return false;
        }

        $capacity = (int) $flight['seat_capacity'];
        $assignedLabels = $this->getActiveSeatLabels($flightId);
        $assigned = array_fill_keys($assignedLabels, true);
        $legacyCount = max(0, $capacity - (int) $flight['remaining_seats'] - count($assignedLabels));
        $legacyBlocked = $this->getLegacyBlockedLabels($capacity, $legacyCount, $assigned);

        foreach ($seatLabels as $label) {
            $index = self::seatIndex($label);
            if (
                $index === null
                || $index >= $capacity
                || isset($assigned[$label])
                || isset($legacyBlocked[$label])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{first_name: string, last_name: string}> $travelers
     * @param list<string> $seatLabels
     *
     * @return array{booking_reference: string, ticket_price: string}
     */
    public function createPendingBooking(
        string $userId,
        int $flightId,
        array $travelers,
        array $seatLabels,
        string $expectedTicketPrice
    ): array {
        if (preg_match('/\A[1-9][0-9]{0,19}\z/', $userId) !== 1 || $flightId < 1) {
            throw new InvalidArgumentException('Booking account or flight is invalid.');
        }

        if (count($travelers) < 1 || count($travelers) > 6) {
            throw new InvalidArgumentException('A booking must include between one and six travelers.');
        }

        if (count($seatLabels) !== count($travelers) || count(array_unique($seatLabels)) !== count($seatLabels)) {
            throw new InvalidArgumentException('Choose a different available seat for every traveler.');
        }

        if (preg_match('/\A\d{1,8}\.\d{2}\z/', $expectedTicketPrice) !== 1) {
            throw new InvalidArgumentException('The reviewed ticket price is invalid.');
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

        foreach ($seatLabels as $label) {
            if (!is_string($label) || self::seatIndex($label) === null) {
                throw new InvalidArgumentException('One or more selected seats are invalid.');
            }
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

            $capacity = (int) $flight['seat_capacity'];
            $occupiedSeats = $this->getActiveOccupancy($flightId);
            $remainingSeats = $capacity - $occupiedSeats;
            if ($remainingSeats < count($validatedTravelers)) {
                throw new DomainException(
                    $remainingSeats > 0
                        ? 'There are not enough seats for everyone in your party. Choose fewer travelers or another flight.'
                        : 'This flight no longer has seats available.'
                );
            }

            $assignedLabels = $this->getActiveSeatLabels($flightId);
            $assigned = array_fill_keys($assignedLabels, true);
            $legacyCount = max(0, $occupiedSeats - count($assignedLabels));
            $legacyBlocked = $this->getLegacyBlockedLabels($capacity, $legacyCount, $assigned);

            foreach ($seatLabels as $label) {
                $index = self::seatIndex($label);
                if (
                    $index === null
                    || $index >= $capacity
                    || isset($assigned[$label])
                    || isset($legacyBlocked[$label])
                ) {
                    throw new DomainException('A selected seat is no longer available. Choose another seat.');
                }
            }

            $ticketPrice = (string) $flight['ticket_price'];
            if ($ticketPrice !== $expectedTicketPrice) {
                throw new DomainException('The ticket price changed; review the new fare before confirming.');
            }

            $bookingReference = bin2hex(random_bytes(12));
            $insertBooking = $this->pdo->prepare(
                'INSERT INTO bookings (booking_reference, user_id, flight_id, ticket_price, status)
                 VALUES (:booking_reference, :user_id, :flight_id, :ticket_price, \'pending\')'
            );
            $insertBooking->execute([
                'booking_reference' => $bookingReference,
                'user_id' => $userId,
                'flight_id' => $flightId,
                'ticket_price' => $ticketPrice,
            ]);

            $bookingId = (int) $this->pdo->lastInsertId();
            if ($bookingId < 1) {
                throw new \RuntimeException('The booking record was not created.');
            }

            $insertTraveler = $this->pdo->prepare(
                'INSERT INTO passengers_manifest (booking_id, first_name, last_name)
                 VALUES (:booking_id, :first_name, :last_name)'
            );
            $insertSeat = $this->pdo->prepare(
                'INSERT INTO booking_seat_assignments (flight_id, booking_id, manifest_id, seat_label)
                 VALUES (:flight_id, :booking_id, :manifest_id, :seat_label)'
            );

            foreach ($validatedTravelers as $index => $traveler) {
                $insertTraveler->execute([
                    'booking_id' => $bookingId,
                    'first_name' => $traveler['first_name'],
                    'last_name' => $traveler['last_name'],
                ]);
                $manifestId = (int) $this->pdo->lastInsertId();
                if ($manifestId < 1) {
                    throw new \RuntimeException('The passenger manifest record was not created.');
                }

                $insertSeat->execute([
                    'flight_id' => $flightId,
                    'booking_id' => $bookingId,
                    'manifest_id' => $manifestId,
                    'seat_label' => $seatLabels[$index],
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

    /**
     * @return array{
     *     booking_reference: string,
     *     status: string,
     *     created_at: string,
     *     ticket_price: string|null,
     *     flight: array{
     *         id: string,
     *         flight_number: string,
     *         departure_at: string,
     *         arrival_at: string,
     *         current_ticket_price: string|null,
     *         origin_iata_code: string,
     *         origin_city: string,
     *         destination_iata_code: string,
     *         destination_city: string
     *     },
     *     travelers: list<array{first_name: string, last_name: string, seat_label: string|null}>
     * }|null
     */
    public function findTicketForPassenger(string $bookingReference, string $userId): ?array
    {
        if (
            preg_match('/\A[a-f0-9]{24}\z/', $bookingReference) !== 1
            || preg_match('/\A[1-9][0-9]{0,19}\z/', $userId) !== 1
        ) {
            return null;
        }

        $bookingStatement = $this->pdo->prepare(
            'SELECT b.id, b.booking_reference, b.status, b.created_at, b.ticket_price AS booking_ticket_price,
                    f.id AS flight_id,
                    f.flight_number, f.departure_at, f.arrival_at, f.ticket_price AS current_ticket_price,
                    origin.iata_code AS origin_iata_code, origin.city AS origin_city,
                    destination.iata_code AS destination_iata_code, destination.city AS destination_city
             FROM bookings b
             INNER JOIN flights f ON f.id = b.flight_id
             INNER JOIN airports origin ON origin.id = f.origin_airport_id
             INNER JOIN airports destination ON destination.id = f.destination_airport_id
             WHERE b.booking_reference = :booking_reference
               AND b.user_id = :user_id'
        );
        $bookingStatement->execute([
            'booking_reference' => $bookingReference,
            'user_id' => $userId,
        ]);
        $booking = $bookingStatement->fetch();

        if (!is_array($booking)) {
            return null;
        }

        $passengerStatement = $this->pdo->prepare(
            'SELECT manifest.first_name, manifest.last_name, assignment.seat_label
             FROM passengers_manifest manifest
             LEFT JOIN booking_seat_assignments assignment
               ON assignment.manifest_id = manifest.id
              AND assignment.booking_id = manifest.booking_id
             WHERE manifest.booking_id = :booking_id
             ORDER BY manifest.id'
        );
        $passengerStatement->execute(['booking_id' => $booking['id']]);
        $travelers = array_map(
            static fn (array $traveler): array => [
                'first_name' => (string) $traveler['first_name'],
                'last_name' => (string) $traveler['last_name'],
                'seat_label' => $traveler['seat_label'] === null ? null : (string) $traveler['seat_label'],
            ],
            $passengerStatement->fetchAll()
        );

        return [
            'booking_reference' => (string) $booking['booking_reference'],
            'status' => (string) $booking['status'],
            'created_at' => (string) $booking['created_at'],
            'ticket_price' => $booking['booking_ticket_price'] === null
                ? null
                : (string) $booking['booking_ticket_price'],
            'flight' => [
                'id' => (string) $booking['flight_id'],
                'flight_number' => (string) $booking['flight_number'],
                'departure_at' => (string) $booking['departure_at'],
                'arrival_at' => (string) $booking['arrival_at'],
                'current_ticket_price' => $booking['current_ticket_price'] === null
                    ? null
                    : (string) $booking['current_ticket_price'],
                'origin_iata_code' => (string) $booking['origin_iata_code'],
                'origin_city' => (string) $booking['origin_city'],
                'destination_iata_code' => (string) $booking['destination_iata_code'],
                'destination_city' => (string) $booking['destination_city'],
            ],
            'travelers' => $travelers,
        ];
    }

    /**
     * @return list<array{
     *     booking_reference: string,
     *     status: string,
     *     created_at: string,
     *     ticket_price: string|null,
     *     flight: array{
     *         flight_number: string,
     *         departure_at: string,
     *         arrival_at: string,
     *         origin_iata_code: string,
     *         origin_city: string,
     *         destination_iata_code: string,
     *         destination_city: string
     *     },
     *     travelers: list<array{first_name: string, last_name: string, seat_label: string|null}>
     * }>
     */
    public function findUpcomingBookingsForPassenger(string $userId): array
    {
        if (preg_match('/\A[1-9][0-9]{0,19}\z/', $userId) !== 1) {
            return [];
        }

        $bookingStatement = $this->pdo->prepare(
            'SELECT b.id, b.booking_reference, b.status, b.created_at, b.ticket_price,
                    f.flight_number, f.departure_at, f.arrival_at,
                    origin.iata_code AS origin_iata_code, origin.city AS origin_city,
                    destination.iata_code AS destination_iata_code, destination.city AS destination_city
             FROM bookings b
             INNER JOIN flights f ON f.id = b.flight_id
             INNER JOIN airports origin ON origin.id = f.origin_airport_id
             INNER JOIN airports destination ON destination.id = f.destination_airport_id
             WHERE b.user_id = :user_id
               AND b.status IN (\'pending\', \'confirmed\')
               AND f.departure_at > NOW()
             ORDER BY f.departure_at ASC, b.id ASC'
        );
        $bookingStatement->execute(['user_id' => $userId]);

        $bookingsById = [];
        foreach ($bookingStatement->fetchAll(PDO::FETCH_ASSOC) as $booking) {
            $bookingId = (string) $booking['id'];
            $bookingsById[$bookingId] = [
                'booking_reference' => (string) $booking['booking_reference'],
                'status' => (string) $booking['status'],
                'created_at' => (string) $booking['created_at'],
                'ticket_price' => $booking['ticket_price'] === null
                    ? null
                    : (string) $booking['ticket_price'],
                'flight' => [
                    'flight_number' => (string) $booking['flight_number'],
                    'departure_at' => (string) $booking['departure_at'],
                    'arrival_at' => (string) $booking['arrival_at'],
                    'origin_iata_code' => (string) $booking['origin_iata_code'],
                    'origin_city' => (string) $booking['origin_city'],
                    'destination_iata_code' => (string) $booking['destination_iata_code'],
                    'destination_city' => (string) $booking['destination_city'],
                ],
                'travelers' => [],
            ];
        }

        if ($bookingsById === []) {
            return [];
        }

        $bookingIds = array_keys($bookingsById);
        $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
        $travelerStatement = $this->pdo->prepare(
            'SELECT manifest.booking_id, manifest.first_name, manifest.last_name, assignment.seat_label
             FROM passengers_manifest manifest
             LEFT JOIN booking_seat_assignments assignment
               ON assignment.manifest_id = manifest.id
              AND assignment.booking_id = manifest.booking_id
             WHERE manifest.booking_id IN (' . $placeholders . ')
             ORDER BY manifest.booking_id, manifest.id'
        );
        $travelerStatement->execute($bookingIds);

        foreach ($travelerStatement->fetchAll(PDO::FETCH_ASSOC) as $traveler) {
            $bookingId = (string) $traveler['booking_id'];
            $bookingsById[$bookingId]['travelers'][] = [
                'first_name' => (string) $traveler['first_name'],
                'last_name' => (string) $traveler['last_name'],
                'seat_label' => $traveler['seat_label'] === null
                    ? null
                    : (string) $traveler['seat_label'],
            ];
        }

        return array_values($bookingsById);
    }

    public function cancelUpcomingBookingForPassenger(string $bookingReference, string $userId): bool
    {
        if (
            preg_match('/\A[a-f0-9]{24}\z/', $bookingReference) !== 1
            || preg_match('/\A[1-9][0-9]{0,19}\z/', $userId) !== 1
        ) {
            return false;
        }

        $lookupStatement = $this->pdo->prepare(
            'SELECT id, flight_id
             FROM bookings
             WHERE booking_reference = :booking_reference
               AND user_id = :user_id'
        );
        $lookupStatement->execute([
            'booking_reference' => $bookingReference,
            'user_id' => $userId,
        ]);
        $bookingIdentity = $lookupStatement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($bookingIdentity)) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $flightStatement = $this->pdo->prepare(
                'SELECT departure_at > NOW() AS is_upcoming
                 FROM flights
                 WHERE id = :flight_id
                 FOR UPDATE'
            );
            $flightStatement->execute(['flight_id' => $bookingIdentity['flight_id']]);
            $flight = $flightStatement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($flight) || (int) $flight['is_upcoming'] !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $bookingStatement = $this->pdo->prepare(
                'SELECT id, flight_id, status
                 FROM bookings
                 WHERE id = :booking_id
                   AND booking_reference = :booking_reference
                   AND user_id = :user_id
                 FOR UPDATE'
            );
            $bookingStatement->execute([
                'booking_id' => $bookingIdentity['id'],
                'booking_reference' => $bookingReference,
                'user_id' => $userId,
            ]);
            $booking = $bookingStatement->fetch(PDO::FETCH_ASSOC);

            if (
                !is_array($booking)
                || (string) $booking['flight_id'] !== (string) $bookingIdentity['flight_id']
                || !in_array($booking['status'], ['pending', 'confirmed'], true)
            ) {
                $this->pdo->rollBack();

                return false;
            }

            $updateStatement = $this->pdo->prepare(
                'UPDATE bookings
                 SET status = \'cancelled\'
                 WHERE id = :booking_id
                   AND user_id = :user_id
                   AND status IN (\'pending\', \'confirmed\')'
            );
            $updateStatement->execute([
                'booking_id' => $booking['id'],
                'user_id' => $userId,
            ]);

            if ($updateStatement->rowCount() !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $deleteSeatsStatement = $this->pdo->prepare(
                'DELETE FROM booking_seat_assignments
                 WHERE booking_id = :booking_id'
            );
            $deleteSeatsStatement->execute(['booking_id' => $booking['id']]);

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private function getActiveSeatLabels(int $flightId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT assignment.seat_label
             FROM booking_seat_assignments assignment
             INNER JOIN bookings ON bookings.id = assignment.booking_id
             WHERE assignment.flight_id = :flight_id
               AND bookings.status IN (\'pending\', \'confirmed\')'
        );
        $statement->execute(['flight_id' => $flightId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function getActiveOccupancy(int $flightId): int
    {
        $statement = $this->pdo->prepare(
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
        $statement->execute(['flight_id' => $flightId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, bool> $assigned
     *
     * @return array<string, bool>
     */
    private function getLegacyBlockedLabels(int $capacity, int $legacyCount, array $assigned): array
    {
        $blocked = [];
        for ($index = 0; $index < $capacity && count($blocked) < $legacyCount; $index++) {
            $label = self::seatLabel($index);
            if (!isset($assigned[$label])) {
                $blocked[$label] = true;
            }
        }

        return $blocked;
    }

    private static function seatLabel(int $index): string
    {
        return (string) (intdiv($index, self::SEATS_PER_ROW) + 1)
            . chr(65 + ($index % self::SEATS_PER_ROW));
    }

    private static function seatIndex(string $label): ?int
    {
        if (preg_match('/\A([1-9][0-9]{0,4})([A-F])\z/', $label, $matches) !== 1) {
            return null;
        }

        return (((int) $matches[1]) - 1) * self::SEATS_PER_ROW + ord($matches[2]) - 65;
    }
}
