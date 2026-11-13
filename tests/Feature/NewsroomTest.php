<?php

use App\Exceptions\AiWriterException;
use App\Jobs\DraftQuiz;
use App\Jobs\FetchFeeds;
use App\Models\Admin;
use App\Models\AiCheck;
use App\Models\AutoPublishSetting;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\Poll;
use App\Models\Quiz;
use App\Models\Story;
use App\Models\StoryCluster;
use App\Models\User;
use App\Notifications\StoryNeedsApproval;
use App\Services\AiWriter;
use App\Services\Newsroom\Clusterer;
use App\Services\Newsroom\FeedParser;
use App\Services\Newsroom\StoryChecks;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['queue.default' => 'sync', 'services.anthropic.key' => null]);
    Sleep::fake();

    $this->feed = FeedSource::create(['name' => 'Test Wire', 'url' => 'https://wire.example.com/feed.xml', 'type' => 'agency', 'trusted' => true, 'active' => true]);
    Http::fake([
        'wire.example.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/feed.xml')), 200, ['Content-Type' => 'application/rss+xml']),
        'img.example.com/*' => Http::response(base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='), 200, ['Content-Type' => 'image/jpeg']),
    ]);
    $this->admin = Admin::where('role', 'admin')->first();
});

/** A draft that passes every check against the Lucknow Metro fixture (facts from the source, own words). */
function cleanMetroDraft(array $overrides = []): array
{
    $en = [
        'headline' => 'Lucknow Metro opens Charbagh to Aminabad underground stretch',
        'card_text' => 'Passengers in Lucknow can now ride underground between Charbagh and Aminabad. The Lucknow Metro Rail Corporation opened the 4.2 km stretch on Thursday, with services every 6 minutes from early morning. Officials expect roughly 45,000 riders a day to use it, easing road traffic around the old city markets and the railway station area.',
        'key_points' => ['The underground stretch links Charbagh and Aminabad.', 'Services run every 6 minutes.', 'About 45,000 daily riders are expected.'],
        'why_it_matters' => 'It gives old-city commuters a faster ride that skips crowded roads.',
        'simple_text' => 'Lucknow has a new underground train line between two busy places. Trains come often, so many people can travel quickly without traffic.',
        'full_text' => "Riders can now travel underground between Charbagh and Aminabad after the Lucknow Metro Rail Corporation opened the stretch on Thursday.\n\nTrains arrive every 6 minutes, officials said, and roughly 45,000 commuters should use the line each day.",
    ];

    return array_replace_recursive([
        'en' => $en,
        'hi' => [
            'headline' => 'लखनऊ मेट्रो का चारबाग-अमीनाबाद भूमिगत खंड शुरू',
            'card_text' => 'लखनऊ में अब चारबाग से अमीनाबाद तक भूमिगत मेट्रो चलेगी। 4.2 किलोमीटर का खंड गुरुवार को खुला और हर 6 मिनट पर ट्रेन मिलेगी।',
            'key_points' => ['चारबाग से अमीनाबाद तक भूमिगत खंड।', 'हर 6 मिनट पर ट्रेन।', 'रोज़ करीब 45,000 यात्री।'],
            'why_it_matters' => 'पुराने शहर में सफ़र तेज़ होगा।',
            'simple_text' => 'लखनऊ में नई भूमिगत ट्रेन चली है।',
            'full_text' => 'लखनऊ मेट्रो ने गुरुवार को नया खंड खोला।',
        ],
        'category' => 'india', 'page' => 'lucknow', 'city' => 'lucknow',
        'importance' => 6, 'risk_flags' => [], 'confidence' => 0.95,
    ], $overrides);
}

describe('feeds and clustering', function () {
    it('parses RSS, Atom and JSON Feed', function () {
        $parser = new FeedParser;
        $rss = $parser->parse(file_get_contents(base_path('tests/Fixtures/feed.xml')));
        expect($rss)->toHaveCount(3)
            ->and($rss[0]['image_url'])->toBe('https://img.example.com/metro.jpg')
            ->and($rss[0]['body'])->toContain('4.2 km');

        $atom = $parser->parse('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Atom story</title><link rel="alternate" href="https://x.example.com/a"/><updated>2026-11-05T06:00:00Z</updated><summary>Body</summary></entry></feed>');
        expect($atom[0]['url'])->toBe('https://x.example.com/a');

        $json = $parser->parse(json_encode(['items' => [['url' => 'https://x.example.com/j', 'title' => 'JSON story', 'content_text' => 'Text']]]));
        expect($json[0]['title'])->toBe('JSON story');
    });

    it('fetches new items once (deduplicated by URL) and clusters the same event together', function () {
        Bus::fake([\App\Jobs\ClusterItems::class]);
        FetchFeeds::dispatchSync();
        FetchFeeds::dispatchSync();
        expect(FeedItem::count())->toBe(3)->and($this->feed->fresh()->last_fetched_at)->not->toBeNull();

        app(Clusterer::class)->run();
        $metro = FeedItem::where('title', 'like', '%Metro%')->pluck('cluster_id')->unique();
        expect($metro)->toHaveCount(1)
            ->and(FeedItem::where('title', 'like', '%Kerala%')->value('cluster_id'))->not->toBe($metro->first());
    });
});

