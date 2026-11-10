<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Idle timeout from Settings → Security (default 60 min). */
class SessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        $limit = (int) setting('security.session_timeout_minutes', 60) * 60;
        $last = (int) $request->session()->get('last_activity_at', time());
        if ($limit > 0 && time() - $last > $limit) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();

            return redirect()->route('admin.login')->withErrors(['email' => 'You were signed out after a period of inactivity.']);
        }
        $request->session()->put('last_activity_at', time());

        return $next($request);
    }
}
