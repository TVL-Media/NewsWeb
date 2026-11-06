<?php

namespace App\Services\Newsroom;

use App\Models\FeedItem;
use App\Models\StoryCluster;
use Illuminate\Support\Collection;

/**
 * Groups feed items about the same event: title similarity (Jaccard over meaningful words)
 * plus a time window. One cluster becomes one story.
 */
class Clusterer
{
    public const THRESHOLD = 0.45;
    public const WINDOW_HOURS = 12;

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'from', 'that', 'this', 'into', 'over', 'after', 'about', 'amid', 'says', 'said',
        'will', 'has', 'have', 'are', 'was', 'were', 'its', 'his', 'her', 'their', 'new', 'how', 'why', 'what', 'who',
        'to', 'of', 'in', 'on', 'at', 'by', 'as', 'is', 'an', 'a', 'be', 'up', 'out', 'more', 'than',
    ];

    /** Assign every unclustered item to a cluster. Returns the clusters that were created. */
    public function run(): Collection
    {
        $created = collect();

        $candidates = FeedItem::whereNotNull('cluster_id')
            ->where('published_at', '>=', now()->subHours(48))
            ->get(['id', 'title', 'published_at', 'cluster_id']);

        FeedItem::whereNull('cluster_id')->orderBy('published_at')->each(function (FeedItem $item) use ($candidates, $created) {
            $match = $candidates
                ->filter(fn ($c) => abs($c->published_at->diffInHours($item->published_at)) <= self::WINDOW_HOURS)
                ->map(fn ($c) => ['item' => $c, 'score' => self::similarity($item->title, $c->title)])
                ->filter(fn ($m) => $m['score'] >= self::THRESHOLD)
                ->sortByDesc('score')
                ->first();

            if ($match) {
                $item->cluster_id = $match['item']->cluster_id;
            } else {
                $cluster = StoryCluster::create();
                $created->push($cluster);
                $item->cluster_id = $cluster->id;
            }

            $item->save();
            $candidates->push($item);
        });

        return $created;
    }

    public static function similarity(string $a, string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if (! $ta || ! $tb) {
            return 0.0;
        }

        return count(array_intersect($ta, $tb)) / count(array_unique([...$ta, ...$tb]));
    }

    /** Lowercase meaningful words (Latin ≥ 3 letters or any Devanagari word, digits kept). */
    public static function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{M}\p{N}]+/u', mb_strtolower($text), $m);

        return array_values(array_unique(array_filter($m[0], fn ($w) => ! in_array($w, self::STOPWORDS, true)
            && (mb_strlen($w) >= 3 || is_numeric($w) || preg_match('/\p{Devanagari}/u', $w)))));
    }
}
