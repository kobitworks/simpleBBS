<?php

use SimpleBBS\Application;
use SimpleBBS\Http\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$storagePath = getenv('SIMPLEBBS_STORAGE_PATH') ?: __DIR__ . '/../.storage';

$app = Application::create($storagePath);
$app->handle(Request::fromGlobals());
