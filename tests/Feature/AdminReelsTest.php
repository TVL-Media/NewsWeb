<?php

use App\Jobs\GenerateReel;
use App\Jobs\ProcessUploadedReel;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Reel;
use App\Models\Report;
use App\Models\Story;
use App\Notifications\ReelNeedsAttention;
use App\Support\ReelFeed;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
| Reels in the admin panel (/admin/reels), editor alerts and the moderation screens.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['reels.enabled' => true, 'queue.default' => 'sync']);
    $this->admin = Admin::where('role', 'admin')->first();
    $this->editor = Admin::where('role', 'editor')->first();
});

function staff(string $role): Admin
{
    return Admin::create(['name' => $role, 'email' => strtolower(str_replace(' ', '', $role)).'@test', 'password' => 'Secret!2026x'])->assignRole($role);
}

it('opens every reels screen and shows Reels in the sidebar with the waiting count', function () {
    $live = makeReel(Story::published()->first());
    makeReel(Story::published()->skip(1)->first(), 'en', ['status' => 'pending_approval', 'published_at' => null]);
    makeReel(Story::published()->skip(2)->first(), 'en', ['status' => 'failed', 'error_message' => "FFmpeg failed: Unknown encoder 'libx264'", 'published_at' => null]);

    $this->actingAs($this->editor, 'admin');
    foreach (['/admin/reels', '/admin/reels?tab=all', '/admin/reels?tab=all&status=failed', '/admin/reels?language=hi&source=upload&q=India', '/admin/reels/upload', "/admin/reels/{$live->id}"] as $url) {
        $code = $this->get($url)->baseResponse->getStatusCode();
        expect($code)->toBe(200, "{$url} returned {$code}");
    }

    $this->get('/admin/reels?tab=all&status=failed')->assertSee("Unknown encoder 'libx264'");
    $this->get('/admin')->assertSee(route('admin.reels.index'), false)->assertSee('<span>Reels</span><span class="count">2</span>', false);
});

it('shows a preview player with both renditions', function () {
    $reel = makeReel(Story::published()->first());

    $this->actingAs($this->editor, 'admin')->get("/admin/reels/{$reel->id}")
        ->assertOk()
        ->assertSee('<video', false)
        ->assertSee($reel->videoUrl('720'), false)
        ->assertSee($reel->videoUrl('480'), false);
});

it('keeps reels away from roles without the permission', function () {
    $reel = makeReel(Story::published()->first(), 'en', ['status' => 'pending_approval', 'published_at' => null]);

    $this->actingAs(staff('Writer'), 'admin')->get('/admin/reels')->assertForbidden();

    $moderator = staff('Community Moderator');
    $this->actingAs($moderator, 'admin')->get('/admin/reels')->assertOk();
    $this->actingAs($moderator, 'admin')->post("/admin/reels/{$reel->id}/approve")->assertForbidden();
});

it('approves a waiting reel: it goes live and the decision is audit-logged', function () {
    $reel = makeReel(Story::published()->first(), 'en', ['status' => 'pending_approval', 'published_at' => null]);

    $this->actingAs($this->editor, 'admin')->post("/admin/reels/{$reel->id}/approve")->assertRedirect()->assertSessionHas('ok');

    $reel->refresh();
    expect($reel->status)->toBe('published')
        ->and($reel->published_at)->not->toBeNull()
        ->and($reel->reviewed_by)->toBe($this->editor->id)
        ->and(AuditLog::where(['action' => 'reel.approved', 'subject_id' => $reel->id])->exists())->toBeTrue();

    // (actingAs(…, 'admin') makes the admin guard the default in tests, so check the public feed query directly.)
    expect(ReelFeed::query(null)->pluck('id'))->toContain($reel->id);
});

