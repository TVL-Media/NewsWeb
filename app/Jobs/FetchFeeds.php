<?php

namespace App\Jobs;

use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Services\Newsroom\FeedParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Every 5 minutes: pull active feed sources and save new items (deduplicated by URL), then cluster. */
class FetchFeeds implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public function __construct(public ?int $feedSourceId = null) {}

    public function handle(FeedParser $parser): void
    {
        $sources = FeedSource::where('active', true)
            ->when($this->feedSourceId, fn ($q) => $q->whereKey($this->feedSourceId))
            ->get();

        $new = 0;
        foreach ($sources as $source) {
            try {
                $response = Http::timeout(20)->retry(2, 1000, throw: false)
                    ->withHeaders(['User-Agent' => 'NextGenNewsBot/1.0 (+'.config('app.url').')'])
                    ->get($source->url);

                if (! $response->successful()) {
                    Log::warning("Feed {$source->name} returned {$response->status()}");
                    continue;
                }

                foreach ($parser->parse($response->body()) as $item) {
                    $new += FeedItem::query()->insertOrIgnore([
                        ...$item,
                        'feed_source_id' => $source->id,
                        'processed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $source->forceFill(['last_fetched_at' => now()])->save();
            } catch (\Throwable $e) {
                Log::warning("Feed {$source->name} failed: {$e->getMessage()}");
            }
        }

        if ($new > 0) {
            ClusterItems::dispatch();
        }
    }
}
