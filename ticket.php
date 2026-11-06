<?php

declare(strict_types=1);

use App\Models\Booking;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Booking.php';

app_start_session();

$currentUser = app_current_user();
if ($currentUser === null || $currentUser['role'] !== 'passenger') {
    http_response_code(403);
    exit('A passenger account is required to view this reservation.');
}

$referenceValue = $_GET['ref'] ?? null;
$reference = is_string($referenceValue) ? $referenceValue : '';
$ticket = null;
$databaseError = false;

try {
    $pdo = require __DIR__ . '/config/db.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('The database connection did not return PDO.');
    }

    $ticket = (new Booking($pdo))->findTicketForPassenger($reference, $currentUser['id']);
} catch (PDOException | RuntimeException $exception) {
    error_log('Passenger ticket page failed: ' . $exception->getMessage());
    $databaseError = true;
}

if ($ticket === null && !$databaseError) {
    http_response_code(404);
}

$pageTitle = 'Reservation ticket';
$pageDescription = 'View and print your passenger reservation ticket.';
$formatNaira = static fn (string $amount): string => '₦' . number_format((float) $amount, 2, '.', ',');
$fareTotal = $ticket !== null && $ticket['ticket_price'] !== null
    ? (float) $ticket['ticket_price'] * count($ticket['travelers'])
    : null;

require __DIR__ . '/includes/header.php';
?>
<section class="container ticket-page">
    <?php if ($databaseError): ?>
        <div class="alert alert-danger" role="alert">
            Reservation details are temporarily unavailable. Please try again later.
        </div>
    <?php elseif ($ticket === null): ?>
        <div class="alert alert-warning" role="alert">This reservation could not be found.</div>
        <a class="btn app-primary-button" href="index.php">Back to flights</a>
    <?php else: ?>
        <div class="ticket-actions">
            <a class="btn btn-outline-secondary" href="book-flight.php?flight_id=<?= app_escape($ticket['flight']['id']) ?>">Return to reservation</a>
            <button class="btn app-primary-button" type="button" id="print-ticket">Print reservation</button>
            <a class="btn btn-outline-dark" href="ticket-download.php?ref=<?= app_escape($ticket['booking_reference']) ?>">Download PDF</a>
        </div>

        <article class="ticket-sheet" aria-labelledby="ticket-title">
            <header class="ticket-header">
                <div>
                    <p class="flight-eyebrow mb-2">Airline reservation</p>
                    <h1 id="ticket-title">Reservation ticket</h1>
                </div>
                <div class="ticket-status booking-status <?= app_escape(app_booking_status_class($ticket['status'])) ?>">
                    <span>Status</span>
                    <strong><?= app_escape(ucfirst($ticket['status'])) ?></strong>
                </div>
            </header>

            <div class="ticket-reference">
                <span>Booking reference</span>
                <strong><?= app_escape($ticket['booking_reference']) ?></strong>
            </div>

            <section class="ticket-itinerary" aria-labelledby="ticket-itinerary-title">
                <h2 id="ticket-itinerary-title">Flight itinerary</h2>
                <p class="ticket-flight-number"><?= app_escape($ticket['flight']['flight_number']) ?></p>
                <div class="ticket-route ticket-route-vertical">
                    <div class="ticket-route-stop">
                        <span class="ticket-route-marker" aria-hidden="true"><i class="fa-solid fa-circle"></i></span>
                        <div class="ticket-route-stop-details">
                            <span class="ticket-route-stop-label">Departure</span>
                            <strong><?= app_escape($ticket['flight']['origin_iata_code']) ?></strong>
                            <span><?= app_escape($ticket['flight']['origin_city']) ?></span>
                            <time datetime="<?= app_escape((new DateTimeImmutable($ticket['flight']['departure_at']))->format(DATE_ATOM)) ?>">
                                <?= app_escape((new DateTimeImmutable($ticket['flight']['departure_at']))->format('D, d M Y · H:i')) ?>
                            </time>
                        </div>
                    </div>
                    <div class="ticket-route-connector" aria-hidden="true">
                        <span><i class="fa-solid fa-plane"></i></span>
                    </div>
                    <div class="ticket-route-stop">
                        <span class="ticket-route-marker" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                        <div class="ticket-route-stop-details">
                            <span class="ticket-route-stop-label">Arrival</span>
                            <strong><?= app_escape($ticket['flight']['destination_iata_code']) ?></strong>
                            <span><?= app_escape($ticket['flight']['destination_city']) ?></span>
                            <time datetime="<?= app_escape((new DateTimeImmutable($ticket['flight']['arrival_at']))->format(DATE_ATOM)) ?>">
                                <?= app_escape((new DateTimeImmutable($ticket['flight']['arrival_at']))->format('D, d M Y · H:i')) ?>
                            </time>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ticket-travelers" aria-labelledby="ticket-travelers-title">
                <h2 id="ticket-travelers-title">Passengers and seats</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Passenger</th>
                                <th scope="col">Seat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ticket['travelers'] as $traveler): ?>
                                <tr>
                                    <td><?= app_escape($traveler['first_name'] . ' ' . $traveler['last_name']) ?></td>
                                    <td><?= app_escape($traveler['seat_label'] ?? 'Not assigned') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ticket-fare" aria-label="Fare summary">
                <span>Fare summary</span>
                <?php if ($fareTotal !== null): ?>
                    <strong><?= app_escape($formatNaira(number_format($fareTotal, 2, '.', ''))) ?></strong>
                    <small><?= app_escape($formatNaira($ticket['ticket_price'])) ?> per passenger</small>
                <?php else: ?>
                    <strong>Fare not recorded</strong>
                    <small>This legacy reservation has no saved booking-time fare.</small>
                <?php endif; ?>
            </section>

            <p class="ticket-disclaimer">
                This document records a <?= app_escape($ticket['status']) ?> reservation. No payment was collected.
                It is not a paid or confirmed ticket and is not a boarding pass.
            </p>
        </article>
    <?php endif; ?>
</section>
<script>
    document.getElementById('print-ticket')?.addEventListener('click', () => window.print());
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
