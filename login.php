<?php

declare(strict_types=1);

use App\Models\User;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/User.php';

app_start_session();

$pageTitle = 'Sign in';
$pageDescription = 'Sign in to your airline reservation account.';
$returnTo = app_booking_return_path($_GET['return_to'] ?? null);
$email = '';
$errors = [];
$pdo = null;

try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (PDOException | RuntimeException $exception) {
    error_log('Authentication database connection failed: ' . $exception->getMessage());
    $errors[] = 'Account services are temporarily unavailable. Please try again later.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $returnTo = app_booking_return_path($_POST['return_to'] ?? null);
    $emailValue = $_POST['email'] ?? null;
    $passwordValue = $_POST['password'] ?? null;
    $email = is_string($emailValue) ? strtolower(trim($emailValue)) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';

    if (!app_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please reload the page and try again.';
    }

    if ($email === ''
        || strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || $password === ''
        || strpos($password, "\0") !== false
    ) {
        $errors[] = 'Enter a valid email address and password.';
    }

    if ($errors === [] && $pdo instanceof PDO) {
        try {
            $user = (new User($pdo))->findByEmail($email);

            if ($user === null || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'The email or password was not recognized.';
            } elseif (!in_array($user['role'], ['passenger', 'admin'], true)) {
                error_log('Login denied for an account with an unsupported role.');
                $errors[] = 'The email or password was not recognized.';
            } else {
                app_login([
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                ]);
                $destination = $user['role'] === 'admin'
                    ? 'admin-flights.php'
                    : ($returnTo ?? 'login.php');
                header('Location: ' . $destination);
                exit;
            }
        } catch (PDOException | RuntimeException $exception) {
            error_log('Login processing failed: ' . $exception->getMessage());
            $errors[] = 'We could not sign you in right now. Please try again later.';
        }
    }
}

$flash = app_take_flash();
$loggedOut = ($_GET['logged_out'] ?? null) === '1';
$authenticatedUser = app_current_user();
if ($authenticatedUser !== null && $returnTo !== null) {
    header('Location: ' . ($authenticatedUser['role'] === 'passenger' ? $returnTo : 'admin-flights.php'));
    exit;
}
$registerUrl = 'register.php' . ($returnTo !== null ? '?return_to=' . rawurlencode($returnTo) : '');

require __DIR__ . '/includes/header.php';
?>
<section class="container auth-section">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-xl-9">
            <div class="auth-card card border-0 shadow-sm">
                <div class="row g-0">
                    <div class="col-lg-4">
                        <div class="auth-promo">
                            <span class="auth-promo-mark"><i class="fa-solid fa-plane-departure" aria-hidden="true"></i></span>
                            <div class="auth-promo-copy">
                                <h2>Make room for what’s next.</h2>
                                <p>Sign in to pick up where your next journey begins.</p>
                            </div>
                            <span class="auth-promo-note">Thoughtful travel starts with a smoother booking experience.</span>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card-body p-4 p-md-5">
                            <p class="auth-eyebrow mb-2">Welcome back</p>
                            <h1 class="h2 mb-2">Sign in</h1>
                            <p class="text-secondary mb-4">Access your airline reservation account.</p>

                            <?php if ($flash !== null): ?>
                                <div class="alert alert-<?= app_escape($flash['type']) ?>" role="status">
                                    <?= app_escape($flash['message']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($loggedOut): ?>
                                <div class="alert alert-info" role="status">You have been signed out.</div>
                            <?php endif; ?>

                            <?php if ($errors !== []): ?>
                                <div class="alert alert-danger" role="alert" aria-live="assertive">
                                    <ul class="mb-0 ps-3">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= app_escape($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if ($authenticatedUser !== null): ?>
                                <div class="alert alert-success mb-0" role="status">
                                    You are signed in as <strong><?= app_escape($authenticatedUser['full_name']) ?></strong>.
                                    Your account role is <?= $authenticatedUser['role'] === 'admin' ? 'Administrator' : 'Passenger' ?>.
                                </div>
                            <?php else: ?>
                                <form method="post" action="login.php">
                                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                                    <?php if ($returnTo !== null): ?>
                                        <input type="hidden" name="return_to" value="<?= app_escape($returnTo) ?>">
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email address</label>
                                        <input
                                            type="email"
                                            class="form-control form-control-lg"
                                            id="email"
                                            name="email"
                                            value="<?= app_escape($email) ?>"
                                            maxlength="254"
                                            autocomplete="username"
                                            required
                                        >
                                    </div>
                                    <div class="mb-4">
                                        <label for="password" class="form-label">Password</label>
                                        <input
                                            type="password"
                                            class="form-control form-control-lg"
                                            id="password"
                                            name="password"
                                            autocomplete="current-password"
                                            required
                                        >
                                    </div>
                                    <button class="btn btn-primary btn-lg app-primary-button w-100" type="submit">Sign in</button>
                                </form>

                                <p class="text-center text-secondary mt-4 mb-0">
                                    New here? <a href="<?= app_escape($registerUrl) ?>">Create a passenger account</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
