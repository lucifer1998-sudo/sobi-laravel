<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // The web group matters too: login, logout, register and the email
        // verification routes sit outside /api and still return copy people read.
        $middleware->api(prepend: [\App\Http\Middleware\SetLocale::class]);
        $middleware->web(prepend: [\App\Http\Middleware\SetLocale::class]);
        // $middleware->validateCsrfTokens(except: [
        //     '/login',
        //     '/register',
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
