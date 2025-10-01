<?php

use SimpleBBS\Application;
use SimpleBBS\Http\Request;
use SimpleBBS\SimpleBBS;

require_once __DIR__ . '/../vendor/autoload.php';

$storagePath = getenv('SIMPLEBBS_STORAGE_PATH') ?: __DIR__ . '/../.storage';

$bbs = SimpleBBS::create($storagePath);
$app = Application::create(storagePath: $storagePath, bbs: $bbs);
$app->handle(Request::fromGlobals());
