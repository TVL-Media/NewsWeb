<?php

namespace App\Services\Newsroom;

use App\Models\Admin;
use App\Models\Approval;
use App\Models\PublishLog;
use App\Models\Story;
use App\Models\StorySnapshot;
use App\Models\Tag;
use App\Support\Audit;
use App\Support\Notify;
use App\Support\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The editorial workflow used by the admin panel, on top of the website's StoryPublisher.
 *
 *   draft ─submit→ pending_approval ─approve→ published (or scheduled)
 *                          │ └request changes→ changes_requested ─resubmit→ …
 *                          └reject→ rejected ─reopen→ draft
 *   published ─unpublish→ removed ─publish→ published
 *
 * Pipeline drafts (FetchFeeds → GenerateDrafts → RouteStory) arrive as pending_approval or auto-publish.
 */
class StoryWorkflow
{
    public function __construct(private StoryPublisher $publisher) {}

    public const EDITABLE_FIELDS = ['category_id', 'page_id', 'city_id', 'source_id', 'source_url', 'importance', 'read_minutes',
        'is_breaking', 'photo_path', 'photo_credit', 'correction_label'];

    /** Create or update a story with both language versions and tags. */
    public function save(?Story $story, array $data, Admin $by): Story
    {
        return DB::transaction(function () use ($story, $data, $by) {
            $fields = collect($data)->only(self::EDITABLE_FIELDS)->all();
            $fields['is_breaking'] = (bool) ($data['is_breaking'] ?? false);

            if (! $story) {
                $story = Story::create($fields + [
                    'slug' => $this->uniqueSlug($data['en']['headline'] ?? 'story'),
                    'status' => 'draft',
                    'publish_mode' => 'manual',
                    'author_id' => $by->id,
                ]);
                Audit::log('story.created', $story);
            } else {
                $before = $story->only(array_keys($fields));
                $story->fill($fields);
                if ($story->isDirty()) {
                    $story->save();
                    Audit::log('story.updated', $story, $before, $story->only(array_keys($fields)));
                }
            }

            $this->saveVersions($story, $data);
            if (array_key_exists('tags', $data)) {
                $this->syncTags($story, (string) $data['tags']);
            }

            if ($story->isLive()) {
                $story->increment('version_no');
                $this->snapshot($story->refresh(), 'Edited while live', $by);
                $this->publisher->refreshLive($story);
            }

            return $story->refresh();
        });
    }

    /** Write en/hi into story_versions. Hindi is optional (readers get the English fallback). */
    public function saveVersions(Story $story, array $data): void
    {
        foreach (['en', 'hi'] as $lang) {
            $v = $data[$lang] ?? null;
            if (! is_array($v)) {
                continue;
            }
            if (blank($v['headline'] ?? null)) {
                if ($lang === 'hi') {
                    continue;
                }
            }
            $row = [
                'headline' => trim((string) ($v['headline'] ?? '')),
                'card_text' => trim((string) ($v['card_text'] ?? '')),
                'key_points' => array_values(array_map('trim', array_filter($v['key_points'] ?? [], 'filled'))),
                'why_it_matters' => $v['why_it_matters'] ?? null,
                'simple_text' => $v['simple_text'] ?? null,
                'full_text' => $v['full_text'] ?? null,
            ];
            $existing = $story->versions()->where('language', $lang)->first();
            if ($existing) {
                $existing->fill($row);
                if ($existing->isDirty()) {
                    // A human edit to a machine translation marks it as edited.
                    if ($existing->translation_status === 'ai') {
                        $existing->translation_status = 'edited';
                    }
                    $existing->ai_assisted = $existing->ai_assisted && ! $existing->isDirty(['headline', 'card_text']);
                    $existing->save();
                }
            } else {
                $story->versions()->create($row + ['language' => $lang, 'ai_assisted' => false]);
            }
        }
    }

