<?php

use SimpleBBS\Application;
use SimpleBBS\Http\Request;
use SimpleBBS\SimpleBBS;

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

if (!class_exists(SimpleBBS::class)) {
    require_once __DIR__ . '/../src/autoload.php';
}

$storagePath = getenv('SIMPLEBBS_STORAGE_PATH') ?: __DIR__ . '/../.storage';

$bbs = SimpleBBS::create($storagePath);
$app = Application::create(storagePath: $storagePath, bbs: $bbs);
$app->handle(Request::fromGlobals());
