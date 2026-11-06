<?php

declare(strict_types=1);

if (!defined('APP_ADMIN_FLIGHTS_VIEW')) {
    header('Location: ../../admin-flights.php');
    exit;
}

$flights = $flights ?? [];
$airports = $airports ?? [];
$pageWindowStart = $pageWindowStart ?? max(1, $currentPage - 2);
$pageWindowEnd = $pageWindowEnd ?? min($totalPages, $pageWindowStart + 4);
$pageNumbers = $pageNumbers ?? range($pageWindowStart, $pageWindowEnd);
$formValues = array_replace([
    'flight_number' => '',
    'origin_airport_id' => '',
    'destination_airport_id' => '',
    'departure_at' => '',
    'arrival_at' => '',
    'seat_capacity' => '',
    'ticket_price' => '',
], isset($formValues) && is_array($formValues) ? $formValues : []);
$airportValues = array_replace([
    'iata_code' => '',
    'name' => '',
    'city' => '',
    'country' => '',
], isset($airportValues) && is_array($airportValues) ? $airportValues : []);
$createAirportValues = array_replace([
    'iata_code' => '',
    'name' => '',
    'city' => '',
    'country' => '',
], isset($createAirportValues) && is_array($createAirportValues) ? $createAirportValues : []);

require __DIR__ . '/../../includes/header.php';
?>
<section class="container admin-flights-section">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div>
            <p class="admin-eyebrow mb-2">Administrator workspace</p>
            <h1 class="h2 mb-2">Manage flights</h1>
            <p class="text-secondary mb-0">Create schedules, update flight details, and manage availability.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                class="btn btn-outline-primary"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#airport-modal"
            >Add airport</button>
            <button
                class="btn btn-primary app-primary-button"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#flight-modal"
                <?= $dashboardError !== null ? 'disabled' : '' ?>
            >
            Add a flight
            </button>
        </div>
    </div>

    <?php if (isset($flash) && $flash !== null): ?>
        <div class="alert alert-<?= app_escape($flash['type']) ?>" role="status">
            <?= app_escape($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($dashboardError !== null): ?>
        <div class="alert alert-danger" role="alert">
            <?= app_escape($dashboardError) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert" aria-live="assertive">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?= app_escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-4">
            <section class="card admin-panel-card border-0 shadow-sm" aria-labelledby="airports-title">
                <div class="card-body p-4">
                    <p class="admin-eyebrow mb-2">Airport setup</p>
                    <h2 id="airports-title" class="h4 mb-3">Airports</h2>
                    <?php if ($dashboardError !== null): ?>
                        <p class="text-secondary mb-0">Airport management is unavailable until the database issue above is resolved.</p>
                    <?php elseif (empty($airports)): ?>
                        <p class="text-secondary mb-3">No airports added yet. Add at least two airports before creating a flight.</p>
                        <button
                            class="btn btn-outline-primary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#airport-modal"
                        >Add your first airport</button>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($airports as $airport): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2">
                                        <div>
                                            <strong><?= app_escape($airport['iata_code']) ?></strong>
                                            <span class="text-secondary"> · <?= app_escape($airport['city']) ?>, <?= app_escape($airport['country']) ?></span>
                                            <span class="d-block small text-secondary"><?= app_escape($airport['name']) ?></span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a
                                                class="btn btn-sm btn-outline-primary"
                                                href="admin-flights.php?edit_airport=<?= app_escape($airport['id']) ?>&amp;page=<?= app_escape((string) $currentPage) ?>"
                                            >Edit</a>
                                            <form
                                                method="post"
                                                action="admin-flights.php"
                                                onsubmit="return confirm('Delete this airport and any flights using it? Deletion is blocked if a flight has bookings.')"
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                                                <input type="hidden" name="return_page" value="<?= app_escape((string) $currentPage) ?>">
                                                <input type="hidden" name="action" value="delete_airport">
                                                <input type="hidden" name="airport_id" value="<?= app_escape($airport['id']) ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button
                            class="btn btn-outline-primary mt-3"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#airport-modal"
                        >Add another airport</button>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="modal fade" id="flight-modal" tabindex="-1" aria-labelledby="flight-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content admin-panel-card">
                    <div class="modal-header">
                        <div>
                            <p class="admin-eyebrow mb-2"><?= $isEditing ? 'Update schedule' : 'Flight setup' ?></p>
                            <h2 class="modal-title fs-5" id="flight-modal-title">
                                <?= $isEditing ? 'Edit flight' : 'Add a flight' ?>
                            </h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($dashboardError !== null): ?>
                            <div class="alert alert-secondary mb-0" role="status">
                                Flight actions are unavailable until the database issue above is resolved.
                            </div>
                        <?php else: ?>
                        <form method="post" action="admin-flights.php">
                            <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                            <input type="hidden" name="return_page" value="<?= app_escape((string) $currentPage) ?>">
                            <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                            <?php if ($isEditing && $editingId !== null): ?>
                                <input type="hidden" name="flight_id" value="<?= app_escape((string) $editingId) ?>">
                            <?php endif; ?>

                            <?php if (count($airports) < 2): ?>
                                <div class="alert alert-info" role="status">
                                    <p class="mb-3">Add at least two airports before saving a flight.</p>
                                    <button
                                        class="btn btn-outline-primary"
                                        type="button"
                                        data-bs-dismiss="modal"
                                        data-open-modal-after-dismiss="airport-modal"
                                    >Add an airport</button>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="flight_number" class="form-label">Flight code</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="flight_number"
                                    name="flight_number"
                                    value="<?= app_escape($formValues['flight_number']) ?>"
                                    maxlength="10"
                                    pattern="[A-Za-z0-9-]{1,10}"
                                    autocomplete="off"
                                    required
                                >
                                <div class="form-text">Up to 10 letters, numbers, or hyphens.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label for="origin_airport_id" class="form-label">Origin</label>
                                    <select class="form-select" id="origin_airport_id" name="origin_airport_id" required>
                                        <option value=""><?= $airports === [] ? 'Add airports first' : 'Choose airport' ?></option>
                                        <?php foreach ($airports as $airport): ?>
                                            <?php $airportLabel = $airport['city'] . ' (' . $airport['iata_code'] . ')'; ?>
                                            <option
                                                value="<?= app_escape($airport['id']) ?>"
                                                <?= $formValues['origin_airport_id'] === $airport['id'] ? 'selected' : '' ?>
                                            >
                                                <?= app_escape($airportLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label for="destination_airport_id" class="form-label">Destination</label>
                                    <select class="form-select" id="destination_airport_id" name="destination_airport_id" required>
                                        <option value=""><?= $airports === [] ? 'Add airports first' : 'Choose airport' ?></option>
                                        <?php foreach ($airports as $airport): ?>
                                            <?php $airportLabel = $airport['city'] . ' (' . $airport['iata_code'] . ')'; ?>
                                            <option
                                                value="<?= app_escape($airport['id']) ?>"
                                                <?= $formValues['destination_airport_id'] === $airport['id'] ? 'selected' : '' ?>
                                            >
                                                <?= app_escape($airportLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="departure_at" class="form-label">Departure</label>
                                    <input
                                        type="datetime-local"
                                        class="form-control"
                                        id="departure_at"
                                        name="departure_at"
                                        value="<?= app_escape($formValues['departure_at']) ?>"
                                        required
                                    >
                                </div>
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="arrival_at" class="form-label">Arrival</label>
                                    <input
                                        type="datetime-local"
                                        class="form-control"
                                        id="arrival_at"
                                        name="arrival_at"
                                        value="<?= app_escape($formValues['arrival_at']) ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="seat_capacity" class="form-label">Total seats</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="seat_capacity"
                                        name="seat_capacity"
                                        value="<?= app_escape($formValues['seat_capacity']) ?>"
                                        min="1"
                                        max="65535"
                                        step="1"
                                        required
                                    >
                                </div>
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="ticket_price" class="form-label">Ticket price</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        id="ticket_price"
                                        name="ticket_price"
                                        value="<?= app_escape($formValues['ticket_price']) ?>"
                                        min="0.01"
                                        max="99999999.99"
                                        step="0.01"
                                        inputmode="decimal"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row gap-2 mt-2">
                                <button
                                    class="btn btn-primary app-primary-button"
                                    type="submit"
                                    <?= count($airports) < 2 ? 'disabled' : '' ?>
                                >
                                    <?= $isEditing ? 'Save changes' : 'Create flight' ?>
                                </button>
                                <?php if ($isEditing): ?>
                                    <a class="btn btn-outline-secondary" href="admin-flights.php?page=<?= app_escape((string) $currentPage) ?>">Cancel edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <section id="flight-list" class="card admin-panel-card border-0 shadow-sm" aria-labelledby="flight-list-title">
                <div class="card-body p-0">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 p-4">
                        <div>
                            <p class="admin-eyebrow mb-2">Schedule overview</p>
                            <h2 id="flight-list-title" class="h4 mb-0">Current flights</h2>
                            <?php if ($dashboardError === null): ?>
                                <p class="small text-secondary mt-2 mb-0">
                                    Showing <?= app_escape((string) $rangeStart) ?>–<?= app_escape((string) $rangeEnd) ?>
                                    of <?= app_escape((string) $totalFlights) ?> flights
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if ($dashboardError === null): ?>
                            <span class="badge rounded-pill admin-count-badge">
                                <?= app_escape((string) $totalFlights) ?>
                                <?= $totalFlights === 1 ? 'flight' : 'flights' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge rounded-pill admin-count-badge">Unavailable</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($dashboardError !== null): ?>
                        <div class="admin-empty-state mx-4 mb-4" role="status">
                            <h3 class="h6 mb-2">Flight list unavailable</h3>
                            <p class="text-secondary mb-0">Resolve the database issue above to load flight records.</p>
                        </div>
                    <?php elseif ($flights === []): ?>
                        <div class="admin-empty-state mx-4 mb-4">
                            <h3 class="h6 mb-2">No flights yet</h3>
                            <p class="text-secondary mb-0">Create a flight to start building the schedule.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table admin-flight-table align-middle mb-0">
                                <caption class="visually-hidden">Scheduled flights and management actions</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Flight</th>
                                        <th scope="col">Route</th>
                                        <th scope="col">Schedule</th>
                                        <th scope="col">Seats</th>
                                        <th scope="col">Ticket price</th>
                                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($flights as $flight): ?>
                                        <tr>
                                            <th scope="row">
                                                <span class="admin-flight-code"><?= app_escape($flight['flight_number']) ?></span>
                                            </th>
                                            <td>
                                                <div class="admin-route">
                                                    <?= app_escape($flight['origin_iata_code']) ?>
                                                    <span aria-hidden="true">&rarr;</span>
                                                    <?= app_escape($flight['destination_iata_code']) ?>
                                                </div>
                                                <span class="small text-secondary">
                                                    <?= app_escape($flight['origin_city']) ?> to <?= app_escape($flight['destination_city']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="d-block"><?= app_escape(substr($flight['departure_at'], 0, 16)) ?></span>
                                                <span class="small text-secondary">
                                                    Arrives <?= app_escape(substr($flight['arrival_at'], 0, 16)) ?>
                                                </span>
                                            </td>
                                            <td><?= app_escape($flight['seat_capacity']) ?></td>
                                            <td>
                                                <?php if ($flight['ticket_price'] === null): ?>
                                                    <span class="text-secondary">Price not set</span>
                                                <?php else: ?>
                                                    NGN <?= app_escape(number_format((float) $flight['ticket_price'], 2, '.', ',')) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column flex-sm-row gap-2">
                                                    <a
                                                        class="btn btn-sm btn-outline-primary"
                                                        href="admin-flights.php?edit=<?= app_escape($flight['id']) ?>&amp;page=<?= app_escape((string) $currentPage) ?>"
                                                    >Edit</a>
                                                    <form method="post" action="admin-flights.php">
                                                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                                                        <input type="hidden" name="return_page" value="<?= app_escape((string) $currentPage) ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="flight_id" value="<?= app_escape($flight['id']) ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">
                                                            Delete
                                                            <span class="visually-hidden"><?= app_escape($flight['flight_number']) ?></span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav class="px-4 pb-4" aria-label="Flight list pages">
                            <ul class="pagination justify-content-end mb-0">
                                <li class="page-item <?= $currentPage === 1 ? 'disabled' : '' ?>">
                                    <?php if ($currentPage === 1): ?>
                                        <span class="page-link">Previous</span>
                                    <?php else: ?>
                                        <a
                                            class="page-link"
                                            href="admin-flights.php?page=<?= app_escape((string) ($currentPage - 1)) ?>"
                                            aria-label="Go to previous page"
                                        >Previous</a>
                                    <?php endif; ?>
                                </li>

                                <?php if ($pageWindowStart > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="admin-flights.php?page=1" aria-label="Go to page 1">1</a>
                                    </li>
                                    <?php if ($pageWindowStart > 2): ?>
                                        <li class="page-item disabled" aria-hidden="true">
                                            <span class="page-link">&hellip;</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php foreach ($pageNumbers as $pageNumber): ?>
                                    <li class="page-item <?= $pageNumber === $currentPage ? 'active' : '' ?>">
                                        <?php if ($pageNumber === $currentPage): ?>
                                            <span class="page-link" aria-current="page">
                                                <span class="visually-hidden">Page </span><?= app_escape((string) $pageNumber) ?>
                                            </span>
                                        <?php else: ?>
                                            <a
                                                class="page-link"
                                                href="admin-flights.php?page=<?= app_escape((string) $pageNumber) ?>"
                                                aria-label="Go to page <?= app_escape((string) $pageNumber) ?>"
                                            ><?= app_escape((string) $pageNumber) ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>

                                <?php if ($pageWindowEnd < $totalPages): ?>
                                    <?php if ($pageWindowEnd < $totalPages - 1): ?>
                                        <li class="page-item disabled" aria-hidden="true">
                                            <span class="page-link">&hellip;</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            href="admin-flights.php?page=<?= app_escape((string) $totalPages) ?>"
                                            aria-label="Go to page <?= app_escape((string) $totalPages) ?>"
                                        ><?= app_escape((string) $totalPages) ?></a>
                                    </li>
                                <?php endif; ?>

                                <li class="page-item <?= $currentPage === $totalPages ? 'disabled' : '' ?>">
                                    <?php if ($currentPage === $totalPages): ?>
                                        <span class="page-link">Next</span>
                                    <?php else: ?>
                                        <a
                                            class="page-link"
                                            href="admin-flights.php?page=<?= app_escape((string) ($currentPage + 1)) ?>"
                                            aria-label="Go to next page"
                                        >Next</a>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>
<div class="modal fade" id="airport-modal" tabindex="-1" aria-labelledby="airport-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-panel-card">
            <div class="modal-header">
                <div>
                    <p class="admin-eyebrow mb-2">Airport setup</p>
                    <h2 class="modal-title fs-5" id="airport-modal-title">Add an airport</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="admin-flights.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                    <input type="hidden" name="return_page" value="<?= app_escape((string) $currentPage) ?>">
                    <input type="hidden" name="action" value="create_airport">

                    <div class="mb-3">
                        <label for="airport_iata_code" class="form-label">IATA code</label>
                        <input
                            type="text"
                            class="form-control text-uppercase"
                            id="airport_iata_code"
                            name="iata_code"
                            value="<?= app_escape($createAirportValues['iata_code']) ?>"
                            minlength="3"
                            maxlength="3"
                            pattern="[A-Za-z]{3}"
                            autocomplete="off"
                            aria-describedby="airportCodeHelp"
                            required
                        >
                        <div class="form-text" id="airportCodeHelp">Enter the airport’s unique three-letter code, such as LOS.</div>
                    </div>
                    <div class="mb-3">
                        <label for="airport_name" class="form-label">Airport name</label>
                        <input
                            type="text"
                            class="form-control"
                            id="airport_name"
                            name="name"
                            value="<?= app_escape($createAirportValues['name']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="airport_city" class="form-label">City</label>
                            <input
                                type="text"
                                class="form-control"
                                id="airport_city"
                                name="city"
                                value="<?= app_escape($createAirportValues['city']) ?>"
                                maxlength="100"
                                required
                            >
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="airport_country" class="form-label">Country</label>
                            <input
                                type="text"
                                class="form-control"
                                id="airport_country"
                                name="country"
                                value="<?= app_escape($createAirportValues['country']) ?>"
                                maxlength="100"
                                required
                            >
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary app-primary-button" type="submit">Save airport</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="airport-edit-modal" tabindex="-1" aria-labelledby="airport-edit-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-panel-card">
            <div class="modal-header">
                <div>
                    <p class="admin-eyebrow mb-2">Airport setup</p>
                    <h2 class="modal-title fs-5" id="airport-edit-modal-title">Edit airport</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="admin-flights.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                    <input type="hidden" name="return_page" value="<?= app_escape((string) $currentPage) ?>">
                    <input type="hidden" name="action" value="update_airport">
                    <?php if ($editingAirportId !== null): ?>
                        <input type="hidden" name="airport_id" value="<?= app_escape((string) $editingAirportId) ?>">
                    <?php endif; ?>
                    <?php if ($activeModal === 'airport-edit-modal' && !empty($errors)): ?>
                        <div class="alert alert-danger" role="alert" aria-live="assertive">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= app_escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="edit_airport_iata_code" class="form-label">IATA code</label>
                        <input
                            type="text"
                            class="form-control text-uppercase"
                            id="edit_airport_iata_code"
                            name="iata_code"
                            value="<?= app_escape($airportValues['iata_code']) ?>"
                            minlength="3"
                            maxlength="3"
                            pattern="[A-Za-z]{3}"
                            autocomplete="off"
                            required
                        >
                    </div>
                    <div class="mb-3">
                        <label for="edit_airport_name" class="form-label">Airport name</label>
                        <input
                            type="text"
                            class="form-control"
                            id="edit_airport_name"
                            name="name"
                            value="<?= app_escape($airportValues['name']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="edit_airport_city" class="form-label">City</label>
                            <input
                                type="text"
                                class="form-control"
                                id="edit_airport_city"
                                name="city"
                                value="<?= app_escape($airportValues['city']) ?>"
                                maxlength="100"
                                required
                            >
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="edit_airport_country" class="form-label">Country</label>
                            <input
                                type="text"
                                class="form-control"
                                id="edit_airport_country"
                                name="country"
                                value="<?= app_escape($airportValues['country']) ?>"
                                maxlength="100"
                                required
                            >
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary app-primary-button" type="submit">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-open-modal-after-dismiss]').forEach(function (button) {
            button.addEventListener('click', function () {
                const currentModal = button.closest('.modal');
                const targetId = button.getAttribute('data-open-modal-after-dismiss');
                const targetModal = targetId ? document.getElementById(targetId) : null;
                if (currentModal && targetModal && window.bootstrap) {
                    currentModal.addEventListener('hidden.bs.modal', function () {
                        bootstrap.Modal.getOrCreateInstance(targetModal).show();
                    }, { once: true });
                }
            });
        });

        const modalId = <?= json_encode($activeModal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const modal = modalId ? document.getElementById(modalId) : null;
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
