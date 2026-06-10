<?php

// tests/bootstrap/app.php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// S'assurer que le dossier cache existe
$cachePath = __DIR__ . '/cache';
if (! is_dir($cachePath)) {
    mkdir($cachePath, 0755, true);
}

// S'assurer que le dossier storage existe aussi
$storagePath = __DIR__ . '/../storage/framework/cache';
if (! is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
}

$app = Application::configure(basePath: __DIR__ . '/../')
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

// Configurer les chemins de storage et cache pour éviter les problèmes d'écriture
$app->useStoragePath(__DIR__ . '/../storage');
$app->useBootstrapPath(__DIR__);

return $app;
