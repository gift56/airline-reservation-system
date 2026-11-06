<?php

declare(strict_types=1);

use App\Models\User;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/User.php';

app_start_session();

$pageTitle = 'Sign in';
$pageDescription = 'Sign in to your airline reservation account.';
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
                header('Location: login.php');
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

require __DIR__ . '/includes/header.php';
?>
<section class="container auth-section">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
            <div class="auth-card card border-0 shadow-sm">
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
                            New here? <a href="register.php">Create a passenger account</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