describe('pipeline routing', function () {
    it('runs fetch → cluster → draft → checks → approval queue with seeded sample drafts (no API key)', function () {
        Notification::fake();

        $this->artisan('newsroom:run')->assertSuccessful();

        $new = Story::whereHas('cluster')->with('aiCheck')->get();
        expect($new)->toHaveCount(2);
        $new->each(function (Story $s) {
            expect($s->aiCheck)->not->toBeNull()
                ->and($s->versions()->count())->toBe(2)
                ->and($s->status)->toBe('pending_approval') // sample text doesn't match the real source
                ->and($s->aiCheck->decision)->toBe('approval')
                ->and($s->aiCheck->reasons)->not->toBeEmpty();
        });
        Notification::assertSentTo(Admin::all(), StoryNeedsApproval::class);
    });

    it('auto-publishes a clean, low-risk, confident draft from a trusted feed and makes its audio', function () {
        $this->mock(AiWriter::class, fn ($m) => $m->shouldReceive('draft')->andReturn(cleanMetroDraft()));

        $this->artisan('newsroom:run')->assertSuccessful();

        $story = Story::whereHas('cluster.items', fn ($q) => $q->where('title', 'like', '%Metro%'))->with('aiCheck')->first();
        expect($story->status)->toBe('published')
            ->and($story->publish_mode)->toBe('auto')
            ->and($story->aiCheck->decision)->toBe('auto')
            ->and($story->aiCheck->fact_mismatches)->toBe([])
            ->and((float) $story->aiCheck->copy_score)->toBeLessThan(0.15)
            ->and($story->photo_path)->toStartWith('stories/')
            ->and($story->version('en')->audio_path)->not->toBeNull();

        $this->get(route('story.show', $story))->assertOk();
    });

    it('sends sensitive, copied, low-confidence or untrusted drafts to editors', function (array $draft, ?callable $setup, string $reason) {
        Notification::fake();
        if ($setup) {
            $setup($this);
        }
        $this->mock(AiWriter::class, fn ($m) => $m->shouldReceive('draft')->andReturn(cleanMetroDraft($draft)));

        $this->artisan('newsroom:run')->assertSuccessful();

        $story = Story::whereHas('cluster.items', fn ($q) => $q->where('title', 'like', '%Metro%'))->with('aiCheck')->first();
        expect($story->status)->toBe('pending_approval')
            ->and(implode(' | ', $story->aiCheck->reasons))->toContain($reason);
    })->with([
        'AI risk flag' => [['risk_flags' => ['deaths']], null, 'AI flag: deaths'],
        'sensitive keyword' => [['en' => ['card_text' => 'Police arrested two men after a scuffle at the new Lucknow Metro stretch between Charbagh and Aminabad, which opened on Thursday with trains every 6 minutes and about 45,000 riders a day expected.']], null, 'keyword: arrested'],
        'low confidence' => [['confidence' => 0.4], null, 'Confidence'],
        'copied sentences' => [['en' => ['full_text' => 'The Lucknow Metro Rail Corporation opened a 4.2 km underground section between Charbagh and Aminabad on Thursday. Trains will run every 6 minutes from 6 am. Officials said about 45,000 people are expected to use the section daily.']], null, 'Copy score'],
        'wrong number' => [['en' => ['key_points' => ['Trains run every 8 minutes.', 'About 45,000 riders.', 'It links Charbagh and Aminabad.']]], null, 'fact(s) not found'],
        'untrusted feed' => [[], fn ($t) => $t->feed->update(['trusted' => false]), 'not marked trusted'],
        'sensitive category' => [[], fn ($t) => AutoPublishSetting::current()->update(['sensitive_categories' => ['india']]), 'category: India'],
    ]);

    it('notes refused drafts for editors instead of creating a story', function () {
        $this->mock(AiWriter::class, fn ($m) => $m->shouldReceive('draft')->andThrow(new AiWriterException('Claude declined this request (category: cyber).')));
        $before = Story::count();

        $this->artisan('newsroom:run')->assertSuccessful();

        expect(Story::count())->toBe($before)
            ->and(FeedItem::where('processed', false)->count())->toBe(0)
            ->and(\App\Models\AdminNotification::where('admin_id', $this->admin->id)->where('type', 'ai_failed')->count())->toBeGreaterThan(0);
    });
});

