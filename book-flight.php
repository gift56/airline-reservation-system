<?php

declare(strict_types=1);

use App\Models\Booking;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Booking.php';

app_start_session();

$pageTitle = 'Reserve your flight';
$pageDescription = 'Reserve seats for your upcoming flight.';
$flightIdValue = $_GET['flight_id'] ?? ($_POST['flight_id'] ?? null);
$flightId = is_string($flightIdValue)
    ? filter_var($flightIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$currentUser = app_current_user();
$errors = [];
$flight = null;
$databaseError = false;
$confirmation = $_SESSION['_booking_confirmation'] ?? null;
$confirmationFlightFields = [
    'id',
    'flight_number',
    'departure_at',
    'arrival_at',
    'ticket_price',
    'remaining_seats',
    'origin_iata_code',
    'origin_city',
    'destination_iata_code',
    'destination_city',
];
$confirmationFlightValid = is_array($confirmation['flight'] ?? null);
if ($confirmationFlightValid) {
    foreach ($confirmationFlightFields as $field) {
        if (!isset($confirmation['flight'][$field]) || !is_string($confirmation['flight'][$field])) {
            $confirmationFlightValid = false;
            break;
        }
    }
}
if (
    !is_array($confirmation)
    || !isset(
        $confirmation['user_id'],
        $confirmation['booking_reference'],
        $confirmation['traveler_count'],
        $confirmation['total_fare'],
        $confirmation['flight']
    )
    || !is_string($confirmation['user_id'])
    || !is_string($confirmation['booking_reference'])
    || preg_match('/\A[a-f0-9]{24}\z/', $confirmation['booking_reference']) !== 1
    || !is_int($confirmation['traveler_count'])
    || $confirmation['traveler_count'] < 1
    || $confirmation['traveler_count'] > 6
    || !is_string($confirmation['total_fare'])
    || preg_match('/\A\d{1,9}\.\d{2}\z/', $confirmation['total_fare']) !== 1
    || !$confirmationFlightValid
    || $currentUser === null
    || $confirmation['user_id'] !== $currentUser['id']
) {
    unset($_SESSION['_booking_confirmation']);
    $confirmation = null;
} else {
    unset($_SESSION['_booking_confirmation']);
}

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
} elseif ($confirmation === null) {
    $travelerCount = 1;
    $travelerInputs = array_fill(0, 6, ['first_name' => '', 'last_name' => '']);
    $pdo = null;

    $travelerCountValue = $_POST['traveler_count'] ?? null;
    if ($travelerCountValue !== null) {
        $validatedTravelerCount = is_string($travelerCountValue)
            ? filter_var($travelerCountValue, FILTER_VALIDATE_INT)
            : false;
        if ($validatedTravelerCount === false || $validatedTravelerCount < 1 || $validatedTravelerCount > 6) {
            $errors[] = 'Choose between one and six travelers.';
        } else {
            $travelerCount = $validatedTravelerCount;
        }
    }

    if (isset($_POST['travelers']) && is_array($_POST['travelers'])) {
        for ($index = 0; $index < 6; $index++) {
            $travelerInput = $_POST['travelers'][$index] ?? null;
            if (!is_array($travelerInput)) {
                continue;
            }

            $firstName = $travelerInput['first_name'] ?? '';
            $lastName = $travelerInput['last_name'] ?? '';
            $travelerInputs[$index] = [
                'first_name' => is_string($firstName) ? trim($firstName) : '',
                'last_name' => is_string($lastName) ? trim($lastName) : '',
            ];
        }
    }

    try {
        $pdo = require __DIR__ . '/config/db.php';
        $bookingModel = new Booking($pdo);
        $flight = $bookingModel->findBookableFlight($flightId);

        if ($flight === null) {
            $errors[] = 'This flight is no longer available. Please choose another flight.';
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!app_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
                $errors[] = 'Your form session expired. Please reload the page and try again.';
            }

            $postedFlightIdValue = $_POST['flight_id'] ?? null;
            $postedFlightId = is_string($postedFlightIdValue)
                ? filter_var($postedFlightIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($postedFlightId === false || $postedFlightId !== $flightId) {
                $errors[] = 'The selected flight could not be verified. Please try again.';
            }

            if ($travelerCountValue === null) {
                $errors[] = 'Choose how many travelers to reserve seats for.';
            }

            for ($index = 0; $index < $travelerCount; $index++) {
                $firstName = $travelerInputs[$index]['first_name'];
                $lastName = $travelerInputs[$index]['last_name'];
                if (
                    preg_match('/\A[^\r\n\0]{1,100}\z/u', $firstName) !== 1
                    || preg_match('/\A[^\r\n\0]{1,100}\z/u', $lastName) !== 1
                ) {
                    $errors[] = 'Enter a first and last name for every traveler (up to 100 characters each).';
                    break;
                }
            }

            if ($errors === [] && $flight !== null && $pdo instanceof PDO) {
                $travelers = array_slice($travelerInputs, 0, $travelerCount);
                try {
                    $booking = $bookingModel->createPendingBooking(
                        $currentUser['id'],
                        $flightId,
                        $travelers
                    );
                    $_SESSION['_booking_confirmation'] = [
                        'user_id' => $currentUser['id'],
                        'booking_reference' => $booking['booking_reference'],
                        'traveler_count' => $travelerCount,
                        'total_fare' => number_format(
                            (float) $booking['ticket_price'] * $travelerCount,
                            2,
                            '.',
                            ''
                        ),
                        'flight' => $flight,
                    ];
                    header('Location: book-flight.php?flight_id=' . $flightId, true, 303);
                    exit;
                } catch (DomainException | InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                } catch (Throwable $exception) {
                    error_log('Passenger booking creation failed: ' . $exception->getMessage());
                    $errors[] = 'We could not reserve these seats right now. Please try again later.';
                }
            }
        }
    } catch (PDOException | RuntimeException $exception) {
        error_log('Passenger booking page failed: ' . $exception->getMessage());
        $databaseError = true;
    }
}

