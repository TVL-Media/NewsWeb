<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Sensitive actions (BR-12): the request must carry `confirm_password`,
 * or the admin must have confirmed it in the last 10 minutes.
 */
class RequirePasswordConfirm
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            return $next($request);
        }
        $admin = $request->user('admin');
        $recent = time() - (int) $request->session()->get('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 600);
        if ($request->filled('confirm_password')) {
            if (! Hash::check($request->input('confirm_password'), $admin->password)) {
                return back()->withInput($request->except('confirm_password'))->withErrors(['confirm_password' => 'That password is not correct.'])->with('error', 'Password confirmation failed.');
            }
            $request->session()->put('auth.password_confirmed_at', time());

            return $next($request);
        }
        if ($recent) {
            return $next($request);
        }

        return back()->withInput()->withErrors(['confirm_password' => 'Please confirm your password to continue.'])->with('error', 'This action needs your password.');
    }
}
