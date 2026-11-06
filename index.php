<?php

declare(strict_types=1);

use App\Models\Flight;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Flight.php';

app_start_session();

$pageTitle = 'Find your next flight';
$pageDescription = 'Search scheduled flights across Nigeria and beyond.';
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$searchAttempted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['search'])
    && $_GET['search'] === '1';
$readQueryValue = static function (string $key): string {
    $value = $_GET[$key] ?? '';

    return is_string($value) ? trim($value) : '';
};
$search = [
    'origin' => $readQueryValue('origin'),
    'destination' => $readQueryValue('destination'),
    'date' => $readQueryValue('date'),
    'min_price' => $readQueryValue('min_price'),
    'max_price' => $readQueryValue('max_price'),
    'departure_after' => $readQueryValue('departure_after'),
    'departure_before' => $readQueryValue('departure_before'),
    'arrival_after' => $readQueryValue('arrival_after'),
    'arrival_before' => $readQueryValue('arrival_before'),
    'sort' => $readQueryValue('sort') !== '' ? $readQueryValue('sort') : 'price',
];
$airports = [];
$flights = [];
$upcomingFlights = [];
$errors = [];
$databaseError = false;
$airportDataUnavailable = false;
$searchValid = false;
$pdo = null;

try {
    $pdo = require __DIR__ . '/config/db.php';
    $flightModel = new Flight($pdo);
    $airports = $flightModel->listAirports();
    $airportDataUnavailable = count($airports) < 2;
    $upcomingFlights = $flightModel->listUpcomingAvailableFlights(8);
} catch (PDOException | RuntimeException $exception) {
    error_log('Public flight search database connection failed: ' . $exception->getMessage());
    $databaseError = true;
}

if ($searchAttempted && !$databaseError) {
    $airportIds = array_column($airports, 'id');
    $originId = filter_var($search['origin'], FILTER_VALIDATE_INT);
    $destinationId = filter_var($search['destination'], FILTER_VALIDATE_INT);

    if ($originId === false || !in_array((string) $originId, $airportIds, true)) {
        $errors[] = 'Choose a valid origin airport.';
    }

    if ($destinationId === false || !in_array((string) $destinationId, $airportIds, true)) {
        $errors[] = 'Choose a valid destination airport.';
    }

    if ($originId !== false && $destinationId !== false && $originId === $destinationId) {
        $errors[] = 'Choose a different destination airport.';
    }

    $travelDate = DateTimeImmutable::createFromFormat('!Y-m-d', $search['date']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        $travelDate === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $travelDate->format('Y-m-d') !== $search['date']
        || $search['date'] < $today
    ) {
        $errors[] = 'Choose today or a future departure date.';
    }

    foreach (['min_price', 'max_price'] as $priceField) {
        if (
            $search[$priceField] !== ''
            && (
                preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $search[$priceField]) !== 1
                || (float) $search[$priceField] <= 0
            )
        ) {
            $errors[] = 'Enter a positive price using up to two decimal places.';
            break;
        }
    }

    if (
        $search['min_price'] !== ''
        && $search['max_price'] !== ''
        && preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $search['min_price']) === 1
        && preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $search['max_price']) === 1
        && (float) $search['min_price'] > (float) $search['max_price']
    ) {
        $errors[] = 'The minimum fare cannot be greater than the maximum fare.';
    }

    foreach (['departure_after', 'departure_before', 'arrival_after', 'arrival_before'] as $timeField) {
        if ($search[$timeField] !== '' && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $search[$timeField]) !== 1) {
            $errors[] = 'Enter departure and arrival times in a valid 24-hour format.';
            break;
        }
    }

    foreach (
        [
            ['departure_after', 'departure_before'],
            ['arrival_after', 'arrival_before'],
        ] as [$startField, $endField]
    ) {
        if (
            $search[$startField] !== ''
            && $search[$endField] !== ''
            && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $search[$startField]) === 1
            && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $search[$endField]) === 1
            && $search[$startField] > $search[$endField]
        ) {
            $errors[] = 'A time range must end at or after its start time.';
        }
    }

    if (!in_array($search['sort'], ['price', 'departure'], true)) {
        $errors[] = 'Choose a valid flight sort order.';
    }

    if ($errors === [] && $pdo instanceof PDO && $originId !== false && $destinationId !== false) {
        try {
            $flights = (new Flight($pdo))->searchAvailableFlights(
                $originId,
                $destinationId,
                $search['date'],
                [
                    'min_price' => $search['min_price'],
                    'max_price' => $search['max_price'],
                    'departure_after' => $search['departure_after'],
                    'departure_before' => $search['departure_before'],
                    'arrival_after' => $search['arrival_after'],
                    'arrival_before' => $search['arrival_before'],
                    'sort' => $search['sort'],
                ]
            );
            $searchValid = true;
        } catch (PDOException | RuntimeException $exception) {
            error_log('Public flight search query failed: ' . $exception->getMessage());
            $databaseError = true;
        }
    }
}

