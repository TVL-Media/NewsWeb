<?php

namespace Database\Seeders;

use App\Jobs\GenerateReel;
use App\Models\Reel;
use App\Models\Story;
use App\Services\Reels\Ffmpeg;
use Illuminate\Database\Seeder;

/**
 * Sample reels rendered with FFmpeg from 10 low-risk seeded stories (one per page where possible),
 * in English and Hindi. Runs synchronously (~6 s per reel). Skipped when REELS_ENABLED=false or
 * FFmpeg cannot be found.
 */
class ReelSeeder extends Seeder
{
    public const COUNT = 10;

    public function run(Ffmpeg $ffmpeg): void
    {
        if (! config('reels.enabled')) {
            $this->command?->warn('  REELS_ENABLED=false — skipping sample reels.');

            return;
        }
        if (! $ffmpeg->available()) {
            $this->command?->warn('  FFmpeg not found at "'.$ffmpeg->binary().'" — skipping sample reels. Set FFMPEG_PATH in .env, then: php artisan db:seed --class=ReelSeeder');

            return;
        }

        $stories = Story::published()
            ->whereNotNull('photo_path')
            ->whereHas('aiCheck', fn ($q) => $q->where('risk_level', 'low'))
            ->orderByDesc('importance')
            ->get()
            ->unique('page_id')
            ->take(self::COUNT);

        if ($stories->count() < self::COUNT) {
            $stories = $stories->concat(
                Story::published()->whereNotNull('photo_path')->whereNotIn('id', $stories->pluck('id'))
                    ->whereHas('aiCheck', fn ($q) => $q->where('risk_level', 'low'))
                    ->orderByDesc('importance')->take(self::COUNT - $stories->count())->get()
            );
        }

        // Both languages, so the reel player's हिं/EN switch has something to switch to.
        foreach ($stories->values() as $i => $story) {
            foreach (['en', 'hi'] as $lang) {
                $started = microtime(true);
                GenerateReel::dispatchSync($story->id, $lang);

                $reel = Reel::where(['story_id' => $story->id, 'language' => $lang])->first();
                $this->command?->line(sprintf('  Reel %d/%d %s  %-10s %4.1fs  %s',
                    $i + 1, $stories->count(), $lang, $reel?->status ?? 'missing', microtime(true) - $started, $reel?->title));
            }
        }
    }
}
