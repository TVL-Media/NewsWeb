<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Requires 2FA set-up for roles listed in Settings → Security (Super Admin by default).
 * The challenge itself happens at login (AuthController).
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $a = Auth::guard('admin')->user();
        if (! $a) {
            return $next($request);
        }
        $roles = (array) setting('security.enforce_2fa_roles', ['Super Admin']);
        $required = $a->roles->pluck('name')->intersect($roles)->isNotEmpty();
        if ($required && ! $a->hasTwoFactor() && ! $request->routeIs('admin.profile*', 'admin.logout') && ! app()->environment('testing')) {
            return redirect()->route('admin.profile')->with('warning', 'Two-factor authentication is required for your role. Set it up below to continue.');
        }

        return $next($request);
    }
}
