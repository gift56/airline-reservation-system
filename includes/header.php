<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
app_start_session();

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Airline Reservation';
$pageDescription = isset($pageDescription) && is_string($pageDescription)
    ? $pageDescription
    : 'Manage your airline reservation account.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= app_escape($pageDescription) ?>">
    <title><?= app_escape($pageTitle) ?> | Airline Reservation</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<?php require __DIR__ . '/navbar.php'; ?>
<main class="page-main">
