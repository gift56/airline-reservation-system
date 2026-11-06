<?php

declare(strict_types=1);

$navUser = app_current_user();
?>
<nav class="navbar navbar-expand-lg app-navbar" aria-label="Main navigation">
    <div class="container">
        <span class="navbar-brand">Airline Reservation</span>
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
                    <a class="nav-link" href="login.php">Sign in</a>
                    <a class="btn btn-primary app-primary-button" href="register.php">Create account</a>
                <?php else: ?>
                    <span class="nav-link"><?= app_escape($navUser['full_name']) ?></span>
                    <span class="badge rounded-pill app-role-badge">
                        <?= $navUser['role'] === 'admin' ? 'Administrator' : 'Passenger' ?>
                    </span>
                    <form method="post" action="logout.php" class="ms-lg-2">
                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                        <button class="btn btn-outline-primary app-logout-button" type="submit">Sign out</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
