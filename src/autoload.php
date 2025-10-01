<?php

spl_autoload_register(static function (string $class): void {
    $prefix = 'SimpleBBS\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($file)) {
        require_once $file;
    }
});
