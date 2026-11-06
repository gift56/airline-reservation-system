<?php

declare(strict_types=1);

use App\Models\Booking;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Booking.php';

app_start_session();

$currentUser = app_current_user();
if ($currentUser === null) {
    header('Location: login.php?return_to=' . rawurlencode('my-bookings.php'));
    exit;
}

if ($currentUser['role'] !== 'passenger') {
    http_response_code(403);
    exit('A passenger account is required to view bookings.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!app_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        app_set_flash('danger', 'Your form session expired. Reload the page and try again.');
    } else {
        $referenceValue = $_POST['booking_reference'] ?? null;
        $reference = is_string($referenceValue) ? $referenceValue : '';
        $actionValue = $_POST['action'] ?? null;
        $action = is_string($actionValue) ? $actionValue : '';

        if ($action !== 'cancel' || preg_match('/\A[a-f0-9]{24}\z/', $reference) !== 1) {
            app_set_flash('danger', 'This cancellation request could not be verified.');
        } else {
            try {
                $pdo = require __DIR__ . '/config/db.php';
                if (!$pdo instanceof PDO) {
                    throw new RuntimeException('The database connection did not return PDO.');
                }

                $cancelled = (new Booking($pdo))->cancelUpcomingBookingForPassenger(
                    $reference,
                    $currentUser['id']
                );
                app_set_flash(
                    $cancelled ? 'success' : 'danger',
                    $cancelled
                        ? 'Your reservation has been canceled.'
                        : 'This reservation can no longer be canceled. It may have departed or is not available to your account.'
                );
            } catch (PDOException | RuntimeException $exception) {
                error_log('Passenger booking cancellation failed: ' . $exception->getMessage());
                app_set_flash('danger', 'We could not cancel this reservation right now. Please try again later.');
            }
        }
    }

    header('Location: my-bookings.php', true, 303);
    exit;
}

$pageTitle = 'My bookings';
$pageDescription = 'Review and manage your upcoming airline reservations.';
$bookings = [];
$databaseError = false;

try {
    $pdo = require __DIR__ . '/config/db.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('The database connection did not return PDO.');
    }

    $bookings = (new Booking($pdo))->findUpcomingBookingsForPassenger($currentUser['id']);
} catch (PDOException | RuntimeException $exception) {
    error_log('Passenger bookings dashboard failed: ' . $exception->getMessage());
    $databaseError = true;
}

$flash = app_take_flash();
$passengerCount = array_sum(array_map(
    static fn (array $booking): int => max(1, count($booking['travelers'])),
    $bookings
));
$formatNaira = static fn (string $amount): string => '₦' . number_format((float) $amount, 2, '.', ',');

