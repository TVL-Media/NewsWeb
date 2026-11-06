<?php

namespace Database\Seeders;

use App\Jobs\PickMorning5;
use App\Models\Admin;
use App\Services\BriefingAudio;
use App\Models\AutoPublishSetting;
use App\Models\Briefing;
use App\Models\Category;
use App\Models\City;
use App\Models\Comment;
use App\Models\Page;
use App\Models\PageStory;
use App\Models\Poll;
use App\Models\Quiz;
use App\Models\ReadingEvent;
use App\Models\Story;
use App\Models\Streak;
use App\Models\User;
use Database\Seeders\Support\DemoMedia;
use Illuminate\Database\Seeder;

/** Admins, demo readers, today's Morning 5 and quiz, 3 polls, page story circles and settings. */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $tz = config('app.timezone');
        $today = now($tz)->toDateString();

        // Newsroom staff (Filament /admin). Local demo passwords: "password".
        Admin::create(['name' => 'Site Admin', 'email' => 'admin@nextgennews.test', 'password' => 'password', 'role' => 'admin']);
        Admin::create(['name' => 'Chief Editor', 'email' => 'chief@nextgennews.test', 'password' => 'password', 'role' => 'chief']);
        Admin::create(['name' => 'Desk Editor', 'email' => 'editor@nextgennews.test', 'password' => 'password', 'role' => 'editor']);

        AutoPublishSetting::create([
            'sensitive_categories' => Category::where('is_sensitive', true)->pluck('slug')->all(),
            'sensitive_keywords' => [
                'election', 'poll body', 'arrested', 'court', 'verdict', 'riot', 'communal', 'temple', 'mosque',
                'killed', 'dead', 'death toll', 'disaster', 'earthquake', 'flood', 'child', 'minor', 'alleged', 'accused', 'cure',
            ],
            'confidence_threshold' => 0.85,
            'copy_threshold' => 0.15,
        ]);

        // Demo reader (log in with phone 9876543210 once OTP login lands in Milestone 3).
        $lucknow = City::where('name_en', 'Lucknow')->first();
        $reader = User::create([
            'name' => 'Riya Sharma', 'phone' => '9876543210', 'language' => 'en', 'city_id' => $lucknow->id,
            'onboarded_at' => now()->subDays(40), 'last_active_at' => now(),
        ]);
        $reader->forceFill(['created_at' => now()->subDays(40)])->save();
        $reader->categories()->attach(Category::whereIn('slug', ['india', 'cricket', 'tech', 'business'])->pluck('id'));
        $reader->followedPages()->attach(Page::whereIn('slug', ['cricket', 'lucknow', 'tech-today', 'markets'])->pluck('id'));
        Streak::create(['user_id' => $reader->id, 'current' => 12, 'longest' => 21, 'last_day' => now($tz)->subDay()->toDateString(), 'freezes_left' => 2, 'freezes_month' => now($tz)->format('Y-m')]);

        $commenters = collect([
            ['Aman Verma', '9811100001'], ['Neha Gupta', '9811100002'], ['Rahul Yadav', '9811100003'],
        ])->map(fn ($u) => User::create([
            'name' => $u[0], 'phone' => $u[1], 'language' => 'en', 'onboarded_at' => now()->subDays(10),
        ]));

        $stories = Story::published()->orderByDesc('importance')->orderByDesc('published_at')->get();

        // Streak history to match the 12-day streak: 3 reads on each of the last 6 days
        // (the week card shows ✓ for them). Uses the least important stories so For You stays full.
        $older = $stories->sortBy('importance')->take(3);
        foreach (range(1, 6) as $daysAgo) {
            foreach ($older as $s) {
                ReadingEvent::query()->insertOrIgnore(['user_id' => $reader->id, 'story_id' => $s->id, 'read_at' => now()->subDays($daysAgo), 'read_on' => now($tz)->subDays($daysAgo)->toDateString()]);
            }
        }

        // Reading history: two stories read today → "2 of 5 read today" on the Morning 5 card.
        foreach ($stories->take(2) as $s) {
            ReadingEvent::query()->insertOrIgnore(['user_id' => $reader->id, 'story_id' => $s->id, 'read_at' => now(), 'read_on' => $today]);
        }

        // A few comments on the top story.
        $top = $stories->first();
        foreach ([
            'What a finish to the series. The spinners were brilliant all match.',
            'The vice-captain\'s knock under pressure was the difference.',
            'Hoping the same combination continues for the ODIs.',
        ] as $i => $body) {
            Comment::create(['story_id' => $top->id, 'user_id' => $commenters[$i]->id, 'body' => $body, 'likes_count' => 12 - $i * 4]);
        }
        $top->update(['comments_count' => 3]);

        // Today's Morning 5 (same ranking as the 06:00 job) with its joined EN/HI audio.
        $briefing = Briefing::create([
            'date' => $today,
            'story_ids' => PickMorning5::rank()->pluck('id')->all(),
        ]);
        app(BriefingAudio::class)->build($briefing);

        // Today's quiz: 5 questions from today's stories.
        $bySlug = Story::pluck('id', 'slug');
        $quiz = Quiz::create(['date' => $today, 'status' => 'published']);
        $questions = [
            ['india-beat-new-zealand-by-seven-wickets-to-clinch-test-series-2-0',
                'By how many wickets did India beat New Zealand to win the Test series 2-0?',
                ['Five', 'Six', 'Seven', 'Eight'], 2,
                'India chased 214 on the fifth morning in Pune with seven wickets to spare.'],
            ['lucknow-metro-east-west-corridor-tunnelling-reaches-aminabad',
                'Where did the Lucknow Metro tunnel boring machine break through this week?',
                ['Hazratganj', 'Aminabad', 'Gomti Nagar', 'Alambagh'], 1,
                'It broke through at the Aminabad station site, about six weeks ahead of schedule.'],
            ['panama-canal-restores-full-daily-transits-as-lake-water-levels-recover',
                'Why is the Panama Canal restoring its full quota of daily transits?',
                ['A new lock opened', 'Rains lifted water levels at Gatun Lake', 'Freight demand fell', 'A strike ended'], 1,
                'Strong rains lifted water levels at Gatun Lake, ending months of restrictions.'],
            ['upi-sets-new-monthly-record-as-festive-shopping-lifts-october-transactions',
                'About how many UPI transactions were made in October?',
                ['9.4 billion', '15.8 billion', '23.4 billion', '31 billion'], 2,
                'NPCI data shows a record 23.4 billion UPI transactions in October, driven by festive shopping.'],
            ['isro-places-varuna-1-ocean-monitoring-satellite-in-orbit-from-sriharikota',
                'What will ISRO\'s Varuna-1 satellite mainly track?',
                ['Crop health', 'Oceans, cyclones and coastal water', 'Air traffic', 'Forest fires'], 1,
                'Varuna-1 tracks sea surface temperature, cyclone formation and coastal water quality.'],
        ];
        foreach ($questions as $i => [$slug, $q, $options, $correct, $explanation]) {
            $quiz->questions()->create([
                'story_id' => $bySlug[$slug] ?? null, 'sort_order' => $i + 1, 'question' => $q,
                'options' => $options, 'correct_index' => $correct, 'explanation' => $explanation,
            ]);
        }

        // 3 polls: two live editorial polls (one tied to a story) and one closed.
        $this->poll('Who should open the batting in the ODI series?', $bySlug['india-beat-new-zealand-by-seven-wickets-to-clinch-test-series-2-0'] ?? null,
            'live', now()->addDays(2), ['The regular pair', 'Try the young opener', 'Rotate by venue'], [1840, 2310, 760]);
        $this->poll('Would odd-even rules change how you travel in Delhi?', $bySlug['delhi-announces-odd-even-scheme-and-staggered-office-hours-as-air-quality-worsens'] ?? null,
            'live', now()->addDay(), ['Yes, I\'ll use the Metro', 'No, I\'ll carpool', 'I work from home'], [980, 640, 410]);
        $this->poll('How do you mostly pay for small purchases?', $bySlug['upi-sets-new-monthly-record-as-festive-shopping-lifts-october-transactions'] ?? null,
            'closed', now()->subDay(), ['UPI', 'Cash', 'Card'], [5210, 1430, 690]);

        // 24-hour story circles for followed pages.
        foreach (['cricket', 'lucknow', 'tech-today', 'markets', 'world-brief', 'good-news-daily'] as $i => $slug) {
            $page = Page::where('slug', $slug)->first();
            PageStory::create([
                'page_id' => $page->id,
                'story_id' => Story::where('page_id', $page->id)->latest('published_at')->value('id'),
                'image_path' => DemoMedia::put('pagestory-'.($i + 1), 'page-stories', 720, 1280),
                'expires_at' => now()->addHours(20 - $i),
            ]);
        }
    }

    private function poll(string $question, ?int $storyId, string $status, $endsAt, array $labels, array $votes): void
    {
        $poll = Poll::create(['story_id' => $storyId, 'question' => $question, 'status' => $status, 'ends_at' => $endsAt]);
        foreach ($labels as $i => $label) {
            $poll->options()->create(['label' => $label, 'sort_order' => $i, 'votes_count' => $votes[$i]]);
        }
    }
}
