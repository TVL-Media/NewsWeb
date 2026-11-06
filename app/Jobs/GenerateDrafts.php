<?php

namespace App\Jobs;

use App\Exceptions\AiWriterException;
use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Page;
use App\Models\Source;
use App\Models\Story;
use App\Models\StoryCluster;
use App\Services\AiWriter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Asks AiWriter for a bilingual draft of one cluster and saves it as a draft story, then runs the checks. */
class GenerateDrafts implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(public int $clusterId) {}

    public function handle(AiWriter $ai): void
    {
        $cluster = StoryCluster::with('items.feedSource')->find($this->clusterId);
        if (! $cluster || $cluster->story_id || $cluster->items->isEmpty()) {
            return;
        }

        $options = [
            'categories' => Category::orderBy('sort_order')->pluck('slug')->all(),
            'pages' => Page::pluck('slug')->all(),
            'cities' => City::pluck('name_en')->map(fn ($c) => Str::slug($c))->all(),
        ];

        try {
            $draft = $ai->draft($cluster->items, $options);
        } catch (AiWriterException $e) {
            Log::warning("Draft failed for cluster {$cluster->id}: {$e->getMessage()}");
            Notification::make()->title('AI draft failed')->body(Str::limit($cluster->items->first()->title, 80).' — '.$e->getMessage())
                ->danger()->sendToDatabase(Admin::all());
            $cluster->items()->update(['processed' => true]);

            return;
        }

        $story = DB::transaction(function () use ($cluster, $draft) {
            $first = $cluster->items->sortBy('published_at')->first();
            $category = Category::where('slug', $draft['category'])->first();
            $page = $draft['page'] ? Page::where('slug', $draft['page'])->first() : $category?->pages()->where('type', 'topic')->first();
            $city = $draft['city'] ? City::get()->first(fn ($c) => Str::slug($c->name_en) === $draft['city']) : null;
            $words = str_word_count(strip_tags((string) $draft['en']['full_text']));

            $story = Story::create([
                'slug' => $this->uniqueSlug($draft['en']['headline']),
                'category_id' => $category?->id,
                'page_id' => $page?->id,
                'city_id' => $city?->id,
                'source_id' => Source::firstOrCreate(['name' => $first->feedSource?->name ?? 'Wire'], ['url' => $first->feedSource?->url])->id,
                'source_url' => $first->url,
                'status' => 'draft',
                'is_breaking' => in_array('breaking', $draft['risk_flags'], true),
                'importance' => $draft['importance'],
                'read_minutes' => max(1, (int) ceil($words / 200)),
                'photo_path' => $this->photo($cluster) ?? $category?->image_path,
                'photo_credit' => $first->feedSource?->name,
            ]);

            foreach (['en', 'hi'] as $lang) {
                $story->versions()->create([
                    'language' => $lang,
                    'headline' => Str::limit(trim($draft[$lang]['headline']), 250, ''),
                    'card_text' => trim($draft[$lang]['card_text']),
                    'key_points' => $draft[$lang]['key_points'],
                    'why_it_matters' => trim($draft[$lang]['why_it_matters']),
                    'simple_text' => trim($draft[$lang]['simple_text']),
                    'full_text' => trim($draft[$lang]['full_text']),
                    'ai_assisted' => true,
                ]);
            }

            $cluster->update(['story_id' => $story->id]);
            $cluster->items()->update(['processed' => true]);

            return $story;
        });

        RunChecks::dispatch($story->id, $draft['risk_flags'], (float) $draft['confidence']);
    }

    private function uniqueSlug(string $headline): string
    {
        $base = Str::limit(Str::slug($headline), 90, '') ?: 'story';
        $slug = $base;
        for ($i = 2; Story::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** First usable image from the cluster's items, copied to the media disk. */
    private function photo(StoryCluster $cluster): ?string
    {
        foreach ($cluster->items as $item) {
            if (! $item->image_url) {
                continue;
            }
            try {
                $res = Http::timeout(10)->get($item->image_url);
                $type = (string) $res->header('Content-Type');
                if ($res->successful() && str_starts_with($type, 'image/') && strlen($res->body()) < 8_000_000) {
                    $ext = match (true) { str_contains($type, 'png') => 'png', str_contains($type, 'webp') => 'webp', default => 'jpg' };
                    $path = 'stories/'.Str::uuid().'.'.$ext;
                    Storage::disk(config('filesystems.media'))->put($path, $res->body());

                    return $path;
                }
            } catch (\Throwable) {
                // try the next item
            }
        }

        return null;
    }
}
