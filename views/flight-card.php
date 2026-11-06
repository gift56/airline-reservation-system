<?php

declare(strict_types=1);

$departure = new DateTimeImmutable($flight['departure_at']);
$arrival = new DateTimeImmutable($flight['arrival_at']);
?>
<article class="flight-card">
    <div class="flight-card-main">
        <div class="flight-card-heading">
            <div>
                <span class="flight-number"><?= app_escape($flight['flight_number']) ?></span>
                <p class="flight-route-caption mb-0">
                    <?= app_escape($flight['origin_city']) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <?= app_escape($flight['destination_city']) ?>
                </p>
            </div>
            <span class="flight-seat-count">
                <i class="fa-solid fa-chair" aria-hidden="true"></i>
                <?= app_escape($flight['remaining_seats']) ?> seats left
            </span>
        </div>

        <div class="flight-route">
            <div class="flight-airport">
                <time datetime="<?= app_escape($departure->format(DATE_ATOM)) ?>">
                    <?= app_escape($departure->format('H:i')) ?>
                </time>
                <strong><?= app_escape($flight['origin_iata_code']) ?></strong>
                <span><?= app_escape($departure->format('D, d M')) ?></span>
            </div>
            <div class="flight-route-line" aria-label="Flight duration">
                <span><?= app_escape($formatDuration($flight['departure_at'], $flight['arrival_at'])) ?></span>
                <i class="fa-solid fa-plane" aria-hidden="true"></i>
            </div>
            <div class="flight-airport flight-airport-destination">
                <time datetime="<?= app_escape($arrival->format(DATE_ATOM)) ?>">
                    <?= app_escape($arrival->format('H:i')) ?>
                </time>
                <strong><?= app_escape($flight['destination_iata_code']) ?></strong>
                <span><?= app_escape($arrival->format('D, d M')) ?></span>
            </div>
        </div>
    </div>

    <div class="flight-card-fare">
        <span class="fare-label">From</span>
        <strong><span aria-label="Nigerian naira">₦</span><?= app_escape($formatFare($flight['ticket_price'])) ?></strong>
        <span class="fare-per-passenger">per passenger</span>
        <a class="btn flight-book-button" href="book-flight.php?flight_id=<?= app_escape($flight['id']) ?>">
            Book flight
        </a>
    </div>
</article>