$formatNaira = static fn (float $amount): string => '₦' . number_format($amount, 2, '.', ',');

require __DIR__ . '/includes/header.php';
?>
<section class="container booking-section">
    <p class="flight-eyebrow"><i class="fa-solid fa-ticket" aria-hidden="true"></i> Secure your seats</p>
    <h1 class="h2 fw-bold mb-2">Reserve your flight</h1>
    <p class="text-secondary mb-4">Add traveler details to request a reservation. Payment is not collected on this page.</p>

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

    <?php if ($confirmation !== null): ?>
        <?php $confirmedFlight = $confirmation['flight']; ?>
        <section class="booking-panel booking-confirmation" aria-labelledby="booking-confirmation-title">
            <p class="auth-eyebrow mb-2">Reservation received</p>
            <h2 id="booking-confirmation-title">Your seats are reserved</h2>
            <p class="text-secondary">Your reservation is pending. This is not a paid or confirmed ticket.</p>
            <p class="small fw-semibold mb-2">Booking reference</p>
            <p class="booking-reference"><?= app_escape($confirmation['booking_reference']) ?></p>
            <div class="booking-summary-route">
                <div class="booking-summary-airport">
                    <strong><?= app_escape($confirmedFlight['origin_iata_code']) ?></strong>
                    <span><?= app_escape($confirmedFlight['origin_city']) ?></span>
                </div>
                <i class="fa-solid fa-arrow-right text-secondary" aria-hidden="true"></i>
                <div class="booking-summary-airport">
                    <strong><?= app_escape($confirmedFlight['destination_iata_code']) ?></strong>
                    <span><?= app_escape($confirmedFlight['destination_city']) ?></span>
                </div>
            </div>
            <p class="booking-summary-meta mb-2">
                <?= app_escape((new DateTimeImmutable($confirmedFlight['departure_at']))->format('D, d M Y · H:i')) ?>
                · <?= app_escape($confirmedFlight['flight_number']) ?>
            </p>
            <p class="booking-summary-meta">
                <?= app_escape((string) $confirmation['traveler_count']) ?>
                <?= $confirmation['traveler_count'] === 1 ? 'traveler' : 'travelers' ?>
                · Indicative total <?= app_escape($formatNaira((float) $confirmation['total_fare'])) ?>
            </p>
            <a class="btn app-primary-button" href="index.php">Back to flights</a>
        </section>
    <?php elseif ($flight !== null && $currentUser !== null && $currentUser['role'] === 'passenger'): ?>
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <form class="booking-panel" method="post" action="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                    <input type="hidden" name="flight_id" value="<?= app_escape($flight['id']) ?>">

                    <h2>Traveler details</h2>
                    <p class="text-secondary small">Enter each traveler’s name as it should appear on the reservation.</p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="traveler_count">Number of travelers</label>
                        <select class="form-select" id="traveler_count" name="traveler_count" data-ticket-price="<?= app_escape($flight['ticket_price']) ?>">
                            <?php for ($count = 1; $count <= 6; $count++): ?>
                                <option value="<?= $count ?>" <?= $travelerCount === $count ? 'selected' : '' ?>>
                                    <?= $count ?> <?= $count === 1 ? 'traveler' : 'travelers' ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php for ($index = 0; $index < 6; $index++): ?>
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
                                        value="<?= app_escape($travelerInputs[$index]['first_name']) ?>"
                                        maxlength="100"
                                        autocomplete="given-name"
                                        <?= $index === 0 ? 'required' : '' ?>>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="traveler_<?= $index ?>_last_name">Last name</label>
                                    <input
                                        class="form-control"
                                        id="traveler_<?= $index ?>_last_name"
                                        name="travelers[<?= $index ?>][last_name]"
                                        value="<?= app_escape($travelerInputs[$index]['last_name']) ?>"
                                        maxlength="100"
                                        autocomplete="family-name"
                                        <?= $index === 0 ? 'required' : '' ?>>
                                </div>
                            </div>
                        </fieldset>
                    <?php endfor; ?>

                    <div class="booking-summary-fare mt-4">
                        <span>Indicative total</span>
                        <output id="booking_total"><?= app_escape($formatNaira((float) $flight['ticket_price'] * $travelerCount)) ?></output>
                    </div>
                    <p class="booking-summary-meta mt-2">No payment is collected. Reservations are created with pending status.</p>
                    <button class="btn app-primary-button btn-lg w-100 mt-3" type="submit">
                        <i class="fa-solid fa-lock me-2" aria-hidden="true"></i>Reserve seats
                    </button>
                </form>
            </div>

            <aside class="col-12 col-lg-5" aria-labelledby="booking-flight-title">
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
                    <p class="booking-summary-meta mb-2">
                        <?= app_escape($flight['remaining_seats']) ?> seats currently available
                    </p>
                    <div class="booking-summary-fare">
                        <span>Fare per traveler</span>
                        <span><?= app_escape($formatNaira((float) $flight['ticket_price'])) ?></span>
                    </div>
                    <p class="booking-summary-meta mt-3 mb-0">
                        This is a reservation request only. No ticket payment or confirmation is processed online.
                    </p>
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
