<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/** Cached key/value platform settings (plan §10.17). */
class Settings
{
    public const DEFAULTS = [
        // general
        'general.platform_name' => 'NextGenNews Newsroom',
        'general.support_email' => null,
        // date & time (the website works in IST)
        'datetime.timezone' => 'Asia/Kolkata',
        'datetime.date_format' => 'd M Y',
        'datetime.time_format' => '12h',
        // stories
        'content.rereview_published_edits' => false,
        'content.duplicate_window_days' => 3,
        'content.max_image_mb' => 8,
        // editorial
        'editorial.guidelines' => '<p>Our own words, facts only from the sources. Every number, name and date must appear in a source. Credit every photo.</p>',
        'editorial.moderation_guidelines' => '<p>Hide comments that are abusive, hateful or spam. Take down misinformation. Restrict repeat offenders before suspending.</p>',
        'editorial.rejection_reasons' => ['Not supported by the sources', 'Duplicate', 'Sensitive: needs more reporting', 'Copied text', 'Poor quality', 'Other'],
        'editorial.review_sla_hours' => 2,
        // readers
        'streaks.reads_per_day' => 3,
        'streaks.freezes_per_month' => 2,
        // security
        'security.enforce_2fa_roles' => ['Super Admin'],
        'security.session_timeout_minutes' => 60,
        'security.password_min_length' => 10,
        'security.ip_allowlist' => [],
        // retention
        'retention.login_logs_months' => 12,
        // translation (EN ↔ HI)
        'translation.provider' => 'auto',
        'translation.model' => '',
        'translation.api_key' => null,
        'translation.style_prompt' => 'Translate as neutral Indian news copy. Keep names, numbers and direct quotes accurate. Hindi: simple, everyday Hindi in Devanagari.',
        'translation.monthly_char_limit' => 2000000,
        // polls
        'polls.low_participation_ratio' => 0.3,
    ];

    public const ENCRYPTED = ['translation.api_key'];

    public static function all(): array
    {
        return Cache::rememberForever('platform_settings', function () {
            $vals = self::DEFAULTS;
            if (! Schema::hasTable('settings')) {
                return $vals;
            }
            foreach (Setting::all() as $s) {
                $v = $s->value;
                if ($s->is_encrypted && is_string($v) && $v !== '') {
                    try {
                        $v = Crypt::decryptString($v);
                    } catch (\Throwable) {
                        $v = null;
                    }
                }
                $vals[$s->key] = $v;
            }

            return $vals;
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, ?int $adminId = null): void
    {
        $enc = in_array($key, self::ENCRYPTED, true);
        $store = $enc && $value !== null && $value !== '' ? Crypt::encryptString((string) $value) : $value;
        Setting::updateOrCreate(['key' => $key], ['group' => explode('.', $key)[0], 'value' => $store, 'is_encrypted' => $enc, 'updated_by' => $adminId]);
        Cache::forget('platform_settings');
    }

    public static function timezone(): string
    {
        return (string) self::get('datetime.timezone', config('app.platform_timezone'));
    }

    public static function masked(string $key): string
    {
        $v = (string) self::get($key, '');

        return $v === '' ? '' : str_repeat('•', 8).mb_substr($v, -4);
    }
}