it('rejects a reel only with a reason', function () {
    $reel = makeReel(Story::published()->first(), 'en', ['status' => 'pending_approval', 'published_at' => null]);
    $this->actingAs($this->editor, 'admin');

    $this->post("/admin/reels/{$reel->id}/reject")->assertSessionHasErrors('reason');
    $this->post("/admin/reels/{$reel->id}/reject", ['reason' => 'Footage is from a different event'])->assertRedirect();

    expect($reel->fresh())->status->toBe('rejected')->rejection_reason->toBe('Footage is from a different event');
});

it('unpublishes a live reel, and a re-generate brings it back to the queue, not live', function () {
    fakeFfmpeg();
    $story = lowRiskStory();
    GenerateReel::dispatchSync($story->id, 'en');
    $reel = Reel::firstOrFail();
    expect($reel->status)->toBe('published');

    $this->actingAs($this->editor, 'admin')->post("/admin/reels/{$reel->id}/unpublish", ['reason' => 'Wrong photo'])->assertRedirect();
    expect($reel->fresh())->status->toBe('rejected')->rejection_reason->toBe('Unpublished: Wrong photo');
    expect(ReelFeed::query(null)->pluck('id'))->not->toContain($reel->id)
        ->and($reel->fresh()->isVisible())->toBeFalse();

    $this->actingAs($this->editor, 'admin')->post("/admin/reels/{$reel->id}/regenerate")->assertRedirect();
    expect($reel->fresh()->status)->toBe('pending_approval');

    $this->post("/admin/reels/{$reel->id}/approve")->assertRedirect();
    expect($reel->fresh())->status->toBe('published')->rejection_reason->toBeNull();
});

it('re-generates auto reels from the story and uploads from the original file', function () {
    Queue::fake();
    $story = Story::published()->first();
    $auto = makeReel($story);
    Storage::disk('public')->put('reels/uploads/a.mp4', 'raw');
    $upload = makeReel(Story::published()->skip(1)->first(), 'en', ['story_id' => null, 'slug' => 'upload-a', 'source_type' => 'upload', 'source_path' => 'reels/uploads/a.mp4']);
    $this->actingAs($this->editor, 'admin');

    $this->post("/admin/reels/{$auto->id}/regenerate")->assertRedirect();
    Queue::assertPushed(GenerateReel::class, fn ($job) => $job->storyId === $story->id && $job->language === 'en' && $job->force);

    $this->post("/admin/reels/{$upload->id}/regenerate")->assertRedirect();
    Queue::assertPushed(ProcessUploadedReel::class, fn ($job) => $job->reelId === $upload->id);

    expect($auto->fresh()->status)->toBe('processing');
    $this->post("/admin/reels/{$auto->id}/regenerate")->assertStatus(422); // already processing

    $story->update(['status' => 'removed']);
    $auto->update(['status' => 'failed']);
    $this->post("/admin/reels/{$auto->id}/regenerate")->assertStatus(422); // story not live
});

it('uploads a video into the processing queue', function () {
    Queue::fake();
    $story = Story::published()->first();

    $this->actingAs($this->editor, 'admin')->post('/admin/reels', [
        'video' => UploadedFile::fake()->create('agency-clip.mp4', 900, 'video/mp4'),
        'title' => 'Flood relief teams reach villages near Patna',
        'language' => 'en',
        'story_id' => $story->id,
    ])->assertRedirect();

    $reel = Reel::where('source_type', 'upload')->firstOrFail();
    expect($reel)->status->toBe('processing')
        ->created_by->toBe($this->editor->id)
        ->story_id->toBe($story->id)
        ->page_id->toBe($story->page_id)
        ->category_id->toBe($story->category_id);
    Storage::disk('public')->assertExists($reel->source_path);
    Queue::assertPushed(ProcessUploadedReel::class, fn ($job) => $job->reelId === $reel->id);
    expect(AuditLog::where('action', 'reel.uploaded')->exists())->toBeTrue();

    $this->post('/admin/reels', ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'title' => 'A title that is long enough', 'language' => 'en'])
        ->assertSessionHasErrors('video');
});

