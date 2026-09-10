<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}

$requested = realpath(__DIR__ . $path);
if ($path !== '/' && $requested && str_starts_with($requested, __DIR__) && is_file($requested)) {
    return false;
}

require __DIR__ . '/index.html';
