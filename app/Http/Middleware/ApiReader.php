<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1: the reader is whoever the Sanctum token belongs to (or a guest), and the admin
 * panel's restrictions apply here too: suspended → 403 on everything; restricted → reading and
 * view counts only (same rule as EnforceReaderStatus on the website).
 */
class ApiReader
{
    private const ALWAYS_ALLOWED = ['api.v1.reels.view'];

    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');
        $user = $request->user();
        $status = $user?->effectiveStatus();

        if ($status === 'suspended') {
            return response()->json(['message' => __('Your account is suspended. Contact the grievance officer if you think this is a mistake.'), 'account_status' => 'suspended'], 403);
        }

        if ($status === 'restricted' && ! $request->isMethodSafe() && ! in_array($request->route()?->getName(), self::ALWAYS_ALLOWED, true)) {
            return response()->json(['message' => __('You can keep reading, but likes, comments, votes and polls are paused.'), 'account_status' => 'restricted'], 403);
        }

        return $next($request);
    }
}
