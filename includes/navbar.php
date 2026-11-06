<?php

declare(strict_types=1);

$navUser = app_current_user();
?>
<nav class="navbar navbar-expand-md app-navbar" data-bs-theme="dark" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="app-brand-mark"><i class="fa-solid fa-plane" aria-hidden="true"></i></span>
            <span>Airline<span class="app-brand-light">Reservation</span></span>
        </a>
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-2 py-lg-0">
                <?php if ($navUser === null): ?>
                    <a class="nav-link" href="login.php"><i class="fa-regular fa-user me-2" aria-hidden="true"></i>Sign in</a>
                    <a class="btn app-primary-button" href="register.php">Create account</a>
                <?php else: ?>
                    <span class="nav-link"><i class="fa-regular fa-circle-user me-2" aria-hidden="true"></i><?= app_escape($navUser['full_name']) ?></span>
                    <span class="badge rounded-pill app-role-badge">
                        <?= $navUser['role'] === 'admin' ? 'Administrator' : 'Passenger' ?>
                    </span>
                    <?php if ($navUser['role'] === 'admin'): ?>
                        <a class="nav-link" href="admin-flights.php#flight-list"><i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Manage flights</a>
                    <?php endif; ?>
                    <form method="post" action="logout.php" class="ms-lg-2">
                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                        <button class="btn btn-outline-light app-logout-button" type="submit">Sign out</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
