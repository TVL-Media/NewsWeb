<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\Media;
use App\Services\MediaService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** M5 Media library (plan §10.5). */
class MediaController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Media::query()->with('usages')->where('provider', 'upload');
        if ($k = $r->query('kind')) {
            $q->where('kind', $k);
        }
        if ($u = $r->query('uploader')) {
            $q->where('uploaded_by', $u);
        }
        if ($s = $r->query('q')) {
            $q->where(fn ($w) => $w->where('original_name', 'like', "%{$s}%")->orWhere('caption', 'like', "%{$s}%")->orWhere('credit', 'like', "%{$s}%")->orWhere('alt_text', 'like', "%{$s}%"));
        }
        if ($r->query('unused')) {
            $q->whereNotIn('path', Story::whereNotNull('photo_path')->select('photo_path'));
        }
        $items = $q->latest()->paginate(48)->withQueryString();
        $used = (int) Media::sum('size_bytes');

        return view('admin.media.index', ['items' => $items, 'usedBytes' => $used, 'view' => $r->query('view', 'grid')]);
    }

    public function picker(Request $r)
    {
        $kind = $r->query('kind', 'image');
        $q = Media::where('kind', $kind)->where('provider', 'upload');
        if ($s = $r->query('q')) {
            $q->where(fn ($w) => $w->where('original_name', 'like', "%{$s}%")->orWhere('caption', 'like', "%{$s}%")->orWhere('credit', 'like', "%{$s}%"));
        }

        return response()->json($q->latest()->limit(60)->get()->map(fn (Media $m) => $this->toJson($m)));
    }

    private function toJson(Media $m): array
    {
        $meta = $m->kind === 'image' ? ($m->width ? "{$m->width}×{$m->height}" : $m->humanSize()) : ($m->duration_seconds ? gmdate($m->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $m->duration_seconds).' · ' : '').$m->humanSize();

        return ['id' => $m->id, 'name' => $m->original_name, 'kind' => $m->kind, 'url' => $m->url(), 'thumb' => $m->thumbUrl(), 'meta' => $meta, 'caption' => $m->caption, 'credit' => $m->credit];
    }

    public function store(Request $r, MediaService $svc)
    {
        $r->validate(['file' => 'required|file', 'kind' => 'nullable|in:image,video,audio,caption,document']);
        $m = $svc->store($r->file('file'), $r->input('kind'), auth('admin')->id());
        Audit::log('media.uploaded', $m, null, ['kind' => $m->kind, 'size' => $m->size_bytes]);
        if ($r->expectsJson()) {
            return response()->json($this->toJson($m), 201);
        }

        return back()->with('ok', 'Uploaded '.$m->original_name.'.');
    }

    public function show(Media $media)
    {
        $usage = $media->path ? Story::with('versions:id,story_id,language,headline')->where('photo_path', $media->path)->get() : collect();

        return view('admin.media.show', ['m' => $media, 'usage' => $usage]);
    }

    public function update(Request $r, Media $media)
    {
        $data = $r->validate(['alt_text' => ($media->kind === 'image' ? 'required' : 'nullable').'|string|max:250', 'caption' => 'nullable|string|max:500', 'credit' => 'nullable|string|max:250']);
        $media->update($data);

        return back()->with('ok', 'Media details saved.');
    }

    public function destroy(Media $media)
    {
        if ($media->usageCount() > 0) {
            return back()->with('error', 'This file is used in content. Remove it from that content first.');
        }
        foreach (array_merge([$media->path], array_values($media->conversions ?? [])) as $p) {
            if ($p) {
                Storage::disk($media->disk)->delete($p);
            }
        }
        $media->forceDelete();
        Audit::log('media.deleted', $media);

        return redirect()->route('admin.media.index')->with('ok', 'File deleted.');
    }
}
