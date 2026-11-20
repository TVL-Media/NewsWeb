<?php

namespace App\Support;

/**
 * Feature switches (Admin → Settings → Features).
 *
 * enabled(): the admin switch is on.
 * on():      the switch is on AND everything it needs is set up (keys, providers).
 *            Readers only ever see features that are on(); the rest hide themselves.
 *
 * Outside production the stand-ins count as "set up" (sample AI drafts, silent test audio,
 * OTPs written to the log), so a developer laptop works without any keys.
 */
class Features
{
    public const LABELS = [
        'ai_autopublish' => 'AI auto-publishing',
        'listen' => 'Listen (text-to-speech)',
        'morning5' => 'Morning 5',
        'quiz' => 'Daily quiz',
        'polls' => 'Polls',
        'comments' => 'Comments',
        'reels' => 'Reels',
        'otp_login' => 'Phone OTP login',
    ];

    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", true);
    }

    public static function on(string $feature): bool
    {
        return match ($feature) {
            // Derived once at boot (applyDerived) and read from config, like the rest of the code does.
            'listen' => (bool) config('services.audio.enabled'),
            'reels' => (bool) config('reels.enabled'),
            'morning5' => self::enabled('morning5') && self::on('listen'),
            'ai_autopublish' => self::enabled('ai_autopublish') && self::aiReady(),
            'otp_login' => self::enabled('otp_login') && self::smsReady(),
            default => self::enabled($feature),
        };
    }

    /** Why a switched-on feature is still hidden, for the admin Settings page. Null when it works. */
    public static function missing(string $feature): ?string
    {
        if (! self::enabled($feature)) {
            return null;
        }

        return $feature === 'morning5' && ! self::on('listen')
            ? 'Morning 5 is an audio briefing, so it needs Listen to be working.'
            : self::blocker($feature);
    }

    /** What stops a feature from working even when its switch is on (ignores the switch itself; Morning 5 → Listen is handled by the caller). */
    public static function blocker(string $feature): ?string
    {
        return match ($feature) {
            'ai_autopublish' => self::aiReady() ? null : 'Add a Claude API key under AI. Until then editors write and publish stories themselves.',
            'listen' => self::ttsReady() ? null : 'Add a text-to-speech key under Text-to-speech.',
            'otp_login' => self::smsReady() ? null : 'Add your SMS provider key under SMS. Until then readers cannot log in.',
            default => null,
        };
    }

    public static function aiReady(): bool
    {
        return filled(config('services.anthropic.key')) || ! app()->isProduction();
    }

    public static function ttsReady(): bool
    {
        return match (config('services.tts.driver')) {
            'google' => filled(config('services.tts.google_key')),
            'azure' => filled(config('services.tts.azure_key')) && filled(config('services.tts.azure_region')),
            default => ! app()->isProduction(), // "fake": silent test audio, development only
        };
    }

    public static function smsReady(): bool
    {
        if (config('services.sms.driver') === 'msg91') {
            return filled(config('services.msg91.auth_key')) && filled(config('services.msg91.template_id'));
        }

        return ! app()->isProduction(); // "log": OTPs go to the log file, development only
    }

    /** Turn the switches + keys into the flags the existing code reads. Called at boot. */
    public static function applyDerived(): void
    {
        config([
            'services.audio.enabled' => self::enabled('listen') && self::ttsReady(),
            'reels.enabled' => self::enabled('reels'),
        ]);
    }
}
