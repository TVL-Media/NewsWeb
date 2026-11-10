<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the restrictions set in the admin panel (Users screen) to signed-in readers.
 *  - suspended: signed out on their next request;
 *  - restricted: can read, but can't like, react, comment, vote, report, share or create polls.
 */
class EnforceReaderStatus
{
    /** Routes a restricted reader may still post to. */
    private const ALWAYS_ALLOWED = [
        'logout', 'account.update', 'account.destroy', 'onboarding.store', 'preferences.language',
        'stories.read', 'quiz.answer',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();
        if (! $user) {
            return $next($request);
        }

        $status = $user->effectiveStatus();

        if ($status === 'suspended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = __('Your account is suspended. Contact the grievance officer if you think this is a mistake.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->route('home')->with('status', $message);
        }

        if ($status === 'restricted' && ! $request->isMethodSafe()
            && ! in_array($request->route()?->getName(), self::ALWAYS_ALLOWED, true)) {
            $until = $user->restricted_until ? ' '.__('until :date', ['date' => $user->restricted_until->timezone(config('app.timezone'))->format('j M, g:i A')]) : '';
            $message = __('Your account is restricted').$until.'. '.__('You can keep reading, but likes, comments, votes and polls are paused.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : back()->with('status', $message);
        }

        return $next($request);
    }
}