require __DIR__ . '/includes/header.php';
?>
<section class="my-bookings-page">
    <div class="container my-bookings-container">
        <header class="my-bookings-heading">
            <div>
                <p class="flight-eyebrow mb-2"><i class="fa-solid fa-compass" aria-hidden="true"></i> Passenger dashboard</p>
                <h1>Your trips, all in one place</h1>
                <p class="my-bookings-intro">Welcome back, <?= app_escape($currentUser['full_name']) ?>. Review your upcoming itineraries and manage your reservations.</p>
            </div>
            <a class="btn app-primary-button my-bookings-search-link" href="index.php">
                <i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Find a flight
            </a>
        </header>

        <div class="my-bookings-stats" role="group" aria-label="Upcoming reservation summary">
            <div class="my-bookings-stat">
                <span class="my-bookings-stat-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></span>
                <div>
                    <span class="my-bookings-stat-label">Upcoming bookings</span>
                    <strong><?= $databaseError ? '—' : count($bookings) ?></strong>
                </div>
            </div>
            <div class="my-bookings-stat">
                <span class="my-bookings-stat-icon my-bookings-stat-icon-light" aria-hidden="true"><i class="fa-solid fa-user-group"></i></span>
                <div>
                    <span class="my-bookings-stat-label">Travelers on your trips</span>
                    <strong><?= $databaseError ? '—' : $passengerCount ?></strong>
                </div>
            </div>
            <div class="my-bookings-next-trip">
                <span class="my-bookings-stat-label">Next departure</span>
                <?php if ($databaseError): ?>
                    <strong>Temporarily unavailable</strong>
                    <span>Departure details could not be loaded.</span>
                <?php elseif ($bookings !== []): ?>
                    <?php $nextDeparture = new DateTimeImmutable($bookings[0]['flight']['departure_at']); ?>
                    <strong><?= app_escape($nextDeparture->format('D, d M')) ?></strong>
                    <span><?= app_escape($bookings[0]['flight']['origin_iata_code']) ?> <i class="fa-solid fa-arrow-right mx-1" aria-hidden="true"></i> <?= app_escape($bookings[0]['flight']['destination_iata_code']) ?></span>
                <?php else: ?>
                    <strong>Nothing scheduled</strong>
                    <span>Your next journey starts here.</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= app_escape($flash['type']) ?> my-bookings-alert" role="status" aria-live="polite">
                <?= app_escape($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">
                Your bookings are temporarily unavailable. Please try again later.
            </div>
        <?php elseif ($bookings === []): ?>
            <section class="my-bookings-empty" aria-labelledby="empty-bookings-title">
                <span class="my-bookings-empty-icon" aria-hidden="true"><i class="fa-solid fa-plane"></i></span>
                <p class="flight-eyebrow mb-2">A little room for adventure</p>
                <h2 id="empty-bookings-title">No upcoming bookings yet</h2>
                <p>Your future reservations will appear here with all the details you need for your trip.</p>
                <a class="btn app-primary-button" href="index.php">Explore available flights</a>
            </section>
        <?php else: ?>
            <div class="my-bookings-section-heading">
                <div>
                    <p class="flight-eyebrow mb-1">Your itinerary</p>
                    <h2>Upcoming bookings</h2>
                </div>
                <span><?= count($bookings) ?> <?= count($bookings) === 1 ? 'trip' : 'trips' ?></span>
            </div>

            <div class="my-bookings-list">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $departure = new DateTimeImmutable($booking['flight']['departure_at']);
                    $arrival = new DateTimeImmutable($booking['flight']['arrival_at']);
                    $travelerCount = count($booking['travelers']);
                    $bookingReference = $booking['booking_reference'];
                    ?>
                    <article class="my-booking-card" aria-labelledby="booking-<?= app_escape($bookingReference) ?>">
                        <header class="my-booking-card-header">
                            <div>
                                <span class="my-booking-flight-label">Flight <?= app_escape($booking['flight']['flight_number']) ?></span>
                                <h3 id="booking-<?= app_escape($bookingReference) ?>">
                                    <?= app_escape($booking['flight']['origin_city']) ?>
                                    <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                                    <?= app_escape($booking['flight']['destination_city']) ?>
                                </h3>
                            </div>
                            <span class="my-booking-status booking-status <?= app_escape(app_booking_status_class($booking['status'])) ?>">
                                <?= app_escape(ucfirst($booking['status'])) ?>
                            </span>
                        </header>

                        <div class="my-booking-route">
                            <div class="my-booking-airport">
                                <strong><?= app_escape($booking['flight']['origin_iata_code']) ?></strong>
                                <span><?= app_escape($booking['flight']['origin_city']) ?></span>
                                <time datetime="<?= app_escape($departure->format(DATE_ATOM)) ?>"><?= app_escape($departure->format('D, d M Y')) ?></time>
                                <span><?= app_escape($departure->format('H:i')) ?></span>
                            </div>
                            <div class="my-booking-route-line" aria-hidden="true">
                                <span><?= app_escape($booking['flight']['flight_number']) ?></span>
                                <i class="fa-solid fa-plane"></i>
                            </div>
                            <div class="my-booking-airport my-booking-airport-end">
                                <strong><?= app_escape($booking['flight']['destination_iata_code']) ?></strong>
                                <span><?= app_escape($booking['flight']['destination_city']) ?></span>
                                <time datetime="<?= app_escape($arrival->format(DATE_ATOM)) ?>"><?= app_escape($arrival->format('D, d M Y')) ?></time>
                                <span><?= app_escape($arrival->format('H:i')) ?></span>
                            </div>
                        </div>

                        <div class="my-booking-details">
                            <div class="my-booking-reference">
                                <span>Booking reference</span>
                                <strong><?= app_escape($bookingReference) ?></strong>
                            </div>
                            <div class="my-booking-travelers">
                                <span><?= $travelerCount > 0 ? $travelerCount . ' ' . ($travelerCount === 1 ? 'traveler' : 'travelers') : 'Traveler details unavailable' ?></span>
                                <?php if ($travelerCount > 0): ?>
                                    <ul class="my-booking-traveler-list">
                                        <?php foreach ($booking['travelers'] as $traveler): ?>
                                            <li>
                                                <span><?= app_escape($traveler['first_name'] . ' ' . $traveler['last_name']) ?></span>
                                                <span class="my-booking-seat"><?= app_escape($traveler['seat_label'] ?? 'Seat not assigned') ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div class="my-booking-fare">
                                <span>Saved fare</span>
                                <?php if ($booking['ticket_price'] !== null): ?>
                                    <strong><?= app_escape($formatNaira($booking['ticket_price'])) ?></strong>
                                    <small>per traveler<?php if ($travelerCount > 1): ?> · <?= app_escape($formatNaira(number_format((float) $booking['ticket_price'] * $travelerCount, 2, '.', ''))) ?> total<?php endif; ?></small>
                                <?php else: ?>
                                    <strong>Not recorded</strong>
                                    <small>Legacy reservation</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <footer class="my-booking-card-actions">
                            <a class="btn btn-outline-dark" href="ticket.php?ref=<?= app_escape(rawurlencode($bookingReference)) ?>">
                                <i class="fa-regular fa-eye me-2" aria-hidden="true"></i>View / print ticket
                            </a>
                            <a class="btn btn-outline-secondary" href="ticket-download.php?ref=<?= app_escape(rawurlencode($bookingReference)) ?>">
                                <i class="fa-solid fa-download me-2" aria-hidden="true"></i>Download PDF
                            </a>
                            <form method="post" action="my-bookings.php" onsubmit="return window.confirm('Cancel this upcoming reservation?');">
                                <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_reference" value="<?= app_escape($bookingReference) ?>">
                                <button class="btn btn-link my-booking-cancel" type="submit">Cancel booking</button>
                            </form>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
