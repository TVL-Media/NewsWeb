<?php

namespace App\Http\Middleware;

use App\Support\Install\Installer;
use Closure;
use Illuminate\Http\Request;

/** Until the app is installed, every page goes to /install (the API answers 503). */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next)
    {
        if (Installer::isInstalled() || $request->is('install', 'install/*')) {
            return $next($request);
        }
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'This site is not installed yet. Open /install in a browser.'], 503);
        }

        return redirect('/install');
    }
}
