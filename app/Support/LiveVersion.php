<?php

namespace App\Support;

use App\Models\Poll;
use App\Models\Reel;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

/**
 * A short fingerprint of what's live on the site. Pages poll GET /live every 10 s and reload when it
 * changes (new/edited/unpublished story, new reel, poll opened or closed).
 */
class LiveVersion
{
    public static function current(): string
    {
        return Cache::remember('live.version', 5, fn () => substr(md5(implode('|', [
            Story::published()->count(),
            Story::published()->max('updated_at'),
            Reel::where('status', 'published')->count(),
            Reel::where('status', 'published')->max('updated_at'),
            Poll::whereIn('status', ['live', 'closed'])->count(),
            Poll::max('updated_at'),
        ])), 0, 12));
    }
}
