<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version stamp for cached pages (home, topic, story). Bumping it makes every cached page key
 * stale at once, without needing cache tags (the database store doesn't support them).
 * Page caching itself is wired up in Milestone 6; publishing already calls flush().
 */
class PageCache
{
    private const KEY = 'pagecache.version';

    public static function version(): int
    {
        return (int) Cache::get(self::KEY, 1);
    }

    public static function key(string $name): string
    {
        return 'page:v'.self::version().':'.$name;
    }

    public static function flush(): void
    {
        Cache::forever(self::KEY, self::version() + 1);
        Cache::forget('nav.categories');
    }
}
