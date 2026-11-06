<?php

use App\Http\Middleware\SetLanguage;
use App\Http\Middleware\TrackReader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLanguage::class, TrackReader::class]);
        // The language cookie is a plain preference, so it is not encrypted.
        $middleware->encryptCookies(except: ['lang']);
        // Guests who hit an auth-only page go home, where the login sheet opens.
        $middleware->redirectGuestsTo(fn () => route('home', ['login' => 1]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
