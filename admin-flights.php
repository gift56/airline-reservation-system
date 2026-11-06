<?php

declare(strict_types=1);

use App\Models\Flight;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Flight.php';

app_start_session();

$admin = app_current_user();
if ($admin === null || $admin['role'] !== 'admin') {
    app_set_flash('danger', 'Administrator access is required to manage flights.');
    header('Location: login.php');
    exit;
}

$pageTitle = 'Manage flights';
$pageDescription = 'Create and manage scheduled flights.';
$errors = [];
$dashboardError = null;
$flights = [];
$airports = [];
$totalFlights = 0;
$totalPages = 1;
$currentPage = 1;
$pageSize = 10;
$rangeStart = 0;
$rangeEnd = 0;
$pageNumbers = [1];
$editingFlight = null;
$editingId = null;
$isEditing = false;
$editingAirportId = null;
$activeModal = null;
$action = '';
$flightModel = null;
$shouldReloadFlights = false;
$formValues = [
    'flight_number' => '',
    'origin_airport_id' => '',
    'destination_airport_id' => '',
    'departure_at' => '',
    'arrival_at' => '',
    'seat_capacity' => '',
    'ticket_price' => '',
];
$airportValues = [
    'iata_code' => '',
    'name' => '',
    'city' => '',
    'country' => '',
];
$createAirportValues = $airportValues;
$flash = app_take_flash();

$readString = static function (mixed $value): string {
    return is_string($value) ? trim($value) : '';
};

$parseId = static function (mixed $value): ?int {
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $id = filter_var((string) $value, FILTER_VALIDATE_INT);

    return is_int($id) && $id > 0 ? $id : null;
};

$parsePage = static function (mixed $value): int {
    if (!is_string($value) && !is_int($value)) {
        return 1;
    }

    $page = filter_var((string) $value, FILTER_VALIDATE_INT);

    return is_int($page) && $page > 0 ? $page : 1;
};

$requestedPage = $parsePage(
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ? ($_POST['return_page'] ?? null)
        : ($_GET['page'] ?? null)
);
$requestedModal = $readString($_GET['open'] ?? null);
if (in_array($requestedModal, ['airport-modal', 'flight-modal'], true)) {
    $activeModal = $requestedModal;
}

$loadPage = static function (Flight $model, int $requestedPage, int $pageSize): array {
    $total = $model->countFlights();
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($requestedPage, $pages);
    $rows = $model->listFlights($pageSize, ($page - 1) * $pageSize);

    return [$total, $pages, $page, $rows];
};

$parseDateTime = static function (string $value, string $label) use (&$errors): ?string {
    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        $dateTime === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $dateTime->format('Y-m-d\TH:i') !== $value
    ) {
        $errors[] = 'Enter a valid ' . $label . ' date and time.';

        return null;
    }

    return $dateTime->format('Y-m-d H:i:s');
};

/**
 * @param array{iata_code: string, name: string, city: string, country: string} $values
 */
$validateAirport = static function (array $values) use (&$errors): void {
    if (preg_match('/\A[A-Z]{3}\z/', $values['iata_code']) !== 1) {
        $errors[] = 'Enter a valid three-letter IATA airport code.';
    }

    if (preg_match('/\A[^\r\n]{1,150}\z/u', $values['name']) !== 1) {
        $errors[] = 'Enter an airport name of no more than 150 characters.';
    }

    if (preg_match('/\A[^\r\n]{1,100}\z/u', $values['city']) !== 1) {
        $errors[] = 'Enter a city of no more than 100 characters.';
    }

    if (preg_match('/\A[^\r\n]{1,100}\z/u', $values['country']) !== 1) {
        $errors[] = 'Enter a country of no more than 100 characters.';
    }
};

