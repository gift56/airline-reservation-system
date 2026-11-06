<?php

declare(strict_types=1);

use App\Models\User;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/User.php';

app_start_session();

$pageTitle = 'Create your account';
$pageDescription = 'Create a passenger account to use the airline reservation system.';
$fullName = '';
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
    $fullNameValue = $_POST['full_name'] ?? null;
    $emailValue = $_POST['email'] ?? null;
    $passwordValue = $_POST['password'] ?? null;
    $confirmationValue = $_POST['password_confirmation'] ?? null;
    $fullName = is_string($fullNameValue) ? trim($fullNameValue) : '';
    $email = is_string($emailValue) ? strtolower(trim($emailValue)) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    $passwordConfirmation = is_string($confirmationValue) ? $confirmationValue : '';

    if (!app_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please reload the page and try again.';
    }

    if ($fullName === '' || preg_match('/\A[^\r\n]{1,120}\z/u', $fullName) !== 1) {
        $errors[] = 'Enter a name of no more than 120 characters.';
    }

    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Enter a valid email address.';
    }

    if (
        preg_match('/\A.{8,}\z/us', $password) !== 1
        || strlen($password) > 72
        || strpos($password, "\0") !== false
    ) {
        $errors[] = 'Your password must be at least 8 characters and no more than 72 bytes.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'The password confirmation does not match.';
    }

    if ($errors === [] && $pdo instanceof PDO) {
        $users = new User($pdo);

        try {
            if ($users->emailExists($email)) {
                $errors[] = 'An account already exists for that email address.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $users->createPassenger($fullName, $email, $passwordHash);
                app_set_flash('success', 'Your passenger account is ready. Sign in to continue.');
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'An account already exists for that email address.';
            } else {
                error_log('Passenger registration failed: ' . $exception->getMessage());
                $errors[] = 'We could not create your account right now. Please try again later.';
            }
        } catch (RuntimeException $exception) {
            error_log('Passenger password hashing failed: ' . $exception->getMessage());
            $errors[] = 'We could not create your account right now. Please try again later.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="container auth-section">
    <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-6 col-xl-5">
            <div class="auth-card card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="auth-eyebrow mb-2">Passenger account</p>
                    <h1 class="h2 mb-2">Create your account</h1>
                    <p class="text-secondary mb-4">Set up your details to get started with your airline account.</p>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger" role="alert" aria-live="assertive">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= app_escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="register.php">
                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full name</label>
                            <input
                                type="text"
                                class="form-control form-control-lg"
                                id="full_name"
                                name="full_name"
                                value="<?= app_escape($fullName) ?>"
                                maxlength="120"
                                autocomplete="name"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input
                                type="email"
                                class="form-control form-control-lg"
                                id="email"
                                name="email"
                                value="<?= app_escape($email) ?>"
                                maxlength="254"
                                autocomplete="email"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input
                                type="password"
                                class="form-control form-control-lg"
                                id="password"
                                name="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                aria-describedby="passwordHelp"
                                required>
                            <div id="passwordHelp" class="form-text">Use at least 8 characters</div>
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <input
                                type="password"
                                class="form-control form-control-lg"
                                id="password_confirmation"
                                name="password_confirmation"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required>
                        </div>
                        <button class="btn btn-primary btn-lg app-primary-button w-100" type="submit">
                            Create passenger account
                        </button>
                    </form>

                    <p class="text-center text-secondary mt-4 mb-0">
                        Already registered? <a href="login.php">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>