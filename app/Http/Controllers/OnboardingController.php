<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Shown once after the first login: language → categories (≥ 1) → city. */
class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->onboarded_at) {
            return redirect()->route('home');
        }

        return view('onboarding', [
            'categories' => Category::active()->orderBy('sort_order')->get(),
            'cities' => City::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(['en', 'hi'])],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        $user = $request->user();
        $user->update([
            'language' => $data['language'],
            'city_id' => $data['city_id'] ?? null,
            'onboarded_at' => now(),
        ]);
        $user->categories()->sync($data['categories']);

        // Follow the matching city page so it shows up in For You and the story circles.
        if ($cityPage = $user->city?->page) {
            $user->followedPages()->syncWithoutDetaching([$cityPage->id]);
        }

        return redirect()->route('for-you')->withCookie(cookie()->forever('lang', $data['language']));
    }
}
