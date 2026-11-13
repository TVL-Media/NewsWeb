<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Audit;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Website categories: English + Hindi names, photo tile, optional icon and colour, on/off, sensitive flag. */
class CategoryController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Category::query()->withCount([
            'stories as total_count',
            'stories as published_count' => fn ($s) => $s->whereIn('status', ['published', 'corrected']),
            'pages',
        ]);
        if ($s = $r->query('q')) {
            $q->where(fn ($w) => $w->where('name_en', 'like', "%{$s}%")->orWhere('name_hi', 'like', "%{$s}%"));
        }
        if ($st = $r->query('status')) {
            $q->where('is_active', $st === 'active');
        }
        $categories = $q->orderBy('sort_order')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function show(Category $category)
    {
        return redirect()->route('admin.stories.index', ['category' => $category->id]);
    }

    private function rules(?Category $c = null): array
    {
        return [
            'name_en' => ['required', 'string', 'min:2', 'max:60', Rule::unique('categories', 'name_en')->ignore($c?->id)],
            'name_hi' => ['required', 'string', 'min:1', 'max:60'],
            'slug' => ['nullable', 'string', 'max:80', Rule::unique('categories', 'slug')->ignore($c?->id)],
            'description' => 'nullable|string|max:300',
            'visual_type' => 'required|in:icon,image',
            'icon' => 'nullable|file|mimes:svg,png,webp|max:512',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => 'required|in:active,inactive',
            'is_sensitive' => 'nullable|boolean',
        ];
    }

    private function prepare(Request $r, array $data): array
    {
        $disk = Storage::disk(config('filesystems.media'));
        if ($r->hasFile('icon')) {
            $f = $r->file('icon');
            if ($f->getClientOriginalExtension() === 'svg' || $f->getMimeType() === 'image/svg+xml') {
                // Sanitise SVG: strip scripts, event handlers and foreign objects.
                $svg = file_get_contents($f->getRealPath());
                $svg = preg_replace('#<script.*?</script>#is', '', $svg);
                $svg = preg_replace('#\son\w+="[^"]*"#i', '', $svg);
                $svg = preg_replace('#<foreignObject.*?</foreignObject>#is', '', $svg);
                $svg = preg_replace('#(href|xlink:href)="javascript:[^"]*"#i', '', $svg);
                $path = 'categories/'.Str::random(20).'.svg';
                $disk->put($path, $svg);
                $data['icon_path'] = $path;
            } else {
                $data['icon_path'] = $f->store('categories', config('filesystems.media'));
            }
        }
        if ($r->hasFile('image')) {
            $data['image_path'] = $r->file('image')->store('categories', config('filesystems.media'));
        }
        $data['is_active'] = ($data['status'] ?? 'active') === 'active';
        $data['is_sensitive'] = $r->boolean('is_sensitive');
        $data['slug'] = Str::slug($data['slug'] ?: $data['name_en']);
        unset($data['icon'], $data['image'], $data['status']);

        return $data;
    }

    public function store(Request $r)
    {
        $data = $this->prepare($r, $r->validate($this->rules()));
        $data['sort_order'] = (int) Category::max('sort_order') + 1;
        $data['created_by'] = $data['updated_by'] = auth('admin')->id();
        $c = Category::create($data);
        Audit::log('category.created', $c, null, $c->only('name_en', 'name_hi', 'is_active', 'is_sensitive'));
        $this->clear();

        return back()->with('ok', "Category “{$c->name_en}” created.");
    }

    public function update(Request $r, Category $category)
    {
        $data = $this->prepare($r, $r->validate($this->rules($category)));
        $data['updated_by'] = auth('admin')->id();
        $before = $category->only('name_en', 'name_hi', 'slug', 'description', 'color_hex', 'is_active', 'is_sensitive', 'visual_type', 'icon_path', 'image_path');
        $category->update($data);
        Audit::log('category.updated', $category, $before, $category->only(array_keys($before)));
        $this->clear();

        return back()->with('ok', 'Category saved.');
    }

    /** Turning a category off hides its chip and tile on the website; its stories stay published. */
    public function toggle(Category $category)
    {
        $on = ! $category->is_active;
        $category->update(['is_active' => $on, 'updated_by' => auth('admin')->id()]);
        Audit::log($on ? 'category.activated' : 'category.deactivated', $category, ['is_active' => ! $on], ['is_active' => $on]);
        $this->clear();
        $n = $category->stories()->count();

        return back()->with('ok', $on ? "“{$category->name_en}” is back on the website." : "“{$category->name_en}” hidden from the website menus. Its {$n} stories stay published.");
    }

    public function reorder(Request $r)
    {
        $ids = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                Category::whereKey($id)->update(['sort_order' => $i]);
            }
        });
        Audit::log('category.reordered', null, null, ['order' => $ids], null, 'Category display order');
        $this->clear();

        return response()->json(['ok' => true]);
    }

    public function destroy(Category $category)
    {
        if ($category->stories()->exists() || $category->pages()->exists()) {
            return back()->with('error', 'This category still has stories or pages. Turn it off instead, or move them first.');
        }
        $category->delete();
        Audit::log('category.deleted', $category);
        $this->clear();

        return back()->with('ok', 'Category deleted.');
    }

    private function clear(): void
    {
        Cache::forget('nav.categories');
        PageCache::flush();
    }
}
