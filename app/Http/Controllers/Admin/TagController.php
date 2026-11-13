<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Tag::withCount('stories');
        if ($s = $r->query('q')) {
            $q->where('name', 'like', "%{$s}%");
        }
        $tags = $q->orderByDesc('stories_count')->orderBy('name')->paginate($this->perPage($r))->withQueryString();

        return view('admin.tags.index', compact('tags'));
    }

    public function update(Request $r, Tag $tag)
    {
        $data = $r->validate(['name' => 'required|string|max:60']);
        $slug = Str::slug($data['name']);
        // Renaming onto an existing tag merges the two.
        if ($other = Tag::where('slug', $slug)->where('id', '!=', $tag->id)->first()) {
            foreach ($tag->stories as $c) {
                $c->tags()->syncWithoutDetaching([$other->id]);
            }
            $tag->stories()->detach();
            $tag->delete();
            Audit::log('tag.merged', $other, null, ['merged' => $tag->name]);

            return back()->with('ok', "Merged “{$tag->name}” into “{$other->name}”.");
        }
        $tag->update(['name' => $data['name'], 'slug' => $slug]);

        return back()->with('ok', 'Tag renamed.');
    }

    public function destroy(Tag $tag)
    {
        $tag->stories()->detach();
        $tag->delete();
        Audit::log('tag.deleted', $tag);

        return back()->with('ok', 'Tag removed from all stories.');
    }
}
