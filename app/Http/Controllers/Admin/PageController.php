<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Page;
use App\Models\Story;
use App\Support\Audit;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Followable pages on the website: topics, cities and shows. */
class PageController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Page::with(['category:id,name_en', 'city:id,name_en'])->withCount(['followers', 'stories']);
        if ($t = $r->query('type')) {
            $q->where('type', $t);
        }
        if ($s = $r->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('name_hi', 'like', "%{$s}%"));
        }

        return view('admin.pages.index', ['pages' => $q->orderBy('name')->paginate(50)->withQueryString()]);
    }

    public function create()
    {
        return $this->form(new Page(['type' => 'topic']));
    }

    public function edit(Page $page)
    {
        return $this->form($page);
    }

    private function form(Page $page)
    {
        return view('admin.pages.edit', [
            'p' => $page,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name_en']),
            'cities' => City::orderBy('name_en')->get(['id', 'name_en']),
            'stories' => Story::published()->with('versions:id,story_id,language,headline')->latest('published_at')->limit(60)->get()->mapWithKeys(fn ($s) => [$s->id => $s->title()]),
        ]);
    }

    public function store(Request $r)
    {
        $page = Page::create($this->validated($r, null));
        Audit::log('page.created', $page);
        PageCache::flush();

        return redirect()->route('admin.pages.edit', $page)->with('ok', 'Page created.');
    }

    public function update(Request $r, Page $page)
    {
        $data = $this->validated($r, $page);
        $before = $page->only(array_keys($data));
        $page->update($data);
        Audit::log('page.updated', $page, $before, $page->only(array_keys($data)));
        PageCache::flush();

        return back()->with('ok', 'Page saved.');
    }

    public function destroy(Page $page)
    {
        abort_if($page->stories()->exists(), 422, 'This page still has stories. Move them to another page first.');
        $page->delete();
        Audit::log('page.deleted', $page);
        PageCache::flush();

        return redirect()->route('admin.pages.index')->with('ok', 'Page deleted. Its followers no longer follow it.');
    }

    private function validated(Request $r, ?Page $page): array
    {
        $data = $r->validate([
            'name' => 'required|string|max:80', 'name_hi' => 'nullable|string|max:80',
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'type' => 'required|in:topic,city,show',
            'category_id' => 'nullable|integer|exists:categories,id', 'city_id' => 'nullable|integer|exists:cities,id',
            'desk_label' => 'nullable|string|max:60', 'verified' => 'nullable|boolean', 'description' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|max:4096', 'cover' => 'nullable|image|max:8192',
            'pinned_label' => 'nullable|string|max:30', 'pinned_text' => 'nullable|string|max:255', 'pinned_story_id' => 'nullable|integer|exists:stories,id',
            'upcoming' => 'nullable|array|max:8', 'upcoming.*.date' => 'nullable|date', 'upcoming.*.title' => 'nullable|string|max:120', 'upcoming.*.subtitle' => 'nullable|string|max:120',
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['verified'] = $r->boolean('verified');
        $disk = config('filesystems.media');
        if ($r->hasFile('avatar')) {
            $data['avatar_path'] = $r->file('avatar')->store('pages', $disk);
        }
        if ($r->hasFile('cover')) {
            $data['cover_path'] = $r->file('cover')->store('pages', $disk);
        }
        $data['upcoming'] = collect($data['upcoming'] ?? [])->filter(fn ($u) => filled($u['date'] ?? null) && filled($u['title'] ?? null))
            ->map(fn ($u) => ['date' => $u['date'], 'title' => $u['title'], 'subtitle' => $u['subtitle'] ?? null])->values()->all() ?: null;
        unset($data['avatar'], $data['cover']);

        return $data;
    }
}