try {
    $pdo = require __DIR__ . '/config/db.php';
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('The database connection did not return PDO.');
    }

    $flightModel = new Flight($pdo);
    $airports = $flightModel->listAirports();
    $airportIds = array_fill_keys(array_column($airports, 'id'), true);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = $readString($_POST['action'] ?? null);
        $activeModal = match ($action) {
            'create_airport' => 'airport-modal',
            'update_airport' => 'airport-edit-modal',
            'create', 'update' => 'flight-modal',
            default => null,
        };
        $csrfValid = app_is_valid_csrf_token($_POST['csrf_token'] ?? null);

        if (!$csrfValid) {
            $errors[] = 'Your form session expired. Reload the page and try again.';
        } elseif ($action === 'create_airport') {
            $airportValues = [
                'iata_code' => strtoupper($readString($_POST['iata_code'] ?? null)),
                'name' => $readString($_POST['name'] ?? null),
                'city' => $readString($_POST['city'] ?? null),
                'country' => $readString($_POST['country'] ?? null),
            ];
            $createAirportValues = $airportValues;
            $validateAirport($airportValues);

            if ($errors === []) {
                $flightModel->createAirport(
                    $airportValues['iata_code'],
                    $airportValues['name'],
                    $airportValues['city'],
                    $airportValues['country']
                );
                app_set_flash('success', 'Airport added. You can now use it when creating a flight.');
                header('Location: admin-flights.php?page=' . $requestedPage . '&open=flight-modal');
                exit;
            }
        } elseif ($action === 'update_airport') {
            $editingAirportId = $parseId($_POST['airport_id'] ?? null);
            $airportValues = [
                'iata_code' => strtoupper($readString($_POST['iata_code'] ?? null)),
                'name' => $readString($_POST['name'] ?? null),
                'city' => $readString($_POST['city'] ?? null),
                'country' => $readString($_POST['country'] ?? null),
            ];

            if ($editingAirportId === null) {
                $errors[] = 'Select a valid airport to update.';
            }
            $validateAirport($airportValues);

            if ($editingAirportId !== null && $errors === []) {
                if (!$flightModel->updateAirport(
                    $editingAirportId,
                    $airportValues['iata_code'],
                    $airportValues['name'],
                    $airportValues['city'],
                    $airportValues['country']
                )) {
                    $errors[] = 'That airport no longer exists.';
                } else {
                    app_set_flash('success', 'Airport updated.');
                    header('Location: admin-flights.php?page=' . $requestedPage);
                    exit;
                }
            }
        } elseif ($action === 'delete_airport') {
            $airportId = $parseId($_POST['airport_id'] ?? null);

            if ($airportId === null) {
                $errors[] = 'Select a valid airport to delete.';
            } else {
                $deletionResult = $flightModel->deleteAirport($airportId);
                if ($deletionResult === 'not_found') {
                    $errors[] = 'That airport no longer exists.';
                } elseif ($deletionResult === 'bookings_exist') {
                    $errors[] = 'This airport cannot be deleted because one or more related flights have bookings. Reassign or otherwise resolve those flights first.';
                } else {
                    app_set_flash('success', 'Airport and its unbooked flights deleted.');
                    header('Location: admin-flights.php?page=' . $requestedPage);
                    exit;
                }
            }
        } elseif ($action === 'delete') {
            $flightId = $parseId($_POST['flight_id'] ?? null);

            if ($flightId === null) {
                $errors[] = 'Select a valid flight to delete.';
            } elseif (!$flightModel->delete($flightId)) {
                $errors[] = 'That flight no longer exists.';
            } else {
                app_set_flash('success', 'Flight deleted.');
                header('Location: admin-flights.php?page=' . $requestedPage);
                exit;
            }
        } elseif ($action === 'create' || $action === 'update') {
            $isEditing = $action === 'update';
            $formValues = [
                'flight_number' => $readString($_POST['flight_number'] ?? null),
                'origin_airport_id' => $readString($_POST['origin_airport_id'] ?? null),
                'destination_airport_id' => $readString($_POST['destination_airport_id'] ?? null),
                'departure_at' => $readString($_POST['departure_at'] ?? null),
                'arrival_at' => $readString($_POST['arrival_at'] ?? null),
                'seat_capacity' => $readString($_POST['seat_capacity'] ?? null),
                'ticket_price' => $readString($_POST['ticket_price'] ?? null),
            ];
            $editingId = $isEditing ? $parseId($_POST['flight_id'] ?? null) : null;

            if ($isEditing && $editingId === null) {
                $errors[] = 'Select a valid flight to update.';
            }

            $flightNumber = $formValues['flight_number'];
            if (preg_match('/\A[A-Za-z0-9-]{1,10}\z/', $flightNumber) !== 1) {
                $errors[] = 'Enter a flight code using 1 to 10 letters, numbers, or hyphens.';
            }

            $originAirportId = $parseId($formValues['origin_airport_id']);
            if ($originAirportId === null || !isset($airportIds[(string) $originAirportId])) {
                $errors[] = 'Select a valid origin airport.';
            }

            $destinationAirportId = $parseId($formValues['destination_airport_id']);
            if ($destinationAirportId === null || !isset($airportIds[(string) $destinationAirportId])) {
                $errors[] = 'Select a valid destination airport.';
            } elseif ($originAirportId !== null && $originAirportId === $destinationAirportId) {
                $errors[] = 'Origin and destination airports must be different.';
            }

            $departureAt = $parseDateTime($formValues['departure_at'], 'departure');
            $arrivalAt = $parseDateTime($formValues['arrival_at'], 'arrival');
            if ($departureAt !== null && $arrivalAt !== null && $arrivalAt <= $departureAt) {
                $errors[] = 'Arrival must be later than departure.';
            }

            $seatValue = $formValues['seat_capacity'];
            $seatCapacity = preg_match('/\A[1-9]\d{0,4}\z/', $seatValue) === 1 ? (int) $seatValue : 0;
            if ($seatCapacity < 1 || $seatCapacity > 65535) {
                $errors[] = 'Seat capacity must be between 1 and 65,535.';
            }

            $ticketPrice = $formValues['ticket_price'];
            if (
                preg_match('/\A(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?\z/', $ticketPrice) !== 1
                || ltrim(str_replace('.', '', $ticketPrice), '0') === ''
            ) {
                $errors[] = 'Enter a positive ticket price with no more than two decimal places.';
            } else {
                [$whole, $fraction] = array_pad(explode('.', $ticketPrice, 2), 2, '');
                $ticketPrice = $whole . '.' . str_pad($fraction, 2, '0');
                $formValues['ticket_price'] = $ticketPrice;
            }

            if ($errors === []) {
                if ($isEditing) {
                    if (!$flightModel->update(
                        $editingId,
                        $flightNumber,
                        $originAirportId,
                        $destinationAirportId,
                        $departureAt,
                        $arrivalAt,
                        $seatCapacity,
                        $ticketPrice
                    )) {
                        $errors[] = 'That flight no longer exists.';
                    } else {
                        app_set_flash('success', 'Flight updated.');
                        header('Location: admin-flights.php?page=' . $requestedPage);
                        exit;
                    }
                } else {
                    $flightModel->create(
                        $flightNumber,
                        $originAirportId,
                        $destinationAirportId,
                        $departureAt,
                        $arrivalAt,
                        $seatCapacity,
                        $ticketPrice
                    );
                    app_set_flash('success', 'Flight created.');
                    header('Location: admin-flights.php?page=' . $requestedPage);
                    exit;
                }
            }
        } else {
            $errors[] = 'Choose a valid flight action.';
        }
    } elseif (isset($_GET['edit_airport'])) {
        $editingAirportId = $parseId($_GET['edit_airport']);
        if ($editingAirportId === null) {
            $errors[] = 'Select a valid airport to edit.';
        } else {
            $editingAirport = $flightModel->findAirportById($editingAirportId);
            if ($editingAirport === null) {
                $errors[] = 'That airport no longer exists.';
                $editingAirportId = null;
            } else {
                $activeModal = 'airport-edit-modal';
                $airportValues = [
                    'iata_code' => $editingAirport['iata_code'],
                    'name' => $editingAirport['name'],
                    'city' => $editingAirport['city'],
                    'country' => $editingAirport['country'],
                ];
            }
        }
    } elseif (isset($_GET['edit'])) {
        $activeModal = 'flight-modal';
        $editingId = $parseId($_GET['edit']);
        if ($editingId === null) {
            $errors[] = 'Select a valid flight to edit.';
        } else {
            $editingFlight = $flightModel->findById($editingId);
            if ($editingFlight === null) {
                $errors[] = 'That flight no longer exists.';
                $editingId = null;
            } else {
                $isEditing = true;
                $formValues = [
                    'flight_number' => $editingFlight['flight_number'],
                    'origin_airport_id' => $editingFlight['origin_airport_id'],
                    'destination_airport_id' => $editingFlight['destination_airport_id'],
                    'departure_at' => (new DateTimeImmutable($editingFlight['departure_at']))->format('Y-m-d\TH:i'),
                    'arrival_at' => (new DateTimeImmutable($editingFlight['arrival_at']))->format('Y-m-d\TH:i'),
                    'seat_capacity' => $editingFlight['seat_capacity'],
                    'ticket_price' => $editingFlight['ticket_price'] ?? '',
                ];
            }
        }
    }

    if ($isEditing && $editingId !== null && $editingFlight === null) {
        $editingFlight = $flightModel->findById($editingId);
    }

    [$totalFlights, $totalPages, $currentPage, $flights] = $loadPage(
        $flightModel,
        $requestedPage,
        $pageSize
    );
} catch (PDOException $exception) {
    $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

    if (in_array($action, ['create_airport', 'update_airport'], true) && $driverCode === 1062) {
        $errors[] = 'An airport with this IATA code already exists.';
        $activeModal = $action === 'update_airport' ? 'airport-edit-modal' : 'airport-modal';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $driverCode === 1062) {
        $errors[] = 'A flight with this code and departure time already exists.';
        $shouldReloadFlights = true;
    } elseif ($action === 'delete_airport' && $driverCode === 1451) {
        $errors[] = 'This airport cannot be deleted because one or more related flights have bookings. Reassign or otherwise resolve those flights first.';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $driverCode === 1451) {
        $errors[] = 'This flight cannot be deleted because it has bookings.';
        $shouldReloadFlights = true;
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $driverCode === 1452) {
        $errors[] = 'One of the selected airports is no longer available.';
        $shouldReloadFlights = true;
    } elseif ($driverCode === 1054) {
        $dashboardError = 'The flight database is missing a required column. Apply database/migrations/06-flight-ticket-price.sql before using this dashboard.';
    } else {
        error_log(
            'Admin flight dashboard database operation failed (SQLSTATE '
                . (string) $exception->getCode()
                . ', driver code '
                . $driverCode
                . ').'
        );
        $dashboardError = 'Flight data is temporarily unavailable. Please try again later.';
    }
} catch (RuntimeException $exception) {
    error_log('Admin flight dashboard database configuration failed.');
    $dashboardError = 'Flight data is temporarily unavailable. Please check the server configuration.';
}