it('alerts editors (bell + email) when a reel needs approval or fails, like stories', function () {
    Notification::fake();
    fakeFfmpeg();

    GenerateReel::dispatchSync(sensitiveStory()->id, 'en');
    $reel = Reel::firstOrFail();

    expect($reel->status)->toBe('pending_approval')
        ->and(AdminNotification::where(['admin_id' => $this->editor->id, 'url' => route('admin.reels.show', $reel)])->exists())->toBeTrue();
    Notification::assertSentTo($this->editor, ReelNeedsAttention::class, fn ($n) => str_starts_with($n->title, 'Reel needs approval'));
    Notification::assertNotSentTo(staff('Writer'), ReelNeedsAttention::class);

    fakeFfmpeg(error: 'Invalid data found when processing input');
    GenerateReel::dispatchSync(lowRiskStory()->id, 'en');
    Notification::assertSentTo($this->editor, ReelNeedsAttention::class, fn ($n) => $n->failed && str_contains($n->why, 'Invalid data'));
});

it('does not alert anyone when a low-risk reel auto-publishes', function () {
    Notification::fake();
    fakeFfmpeg();

    GenerateReel::dispatchSync(lowRiskStory()->id, 'en');

    Notification::assertNothingSent();
    expect(AdminNotification::count())->toBe(0);
});

it('hides reels whose story was unpublished', function () {
    $story = Story::published()->first();
    $reel = makeReel($story);

    $story->update(['status' => 'removed']);

    $this->get('/reels/'.$reel->slug)->assertNotFound();
    expect($this->getJson('/reels/feed')->json('reels'))->toBeEmpty();
});

describe('moderation', function () {
    it('keeps the reel comment count right when a comment is hidden and restored', function () {
        $reel = makeReel(Story::published()->first(), 'en', ['comments_count' => 1]);
        $story = $reel->story;
        $before = $story->comments_count;
        $comment = Comment::create(['reel_id' => $reel->id, 'user_id' => reader()->id, 'body' => 'Spam spam spam']);
        $this->actingAs($this->editor, 'admin');

        $this->get('/admin/comments?tab=all')->assertOk()->assertSee(route('admin.reels.show', $reel), false);

        $this->post("/admin/comments/{$comment->id}/hide")->assertRedirect();
        expect($reel->fresh()->comments_count)->toBe(0)->and($story->fresh()->comments_count)->toBe($before);

        $this->post("/admin/comments/{$comment->id}/restore")->assertRedirect();
        expect($reel->fresh()->comments_count)->toBe(1);
    });

    it('shows and takes down a reported reel', function () {
        $reel = makeReel(Story::published()->first());
        $report = Report::create(['reporter_id' => reader()->id, 'target_type' => 'reel', 'target_id' => $reel->id, 'reason' => 'misinformation']);
        $this->actingAs($this->editor, 'admin');

        $this->get('/admin/reports?type=reel')->assertOk()->assertSee($reel->title);
        $this->get("/admin/reports/{$report->id}")->assertOk()->assertSee('Reel: '.$reel->title)->assertSee(route('admin.reels.show', $reel), false);

        $this->post("/admin/reports/{$report->id}/act", ['action' => 'take_down', 'note' => 'Misleading caption'])->assertRedirect();

        expect($reel->fresh())->status->toBe('rejected')->rejection_reason->toBe('Report: Misleading caption')
            ->and($report->fresh()->status)->toBe('actioned');
        expect($reel->fresh()->isVisible())->toBeFalse();
    });

    it('opens a report about a comment on a reel', function () {
        $reel = makeReel(Story::published()->first());
        $comment = Comment::create(['reel_id' => $reel->id, 'user_id' => reader()->id, 'body' => 'Rude words here']);
        $report = Report::create(['reporter_id' => reader()->id, 'target_type' => 'comment', 'target_id' => $comment->id, 'reason' => 'abusive']);

        $this->actingAs($this->editor, 'admin')->get("/admin/reports/{$report->id}")
            ->assertOk()->assertSee('On reel')->assertSee($reel->title);
    });
});
