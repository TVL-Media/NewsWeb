<?php

return [

    // Off = no reel jobs are queued and (Milestone 3) every Reels link and page is hidden.
    'enabled' => (bool) env('REELS_ENABLED', false),

    // Full path to the ffmpeg binary. ffprobe is expected next to it unless FFPROBE_PATH is set.
    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH'),

    // Hard cap for any reel (auto or uploaded). Auto reels also aim for the 20–45 s window.
    'max_seconds' => (int) env('REEL_MAX_SECONDS', 60),
    'auto_min_seconds' => 20,
    'auto_max_seconds' => 45,

    // Largest video an editor can upload in the admin panel (PHP's upload_max_filesize / post_max_size must allow it).
    'upload_max_mb' => (int) env('REEL_UPLOAD_MAX_MB', 200),

    // Licensed background music folder (not used yet — reels have no music until it is provided).
    'music_dir' => env('REEL_MUSIC_DIR'),

    // Output renditions: H.264 + AAC MP4 with +faststart.
    'renditions' => [
        '720' => ['width' => 720, 'height' => 1280, 'video_kbps' => 1500, 'audio_kbps' => 128],
        '480' => ['width' => 480, 'height' => 854, 'video_kbps' => 700, 'audio_kbps' => 96],
    ],

    'fps' => 30,

    // Seconds a single ffmpeg run may take before the job gives up.
    'timeout' => (int) env('REEL_FFMPEG_TIMEOUT', 600),
];
