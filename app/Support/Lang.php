<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;

/**
 * Reader language (en|hi). Signed-in users: users.language. Guests: the `lang` cookie.
 * The SetLanguage middleware resolves it once per request and sets the app locale.
 */
class Lang
{
    public const SUPPORTED = ['en', 'hi'];

    public static function current(): string
    {
        $locale = App::getLocale();

        return in_array($locale, self::SUPPORTED, true) ? $locale : 'en';
    }

    public static function other(?string $lang = null): string
    {
        return ($lang ?? self::current()) === 'hi' ? 'en' : 'hi';
    }

    /** "25 min ago" / "25 मिनट पहले" */
    public static function ago(?CarbonInterface $time, ?string $lang = null): string
    {
        if (! $time) {
            return '';
        }

        $hi = ($lang ?? self::current()) === 'hi';
        $minutes = max(1, (int) $time->diffInMinutes(now()));

        return match (true) {
            $minutes < 60 => $hi ? "{$minutes} मिनट पहले" : "{$minutes} min ago",
            $minutes < 1440 => $hi ? intdiv($minutes, 60).' घंटे पहले' : intdiv($minutes, 60).' hr ago',
            $minutes < 2880 => $hi ? 'कल' : 'Yesterday',
            default => $hi ? intdiv($minutes, 1440).' दिन पहले' : intdiv($minutes, 1440).' days ago',
        };
    }

    /** Greeting by IST hour: Good Morning (5–11), Good Afternoon (12–16), Good Evening. */
    public static function greeting(): string
    {
        $hour = (int) now(config('app.timezone'))->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => __('Good Morning'),
            $hour >= 12 && $hour < 17 => __('Good Afternoon'),
            default => __('Good Evening'),
        };
    }
}
