<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** A deactivated admin's session ends on the next request (plan §7.3). */
class EnsureAdminActive
{
    public function handle(Request $request, Closure $next)
    {
        $a = Auth::guard('admin')->user();
        if ($a && $a->status !== 'active') {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();

            return redirect()->route('admin.login')->withErrors(['email' => 'Your account is inactive. Contact a Super Admin.']);
        }

        return $next($request);
    }
}
