<?php

use App\Http\Request;

require_once __DIR__ . '/../app/bootstrap.php';

$request = Request::fromGlobals();

handle($request);
