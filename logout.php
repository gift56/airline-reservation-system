<?php

declare(strict_types=1);

require_once __DIR__ . '/config/session.php';

app_start_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

if (!app_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid form token. Return to the previous page and try again.');
}

app_logout();
header('Location: login.php?logged_out=1');
exit;
