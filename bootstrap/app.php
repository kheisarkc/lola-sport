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
        // Halaman pegawai/pemilik yang dibuka tanpa login diarahkan ke form masuk.
        $middleware->redirectGuestsTo('/masuk');
        // Pegawai/pemilik yang sudah login tidak perlu membuka form masuk lagi.
        $middleware->redirectUsersTo(fn ($request) => $request->user()->homeUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
