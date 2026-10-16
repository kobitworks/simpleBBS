<?php

// Keep SQLite files private even when newly created by PHP.
umask(0077);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

use SimpleBBS\Application;

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

if (!class_exists(Application::class)) {
    require_once __DIR__ . '/../src/autoload.php';
}

$storagePath = getenv('STORAGE_PATH') ?: getenv('SIMPLEBBS_STORAGE_PATH') ?: null;

if ($storagePath === null) {
    $envFile = __DIR__ . '/../.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            if ($key === 'STORAGE_PATH' || $key === 'SIMPLEBBS_STORAGE_PATH') {
                $storagePath = trim($value);
                break;
            }
        }
    }
}

$app = Application::create($storagePath ?: __DIR__ . '/../.storage');
$app->handle();