$formatFare = static function (string $fare): string {
    return rtrim(rtrim(number_format((float) $fare, 2, '.', ','), '0'), '.');
};
$formatDuration = static function (string $departureAt, string $arrivalAt): string {
    $departure = new DateTimeImmutable($departureAt);
    $arrival = new DateTimeImmutable($arrivalAt);
    $minutes = (int) floor(($arrival->getTimestamp() - $departure->getTimestamp()) / 60);
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return $hours > 0 ? $hours . 'h ' . $remainingMinutes . 'm' : $remainingMinutes . 'm';
};
$renderFlightCard = static function (array $flight) use ($formatFare, $formatDuration): void {
    require __DIR__ . '/views/flight-card.php';
};

require __DIR__ . '/includes/header.php';
?>
<section class="flight-search-page">
    <div class="container flight-search-container">
        <div class="flight-intro">
            <p class="flight-eyebrow"><i class="fa-solid fa-sparkles" aria-hidden="true"></i> Better journeys start here</p>
            <h1>Where will you <span>go next?</span></h1>
            <p class="flight-intro-copy">Find the right flight and make your next trip feel effortless.</p>
        </div>

        <form class="flight-search-panel" method="get" action="index.php" aria-label="Search for flights">
            <input type="hidden" name="search" value="1">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="origin">From</label>
                    <div class="flight-search-field">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <select class="form-select" id="origin" name="origin" required <?= $databaseError || $airportDataUnavailable ? 'disabled' : '' ?>>
                            <option value="">Choose origin</option>
                            <?php foreach ($airports as $airport): ?>
                                <option
                                    value="<?= app_escape($airport['id']) ?>"
                                    <?= $search['origin'] === $airport['id'] ? 'selected' : '' ?>>
                                    <?= app_escape($airport['city'] . ' · ' . $airport['iata_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="destination">To</label>
                    <div class="flight-search-field">
                        <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                        <select class="form-select" id="destination" name="destination" required <?= $databaseError || $airportDataUnavailable ? 'disabled' : '' ?>>
                            <option value="">Choose destination</option>
                            <?php foreach ($airports as $airport): ?>
                                <option
                                    value="<?= app_escape($airport['id']) ?>"
                                    <?= $search['destination'] === $airport['id'] ? 'selected' : '' ?>>
                                    <?= app_escape($airport['city'] . ' · ' . $airport['iata_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date">Departure</label>
                    <div class="flight-search-field">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <input
                            class="form-control"
                            id="date"
                            type="date"
                            name="date"
                            min="<?= app_escape($today) ?>"
                            value="<?= app_escape($search['date']) ?>"
                            required>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <button class="btn flight-search-button w-100" type="submit" <?= $databaseError || $airportDataUnavailable ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Search flights
                    </button>
                </div>
            </div>
        </form>

        <?php if ($databaseError): ?>
            <div class="alert alert-danger flight-feedback" role="alert">
                Flight search is temporarily unavailable. Please try again later.
            </div>
        <?php elseif ($airportDataUnavailable): ?>
            <div class="alert alert-info flight-feedback" role="status">
                Airport options are not available yet, so flight search cannot run right now.
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger flight-feedback" role="alert" aria-live="assertive">
                <p class="fw-semibold mb-2">Check your search details:</p>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= app_escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($searchValid): ?>
            <section class="flight-results-section" aria-labelledby="results-title">
                <div class="flight-results-heading">
                    <div>
                        <p class="flight-eyebrow mb-2">Your journey, sorted</p>
                        <h2 id="results-title">Available flights</h2>
                        <p class="text-secondary mb-0">
                            <?= count($flights) ?> <?= count($flights) === 1 ? 'flight' : 'flights' ?> found
                        </p>
                    </div>
                    <span class="flight-result-date">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <?= app_escape((new DateTimeImmutable($search['date']))->format('D, d M Y')) ?>
                    </span>
                </div>

                <form method="get" action="index.php" class="row g-4 flight-results-grid">
                    <input type="hidden" name="search" value="1">
                    <input type="hidden" name="origin" value="<?= app_escape($search['origin']) ?>">
                    <input type="hidden" name="destination" value="<?= app_escape($search['destination']) ?>">
                    <input type="hidden" name="date" value="<?= app_escape($search['date']) ?>">
                    <div class="col-12 col-lg-8">
                        <div class="flight-sort-row">
                            <label for="sort" class="form-label mb-0">Sort by</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="price" <?= $search['sort'] === 'price' ? 'selected' : '' ?>>Lowest fare</option>
                                <option value="departure" <?= $search['sort'] === 'departure' ? 'selected' : '' ?>>Earliest departure</option>
                            </select>
                        </div>

                        <?php if ($flights === []): ?>
                            <div class="flight-empty-state" role="status">
                                <span class="flight-empty-icon"><i class="fa-solid fa-plane-slash" aria-hidden="true"></i></span>
                                <h3>No flights found</h3>
                                <p class="mb-0">Try another date or adjust your fare and time filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="flight-card-list">
                                <?php foreach ($flights as $flight): ?>
                                    <?php $renderFlightCard($flight); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="col-12 col-lg-4" aria-labelledby="filters-title">
                        <div class="flight-filter-panel">
                            <div class="flight-filter-heading">
                                <h3 id="filters-title">Filters</h3>
                                <a href="?<?= app_escape(http_build_query([
                                                'search' => '1',
                                                'origin' => $search['origin'],
                                                'destination' => $search['destination'],
                                                'date' => $search['date'],
                                            ])) ?>">Reset</a>
                            </div>

                            <fieldset class="flight-filter-group">
                                <legend>Ticket price (₦)</legend>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="visually-hidden" for="min_price">Minimum ticket price</label>
                                        <input
                                            class="form-control"
                                            id="min_price"
                                            name="min_price"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            placeholder="Min"
                                            value="<?= app_escape($search['min_price']) ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="visually-hidden" for="max_price">Maximum ticket price</label>
                                        <input
                                            class="form-control"
                                            id="max_price"
                                            name="max_price"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            placeholder="Max"
                                            value="<?= app_escape($search['max_price']) ?>">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="flight-filter-group">
                                <legend>Departure time</legend>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="flight-time-label" for="departure_after">From</label>
                                        <input class="form-control" id="departure_after" name="departure_after" type="time" value="<?= app_escape($search['departure_after']) ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="flight-time-label" for="departure_before">Until</label>
                                        <input class="form-control" id="departure_before" name="departure_before" type="time" value="<?= app_escape($search['departure_before']) ?>">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="flight-filter-group">
                                <legend>Arrival time</legend>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="flight-time-label" for="arrival_after">From</label>
                                        <input class="form-control" id="arrival_after" name="arrival_after" type="time" value="<?= app_escape($search['arrival_after']) ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="flight-time-label" for="arrival_before">Until</label>
                                        <input class="form-control" id="arrival_before" name="arrival_before" type="time" value="<?= app_escape($search['arrival_before']) ?>">
                                    </div>
                                </div>
                            </fieldset>

                            <button class="btn flight-filter-button w-100" type="submit">
                                Update results <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </aside>
                </form>
            </section>
        <?php elseif (!$searchAttempted && !$databaseError): ?>
            <div class="flight-search-prompt">
                <div class="flight-search-prompt-icon"><i class="fa-solid fa-plane-departure" aria-hidden="true"></i></div>
                <div>
                    <h2>Ready when you are</h2>
                    <p class="mb-0">Browse available flights or choose your route and travel date to find a trip.</p>
                </div>
            </div>
            <section class="flight-results-section upcoming-flights-section" aria-labelledby="upcoming-flights-title">
                <div class="flight-results-heading">
                    <div>
                        <p class="flight-eyebrow mb-2">Your next trip is closer</p>
                        <h2 id="upcoming-flights-title">Upcoming available flights</h2>
                        <p class="text-secondary mb-0">Fictional demo schedules · fares shown in Nigerian naira</p>
                    </div>
                </div>
                <?php if ($upcomingFlights === []): ?>
                    <div class="flight-empty-state" role="status">
                        <span class="flight-empty-icon"><i class="fa-solid fa-plane-slash" aria-hidden="true"></i></span>
                        <h3>No upcoming flights are available</h3>
                        <p class="mb-0">Please check back later or search for another travel date.</p>
                    </div>
                <?php else: ?>
                    <div class="flight-card-list">
                        <?php foreach ($upcomingFlights as $flight): ?>
                            <?php $renderFlightCard($flight); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>