if ($flightModel instanceof Flight && $shouldReloadFlights) {
    try {
        if ($isEditing && $editingId !== null && $editingFlight === null) {
            $editingFlight = $flightModel->findById($editingId);
        }
        [$totalFlights, $totalPages, $currentPage, $flights] = $loadPage(
            $flightModel,
            $requestedPage,
            $pageSize
        );
    } catch (PDOException $exception) {
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;
        if ($driverCode === 1054) {
            $dashboardError = 'The flight database is missing a required column. Apply database/migrations/06-flight-ticket-price.sql before using this dashboard.';
        } else {
            error_log(
                'Admin flight dashboard could not reload flight data (SQLSTATE '
                    . (string) $exception->getCode()
                    . ', driver code '
                    . $driverCode
                    . ').'
            );
            $dashboardError = 'Flight data is temporarily unavailable. Please try again later.';
        }
    }
}

$rangeStart = $totalFlights === 0 ? 0 : (($currentPage - 1) * $pageSize) + 1;
$rangeEnd = min($currentPage * $pageSize, $totalFlights);
$pageWindowStart = max(1, min($currentPage - 2, $totalPages - 4));
$pageWindowEnd = min($totalPages, $pageWindowStart + 4);
$pageWindowStart = max(1, $pageWindowEnd - 4);
$pageNumbers = range($pageWindowStart, $pageWindowEnd);

if ($flash === null) {
    $flash = app_take_flash();
}

define('APP_ADMIN_FLIGHTS_VIEW', true);
require __DIR__ . '/views/admin/flights.php';