describe('checks', function () {
    it('finds names, numbers and dates that are not in the sources', function () {
        $checks = new StoryChecks;
        $missing = $checks->factMismatches(
            'Trains run every 8 minutes from Charbagh. Mayor Sushma Rao opened it on Friday with 45,000 riders expected.',
            'Trains will run every 6 minutes from Charbagh. 45,000 people are expected. It opened on Thursday.',
        );
        $values = array_column($missing, 'value');

        expect($values)->toContain('8')->toContain('Friday')->toContain('Mayor Sushma Rao')
            ->not->toContain('45,000');
    });

    it('scores copied text by shared 5-word sequences', function () {
        $checks = new StoryChecks;
        $source = 'The corporation opened a new underground section between the two busy stations on Thursday morning.';

        expect($checks->copyScore($source, $source))->toBe(1.0)
            ->and($checks->copyScore('Commuters can now travel below ground linking both stations, officials confirmed.', $source))->toBe(0.0);
    });

    it('scores title similarity for clustering', function () {
        expect(Clusterer::similarity('Lucknow Metro opens new Charbagh to Aminabad underground section', 'Charbagh–Aminabad underground section of Lucknow Metro opens'))->toBeGreaterThan(0.45)
            ->and(Clusterer::similarity('Lucknow Metro opens new section', 'Kerala backwaters see record tourist arrivals'))->toBeLessThan(0.2);
    });
});

describe('AiWriter (Claude API)', function () {
    beforeEach(fn () => config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-opus-5-5']));

    $okResponse = fn (array $draft) => Http::response([
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => json_encode($draft)]],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ]);

    it('sends a strict JSON-schema request with fallbacks and parses the draft', function () use ($okResponse) {
        Http::fake(['api.anthropic.com/*' => $okResponse(cleanMetroDraft())]);
        $items = collect([new FeedItem(['title' => 'Metro opens', 'body' => 'Body', 'url' => 'https://x.example.com/1'])]);

        $draft = app(AiWriter::class)->draft($items, ['categories' => ['india', 'world'], 'pages' => ['lucknow'], 'cities' => ['lucknow']]);

        expect($draft['en']['key_points'])->toHaveCount(3)->and($draft['category'])->toBe('india');
        Http::assertSent(function (Request $r) {
            $body = $r->data();

            return $r->url() === 'https://api.anthropic.com/v1/messages'
                && $r->hasHeader('x-api-key', 'test-key')
                && $r->hasHeader('anthropic-version', '2026-10-21')
                && $r->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
                && $body['model'] === 'claude-opus-5-5'
                && $body['fallbacks'] === 'default'
                && $body['output_config']['format']['type'] === 'json_schema'
                && $body['output_config']['format']['schema']['additionalProperties'] === false
                && $body['output_config']['effort'] === 'medium'
                && ! isset($body['thinking'], $body['tool_choice'], $body['temperature']);
        });
    });

    it('retries server errors and rate limits', function () use ($okResponse) {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Overloaded']], 529)
            ->push(['error' => ['message' => 'Slow down']], 429)
            ->whenEmpty($okResponse(cleanMetroDraft()))]);

        $draft = app(AiWriter::class)->draft(collect([new FeedItem(['title' => 'x', 'url' => 'https://x.example.com/2'])]), ['categories' => ['india'], 'pages' => [], 'cities' => []]);

        expect($draft['importance'])->toBe(6);
        Http::assertSentCount(3);
    });

    it('turns refusals, truncation and bad requests into AiWriterException', function (array $response, int $status) {
        Http::fake(['api.anthropic.com/*' => Http::response($response, $status)]);

        app(AiWriter::class)->draft(collect([new FeedItem(['title' => 'x', 'url' => 'https://x.example.com/3'])]), ['categories' => ['india'], 'pages' => [], 'cities' => []]);
    })->with([
        'refusal' => [['stop_reason' => 'refusal', 'stop_details' => ['type' => 'refusal', 'category' => 'cyber'], 'content' => []], 200],
        'max_tokens' => [['stop_reason' => 'max_tokens', 'content' => [['type' => 'text', 'text' => '{"en":']]], 200],
        'bad request' => [['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'bad']], 400],
    ])->throws(AiWriterException::class);
});

