<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // AI summaries (App\Services\AiWriter). Empty key = seeded sample drafts.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'), // low | medium | high | xhigh | max
    ],

    // Text-to-speech (App\Contracts\TextToSpeech): fake | google
    'tts' => [
        'driver' => env('TTS_DRIVER', 'fake'),
        'google_key' => env('GOOGLE_TTS_KEY'),
    ],

    // Audio on the website (Listen buttons, the docked player, the Morning 5 player on /briefing).
    // Off: none of it is shown and the "Top 5" card lists the trending stories. Reels keep their voice-over.
    'audio' => [
        'enabled' => (bool) env('AUDIO_ENABLED', false),
    ],

    // Share-as-image renderer: gd | imagick (imagick + libraqm renders Hindi conjuncts correctly)
    'image' => [
        'driver' => env('IMAGE_DRIVER', 'gd'),
    ],

    // OTP SMS (App\Contracts\SmsSender): log | msg91
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        // Development only: every OTP is this code (ignored when APP_ENV=production).
        'dev_code' => env('OTP_DEV_CODE'),
    ],

    'msg91' => [
        'auth_key' => env('MSG91_AUTH_KEY'),
        'template_id' => env('MSG91_TEMPLATE_ID'),
        'sender_id' => env('MSG91_SENDER_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
