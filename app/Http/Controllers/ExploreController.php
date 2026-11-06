<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\Rails;
use Illuminate\Http\Request;

class ExploreController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Each tile opens the category's topic page when it has one, else Home filtered by category.
        $categories = Category::with(['pages' => fn ($q) => $q->where('type', 'topic')->orderBy('id')])
            ->orderBy('sort_order')->get();

        return view('explore', [
            'categories' => $categories,
            'trending' => Rails::trending(),
            'pages' => Rails::pagesToFollow($user, 8),
            'followed' => Rails::followedPageIds($user),
        ]);
    }
}
