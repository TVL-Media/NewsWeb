<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Plan §14 security headers. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $res = $next($request);
        $res->headers->set('X-Content-Type-Options', 'nosniff');
        $res->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // The website needs geolocation (onboarding "Use my current location"), so these are admin-only.
        if ($request->is('admin', 'admin/*')) {
            $res->headers->set('X-Frame-Options', 'DENY');
            $res->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            // Media (story photos, audio) may come from APP_URL's host even when the panel is opened on another one.
            $origins = collect([config('app.url'), config('filesystems.disks.'.config('filesystems.media').'.url')])
                ->filter()->map(fn ($u) => parse_url($u, PHP_URL_SCHEME).'://'.parse_url($u, PHP_URL_HOST).(parse_url($u, PHP_URL_PORT) ? ':'.parse_url($u, PHP_URL_PORT) : ''))
                ->unique()->join(' ');
            $res->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob: https: {$origins}; media-src 'self' blob: https: {$origins}; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; frame-src https://www.youtube.com https://player.vimeo.com; connect-src 'self'; font-src 'self' data:");
        }
        if ($request->isSecure()) {
            $res->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $res;
    }
}
