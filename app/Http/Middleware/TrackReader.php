<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For signed-in readers: refresh last_active_at (at most every 5 minutes), and send anyone
 * who hasn't finished onboarding to /onboarding when they open a normal page.
 */
class TrackReader
{
    private const ALLOWED = ['onboarding', 'onboarding.store', 'logout', 'preferences.language'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*')) {
            return $next($request); // the newsroom panel has its own guard
        }

        $user = $request->user('web');

        if ($user) {
            if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5))) {
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }

            if (! $user->onboarded_at && $request->isMethod('GET') && ! $request->expectsJson()
                && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
                return redirect()->route('onboarding');
            }
        }

        return $next($request);
    }
}
