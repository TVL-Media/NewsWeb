<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** REELS_ENABLED=false: every reel page and endpoint is a 404. */
class EnsureReelsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('reels.enabled'), 404);

        return $next($request);
    }
}
