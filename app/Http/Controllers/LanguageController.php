<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /preferences/language — the हिं/EN toggle. Saved on the user, or in a cookie for guests. */
class LanguageController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['language' => ['required', Rule::in(['en', 'hi'])]]);

        $request->user()?->update(['language' => $data['language']]);
        $cookie = cookie()->forever('lang', $data['language']);

        if ($request->expectsJson()) {
            return response()->json(['language' => $data['language']])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }
}
