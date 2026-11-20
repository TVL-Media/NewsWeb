<?php

/*
| Feature switches. These are the starting values only: once an admin saves
| Admin → Settings → Features, the saved values win (App\Support\Settings::applyToConfig).
|
| A switch that is on can still be unavailable when its key is missing, for example
| Listen without a text-to-speech key, or Phone OTP login without an SMS key on a live
| server. App\Support\Features::on() checks both; the feature then hides itself.
*/

return [
    'ai_autopublish' => (bool) env('FEATURE_AI_AUTOPUBLISH', true),
    'listen' => (bool) env('AUDIO_ENABLED', false),
    'morning5' => (bool) env('FEATURE_MORNING5', true),
    'quiz' => (bool) env('FEATURE_QUIZ', true),
    'polls' => (bool) env('FEATURE_POLLS', true),
    'comments' => (bool) env('FEATURE_COMMENTS', true),
    'reels' => (bool) env('REELS_ENABLED', false),
    'otp_login' => (bool) env('FEATURE_OTP_LOGIN', true),
];
