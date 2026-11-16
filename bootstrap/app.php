<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * [NOVIDADE LARAVEL 11 — bootstrap/app.php]
 * No Laravel 11, o antigo `app/Http/Kernel.php` e `app/Providers/RouteServiceProvider.php`
 * foram substituídos por esta configuração central fluente no `bootstrap/app.php`!
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
