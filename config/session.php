<?php

declare(strict_types=1);

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    if (
        ini_set('session.use_strict_mode', '1') === false
        || ini_set('session.use_only_cookies', '1') === false
        || ini_set('session.use_trans_sid', '0') === false
    ) {
        throw new RuntimeException('Unable to apply secure session settings.');
    }

    if (!session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ])) {
        throw new RuntimeException('Unable to configure the session cookie.');
    }

    if (!session_start()) {
        throw new RuntimeException('Unable to start the application session.');
    }
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function app_is_valid_csrf_token(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * @param array{id: string, full_name: string, role: string} $identity
 */
function app_login(array $identity): void
{
    if (!in_array($identity['role'], ['passenger', 'admin'], true)) {
        throw new InvalidArgumentException('Unsupported account role.');
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to renew the authenticated session.');
    }

    $_SESSION['auth_user'] = $identity;
    unset($_SESSION['_csrf_token']);
}

/**
 * @return array{id: string, full_name: string, role: string}|null
 */
function app_current_user(): ?array
{
    $identity = $_SESSION['auth_user'] ?? null;

    if (
        !is_array($identity)
        || !isset($identity['id'], $identity['full_name'], $identity['role'])
        || !is_string($identity['id'])
        || !is_string($identity['full_name'])
        || !is_string($identity['role'])
        || !in_array($identity['role'], ['passenger', 'admin'], true)
    ) {
        unset($_SESSION['auth_user']);

        return null;
    }

    return [
        'id' => $identity['id'],
        'full_name' => $identity['full_name'],
        'role' => $identity['role'],
    ];
}

function app_set_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'danger', 'info'], true)) {
        throw new InvalidArgumentException('Unsupported flash message type.');
    }

    $_SESSION['_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * @return array{type: string, message: string}|null
 */
function app_take_flash(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    if (
        !is_array($flash)
        || !isset($flash['type'], $flash['message'])
        || !is_string($flash['type'])
        || !is_string($flash['message'])
        || !in_array($flash['type'], ['success', 'danger', 'info'], true)
    ) {
        return null;
    }

    return [
        'type' => $flash['type'],
        'message' => $flash['message'],
    ];
}

function app_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_logout(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();

    $cookieOptions = [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ];

    if ($params['domain'] !== '') {
        $cookieOptions['domain'] = $params['domain'];
    }

    if (!setcookie(session_name(), '', $cookieOptions)) {
        throw new RuntimeException('Unable to expire the session cookie.');
    }

    if (!session_destroy()) {
        throw new RuntimeException('Unable to destroy the application session.');
    }
}
