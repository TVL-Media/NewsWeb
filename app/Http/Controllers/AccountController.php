<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\Like;
use App\Models\Quiz;
use App\Models\ReadingEvent;
use App\Models\Story;
use App\Support\Rails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** /account: account card, stats, library and preferences. Not a social profile. */
class AccountController extends Controller
{
    public const LIBRARY = ['saved', 'following', 'liked', 'history'];

    public function show(Request $request)
    {
        $user = $request->user()->load(['city', 'categories', 'streak']);
        $quiz = Quiz::today();
        $attempt = $quiz ? $user->quizAttempts()->where('quiz_id', $quiz->id)->whereNotNull('completed_at')->first() : null;

        return view('account.show', [
            'user' => $user,
            'streak' => Rails::streakDays($user),
            'quizScore' => $attempt ? $attempt->score.'/'.$quiz->questions()->count() : '–/5',
            'readThisWeek' => ReadingEvent::where('user_id', $user->id)->where('read_on', '>=', now(config('app.timezone'))->subDays(6)->toDateString())->distinct()->count('story_id'),
            'counts' => [
                'saved' => $user->savedStories()->count(),
                'following' => $user->followedPages()->count(),
                'liked' => Like::where('user_id', $user->id)->where('likeable_type', Story::class)->count(),
                'history' => ReadingEvent::where('user_id', $user->id)->distinct()->count('story_id'),
            ],
            'categories' => Category::orderBy('sort_order')->get(),
            'cities' => City::orderBy('id')->get(),
        ]);
    }

    /** PATCH /account — any subset of the editable fields (Edit sheet and preference rows). */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            'language' => ['sometimes', Rule::in(['en', 'hi'])],
            'text_size' => ['sometimes', Rule::in(['small', 'medium', 'large'])],
            'theme' => ['sometimes', Rule::in(['light', 'dark'])],
            'notifications_per_day' => ['sometimes', 'integer', 'between:0,10'],
            'categories' => ['sometimes', 'array', 'min:1'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ]);

        $user = $request->user();
        $user->update(collect($data)->except('categories')->all());
        if (array_key_exists('categories', $data)) {
            $user->categories()->sync($data['categories']);
        }

        $response = $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('status', __('Saved'));

        return isset($data['language']) ? $response->withCookie(cookie()->forever('lang', $data['language'])) : $response;
    }

    /** /account/{saved|following|liked|history} */
    public function library(Request $request, string $type)
    {
        abort_unless(in_array($type, self::LIBRARY, true), 404);
        $user = $request->user();

        if ($type === 'following') {
            return view('account.library', [
                'type' => $type,
                'pages' => $user->followedPages()->withCount('followers')->orderBy('name')->get(),
                'stories' => collect(),
            ]);
        }

        $ids = match ($type) {
            'saved' => $user->savedStories()->orderByPivot('created_at', 'desc')->pluck('stories.id'),
            'liked' => Like::where('user_id', $user->id)->where('likeable_type', Story::class)->latest()->pluck('likeable_id'),
            'history' => ReadingEvent::where('user_id', $user->id)->orderByDesc('read_at')->pluck('story_id')->unique()->values(),
        };
        $ids = $ids->take(100);
        $order = $ids->flip();

        $stories = Story::published()->with(['versions', 'category'])->whereIn('id', $ids)->get()
            ->sortBy(fn ($s) => $order[$s->id])->values();

        return view('account.library', ['type' => $type, 'stories' => $stories, 'pages' => collect()]);
    }

    /** DELETE /account — soft-deletes the reader (Privacy policy: erasure on request). */
    public function destroy(Request $request)
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
