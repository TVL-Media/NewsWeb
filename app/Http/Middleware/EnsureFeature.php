<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** route middleware `feature:quiz`: 404 while the feature is switched off or not set up. */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Features::on($feature), 404);

        return $next($request);
    }
}
