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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: fn () => \Illuminate\Support\Facades\Route::middleware('web')->group(base_path('routes/install.php')),
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Not installed yet: everything goes to /install (runs before sessions, so a bare server works).
        $middleware->web(prepend: [\App\Http\Middleware\RedirectToInstaller::class]);
        $middleware->api(prepend: [\App\Http\Middleware\RedirectToInstaller::class]);
        $middleware->web(append: [SetLanguage::class, TrackReader::class, \App\Http\Middleware\EnforceReaderStatus::class]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'admin.active' => \App\Http\Middleware\EnsureAdminActive::class,
            'admin.2fa' => \App\Http\Middleware\EnsureTwoFactor::class,
            'admin.timeout' => \App\Http\Middleware\SessionTimeout::class,
            'password.confirmed.inline' => \App\Http\Middleware\RequirePasswordConfirm::class,
            'feature' => \App\Http\Middleware\EnsureFeature::class,
            'installer.open' => \App\Http\Middleware\InstallerOpen::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        // The language cookie is a plain preference, so it is not encrypted.
        $middleware->encryptCookies(except: ['lang']);
        // Guests who hit an auth-only page go home, where the login sheet opens.
        // Admin pages send guests to the staff login; the website opens its login sheet.
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('admin', 'admin/*') ? route('admin.login') : route('home', ['login' => 1]));
        $middleware->redirectUsersTo(fn (Request $r) => $r->is('admin', 'admin/*') ? route('admin.dashboard') : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
