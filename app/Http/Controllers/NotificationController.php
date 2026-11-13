<?php

namespace App\Http\Controllers;

use App\Support\ReaderNotifications;
use Illuminate\Http\Request;

/** GET /notifications — the bell. Opening it marks everything as seen (a cookie, so guests work too). */
class NotificationController extends Controller
{
    public const SEEN_COOKIE = 'notif_seen';

    public function __invoke(Request $request)
    {
        $seenAt = (int) $request->cookie(self::SEEN_COOKIE) ?: null;

        return response()
            ->view('notifications', [
                'items' => ReaderNotifications::for($request->user()),
                'seenAt' => $seenAt,
            ])
            ->cookie(self::SEEN_COOKIE, (string) now()->getTimestamp(), 60 * 24 * 365);
    }
}
