<?php

declare(strict_types=1);

use App\Models\Booking;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Booking.php';

app_start_session();

$pageTitle = 'Reserve your flight';
$pageDescription = 'Enter traveler details, choose seats, and confirm a flight reservation.';
$flightIdValue = $_GET['flight_id'] ?? ($_POST['flight_id'] ?? null);
$flightId = is_string($flightIdValue)
    ? filter_var($flightIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$currentUser = app_current_user();
$errors = [];
$flight = null;
$seatMap = null;
$draft = null;
$ticket = null;
$databaseError = false;
$step = $_GET['step'] ?? 'details';
if (!is_string($step) || !in_array($step, ['details', 'seats', 'review', 'confirmation'], true)) {
    $step = 'details';
}

$validDraft = static function (mixed $value, string $userId, int $selectedFlightId): ?array {
    if (
        !is_array($value)
        || !isset(
            $value['user_id'],
            $value['flight_id'],
            $value['created_at'],
            $value['traveler_count'],
            $value['travelers'],
            $value['seats'],
            $value['ticket_price']
        )
        || !is_string($value['user_id'])
        || $value['user_id'] !== $userId
        || !is_string($value['flight_id'])
        || $value['flight_id'] !== (string) $selectedFlightId
        || !is_int($value['created_at'])
        || $value['created_at'] < time() - 1800
        || !is_int($value['traveler_count'])
        || $value['traveler_count'] < 1
        || $value['traveler_count'] > 6
        || !is_array($value['travelers'])
        || !is_array($value['seats'])
        || !is_string($value['ticket_price'])
        || preg_match('/\A\d{1,8}\.\d{2}\z/', $value['ticket_price']) !== 1
    ) {
        return null;
    }

    $travelers = [];
    $seats = [];
    for ($index = 0; $index < $value['traveler_count']; $index++) {
        $traveler = $value['travelers'][$index] ?? null;
        if (
            !is_array($traveler)
            || !isset($traveler['first_name'], $traveler['last_name'])
            || !is_string($traveler['first_name'])
            || !is_string($traveler['last_name'])
            || preg_match('/\A[^\r\n\0]{1,100}\z/u', $traveler['first_name']) !== 1
            || preg_match('/\A[^\r\n\0]{1,100}\z/u', $traveler['last_name']) !== 1
        ) {
            return null;
        }

        $seat = $value['seats'][$index] ?? null;
        if ($seat !== null && (!is_string($seat) || preg_match('/\A[1-9][0-9]{0,4}[A-F]\z/', $seat) !== 1)) {
            return null;
        }

        $travelers[] = [
            'first_name' => $traveler['first_name'],
            'last_name' => $traveler['last_name'],
        ];
        $seats[] = $seat;
    }

    return [
        'user_id' => $userId,
        'flight_id' => (string) $selectedFlightId,
        'created_at' => $value['created_at'],
        'traveler_count' => $value['traveler_count'],
        'travelers' => $travelers,
        'seats' => $seats,
        'ticket_price' => $value['ticket_price'],
    ];
};

if ($flightId === false) {
    http_response_code(400);
    $errors[] = 'Choose a valid flight to continue.';
} elseif ($currentUser === null) {
    $returnTo = 'book-flight.php?flight_id=' . $flightId;
    header('Location: login.php?return_to=' . rawurlencode($returnTo));
    exit;
} elseif ($currentUser['role'] !== 'passenger') {
    http_response_code(403);
    $errors[] = 'Only passenger accounts can reserve flights.';
} else {
    $draft = $validDraft($_SESSION['_booking_draft'] ?? null, $currentUser['id'], $flightId);
    if ($draft === null) {
        unset($_SESSION['_booking_draft']);
    } else {
        $_SESSION['_booking_draft'] = $draft;
    }

    $travelerCount = $draft['traveler_count'] ?? 1;
    $travelerInputs = $draft['travelers'] ?? array_fill(0, 6, ['first_name' => '', 'last_name' => '']);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postedFlightValue = $_POST['flight_id'] ?? null;
        $postedFlightId = is_string($postedFlightValue)
            ? filter_var($postedFlightValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $actionValue = $_POST['action'] ?? null;
        $action = is_string($actionValue) ? $actionValue : '';

        if (!app_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your form session expired. Reload the page and try again.';
        } elseif ($postedFlightId === false || $postedFlightId !== $flightId) {
            $errors[] = 'The selected flight could not be verified. Please try again.';
        } else {
            try {
                $pdo = require __DIR__ . '/config/db.php';
                if (!$pdo instanceof PDO) {
                    throw new RuntimeException('The database connection did not return PDO.');
                }
                $bookingModel = new Booking($pdo);
                $flight = $bookingModel->findBookableFlight($flightId);

                if ($flight === null) {
                    throw new DomainException('This flight is no longer available. Please choose another flight.');
                }

                if ($action === 'save_details') {
                    $countValue = $_POST['traveler_count'] ?? null;
                    $validatedCount = is_string($countValue) ? filter_var($countValue, FILTER_VALIDATE_INT) : false;
                    if (
                        $validatedCount === false
                        || $validatedCount < 1
                        || $validatedCount > 6
                        || $validatedCount > (int) $flight['remaining_seats']
                    ) {
                        $errors[] = 'Choose a traveler count that fits the currently available seats (one to six).';
                    } else {
                        $travelerCount = $validatedCount;
                        $travelerInputs = [];
                        $travelersValid = true;
                        for ($index = 0; $index < $travelerCount; $index++) {
                            $input = $_POST['travelers'][$index] ?? null;
                            $firstNameValue = is_array($input) ? ($input['first_name'] ?? null) : null;
                            $lastNameValue = is_array($input) ? ($input['last_name'] ?? null) : null;
                            $firstName = is_string($firstNameValue) ? trim($firstNameValue) : '';
                            $lastName = is_string($lastNameValue) ? trim($lastNameValue) : '';
                            $travelerInputs[] = [
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                            ];

                            if (
                                preg_match('/\A[^\r\n\0]{1,100}\z/u', $firstName) !== 1
                                || preg_match('/\A[^\r\n\0]{1,100}\z/u', $lastName) !== 1
                            ) {
                                $travelersValid = false;
                            }
                        }

                        if (!$travelersValid) {
                            $errors[] = 'Enter a first and last name for every traveler (up to 100 characters each).';
                        } else {
                            $_SESSION['_booking_draft'] = [
                                'user_id' => $currentUser['id'],
                                'flight_id' => (string) $flightId,
                                'created_at' => time(),
                                'traveler_count' => $travelerCount,
                                'travelers' => $travelerInputs,
                                'seats' => array_fill(0, $travelerCount, null),
                                'ticket_price' => $flight['ticket_price'],
                            ];
                            header('Location: book-flight.php?flight_id=' . $flightId . '&step=seats', true, 303);
                            exit;
                        }
                    }
                    $step = 'details';
                } elseif ($draft === null) {
                    $errors[] = 'Your booking details have expired. Please start again.';
                    $step = 'details';
                } elseif ($action === 'select_seat') {
                    $travelerIndexValue = $_POST['traveler_index'] ?? null;
                    $travelerIndex = is_string($travelerIndexValue)
                        ? filter_var($travelerIndexValue, FILTER_VALIDATE_INT)
                        : false;
                    $seatLabelValue = $_POST['seat_label'] ?? null;
                    $seatLabel = is_string($seatLabelValue) ? $seatLabelValue : '';

                    if (
                        $travelerIndex === false
                        || $travelerIndex < 0
                        || $travelerIndex >= $draft['traveler_count']
                        || !$bookingModel->areSeatsAvailable($flightId, [$seatLabel])
                    ) {
                        $errors[] = 'That seat is no longer available. Choose an available seat.';
                    } else {
                        $usedByAnotherTraveler = false;
                        foreach ($draft['seats'] as $index => $selectedSeat) {
                            if ($index !== $travelerIndex && $selectedSeat === $seatLabel) {
                                $usedByAnotherTraveler = true;
                                break;
                            }
                        }

                        if ($usedByAnotherTraveler) {
                            $errors[] = 'Choose a different seat for each traveler.';
                        } else {
                            $draft['seats'][$travelerIndex] = $seatLabel;
                            $_SESSION['_booking_draft'] = $draft;
                            $pageValue = $_POST['seat_page'] ?? '0';
                            $page = is_string($pageValue)
                                ? filter_var($pageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                                : 0;
                            $page = $page === false ? 0 : $page;
                            header(
                                'Location: book-flight.php?flight_id=' . $flightId . '&step=seats&seat_page=' . $page,
                                true,
                                303
                            );
                            exit;
                        }
                    }
                } elseif ($action === 'save_seats') {
                    $selectedSeats = $draft['seats'];
                    $complete = count(array_filter($selectedSeats, 'is_string')) === $draft['traveler_count'];
                    if (
                        !$complete
                        || count(array_unique($selectedSeats)) !== $draft['traveler_count']
                        || !$bookingModel->areSeatsAvailable($flightId, $selectedSeats)
                    ) {
                        foreach ($selectedSeats as $index => $selectedSeat) {
                            if (is_string($selectedSeat) && !$bookingModel->areSeatsAvailable($flightId, [$selectedSeat])) {
                                $draft['seats'][$index] = null;
                            }
                        }
                        $_SESSION['_booking_draft'] = $draft;
                        $errors[] = 'Choose one different, currently available seat for every traveler.';
                        $step = 'seats';
                    } else {
                        $_SESSION['_booking_draft'] = $draft;
                        header('Location: book-flight.php?flight_id=' . $flightId . '&step=review', true, 303);
                        exit;
                    }
                } elseif ($action === 'confirm') {
                    $selectedSeats = $draft['seats'];
                    if (
                        count(array_filter($selectedSeats, 'is_string')) !== $draft['traveler_count']
                        || count(array_unique($selectedSeats)) !== $draft['traveler_count']
                    ) {
                        $errors[] = 'Choose one seat for every traveler before confirming.';
                        $step = 'seats';
                    } else {
                        try {
                            $booking = $bookingModel->createPendingBooking(
                                $currentUser['id'],
                                $flightId,
                                $draft['travelers'],
                                $selectedSeats,
                                $draft['ticket_price']
                            );
                            $_SESSION['_booking_confirmation'] = [
                                'user_id' => $currentUser['id'],
                                'reference' => $booking['booking_reference'],
                            ];
                            unset($_SESSION['_booking_draft']);
                            header(
                                'Location: book-flight.php?flight_id=' . $flightId . '&step=confirmation',
                                true,
                                303
                            );
                            exit;
                        } catch (\DomainException | \InvalidArgumentException $exception) {
                            if ($exception->getMessage() === 'The ticket price changed; review the new fare before confirming.') {
                                $latestFlight = $bookingModel->findBookableFlight($flightId);
                                if ($latestFlight !== null) {
                                    $flight = $latestFlight;
                                    $draft['ticket_price'] = $latestFlight['ticket_price'];
                                    $_SESSION['_booking_draft'] = $draft;
                                    $errors[] = 'The fare changed. Review the updated total before confirming again.';
                                    $step = 'review';
                                } else {
                                    $errors[] = 'This flight is no longer available. Please choose another flight.';
                                    $step = 'details';
                                }
                            } else {
                                $errors[] = $exception->getMessage();
                                foreach ($selectedSeats as $index => $selectedSeat) {
                                    if (!$bookingModel->areSeatsAvailable($flightId, [$selectedSeat])) {
                                        $draft['seats'][$index] = null;
                                    }
                                }
                                $_SESSION['_booking_draft'] = $draft;
                                $step = 'seats';
                            }
                        } catch (\PDOException $exception) {
                            $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;
                            if ($driverCode === 1062) {
                                $errors[] = 'A selected seat was just assigned to another passenger. Choose another seat.';
                                foreach ($selectedSeats as $index => $selectedSeat) {
                                    if (!$bookingModel->areSeatsAvailable($flightId, [$selectedSeat])) {
                                        $draft['seats'][$index] = null;
                                    }
                                }
                                $_SESSION['_booking_draft'] = $draft;
                                $step = 'seats';
                            } else {
                                error_log('Passenger booking confirmation database failure: ' . $exception->getMessage());
                                $errors[] = 'We could not confirm this reservation right now. Please try again later.';
                                $step = 'review';
                            }
                        } catch (\Throwable $exception) {
                            error_log('Passenger booking confirmation failed: ' . $exception->getMessage());
                            $errors[] = 'We could not confirm this reservation right now. Please try again later.';
                            $step = 'review';
                        }
                    }
                } elseif ($action === 'back_to_seats') {
                    $step = 'seats';
                } elseif ($action === 'back_to_details') {
                    $step = 'details';
                } else {
                    $errors[] = 'Choose a valid booking step.';
                }
            } catch (\PDOException | \RuntimeException $exception) {
                error_log('Passenger booking flow failed: ' . $exception->getMessage());
                $databaseError = true;
            } catch (\DomainException $exception) {
                $errors[] = $exception->getMessage();
                $step = 'details';
            }
        }
    } else {
        try {
            $pdo = require __DIR__ . '/config/db.php';
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('The database connection did not return PDO.');
            }
            $bookingModel = new Booking($pdo);

            if ($step === 'confirmation') {
                $confirmation = $_SESSION['_booking_confirmation'] ?? null;
                if (
                    is_array($confirmation)
                    && isset($confirmation['user_id'], $confirmation['reference'])
                    && is_string($confirmation['user_id'])
                    && $confirmation['user_id'] === $currentUser['id']
                    && is_string($confirmation['reference'])
                ) {
                    $ticket = $bookingModel->findTicketForPassenger(
                        $confirmation['reference'],
                        $currentUser['id']
                    );
                }
                if ($ticket === null) {
                    http_response_code(404);
                    $errors[] = 'This reservation confirmation is unavailable.';
                }
            } else {
                $flight = $bookingModel->findBookableFlight($flightId);
                if ($flight === null) {
                    http_response_code(404);
                    $errors[] = 'This flight is no longer available. Please choose another flight.';
                } elseif ($draft === null && $step !== 'details') {
                    $step = 'details';
                } elseif ($step === 'seats') {
                    $seatPageValue = $_GET['seat_page'] ?? '0';
                    $seatPage = is_string($seatPageValue)
                        ? filter_var($seatPageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                        : false;
                    if ($seatPage === false) {
                        throw new InvalidArgumentException('Seat-map page is invalid.');
                    }
                    $seatMap = $bookingModel->getSeatMap($flightId, $seatPage);
                } elseif ($step === 'review' && $draft !== null) {
                    $draft['ticket_price'] = $flight['ticket_price'];
                    $_SESSION['_booking_draft'] = $draft;
                }
            }
        } catch (\PDOException | \RuntimeException $exception) {
            error_log('Passenger booking page failed: ' . $exception->getMessage());
            $databaseError = true;
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
            $step = 'seats';
        }
    }

    if ($flight === null && $step !== 'confirmation' && !$databaseError) {
        try {
            if (!isset($pdo)) {
                $pdo = require __DIR__ . '/config/db.php';
            }
            if (!$pdo instanceof \PDO) {
                throw new \RuntimeException('The database connection did not return PDO.');
            }
            if (!isset($bookingModel)) {
                $bookingModel = new Booking($pdo);
            }
            $flight = $bookingModel->findBookableFlight($flightId);
        } catch (\PDOException | \RuntimeException $exception) {
            error_log('Passenger booking flight refresh failed: ' . $exception->getMessage());
            $databaseError = true;
        }
    }

    if ($step === 'seats' && $flight !== null && $seatMap === null && isset($bookingModel)) {
        $seatPageValue = $_GET['seat_page'] ?? ($_POST['seat_page'] ?? '0');
        $seatPage = is_string($seatPageValue)
            ? filter_var($seatPageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : false;
        if ($seatPage === false) {
            $seatPage = 0;
        }

        try {
            $seatMap = $bookingModel->getSeatMap($flightId, $seatPage);
            if ($draft !== null) {
                foreach ($draft['seats'] as $index => $selectedSeat) {
                    if (
                        is_string($selectedSeat)
                        && !$bookingModel->areSeatsAvailable($flightId, [$selectedSeat])
                    ) {
                        $draft['seats'][$index] = null;
                        $_SESSION['_booking_draft'] = $draft;
                        $errors[] = 'A previously selected seat is no longer available. Choose another seat.';
                    }
                }
            }
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
            $seatMap = $bookingModel->getSeatMap($flightId);
        } catch (\PDOException $exception) {
            error_log('Passenger booking seat map failed: ' . $exception->getMessage());
            $databaseError = true;
        }
    }
}

$formatNaira = static fn (string $amount): string => '₦' . number_format((float) $amount, 2, '.', ',');
$pageLabels = ['details' => 'Passenger details', 'seats' => 'Seat assignment', 'review' => 'Review'];
$stepOrder = ['details', 'seats', 'review'];
$stepIndex = array_search($step, $stepOrder, true);
$stepIndex = $stepIndex === false ? 0 : $stepIndex;

require __DIR__ . '/includes/header.php';
?>
<section class="container booking-section">
    <p class="flight-eyebrow"><i class="fa-solid fa-ticket" aria-hidden="true"></i> Secure your seats</p>
    <h1 class="h2 fw-bold mb-2">Reserve your flight</h1>
    <p class="text-secondary mb-4">Complete each step to reserve seats. Payment is not collected.</p>

    <?php if ($databaseError): ?>
        <div class="alert alert-danger" role="alert">
            Reservation services are temporarily unavailable. Please try again later.
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert" aria-live="assertive">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= app_escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($ticket !== null): ?>
        <?php
        $confirmationFare = $ticket['ticket_price'];
        $confirmationTotal = $confirmationFare === null
            ? null
            : (float) $confirmationFare * count($ticket['travelers']);
        ?>
        <section class="booking-panel booking-confirmation" aria-labelledby="booking-confirmation-title">
            <p class="auth-eyebrow mb-2">Reservation received</p>
            <h2 id="booking-confirmation-title">Your seats are reserved</h2>
            <p class="text-secondary">
                Status: <span class="booking-status <?= app_escape(app_booking_status_class($ticket['status'])) ?>"><?= app_escape(ucfirst($ticket['status'])) ?></span>.
                This is not a paid ticket or boarding pass.
            </p>
            <p class="small fw-semibold mb-2">Booking reference</p>
            <p class="booking-reference"><?= app_escape($ticket['booking_reference']) ?></p>
            <div class="booking-summary-route">
                <div class="booking-summary-airport">
                    <strong><?= app_escape($ticket['flight']['origin_iata_code']) ?></strong>
                    <span><?= app_escape($ticket['flight']['origin_city']) ?></span>
                </div>
                <i class="fa-solid fa-arrow-right text-secondary" aria-hidden="true"></i>
                <div class="booking-summary-airport">
                    <strong><?= app_escape($ticket['flight']['destination_iata_code']) ?></strong>
                    <span><?= app_escape($ticket['flight']['destination_city']) ?></span>
                </div>
            </div>
            <p class="booking-summary-meta">
                <?= app_escape((new DateTimeImmutable($ticket['flight']['departure_at']))->format('D, d M Y · H:i')) ?>
                · <?= app_escape($ticket['flight']['flight_number']) ?>
            </p>
            <ul class="booking-confirmation-passengers">
                <?php foreach ($ticket['travelers'] as $traveler): ?>
                    <li>
                        <?= app_escape($traveler['first_name'] . ' ' . $traveler['last_name']) ?>
                        · Seat <?= app_escape($traveler['seat_label'] ?? 'Not assigned') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($confirmationTotal !== null): ?>
                <p class="booking-summary-meta">Fare total <?= app_escape($formatNaira(number_format($confirmationTotal, 2, '.', ''))) ?></p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn app-primary-button" href="ticket.php?ref=<?= app_escape($ticket['booking_reference']) ?>">
                    View and print reservation ticket
                </a>
                <a class="btn btn-outline-dark" href="ticket-download.php?ref=<?= app_escape($ticket['booking_reference']) ?>">
                    Download PDF
                </a>
            </div>
        </section>
    <?php elseif ($flight !== null && $currentUser !== null && $currentUser['role'] === 'passenger'): ?>
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-8">
                <nav aria-label="Booking steps" class="booking-step-nav mb-4">
                    <ol class="booking-steps">
                        <?php foreach ($pageLabels as $stepName => $label): ?>
                            <?php $currentIndex = array_search($stepName, $stepOrder, true); ?>
                            <li class="<?= $stepName === $step ? 'is-current' : ($currentIndex < $stepIndex ? 'is-complete' : '') ?>">
                                <span class="booking-step-number"><?= $currentIndex + 1 ?></span>
                                <span><?= app_escape($label) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <?php if ($step === 'details'): ?>
                    <form class="booking-panel" method="post" action="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>&amp;step=details">
                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                        <input type="hidden" name="flight_id" value="<?= app_escape($flight['id']) ?>">
                        <input type="hidden" name="action" value="save_details">

                        <h2>Passenger details</h2>
                        <p class="text-secondary small">Enter each traveler’s name as it should appear on the reservation.</p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="traveler_count">Number of travelers</label>
                            <select
                                class="form-select"
                                id="traveler_count"
                                name="traveler_count"
                                data-ticket-price="<?= app_escape($flight['ticket_price']) ?>"
                            >
                                <?php for ($count = 1; $count <= min(6, (int) $flight['remaining_seats']); $count++): ?>
                                    <option value="<?= $count ?>" <?= $travelerCount === $count ? 'selected' : '' ?>>
                                        <?= $count ?> <?= $count === 1 ? 'traveler' : 'travelers' ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <?php for ($index = 0; $index < 6; $index++): ?>
                            <?php $travelerInput = $travelerInputs[$index] ?? ['first_name' => '', 'last_name' => '']; ?>
                            <fieldset class="booking-traveler" data-traveler-index="<?= $index ?>">
                                <legend class="visually-hidden">Traveler <?= $index + 1 ?> details</legend>
                                <h3>Traveler <?= $index + 1 ?></h3>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="traveler_<?= $index ?>_first_name">First name</label>
                                        <input
                                            class="form-control"
                                            id="traveler_<?= $index ?>_first_name"
                                            name="travelers[<?= $index ?>][first_name]"
                                            value="<?= app_escape($travelerInput['first_name']) ?>"
                                            maxlength="100"
                                            autocomplete="given-name">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="traveler_<?= $index ?>_last_name">Last name</label>
                                        <input
                                            class="form-control"
                                            id="traveler_<?= $index ?>_last_name"
                                            name="travelers[<?= $index ?>][last_name]"
                                            value="<?= app_escape($travelerInput['last_name']) ?>"
                                            maxlength="100"
                                            autocomplete="family-name">
                                    </div>
                                </div>
                            </fieldset>
                        <?php endfor; ?>

                        <div class="booking-summary-fare mt-4">
                            <span>Indicative total</span>
                            <output id="booking_total"><?= app_escape($formatNaira(number_format((float) $flight['ticket_price'] * $travelerCount, 2, '.', ''))) ?></output>
                        </div>
                        <p class="booking-summary-meta mt-2">The reservation is pending; no payment is collected.</p>
                        <button class="btn app-primary-button btn-lg w-100 mt-3" type="submit">Continue to seat selection</button>
                    </form>
                <?php elseif ($step === 'seats' && $draft !== null && $seatMap !== null): ?>
                    <form class="booking-panel" method="post" action="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>&amp;step=seats&amp;seat_page=<?= $seatMap['page'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                        <input type="hidden" name="flight_id" value="<?= app_escape($flight['id']) ?>">
                        <input type="hidden" name="seat_page" value="<?= $seatMap['page'] ?>">
                        <input type="hidden" name="action" value="select_seat">

                        <h2>Choose seats</h2>
                        <p class="text-secondary small">
                            Choose <?= app_escape((string) $draft['traveler_count']) ?> available seats.
                            Select the traveler above before choosing a seat. Each row has up to six seats, labelled A–F.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="traveler_index">Assign the next seat to</label>
                            <select class="form-select" id="traveler_index" name="traveler_index">
                                <?php foreach ($draft['travelers'] as $index => $traveler): ?>
                                    <option value="<?= $index ?>">
                                        Traveler <?= $index + 1 ?> — <?= app_escape($traveler['first_name'] . ' ' . $traveler['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <p class="booking-seat-legend">
                            <span><span class="booking-seat-key is-available" aria-hidden="true"></span> Available</span>
                            <span><span class="booking-seat-key is-selected" aria-hidden="true"></span> Selected</span>
                            <span><span class="booking-seat-key is-unavailable" aria-hidden="true"></span> Unavailable</span>
                        </p>
                        <div class="booking-seat-map" role="group" aria-label="Seat selection">
                            <?php foreach ($seatMap['seats'] as $seat): ?>
                                <?php
                                $selectedTraveler = array_search($seat['label'], $draft['seats'], true);
                                $isSelected = $selectedTraveler !== false;
                                $isAvailable = $seat['available'];
                                $seatDescription = $isSelected
                                    ? 'selected for traveler ' . ($selectedTraveler + 1)
                                    : ($isAvailable ? 'available' : 'unavailable');
                                ?>
                                <button
                                    class="booking-seat <?= $isSelected ? 'is-selected' : ($isAvailable ? 'is-available' : 'is-unavailable') ?>"
                                    type="submit"
                                    name="seat_label"
                                    value="<?= app_escape($seat['label']) ?>"
                                    aria-label="Seat <?= app_escape($seat['label']) ?>, <?= app_escape($seatDescription) ?>"
                                    <?= !$isAvailable ? 'disabled' : '' ?>
                                >
                                    <?= app_escape($seat['label']) ?>
                                    <?php if ($isSelected): ?>
                                        <span class="visually-hidden">Selected for traveler <?= $selectedTraveler + 1 ?></span>
                                    <?php elseif (!$isAvailable): ?>
                                        <span class="visually-hidden">Unavailable</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($seatMap['page_count'] > 1): ?>
                            <nav class="booking-seat-pagination" aria-label="Seat map pages">
                                <?php if ($seatMap['page'] > 0): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>&amp;step=seats&amp;seat_page=<?= $seatMap['page'] - 1 ?>">Previous seats</a>
                                <?php endif; ?>
                                <span>Rows <?= ($seatMap['page'] * 10) + 1 ?>–<?= min((($seatMap['page'] + 1) * 10), (int) ceil($seatMap['capacity'] / 6)) ?> of <?= (int) ceil($seatMap['capacity'] / 6) ?></span>
                                <?php if ($seatMap['page'] + 1 < $seatMap['page_count']): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>&amp;step=seats&amp;seat_page=<?= $seatMap['page'] + 1 ?>">Next seats</a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>

                        <ul class="booking-selected-seats" aria-live="polite">
                            <?php foreach ($draft['travelers'] as $index => $traveler): ?>
                                <li>
                                    Traveler <?= $index + 1 ?>, <?= app_escape($traveler['first_name'] . ' ' . $traveler['last_name']) ?>:
                                    <strong><?= app_escape($draft['seats'][$index] ?? 'Choose a seat') ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                            <a class="btn btn-outline-secondary" href="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>&amp;step=details">Back to passenger details</a>
                            <button class="btn app-primary-button flex-grow-1" type="submit" name="action" value="save_seats">Continue to review</button>
                        </div>
                    </form>
                <?php elseif ($step === 'review' && $draft !== null): ?>
                    <section class="booking-panel" aria-labelledby="booking-review-title">
                        <h2 id="booking-review-title">Review your reservation</h2>
                        <p class="text-secondary small">Check every passenger and seat before confirming your pending reservation.</p>
                        <ul class="booking-review-passengers">
                            <?php foreach ($draft['travelers'] as $index => $traveler): ?>
                                <li>
                                    <span>Traveler <?= $index + 1 ?></span>
                                    <strong><?= app_escape($traveler['first_name'] . ' ' . $traveler['last_name']) ?></strong>
                                    <span>Seat <?= app_escape($draft['seats'][$index] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="booking-summary-fare">
                            <span><?= app_escape($formatNaira($flight['ticket_price'])) ?> × <?= $draft['traveler_count'] ?> traveler(s)</span>
                            <strong><?= app_escape($formatNaira(number_format((float) $flight['ticket_price'] * $draft['traveler_count'], 2, '.', ''))) ?></strong>
                        </div>
                        <p class="booking-summary-meta mt-3">No payment is collected. Final confirmation creates a pending reservation.</p>
                        <form method="post" action="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>&amp;step=review">
                            <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                            <input type="hidden" name="flight_id" value="<?= app_escape($flight['id']) ?>">
                            <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                                <button class="btn btn-outline-secondary" type="submit" name="action" value="back_to_seats">Change seats</button>
                                <button class="btn app-primary-button flex-grow-1" type="submit" name="action" value="confirm">Confirm reservation</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="col-12 col-lg-4" aria-labelledby="booking-flight-title">
                <div class="booking-summary">
                    <p class="flight-eyebrow mb-2">Selected flight</p>
                    <h2 id="booking-flight-title"><?= app_escape($flight['flight_number']) ?></h2>
                    <div class="booking-summary-route">
                        <div class="booking-summary-airport">
                            <strong><?= app_escape($flight['origin_iata_code']) ?></strong>
                            <span><?= app_escape($flight['origin_city']) ?></span>
                            <span><?= app_escape((new DateTimeImmutable($flight['departure_at']))->format('H:i')) ?></span>
                        </div>
                        <i class="fa-solid fa-plane text-secondary" aria-hidden="true"></i>
                        <div class="booking-summary-airport">
                            <strong><?= app_escape($flight['destination_iata_code']) ?></strong>
                            <span><?= app_escape($flight['destination_city']) ?></span>
                            <span><?= app_escape((new DateTimeImmutable($flight['arrival_at']))->format('H:i')) ?></span>
                        </div>
                    </div>
                    <p class="booking-summary-meta mb-2">
                        <?= app_escape((new DateTimeImmutable($flight['departure_at']))->format('D, d M Y')) ?>
                    </p>
                    <p class="booking-summary-meta mb-2"><?= app_escape($flight['remaining_seats']) ?> seats currently available</p>
                    <div class="booking-summary-fare">
                        <span>Fare per traveler</span>
                        <span><?= app_escape($formatNaira($flight['ticket_price'])) ?></span>
                    </div>
                    <p class="booking-summary-meta mt-3 mb-0">Reservations remain pending. No online payment is processed.</p>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</section>
<script>
    (() => {
        const countSelect = document.getElementById('traveler_count');
        const totalOutput = document.getElementById('booking_total');
        if (!countSelect || !totalOutput) {
            return;
        }

        const travelerFields = Array.from(document.querySelectorAll('[data-traveler-index]'));
        const fare = Number(countSelect.dataset.ticketPrice);
        const updateTravelers = () => {
            const count = Number(countSelect.value);
            travelerFields.forEach((fieldset, index) => {
                const visible = index < count;
                fieldset.hidden = !visible;
                fieldset.querySelectorAll('input').forEach(input => {
                    input.required = visible;
                });
            });
            totalOutput.textContent = '₦' + (fare * count).toLocaleString('en-NG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        countSelect.addEventListener('change', updateTravelers);
        updateTravelers();
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