    public function syncTags(Story $story, string $csv): void
    {
        $ids = collect(explode(',', $csv))->map(fn ($t) => trim($t))->filter()->unique()->take(15)
            ->map(fn ($name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => Str::limit($name, 60, '')])->id);
        $story->tags()->sync($ids);
    }

    /** What still blocks publishing. Empty array = ready. */
    public function missing(Story $story): array
    {
        $en = $story->versions->firstWhere('language', 'en') ?? $story->versions()->where('language', 'en')->first();
        $out = [];
        if (! $story->category_id) {
            $out[] = 'Choose a category.';
        }
        if (! $en || blank($en->headline)) {
            $out[] = 'Write the English headline.';
        }
        if (! $en || blank($en->card_text)) {
            $out[] = 'Write the English card text.';
        } elseif (count(preg_split('/\s+/u', trim($en->card_text))) > 60) {
            $out[] = 'The English card text must be 60 words or fewer.';
        }
        if (! $en || count(array_filter($en->key_points ?? [], 'filled')) < 3) {
            $out[] = 'Add three key points in English.';
        }
        $hi = $story->versions->firstWhere('language', 'hi');
        if ($hi && filled($hi->card_text) && count(preg_split('/\s+/u', trim($hi->card_text))) > 60) {
            $out[] = 'The Hindi card text must be 60 words or fewer.';
        }

        return $out;
    }

    private function assertReady(Story $story): void
    {
        if ($m = $this->missing($story->load('versions'))) {
            throw ValidationException::withMessages(['story' => $m]);
        }
    }

    public function submit(Story $story, Admin $by): void
    {
        abort_unless(in_array($story->status, ['draft', 'changes_requested', 'rejected', 'removed'], true), 422, 'Only drafts can be submitted.');
        $this->assertReady($story);
        $from = $story->status;
        $story->update(['status' => 'pending_approval', 'submitted_at' => now()]);
        $this->snapshot($story, 'Submitted for review', $by);
        Audit::log('story.submitted', $story, ['status' => $from], ['status' => 'pending_approval']);
        Notify::permission('review.decide', 'review', 'Story waiting for review', Str::limit($story->title(), 120), route('admin.review.show', $story));
    }

    /** Approve now, or approve for a time in the future (scheduled). Writers can't approve their own story. */
    public function approve(Story $story, Admin $by, ?Carbon $at = null, bool $override = false): void
    {
        abort_if(! $override && $story->author_id === $by->id && ! $by->isSuperAdmin(), 403, 'You can’t approve your own story. Ask another editor, or use override.');
        $this->assertReady($story);
        if ($at && $at->isFuture()) {
            Approval::create(['story_id' => $story->id, 'editor_id' => $by->id, 'action' => 'approved', 'reason' => 'Scheduled for '.$at->toIso8601String(), 'acted_at' => now()]);
            $this->schedule($story, $at, $by, logApproval: false);

            return;
        }
        $this->publisher->approve($story, $by);
        $this->afterPublish($story, $by, $override ? 'Published (override)' : 'Approved and published');
        $this->notifyAuthor($story, $by, 'approved', 'Your story is live');
    }

    public function requestChanges(Story $story, Admin $by, string $note): void
    {
        Approval::create(['story_id' => $story->id, 'editor_id' => $by->id, 'action' => 'changes_requested', 'reason' => $note, 'acted_at' => now()]);
        $story->update(['status' => 'changes_requested']);
        Audit::log('story.changes_requested', $story, ['status' => 'pending_approval'], ['status' => 'changes_requested'], $note);
        $this->notifyAuthor($story, $by, 'changes_requested', 'Changes requested on your story', $note);
    }

    public function reject(Story $story, Admin $by, string $reason): void
    {
        $from = $story->status;
        $this->publisher->reject($story, $by, $reason);
        Audit::log('story.rejected', $story, ['status' => $from], ['status' => 'rejected'], $reason);
        $this->notifyAuthor($story, $by, 'rejected', 'Your story was rejected', $reason);
    }

    /** Publish straight away (editors with content.publish). */
    public function publishNow(Story $story, Admin $by): void
    {
        $this->assertReady($story);
        $this->publisher->approve($story, $by, edited: true);
        $this->afterPublish($story, $by, 'Published');
    }

    public function schedule(Story $story, Carbon $at, Admin $by, bool $logApproval = true): void
    {
        $this->assertReady($story);
        abort_unless($at->isFuture(), 422, 'Pick a time in the future.');
        $from = $story->status;
        $story->update(['status' => 'scheduled', 'scheduled_at' => $at, 'publish_attempts' => 0, 'last_publish_error' => null, 'approved_by' => $by->id]);
        Audit::log('story.scheduled', $story, ['status' => $from], ['status' => 'scheduled', 'scheduled_at' => $at->toIso8601String()]);
    }

    public function cancelSchedule(Story $story, Admin $by): void
    {
        abort_unless($story->status === 'scheduled', 422);
        $story->update(['status' => 'draft', 'scheduled_at' => null, 'publish_attempts' => 0, 'last_publish_error' => null]);
        Audit::log('story.schedule_cancelled', $story, ['status' => 'scheduled'], ['status' => 'draft']);
    }

    public function unpublish(Story $story, Admin $by, ?string $reason = null): void
    {
        abort_unless($story->isLive(), 422, 'This story is not live.');
        $from = $story->status;
        $this->publisher->unpublish($story);
        $story->update(['unpublished_at' => now()]);
        Audit::log('story.unpublished', $story, ['status' => $from], ['status' => 'removed'], $reason);
    }

    public function reopen(Story $story, Admin $by): void
    {
        abort_unless(in_array($story->status, ['rejected', 'removed'], true), 422);
        $from = $story->status;
        $story->update(['status' => 'draft']);
        Audit::log('story.reopened', $story, ['status' => $from], ['status' => 'draft']);
    }

    /** Permanent delete: versions, comments, likes, polls links etc. go with it (FK cascades). */
    public function delete(Story $story, ?string $reason): void
    {
        Audit::log('story.deleted', $story, $story->only('status', 'slug'), null, $reason);
        $story->delete();
        PageCache::flush();
    }

    // ── Versions ────────────────────────────────────────────────────────────

    public function snapshot(Story $story, string $note, ?Admin $by): StorySnapshot
    {
        $story->loadMissing('versions', 'tags');

        return StorySnapshot::create([
            'story_id' => $story->id,
            'version_no' => $story->version_no,
            'snapshot' => [
                'fields' => $story->only(self::EDITABLE_FIELDS),
                'versions' => $story->versions->mapWithKeys(fn ($v) => [$v->language => $v->only('headline', 'card_text', 'key_points', 'why_it_matters', 'simple_text', 'full_text')])->all(),
                'tags' => $story->tags->pluck('name')->join(', '),
            ],
            'change_note' => $note,
            'created_by' => $by?->id,
        ]);
    }

    /** Restore text and details from an older snapshot. The current state is kept as a snapshot first. */
    public function restore(Story $story, StorySnapshot $snap, Admin $by): void
    {
        abort_unless($snap->story_id === $story->id, 404);
        $this->snapshot($story, 'Before restoring version '.$snap->version_no, $by);
        $data = $snap->snapshot['fields'] ?? [];
        foreach ($snap->snapshot['versions'] ?? [] as $lang => $v) {
            $data[$lang] = $v;
        }
        $data['tags'] = $snap->snapshot['tags'] ?? '';
        $story->increment('version_no');
        $this->save($story->refresh(), $data, $by);
        Audit::log('story.version_restored', $story, null, ['restored_version' => $snap->version_no]);
    }

    // ── Scheduler ───────────────────────────────────────────────────────────

    /** Every minute: publish due stories. Three tries, then the story is flagged and editors are told. */
    public function publishDue(): int
    {
        $n = 0;
        $due = Story::where('status', 'scheduled')->where('scheduled_at', '<=', now())->where('publish_attempts', '<', 3)->pluck('id');
        foreach ($due as $id) {
            DB::transaction(function () use ($id, &$n) {
                $story = Story::whereKey($id)->lockForUpdate()->first();
                if (! $story || $story->status !== 'scheduled') {
                    return;
                }
                try {
                    $this->assertReady($story);
                    $editor = $story->approved_by ? Admin::find($story->approved_by) : null;
                    $story->published_at = $story->scheduled_at;
                    $this->publisher->publish($story, 'manual', $editor);
                    PublishLog::create(['story_id' => $story->id, 'scheduled_for' => $story->scheduled_at, 'result' => 'success']);
                    $this->snapshot($story, 'Published on schedule', null);
                    Audit::log('story.published_scheduled', $story);
                    $n++;
                } catch (\Throwable $e) {
                    $msg = $e instanceof ValidationException ? collect($e->errors())->flatten()->join(' ') : $e->getMessage();
                    $story->increment('publish_attempts');
                    $story->update(['last_publish_error' => Str::limit($msg, 500)]);
                    PublishLog::create(['story_id' => $story->id, 'scheduled_for' => $story->scheduled_at, 'result' => 'failed', 'error_message' => Str::limit($msg, 1000)]);
                    if ($story->publish_attempts >= 3) {
                        Notify::permission('content.publish', 'publish_failed', 'Scheduled story failed to publish', Str::limit($story->title(), 100).': '.Str::limit($msg, 200), route('admin.stories.edit', $story));
                    }
                }
            });
        }

        return $n;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function afterPublish(Story $story, Admin $by, string $note): void
    {
        $story->refresh();
        $story->update(['scheduled_at' => null, 'publish_attempts' => 0, 'last_publish_error' => null]);
        $this->snapshot($story, $note, $by);
        Audit::log('story.published', $story, null, ['status' => $story->status]);
    }

    private function notifyAuthor(Story $story, Admin $by, string $type, string $title, ?string $body = null): void
    {
        if ($story->author_id && $story->author_id !== $by->id) {
            Notify::admin($story->author_id, $type, $title, $body ?? Str::limit($story->title(), 120), route('admin.stories.edit', $story));
        }
    }

    public function uniqueSlug(string $headline): string
    {
        $base = Str::limit(Str::slug($headline), 90, '') ?: 'story';
        $slug = $base;
        for ($i = 2; Story::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
