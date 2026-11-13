<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Media;
use App\Models\Page;
use App\Models\Source;
use App\Models\Story;
use App\Models\StorySnapshot;
use App\Services\Newsroom\StoryWorkflow;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Stories (both languages): list, write, edit, review actions, schedule, history. */
class StoryController extends Controller
{
    use Concerns;

    public function __construct(private StoryWorkflow $wf) {}

    public function index(Request $r)
    {
        $me = auth('admin')->user();
        $base = Story::query();
        if (! $me->can('content.edit.any')) {
            $base->where('author_id', $me->id); // writers see their own stories
        }

        $q = (clone $base)->with(['versions:id,story_id,language,headline', 'category:id,name_en,color_hex', 'author:id,name', 'aiCheck']);
        if ($st = $r->query('status')) {
            $q->where('status', $st);
        }
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->whereHas('versions', fn ($v) => $v->where('headline', 'like', "%{$s}%"))
                ->orWhere('slug', 'like', "%{$s}%")->orWhere('id', ctype_digit($s) ? (int) $s : 0)
                ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$s}%")));
        }
        foreach (['category' => 'category_id', 'page' => 'page_id', 'author' => 'author_id', 'mode' => 'publish_mode'] as $param => $col) {
            if ($v = $r->query($param)) {
                $q->where($col, $v);
            }
        }
        if ($r->boolean('breaking')) {
            $q->where('is_breaking', true);
        }
        if ($r->boolean('no_hindi')) {
            $q->whereDoesntHave('versions', fn ($v) => $v->where('language', 'hi'));
        }
        if ($from = $r->query('from')) {
            $q->where('created_at', '>=', from_local_input($from.'T00:00'));
        }
        if ($to = $r->query('to')) {
            $q->where('created_at', '<=', from_local_input($to.'T23:59'));
        }
        match ($r->query('sort')) {
            'published' => $q->orderByDesc('published_at'),
            'created' => $q->orderByDesc('id'),
            'importance' => $q->orderByDesc('importance')->orderByDesc('id'),
            'likes' => $q->orderByDesc('likes_count'),
            default => $q->orderByDesc('updated_at'),
        };

        $counts = (clone $base)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return view('admin.stories.index', [
            'items' => $q->paginate($this->perPage($r))->withQueryString(),
            'counts' => $counts,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name_en', 'is_active']),
            'pages' => Page::orderBy('name')->get(['id', 'name']),
            'authors' => Admin::orderBy('name')->get(['id', 'name']),
            'canBulk' => $me->can('content.edit.any') || $me->can('content.publish'),
        ]);
    }

    public function create()
    {
        $this->need('content.create');

        return view('admin.stories.edit', $this->formData(new Story(['importance' => 5, 'read_minutes' => 2, 'status' => 'draft'])));
    }

    public function store(Request $r)
    {
        $this->need('content.create');
        $data = $this->validated($r, null);
        $story = $this->wf->save(null, $data, $r->user('admin'));

        return $this->afterSave($r, $story, 'Story created.');
    }

    public function edit(Story $story)
    {
        $story->load(['versions', 'tags', 'approvals.editor', 'aiCheck', 'cluster.items.feedSource', 'translationJobs', 'author', 'reviewer']);

        return view('admin.stories.edit', $this->formData($story));
    }

    public function update(Request $r, Story $story)
    {
        $this->authorizeEdit($story);
        $data = $this->validated($r, $story);
        $this->wf->save($story, $data, $r->user('admin'));

        if ($r->boolean('_autosave')) {
            return response()->json(['ok' => true, 'saved_at' => now(ptz())->format('H:i')]);
        }

        return $this->afterSave($r, $story->refresh(), 'Saved.');
    }

    /** Runs the button the editor pressed: plain save, submit, publish or schedule. */
    private function afterSave(Request $r, Story $story, string $ok)
    {
        $me = $r->user('admin');
        try {
            switch ($r->input('action')) {
                case 'submit':
                    $this->need('content.submit');
                    $this->wf->submit($story, $me);
                    $ok = 'Submitted for review.';
                    break;
                case 'publish':
                    $this->need('content.publish');
                    $this->wf->publishNow($story, $me);
                    $ok = 'Published. The audio is being made now.';
                    break;
                case 'schedule':
                    $this->need('content.schedule');
                    $at = from_local_input((string) $r->input('scheduled_at'));
                    if (! $at) {
                        throw ValidationException::withMessages(['scheduled_at' => 'Pick a date and time.']);
                    }
                    $this->wf->schedule($story, $at, $me);
                    $ok = 'Scheduled for '.fmt_dt($at).' '.tz_label().'.';
                    break;
            }
        } catch (ValidationException $e) {
            return redirect()->route('admin.stories.edit', $story)->withErrors($e->errors())->with('error', 'Saved as a draft, but not ready yet.');
        }

        return redirect()->route('admin.stories.edit', $story)->with('ok', $ok);
    }

    public function submit(Story $story)
    {
        $this->need('content.submit');
        $this->authorizeEdit($story);
        $this->wf->submit($story, auth('admin')->user());

        return back()->with('ok', 'Submitted for review.');
    }

    public function publish(Story $story)
    {
        $this->need('content.publish');
        $this->wf->publishNow($story, auth('admin')->user());

        return back()->with('ok', 'Published.');
    }

    public function schedule(Request $r, Story $story)
    {
        $this->need('content.schedule');
        $at = from_local_input((string) $r->validate(['scheduled_at' => 'required|string'])['scheduled_at']);
        $this->wf->schedule($story, $at, $r->user('admin'));

        return back()->with('ok', 'Scheduled for '.fmt_dt($at).'.');
    }

    public function cancelSchedule(Story $story)
    {
        $this->need('content.schedule');
        $this->wf->cancelSchedule($story, auth('admin')->user());

        return back()->with('ok', 'Schedule cancelled. The story is a draft again.');
    }

    public function unpublish(Request $r, Story $story)
    {
        $this->need('content.publish');
        $this->wf->unpublish($story, $r->user('admin'), $r->input('reason'));

        return back()->with('ok', 'Unpublished. It is off the website now.');
    }

    public function reopen(Story $story)
    {
        $this->authorizeEdit($story);
        $this->wf->reopen($story, auth('admin')->user());

        return back()->with('ok', 'Reopened as a draft.');
    }

    public function destroy(Request $r, Story $story)
    {
        if ($r->input('confirm_word') !== 'DELETE') {
            return back()->with('error', 'Type DELETE to confirm.');
        }
        $this->wf->delete($story, $r->input('reason'));

        return redirect()->route('admin.stories.index')->with('ok', 'Deleted permanently.');
    }

    public function preview(Story $story)
    {
        $story->load('versions', 'category', 'page');

        return view('admin.stories.preview', ['s' => $story]);
    }

    public function history(Story $story)
    {
        $story->load(['snapshots.author', 'approvals.editor', 'publishLogs', 'aiCheck']);
        $audit = \App\Models\AuditLog::with('admin:id,name')->where('subject_type', 'Story')->where('subject_id', $story->id)->latest('created_at')->limit(50)->get();

        return view('admin.stories.history', ['s' => $story, 'audit' => $audit]);
    }

    public function restoreVersion(Story $story, StorySnapshot $snapshot)
    {
        $this->authorizeEdit($story);
        $this->wf->restore($story, $snapshot, auth('admin')->user());

        return redirect()->route('admin.stories.edit', $story)->with('ok', "Version {$snapshot->version_no} restored. Nothing was lost: the previous text is saved in History.");
    }

    public function bulk(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|max:200', 'ids.*' => 'integer', 'op' => 'required|in:unpublish,category,tag,submit,importance,delete', 'category_id' => 'nullable|integer|exists:categories,id', 'tag' => 'nullable|string|max:60', 'importance' => 'nullable|integer|min:1|max:10']);
        $me = $r->user('admin');
        $stories = Story::whereIn('id', $data['ids'])->get();
        $done = 0;
        $skipped = 0;
        foreach ($stories as $s) {
            try {
                match ($data['op']) {
                    'unpublish' => $this->need('content.publish') ?? ($s->isLive() ? $this->wf->unpublish($s, $me, 'Bulk unpublish') : throw new \RuntimeException),
                    'category' => $this->need('content.edit.any') ?? $s->update(['category_id' => $data['category_id']]),
                    'importance' => $this->need('content.edit.any') ?? $s->update(['importance' => $data['importance'] ?? 5]),
                    'tag' => $this->need('content.edit.any') ?? $this->wf->syncTags($s, $s->tags()->pluck('name')->push($data['tag'] ?? '')->join(',')),
                    'submit' => $this->need('content.submit') ?? $this->wf->submit($s, $me),
                    'delete' => $me->isSuperAdmin() ? $this->wf->delete($s, 'Bulk delete') : abort(403),
                };
                $done++;
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                if ($e->getStatusCode() === 403) {
                    throw $e;
                }
                $skipped++;
            } catch (\Throwable) {
                $skipped++;
            }
        }
        if (in_array($data['op'], ['category', 'importance', 'tag'], true)) {
            \App\Support\PageCache::flush();
        }
        Audit::log('story.bulk_'.$data['op'], null, null, ['ids' => $data['ids'], 'done' => $done, 'skipped' => $skipped], null, 'Bulk action on '.count($data['ids']).' stories');

        return back()->with('ok', "{$done} updated".($skipped ? ", {$skipped} skipped (not in the right state or not ready)." : '.'));
    }

    /** JSON for story pickers (polls, Morning 5, quiz). */
    public function lookup(Request $r)
    {
        $q = Story::with('versions:id,story_id,language,headline')->latest('id');
        if ($r->boolean('live')) {
            $q->whereIn('status', ['published', 'corrected']);
        }
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->whereHas('versions', fn ($v) => $v->where('headline', 'like', "%{$s}%"))->orWhere('id', ctype_digit($s) ? (int) $s : 0));
        }

        return $q->limit(20)->get()->map(fn (Story $s) => ['id' => $s->id, 'title' => $s->title(), 'status' => $s->status, 'published' => fmt_dt($s->published_at)]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function authorizeEdit(Story $story): void
    {
        $me = auth('admin')->user();
        $own = $story->author_id === $me->id;
        abort_unless($me->can('content.edit.any') || ($own && $me->can('content.edit.own')), 403, 'You can only edit your own stories.');
        if ($story->isLive() || $story->status === 'scheduled') {
            abort_unless($me->can('content.edit.published'), 403, 'Your role can’t edit live or scheduled stories. Ask an editor.');
        }
    }

    private function validated(Request $r, ?Story $story): array
    {
        $words60 = function (string $attr, $value, \Closure $fail) {
            if ($value && count(preg_split('/\s+/u', trim($value))) > 60) {
                $fail('The card text must be 60 words or fewer.');
            }
        };
        $data = $r->validate([
            'en.headline' => 'required|string|max:250',
            'en.card_text' => ['nullable', 'string', 'max:1500', $words60],
            'en.key_points' => 'nullable|array|max:3', 'en.key_points.*' => 'nullable|string|max:250',
            'en.why_it_matters' => 'nullable|string|max:1500', 'en.simple_text' => 'nullable|string|max:5000', 'en.full_text' => 'nullable|string|max:60000',
            'hi.headline' => 'nullable|string|max:250',
            'hi.card_text' => ['nullable', 'string', 'max:2500', $words60],
            'hi.key_points' => 'nullable|array|max:3', 'hi.key_points.*' => 'nullable|string|max:400',
            'hi.why_it_matters' => 'nullable|string|max:2500', 'hi.simple_text' => 'nullable|string|max:8000', 'hi.full_text' => 'nullable|string|max:90000',
            'category_id' => 'nullable|integer|exists:categories,id',
            'page_id' => 'nullable|integer|exists:pages,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'source_id' => 'nullable|integer|exists:sources,id',
            'source_url' => 'nullable|url|max:255',
            'importance' => 'required|integer|min:1|max:10',
            'read_minutes' => 'required|integer|min:1|max:30',
            'is_breaking' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:'.((int) setting('content.max_image_mb', 8) * 1024),
            'photo_media_id' => 'nullable|integer|exists:media,id',
            'remove_photo' => 'nullable|boolean',
            'photo_credit' => 'nullable|string|max:255',
            'correction_label' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:500',
        ]);
        $data['is_breaking'] = $r->boolean('is_breaking');

        // Photo: upload, pick from the media library, or keep.
        if ($r->hasFile('photo')) {
            $data['photo_path'] = $r->file('photo')->store('stories', config('filesystems.media'));
        } elseif ($id = $r->input('photo_media_id')) {
            $m = Media::find($id);
            if ($m && $m->path && $m->path !== $story?->photo_path) {
                $data['photo_path'] = $m->path;
                $data['photo_credit'] = $data['photo_credit'] ?: $m->credit;
            }
        } elseif ($r->boolean('remove_photo')) {
            $data['photo_path'] = null;
        }
        unset($data['photo'], $data['photo_media_id'], $data['remove_photo']);

        return $data;
    }

    private function formData(Story $story): array
    {
        $me = auth('admin')->user();
        $similar = collect();
        if ($story->exists && ($h = $story->version('en')?->headline)) {
            $words = collect(preg_split('/\W+/u', mb_strtolower($h)))->filter(fn ($w) => mb_strlen($w) > 4)->take(4);
            if ($words->count() >= 2) {
                $similar = Story::with('versions:id,story_id,language,headline')->where('id', '!=', $story->id)
                    ->whereIn('status', ['published', 'corrected', 'pending_approval', 'scheduled'])
                    ->where('created_at', '>=', now()->subDays((int) setting('content.duplicate_window_days', 3)))
                    ->whereHas('versions', function ($v) use ($words) {
                        $v->where('language', 'en');
                        foreach ($words as $w) {
                            $v->where('headline', 'like', "%{$w}%");
                        }
                    })->limit(5)->get();
            }
        }
        $readonly = $story->exists && ! ($me->can('content.edit.any') || ($story->author_id === $me->id && $me->can('content.edit.own')))
            || ($story->exists && ($story->isLive() || $story->status === 'scheduled') && ! $me->can('content.edit.published'));

        return [
            's' => $story,
            'admin' => $me,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name_en', 'is_active', 'is_sensitive']),
            'pages' => Page::orderBy('name')->get(['id', 'name', 'type']),
            'cities' => City::orderBy('name_en')->get(['id', 'name_en']),
            'sources' => Source::orderBy('name')->get(['id', 'name']),
            'missing' => $story->exists ? $this->wf->missing($story) : [],
            'similar' => $similar,
            'readonly' => $readonly,
            'v' => fn (string $lang) => $story->exists ? $story->versions->firstWhere('language', $lang) : null,
        ];
    }
}
