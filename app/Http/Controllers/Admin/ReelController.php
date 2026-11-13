<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReel;
use App\Jobs\ProcessUploadedReel;
use App\Models\Category;
use App\Models\Page;
use App\Models\Reel;
use App\Models\Report;
use App\Models\Story;
use App\Support\Audit;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reels: auto reels made from published stories and videos uploaded here.
 * Sensitive-story reels and every upload wait for approval; failures show the FFmpeg error.
 */
class ReelController extends Controller
{
    use Concerns;

    public const LABELS = [
        'processing' => ['Processing', 'blue'],
        'pending_approval' => ['Waiting for approval', 'amber'],
        'published' => ['Published', 'green'],
        'rejected' => ['Rejected', 'red'],
        'failed' => ['Failed', 'red'],
    ];

    public function index(Request $r)
    {
        $q = Reel::with(['story:id,slug', 'page:id,name', 'creator:id,name']);
        $tab = $r->query('tab', ($r->query('status') ? 'all' : (Reel::where('status', 'pending_approval')->exists() ? 'pending' : 'all')));
        if ($tab === 'pending') {
            $q->where('status', 'pending_approval');
        } elseif (($st = $r->query('status')) && isset(self::LABELS[$st])) {
            $q->where('status', $st);
        }
        if (in_array($lang = $r->query('language'), ['en', 'hi'], true)) {
            $q->where('language', $lang);
        }
        if (in_array($src = $r->query('source'), ['auto', 'upload'], true)) {
            $q->where('source_type', $src);
        }
        if ($s = $r->query('q')) {
            $q->where('title', 'like', "%{$s}%");
        }

        $reels = $q->latest('id')->paginate($this->perPage($r))->withQueryString();
        $counts = Reel::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $reported = Report::where('target_type', 'reel')->whereIn('status', ['open', 'escalated'])->pluck('target_id')->countBy();

        return view('admin.reels.index', ['reels' => $reels, 'counts' => $counts, 'tab' => $tab, 'reported' => $reported, 'labels' => self::LABELS]);
    }

    public function show(Reel $reel)
    {
        $reel->load(['story.versions:id,story_id,language,headline', 'story.aiCheck', 'page', 'category', 'creator', 'reviewer']);
        $views = $reel->views()->selectRaw('count(*) as n, coalesce(avg(watched_seconds), 0) as avg_s, coalesce(sum(case when completed then 1 else 0 end), 0) as done')->first();
        $reports = Report::where('target_type', 'reel')->where('target_id', $reel->id)->whereIn('status', ['open', 'escalated'])->count();
        $other = $reel->story_id ? Reel::where('story_id', $reel->story_id)->whereKeyNot($reel->id)->get(['id', 'language', 'status']) : collect();

        return view('admin.reels.show', ['reel' => $reel, 'views' => $views, 'reports' => $reports, 'other' => $other, 'labels' => self::LABELS]);
    }

    public function create()
    {
        return view('admin.reels.upload', [
            'pages' => Page::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name_en']),
            'stories' => Story::published()->with('versions:id,story_id,language,headline')->latest('published_at')->limit(80)->get()->mapWithKeys(fn ($s) => [$s->id => $s->title()]),
            'maxMb' => (int) config('reels.upload_max_mb'),
            'maxSeconds' => (int) config('reels.max_seconds'),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska|max:'.((int) config('reels.upload_max_mb') * 1024),
            'title' => 'required|string|min:10|max:200',
            'language' => 'required|in:en,hi',
            'page_id' => 'nullable|integer|exists:pages,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'story_id' => 'nullable|integer|exists:stories,id',
        ], ['video.mimetypes' => 'Upload an MP4, MOV, WebM or MKV video.', 'video.max' => 'The video is larger than '.config('reels.upload_max_mb').' MB.']);

        $story = ! empty($data['story_id']) ? Story::find($data['story_id']) : null;
        $file = $r->file('video');
        $path = $file->storeAs('reels/uploads', Str::uuid().'.'.($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'mp4'), config('filesystems.media'));

        $reel = Reel::create([
            'story_id' => $story?->id,
            'page_id' => $data['page_id'] ?? $story?->page_id,
            'category_id' => $data['category_id'] ?? $story?->category_id,
            'language' => $data['language'],
            'title' => $data['title'],
            'slug' => (Str::slug(Str::limit($data['title'], 80, '')) ?: 'reel').'-'.Str::lower(Str::random(6)),
            'source_type' => 'upload',
            'source_path' => $path,
            'status' => 'processing',
            'created_by' => auth('admin')->id(),
        ]);
        Audit::log('reel.uploaded', $reel, null, ['title' => $reel->title, 'language' => $reel->language]);
        ProcessUploadedReel::dispatch($reel->id);

        return redirect()->route('admin.reels.show', $reel)->with('ok', 'Video uploaded. It is being processed and will wait here for approval.');
    }

    public function approve(Reel $reel)
    {
        abort_unless(in_array($reel->status, ['pending_approval', 'rejected'], true) && $reel->video_720_path, 422, 'Only a processed reel that is waiting or rejected can be approved.');
        $from = $reel->status;
        $reel->update(['status' => 'published', 'published_at' => now(), 'rejection_reason' => null, 'reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
        Audit::log('reel.approved', $reel, ['status' => $from], ['status' => 'published']);
        PageCache::flush();

        return back()->with('ok', 'Reel approved. It is live on the website.');
    }

    public function reject(Request $r, Reel $reel)
    {
        $reason = $r->validate(['reason' => 'required|string|max:1000'], ['reason.required' => 'Please give a reason.'])['reason'];
        $this->takeDown($reel, 'reel.rejected', $reason);

        return back()->with('ok', 'Reel rejected.');
    }

    public function unpublish(Request $r, Reel $reel)
    {
        abort_unless($reel->status === 'published', 422);
        $reason = trim((string) $r->input('reason'));
        $this->takeDown($reel, 'reel.unpublished', 'Unpublished'.($reason !== '' ? ': '.$reason : ''));

        return back()->with('ok', 'Reel taken off the website.');
    }

    /** Off the site: status "rejected" with the reason (also used by the reports screen). */
    public function takeDown(Reel $reel, string $event, string $reason): void
    {
        $from = $reel->status;
        $reel->update(['status' => 'rejected', 'rejection_reason' => $reason, 'reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
        Audit::log($event, $reel, ['status' => $from], ['status' => 'rejected'], $reason);
        PageCache::flush();
    }

    public function regenerate(Reel $reel)
    {
        abort_if($reel->status === 'processing', 422, 'This reel is already being processed.');
        if ($reel->source_type === 'upload') {
            abort_unless($reel->source_path && Storage::disk(config('filesystems.media'))->exists($reel->source_path), 422, 'The original upload is missing. Upload the video again.');
            $reel->update(['status' => 'processing', 'error_message' => null]);
            ProcessUploadedReel::dispatch($reel->id);
        } else {
            abort_unless($reel->story?->isLive(), 422, 'The story is not live, so its reel cannot be made.');
            $reel->update(['status' => 'processing', 'error_message' => null]);
            GenerateReel::dispatch($reel->story_id, $reel->language, force: true);
        }
        Audit::log('reel.regenerated', $reel);

        return back()->with('ok', 'Re-generating the reel. Refresh in a minute.');
    }
}
