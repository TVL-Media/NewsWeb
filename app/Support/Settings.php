<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Cached key/value platform settings (plan §10.17), edited in Admin → Settings.
 *
 * Keys in MAP also feed Laravel config: at boot, applyToConfig() copies every saved value over
 * the matching config key, so code that reads config('services.anthropic.key') etc. keeps working.
 * A key nobody has saved yet keeps its .env / config value, so an existing install behaves exactly
 * as before until an admin changes something.
 */
class Settings
{
    public const DEFAULTS = [
        // website brand (site.name, site.locale and site.timezone come from config, see MAP)
        'site.tagline' => null,          // null = "Short news with depth, in Hindi and English" (translated)
        'site.logo' => null,             // file on the public disk; null = the red "N" mark
        'site.favicon' => null,
        'site.brand_color' => '#D7141E',
        'site.contact_email' => 'hello@nextgennews.in',
        // admin panel
        'general.platform_name' => null, // null = "<site name> Newsroom"
        'general.support_email' => null,
        // date & time (the website works in the site timezone)
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
        // legal pages (empty = the built-in text)
        'legal.grievance_name' => 'Grievance Officer (to be appointed)',
        'legal.grievance_email' => 'grievance@nextgennews.in',
        'legal.grievance_phone' => '+91 522 400 0000 (Mon–Fri, 10:00–18:00 IST)',
        'legal.grievance_address' => '3rd Floor, Placeholder House, Hazratganj, Lucknow, Uttar Pradesh 226001, India',
        'legal.about_html' => null,
        'legal.privacy_html' => null,
        'legal.terms_html' => null,
    ];

    /** Setting key → config key. Their default is whatever config holds before settings are applied. */
    public const MAP = [
        'site.name' => 'app.name',
        'site.locale' => 'app.locale',
        'site.timezone' => 'app.timezone',
        'features.ai_autopublish' => 'features.ai_autopublish',
        'features.listen' => 'features.listen',
        'features.morning5' => 'features.morning5',
        'features.quiz' => 'features.quiz',
        'features.polls' => 'features.polls',
        'features.comments' => 'features.comments',
        'features.reels' => 'features.reels',
        'features.otp_login' => 'features.otp_login',
        'ai.api_key' => 'services.anthropic.key',
        'ai.model' => 'services.anthropic.model',
        'tts.provider' => 'services.tts.driver',
        'tts.google_key' => 'services.tts.google_key',
        'tts.azure_key' => 'services.tts.azure_key',
        'tts.azure_region' => 'services.tts.azure_region',
        'sms.provider' => 'services.sms.driver',
        'sms.api_key' => 'services.msg91.auth_key',
        'sms.sender_id' => 'services.msg91.sender_id',
        'sms.template_id' => 'services.msg91.template_id',
        'storage.driver' => 'filesystems.media',
        'storage.aws_key' => 'filesystems.disks.s3.key',
        'storage.aws_secret' => 'filesystems.disks.s3.secret',
        'storage.aws_bucket' => 'filesystems.disks.s3.bucket',
        'storage.aws_region' => 'filesystems.disks.s3.region',
        'storage.cdn_url' => 'filesystems.disks.s3.url',
        'analytics.ga4_id' => 'app.ga4_id',
        'analytics.firebase_config' => 'services.firebase.web_config',
        'analytics.sentry_dsn' => 'sentry.dsn',
    ];

    /** Stored encrypted (Crypt) and shown masked in the admin panel. */
    public const ENCRYPTED = [
        'translation.api_key', 'ai.api_key', 'tts.google_key', 'tts.azure_key', 'sms.api_key',
        'storage.aws_key', 'storage.aws_secret', 'analytics.sentry_dsn',
    ];

    /** The development OTP every reader can use outside production (sms.dev_otp switch). */
    public const DEV_OTP = '123456';

    /** Config values captured before settings were applied (the .env defaults). */
    private static array $base = [];

    /** Values saved in the database (decrypted). Empty when the database is not reachable yet. */
    public static function stored(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Cache::rememberForever('platform_settings', function () {
                $vals = [];
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
        } catch (\Throwable) {
            return []; // no database yet (fresh install) or it is down: run on defaults
        }
    }

    public static function all(): array
    {
        $base = [];
        foreach (self::MAP as $key => $path) {
            $base[$key] = array_key_exists($key, self::$base) ? self::$base[$key] : config($path);
        }
        $base['sms.dev_otp'] = self::$base['sms.dev_otp'] ?? (config('services.sms.dev_code') === self::DEV_OTP);

        return array_merge(self::DEFAULTS, $base, array_filter(self::stored(), fn ($v) => $v !== null));
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

        // Take effect in this request too (only the changed key, so nothing else is reset).
        if (isset(self::MAP[$key])) {
            config([self::MAP[$key] => ($value === null || $value === '') ? (self::$base[$key] ?? config(self::MAP[$key])) : $value]);
        } elseif ($key === 'sms.dev_otp') {
            config(['services.sms.dev_code' => $value ? self::DEV_OTP : null]);
        }
        if (str_starts_with($key, 'features.') || str_starts_with($key, 'tts.')) {
            Features::applyDerived();
        }
    }

    /** Copy saved settings over Laravel config (boot, and after every save). */
    public static function applyToConfig(): void
    {
        foreach (self::MAP as $key => $path) {
            if (! array_key_exists($key, self::$base)) {
                self::$base[$key] = config($path);
            }
        }
        if (! array_key_exists('sms.dev_otp', self::$base)) {
            self::$base['sms.dev_otp'] = config('services.sms.dev_code') === self::DEV_OTP;
        }

        $stored = self::stored();
        foreach (self::MAP as $key => $path) {
            $value = $stored[$key] ?? null;
            config([$path => ($value === null || $value === '') ? self::$base[$key] : $value]);
        }

        if (array_key_exists('sms.dev_otp', $stored)) {
            config(['services.sms.dev_code' => $stored['sms.dev_otp'] ? self::DEV_OTP : null]);
        }
        if (filled(config('app.timezone'))) {
            date_default_timezone_set(config('app.timezone'));
        }

        Features::applyDerived();
    }

    /** Forget the captured .env defaults (tests that change config at runtime). */
    public static function resetBase(): void
    {
        self::$base = [];
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