describe('daily quiz drafting', function () {
    it("drafts tomorrow's quiz and approves it when every source story is low-risk", function () {
        AiCheck::query()->update(['risk_level' => 'low']);
        $tomorrow = now(config('app.timezone'))->addDay()->toDateString();

        DraftQuiz::dispatchSync();

        $quiz = Quiz::whereDate('date', $tomorrow)->with('questions')->first();
        expect($quiz->status)->toBe('approved')->and($quiz->questions)->toHaveCount(5);
        $quiz->questions->each(fn ($q) => expect($q->options)->toHaveCount(4)->and($q->correct_index)->toBeBetween(0, 3));
    });

    it('keeps it as a draft for editors when a source story is sensitive', function () {
        AiCheck::query()->update(['risk_level' => 'sensitive']);

        DraftQuiz::dispatchSync();

        expect(Quiz::whereDate('date', now(config('app.timezone'))->addDay()->toDateString())->value('status'))->toBe('draft');
    });
});

describe('admin panel', function () {
    it('lets newsroom staff open every newsroom screen', function (string $url) {
        $this->actingAs($this->admin, 'admin')->get($url)->assertOk();
    })->with([
        '/admin', '/admin/review', '/admin/stories', '/admin/stories/new', '/admin/newsroom/log',
        '/admin/newsroom/feeds', '/admin/newsroom/auto-publish', '/admin/daily/briefings', '/admin/daily/quizzes',
        '/admin/comments', '/admin/polls', '/admin/reports', '/admin/pages', '/admin/categories',
    ]);

    it('opens the story editor and the review screen', function () {
        $story = Story::first();
        $story->update(['status' => 'pending_approval']);

        $this->actingAs($this->admin, 'admin')->get("/admin/stories/{$story->id}/edit")->assertOk()->assertSee($story->version('en')->headline);
        $this->get("/admin/review/{$story->id}")->assertOk()->assertSee('AI checks')->assertSee('Sources');
    });

    it('limits feeds and settings to chiefs and admins, pages and categories to admins', function () {
        $editor = Admin::where('role', 'editor')->first();
        $chief = Admin::where('role', 'chief')->first();
        $as = function (Admin $who) {
            $this->flushSession();
            app('auth')->forgetGuards();

            return $this->actingAs($who, 'admin');
        };

        $as($editor)->get('/admin/review')->assertOk();
        $as($editor)->get('/admin/newsroom/feeds')->assertForbidden();
        $as($editor)->get('/admin/newsroom/auto-publish')->assertForbidden();
        $as($chief)->get('/admin/newsroom/feeds')->assertOk();
        $as($chief)->get('/admin/pages')->assertForbidden();
    });

    it('keeps readers out of /admin', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::first())->get('/admin')->assertRedirect('/admin/login');
    });

    it('approves and rejects from the review screen', function () {
        $this->actingAs($this->admin, 'admin');
        [$a, $b] = Story::take(2)->get();
        $a->update(['status' => 'pending_approval']);
        $b->update(['status' => 'pending_approval']);

        $this->post("/admin/review/{$a->id}/decide", ['decision' => 'approve'])->assertRedirect();
        expect($a->fresh()->status)->toBe('published')->and($a->approvals()->value('action'))->toBe('approved');

        $this->post("/admin/review/{$b->id}/decide", ['decision' => 'reject', 'reason' => 'Unverified claim', 'comment' => 'No second source'])->assertRedirect();
        expect($b->fresh()->status)->toBe('rejected')->and($b->approvals()->value('reason'))->toContain('Unverified claim');
    });

    it('approves a reader poll', function () {
        $this->actingAs($this->admin, 'admin');
        $poll = Poll::create(['question' => 'Should winter school hours change?', 'status' => 'pending', 'created_by' => User::first()->id, 'ends_at' => now()->addDays(3)]);

        $this->post("/admin/polls/{$poll->id}/approve")->assertRedirect();

        expect($poll->fresh()->status)->toBe('live')->and($poll->fresh()->ends_at->isFuture())->toBeTrue();
    });
});
