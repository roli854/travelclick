<?php

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

require_once __DIR__.'/../../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__, 2)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Set Testing Environment
|--------------------------------------------------------------------------
*/

$app->detectEnvironment(function () {
    return 'testing';
});

/*
|--------------------------------------------------------------------------
| Bootstrap The Application
|--------------------------------------------------------------------------
*/

$app->make(Kernel::class)->bootstrap();

return $app;