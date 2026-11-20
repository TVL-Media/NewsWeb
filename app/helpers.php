<?php

use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('site_name')) {
    /** Website name (Admin → Settings → General). Default "NextGenNews". */
    function site_name(): string
    {
        return (string) (config('app.name') ?: 'NextGenNews');
    }
}

if (! function_exists('panel_name')) {
    /** Admin panel name (Settings → General). Default "<site name> Newsroom". */
    function panel_name(): string
    {
        return (string) (setting('general.platform_name') ?: site_name().' Newsroom');
    }
}

if (! function_exists('site_tagline')) {
    function site_tagline(): string
    {
        return (string) (setting('site.tagline') ?: __('Short news with depth, in Hindi and English'));
    }
}

if (! function_exists('site_color')) {
    /** Brand colour as #RRGGBB. Default red #D7141E. */
    function site_color(): string
    {
        $c = (string) setting('site.brand_color', '#D7141E');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : '#D7141E';
    }
}

if (! function_exists('site_logo_url')) {
    /** Uploaded logo, or null for the built-in red "N" mark. */
    function site_logo_url(): ?string
    {
        $p = setting('site.logo');

        return $p ? asset('storage/'.$p) : null;
    }
}

if (! function_exists('site_favicon_url')) {
    function site_favicon_url(): string
    {
        $p = setting('site.favicon');

        return $p ? asset('storage/'.$p) : asset('images/logo.svg');
    }
}

if (! function_exists('feature')) {
    /** Is this reader feature switched on and set up? See App\Support\Features. */
    function feature(string $name): bool
    {
        return \App\Support\Features::on($name);
    }
}

if (! function_exists('ptz')) {
    /** Platform timezone name. */
    function ptz(): string
    {
        return Settings::timezone();
    }
}

if (! function_exists('tz_label')) {
    /** e.g. "IST (UTC+05:30)" — shown next to every schedule picker (PRD §5.5). */
    function tz_label(): string
    {
        $now = now(ptz());

        return $now->format('T').' (UTC'.$now->format('P').')';
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(?CarbonInterface $dt): string
    {
        return $dt ? $dt->copy()->setTimezone(ptz())->format(setting('datetime.date_format', 'd M Y')) : '—';
    }
}

if (! function_exists('fmt_dt')) {
    function fmt_dt(?CarbonInterface $dt): string
    {
        if (! $dt) {
            return '—';
        }
        $t = setting('datetime.time_format') === '12h' ? 'g:i A' : 'H:i';

        return $dt->copy()->setTimezone(ptz())->format(setting('datetime.date_format', 'd M Y').', '.$t);
    }
}

if (! function_exists('to_local_input')) {
    /** Value for <input type=datetime-local> in platform tz. */
    function to_local_input(?CarbonInterface $dt): string
    {
        return $dt ? $dt->copy()->setTimezone(ptz())->format('Y-m-d\TH:i') : '';
    }
}

if (! function_exists('from_local_input')) {
    /** Parse a platform-tz datetime-local string into UTC. */
    function from_local_input(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value, ptz())->utc();
    }
}

if (! function_exists('num')) {
    function num(float|int|null $n): string
    {
        $n = (float) $n;
        if ($n >= 1_000_000) {
            return round($n / 1_000_000, 1).'M';
        }
        if ($n >= 10_000) {
            return round($n / 1000, 1).'k';
        }

        return number_format($n, $n == (int) $n ? 0 : 1);
    }
}

if (! function_exists('pct_change')) {
    function pct_change(float|int $now, float|int $prev): ?float
    {
        if ($prev == 0) {
            return $now > 0 ? 100.0 : null;
        }

        return round(($now - $prev) / $prev * 100, 1);
    }
}

if (! function_exists('icon')) {
    /** Inline Lucide icon (1.5px stroke). */
    function icon(string $name, int $size = 16, string $class = ''): string
    {
        static $cache = [];
        if (! isset($cache[$name])) {
            $p = resource_path("icons/{$name}.svg");
            $svg = is_file($p) ? file_get_contents($p) : '';
            $svg = preg_replace('/<!--.*?-->/s', '', $svg);
            $svg = preg_replace_callback('/<svg\b[^>]*>/', fn ($m) => preg_replace('/\s(width|height|class)="[^"]*"/', '', $m[0]), $svg, 1);
            $svg = str_replace('stroke-width="2"', 'stroke-width="1.6"', $svg);
            $cache[$name] = trim(preg_replace('/\s+/', ' ', $svg));
        }

        return str_replace('<svg', '<svg class="ic '.$class.'" width="'.$size.'" height="'.$size.'" aria-hidden="true"', $cache[$name]);
    }
}
