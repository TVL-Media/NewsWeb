<?php

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Briefing;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Poll;
use App\Models\Quiz;
use App\Models\Report;
use App\Models\Story;
use App\Models\TranslationJob;
use App\Models\User;
use App\Services\Newsroom\StoryWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/*
| The admin panel (/admin) running on the website's own tables.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['queue.default' => 'sync', 'services.anthropic.key' => null]);
    $this->admin = Admin::where('role', 'admin')->first();
    $this->admin->update(['password' => 'Secret!2026x']);
});

function storyPayload(array $over = []): array
{
    return array_replace_recursive([
        'en' => [
            'headline' => 'Lucknow opens a new cycle track along the Gomti',
            'card_text' => 'A 6 km cycle track along the Gomti riverfront opened on Monday, with lights and water points every kilometre.',
            'key_points' => ['6 km long', 'Lights along the route', 'Water points every kilometre'],
            'why_it_matters' => 'Safer rides for commuters.', 'simple_text' => '', 'full_text' => "Para one.\n\nPara two.",
        ],
        'category_id' => Category::first()->id,
        'importance' => 6, 'read_minutes' => 2,
    ], $over);
}

describe('screens', function () {
    it('opens every admin screen for a Super Admin', function () {
        $story = Story::first();
        $story->update(['status' => 'pending_approval']);
        $poll = Poll::first() ?? Poll::create(['question' => 'Is this a test poll here?', 'status' => 'live']);
        $user = User::first();
        $report = Report::create(['reporter_id' => $user->id, 'target_type' => 'story', 'target_id' => $story->id, 'reason' => 'misinformation']);
        $briefing = Briefing::first();
        $quiz = Quiz::first();
        $urls = ['/admin', '/admin?range=30d', '/admin/stories', '/admin/stories?status=published&sort=likes', '/admin/stories/new', "/admin/stories/{$story->id}/edit",
            "/admin/stories/{$story->id}/preview", "/admin/stories/{$story->id}/history", '/admin/review', '/admin/review?tab=ai', "/admin/review/{$story->id}",
            '/admin/schedule', '/admin/schedule?view=list', '/admin/schedule/events', '/admin/newsroom/feeds', '/admin/newsroom/auto-publish', '/admin/newsroom/log',
            '/admin/daily/briefings', '/admin/daily/briefings/new', '/admin/daily/quizzes', '/admin/daily/quizzes/new',
            '/admin/media', '/admin/categories', '/admin/tags', '/admin/pages', '/admin/pages/new', '/admin/pages/'.Page::first()->id,
            '/admin/polls', '/admin/polls?tab=all', '/admin/polls/create', "/admin/polls/{$poll->id}", "/admin/polls/{$poll->id}/edit", "/admin/polls/{$poll->id}/export",
            '/admin/translations', '/admin/translation-settings', '/admin/glossary', '/admin/readers', '/admin/readers?engagement=high', "/admin/readers/{$user->id}",
            '/admin/comments', '/admin/comments?tab=all', '/admin/reports', '/admin/reports?status=all', '/admin/reports/log', "/admin/reports/{$report->id}",
            '/admin/streaks', '/admin/analytics', '/admin/analytics/newsroom', '/admin/analytics/readers', '/admin/analytics/habits', '/admin/analytics/moderation',
            '/admin/analytics/content?compare=previous', '/admin/team', '/admin/roles', '/admin/settings', '/admin/settings/security', '/admin/audit', '/admin/profile',
            '/admin/notifications', '/admin/search?q=lu', '/admin/stories-lookup?q=a'];
        if ($briefing) {
            $urls[] = "/admin/daily/briefings/{$briefing->id}";
        }
        if ($quiz) {
            $urls[] = "/admin/daily/quizzes/{$quiz->id}";
        }
        $this->actingAs($this->admin, 'admin');
        foreach ($urls as $url) {
            $code = $this->get($url)->baseResponse->getStatusCode();
            expect($code)->toBe(200, "{$url} returned {$code}");
        }
    });

    it('limits screens by role', function () {
        $writer = Admin::create(['name' => 'Writer', 'email' => 'writer@test', 'password' => 'Secret!2026x'])->assignRole('Writer');
        $this->actingAs($writer, 'admin');
        $this->get('/admin/stories/new')->assertOk();
        $this->get('/admin/review')->assertForbidden();
        $this->get('/admin/readers')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    });
});

describe('stories', function () {
    it('lets a writer write and submit; another editor approves', function () {
        $writer = Admin::create(['name' => 'Writer', 'email' => 'writer@test', 'password' => 'Secret!2026x'])->assignRole('Writer');
        $this->actingAs($writer, 'admin')->post('/admin/stories', storyPayload() + ['action' => 'submit'])->assertRedirect();
        $story = Story::latest('id')->first();
        expect($story->status)->toBe('pending_approval')->and($story->author_id)->toBe($writer->id)
            ->and($story->versions()->count())->toBe(1);

        $editor = Admin::where('role', 'editor')->first();
        $this->flushSession();
        app('auth')->forgetGuards();
        $this->actingAs($editor, 'admin')->post("/admin/review/{$story->id}/decide", ['decision' => 'approve'])->assertRedirect();
        $story->refresh();
        expect($story->status)->toBe('published')->and($story->published_at)->not->toBeNull()
            ->and($story->snapshots()->count())->toBeGreaterThan(0);
        app('auth')->shouldUse('web'); // back on the website as a guest
        $this->get(route('story.show', $story))->assertOk()->assertSee('Lucknow opens a new cycle track');
    });

    it('blocks approving your own story and over-long card text', function () {
        $editor = Admin::where('role', 'editor')->first();
        $this->actingAs($editor, 'admin')->post('/admin/stories', storyPayload() + ['action' => 'submit']);
        $story = Story::latest('id')->first();
        $this->post("/admin/review/{$story->id}/decide", ['decision' => 'approve'])->assertForbidden();

        $long = implode(' ', array_fill(0, 61, 'word'));
        $this->post('/admin/stories', storyPayload(['en' => ['card_text' => $long]]))->assertSessionHasErrors('en.card_text');
    });

    it('schedules a story and publishes it when due', function () {
        $this->actingAs($this->admin, 'admin')->post('/admin/stories', storyPayload() + ['action' => 'schedule', 'scheduled_at' => now(ptz())->addHour()->format('Y-m-d\TH:i')]);
        $story = Story::latest('id')->first();
        expect($story->status)->toBe('scheduled');

        $this->travel(2)->hours();
        expect(app(StoryWorkflow::class)->publishDue())->toBe(1);
        expect($story->fresh()->status)->toBe('published');
    });

    it('unpublishes, restores a version, and deletes only with DELETE + password', function () {
        $this->actingAs($this->admin, 'admin')->post('/admin/stories', storyPayload() + ['action' => 'publish']);
        $story = Story::latest('id')->first();
        expect($story->status)->toBe('published');

        $this->put("/admin/stories/{$story->id}", storyPayload(['en' => ['headline' => 'A changed headline for the cycle track']]))->assertRedirect();
        $snap = $story->snapshots()->reorder()->orderBy('id')->first();
        $this->post("/admin/stories/{$story->id}/versions/{$snap->id}/restore")->assertRedirect();
        expect($story->fresh()->version('en')->headline)->toBe('Lucknow opens a new cycle track along the Gomti');

        $this->post("/admin/stories/{$story->id}/unpublish")->assertRedirect();
        expect($story->fresh()->status)->toBe('removed');
        app('auth')->shouldUse('web');
        $this->get(route('story.show', $story))->assertNotFound();
        app('auth')->shouldUse('admin');

        $this->delete("/admin/stories/{$story->id}", ['confirm_word' => 'DELETE'])->assertSessionHasErrors('confirm_password');
        $this->delete("/admin/stories/{$story->id}", ['confirm_word' => 'DELETE', 'confirm_password' => 'Secret!2026x'])->assertRedirect('/admin/stories');
        expect(Story::find($story->id))->toBeNull();
    });
});

describe('translation', function () {
    it('only shows the Hindi version after an editor approves it', function () {
        $this->actingAs($this->admin, 'admin')->post('/admin/stories', storyPayload() + ['action' => 'publish']);
        $story = Story::latest('id')->first();
        $this->post("/admin/stories/{$story->id}/translate", ['language' => 'hi'])->assertRedirect();

        $job = TranslationJob::latest('id')->first();
        expect($job->status)->toBe('ai_completed')->and($story->versions()->where('language', 'hi')->exists())->toBeFalse();

        $this->post("/admin/translations/{$job->id}/approve")->assertRedirect();
        expect($story->versions()->where('language', 'hi')->value('headline'))->toStartWith('[HI]');
    });
});

describe('readers and moderation', function () {
    it('restricting a reader stops comments; suspending signs them out', function () {
        $reader = User::first();
        $story = Story::published()->first();
        $this->actingAs($this->admin, 'admin')->post("/admin/readers/{$reader->id}/status", ['to' => 'restricted', 'reason' => 'abuse', 'days' => 7])->assertRedirect();

        $this->actingAs($reader->fresh(), 'web')->postJson("/stories/{$story->id}/comments", ['body' => 'A perfectly polite comment'])->assertForbidden();
        $this->get('/')->assertOk();

        $reader->update(['status' => 'suspended']);
        $this->actingAs($reader->fresh(), 'web')->get('/account')->assertRedirect('/');
        $this->assertGuest('web');
    });

    it('taking down a reported comment hides it and closes the reports', function () {
        $reader = User::first();
        $story = Story::published()->first();
        $comment = Comment::create(['story_id' => $story->id, 'user_id' => $reader->id, 'body' => 'Rude comment', 'status' => 'visible']);
        $story->increment('comments_count');
        $report = Report::create(['reporter_id' => User::skip(1)->first()->id, 'target_type' => 'comment', 'target_id' => $comment->id, 'reason' => 'abusive']);

        $this->actingAs($this->admin, 'admin')->post("/admin/reports/{$report->id}/act", ['action' => 'take_down', 'note' => 'Abusive'])->assertRedirect();
        expect($comment->fresh()->status)->toBe('hidden')->and($report->fresh()->status)->toBe('actioned');
    });

    it('hiding a category removes it from the website menus', function () {
        $cat = Category::first();
        $this->actingAs($this->admin, 'admin')->post("/admin/categories/{$cat->id}/toggle")->assertRedirect();
        expect($cat->fresh()->is_active)->toBeFalse();
        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
        $this->get('/explore')->assertOk()->assertDontSee('/?category='.$cat->slug, false);
    });

    it('changes the streak rule the website uses', function () {
        $this->actingAs($this->admin, 'admin')->put('/admin/streaks', ['reads_per_day' => 5, 'freezes_per_month' => 1, 'confirm_password' => 'Secret!2026x'])->assertRedirect();
        expect(\App\Services\StreakService::readsPerDay())->toBe(5)->and(\App\Services\StreakService::freezesPerMonth())->toBe(1);
    });

    it('notifies reviewers when a story needs approval', function () {
        $writer = Admin::create(['name' => 'Writer', 'email' => 'writer@test', 'password' => 'Secret!2026x'])->assignRole('Writer');
        $this->actingAs($writer, 'admin')->post('/admin/stories', storyPayload() + ['action' => 'submit']);
        expect(AdminNotification::where('admin_id', Admin::where('role', 'editor')->value('id'))->where('type', 'review')->exists())->toBeTrue();
    });
});

describe('admin → website', function () {
    it('shows a breaking flag the editor sets', function () {
        $story = Story::published()->latest('published_at')->first();
        $story->update(['is_breaking' => true, 'published_at' => now()->subHour()]);
        $this->get(route('story.show', $story))->assertOk()->assertSee('Breaking');
    });

    it('stops a suspended reader from signing in again', function () {
        config(['services.sms.dev_code' => '123456']);
        $reader = User::first();
        $reader->update(['status' => 'suspended']);
        $this->postJson('/auth/otp/verify', ['phone' => $reader->phone, 'code' => '123456'])->assertUnprocessable();
        $this->assertGuest('web');
    });

    it('uses the streak rule from the panel in the website copy', function () {
        \App\Support\Settings::set('streaks.reads_per_day', 4);
        $this->get('/briefing')->assertOk()->assertSee('read 4 stories', false);
    });

    it('tells moderators about a new report and a reader poll', function () {
        $reader = User::first();
        $story = Story::published()->first();
        $this->actingAs($reader, 'web')->postJson('/reports', ['target_type' => 'story', 'target_id' => $story->id, 'reason' => 'spam'])->assertSuccessful();
        expect(AdminNotification::where('type', 'report')->exists())->toBeTrue();

        Poll::create(['question' => 'Should the city add more buses at night?', 'status' => 'pending', 'created_by' => $reader->id, 'ends_at' => now()->addDay()]);
        expect(AdminNotification::where('type', 'poll')->exists())->toBeTrue();
    });
});

describe('sign in', function () {
    it('signs staff in with email and password and records it', function () {
        $this->post('/admin/login', ['email' => 'chief@nextgennews.test', 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticated('admin');
        expect(\App\Models\AdminLoginLog::where('email', 'chief@nextgennews.test')->exists())->toBeTrue();
    });
});
