<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 🌟 AGREGAMOS ESTA LÍNEA PARA PERMITIR EL CHECKOUT PÚBLICO
        $middleware->validateCsrfTokens(except: [
            'checkout/process',
            '*/checkout/process', // El asterisco cubre cualquier subdominio (minimarketenma, etc)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
