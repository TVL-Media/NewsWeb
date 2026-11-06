<?php

namespace App\Jobs;

use App\Models\Briefing;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * 06:00 IST: choose today's Morning 5 — the top stories of the last 24 hours by importance and
 * reads — then build the joined audio. Editor override: if today's briefing already exists
 * (set by an editor in /admin), its stories are kept and only the audio is rebuilt.
 */
class PickMorning5 implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $date = null) {}

    public function handle(): void
    {
        $date = $this->date ?? now(config('app.timezone'))->toDateString();

        $briefing = Briefing::whereDate('date', $date)->first();

        if (! $briefing) {
            $ids = self::rank()->pluck('id')->all();
            if (count($ids) < 5) {
                return; // not enough news yet
            }
            $briefing = Briefing::create(['date' => $date, 'story_ids' => $ids]);
        }

        BuildBriefingAudio::dispatch($briefing->id);
    }

    /** Score = importance × 10 + reads in the last 24 h + likes ÷ 50. Sensitive-pending stories never appear (published only). */
    public static function rank(int $limit = 5)
    {
        $reads = DB::table('reading_events')
            ->selectRaw('story_id, COUNT(*) as read_count')
            ->where('read_at', '>=', now()->subDay())
            ->groupBy('story_id');

        return Story::published()
            ->where('published_at', '>=', now()->subDay())
            ->leftJoinSub($reads, 'r', 'r.story_id', '=', 'stories.id')
            ->select('stories.*')
            ->orderByRaw('(stories.importance * 10 + COALESCE(r.read_count, 0) + stories.likes_count / 50.0) DESC')
            ->orderByDesc('stories.published_at')
            ->limit($limit)
            ->get();
    }
}
