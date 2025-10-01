<?php

use SimpleBBS\Application;
use SimpleBBS\Http\Request;
use SimpleBBS\SimpleBBS;
use SimpleBBS\Support\Config;

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

if (!class_exists(SimpleBBS::class)) {
    require_once __DIR__ . '/../src/autoload.php';
}

$config = Config::load(__DIR__ . '/../.env');
$storagePath = $config->storagePath(__DIR__ . '/../.storage');

$bbs = SimpleBBS::create($storagePath);
$app = Application::create(storagePath: $storagePath, bbs: $bbs, config: $config);
$app->handle(Request::fromGlobals());
