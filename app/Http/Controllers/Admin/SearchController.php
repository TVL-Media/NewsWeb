<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Story;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Http\Request;

/** Command palette search (Ctrl/Cmd+K). Results respect permissions. */
class SearchController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $a = auth('admin')->user();
        $out = [];
        $pages = [
            ['Dashboard', 'admin.dashboard', 'dashboard.view'], ['Stories', 'admin.stories.index', 'content.view'], ['Write a story', 'admin.stories.create', 'content.create'],
            ['Approval queue', 'admin.review.index', 'review.view'], ['Schedule', 'admin.schedule.index', 'content.view'], ['Media library', 'admin.media.index', 'media.upload'],
            ['Feeds & sources', 'admin.newsroom.feeds', 'feeds.manage'], ['Auto-publish rules', 'admin.newsroom.autopublish', 'autopublish.manage'], ['Auto-publish log', 'admin.newsroom.log', 'review.view'],
            ['Morning 5', 'admin.daily.briefings', 'briefing.manage'], ['Daily quiz', 'admin.daily.quizzes', 'quiz.manage'],
            ['Categories', 'admin.categories.index', 'categories.view'], ['Pages', 'admin.pages.index', 'pages.manage'], ['Polls', 'admin.polls.index', 'polls.view'],
            ['Translations', 'admin.translations.index', 'translation.review'], ['Streaks', 'admin.streaks.index', 'analytics.view'],
            ['Readers', 'admin.users.index', 'users.view'], ['Comments', 'admin.comments.index', 'comments.moderate'], ['Reports & moderation', 'admin.reports.index', 'reports.view'],
            ['Analytics', 'admin.analytics', 'analytics.view'], ['Admin team', 'admin.team.index', 'admins.manage'], ['Roles & permissions', 'admin.roles.index', 'roles.manage'],
            ['Platform settings', 'admin.settings', 'settings.manage'], ['Audit log', 'admin.audit.index', 'audit.view'], ['My profile', 'admin.profile', null],
            ['Open the website', 'home', null],
        ];
        foreach ($pages as [$label, $route, $perm]) {
            if ((! $perm || $a->can($perm)) && ($q === '' || stripos($label, $q) !== false)) {
                $out[] = ['type' => 'Page', 'label' => $label, 'url' => route($route)];
            }
        }
        if (mb_strlen($q) >= 2) {
            if ($a->can('content.view')) {
                $stories = Story::with('versions:id,story_id,language,headline')
                    ->where(fn ($w) => $w->whereHas('versions', fn ($v) => $v->where('headline', 'like', "%{$q}%"))->orWhere('id', ctype_digit($q) ? (int) $q : 0))
                    ->latest('id')->limit(6)->get();
                foreach ($stories as $s) {
                    $out[] = ['type' => 'Story', 'label' => $s->title(), 'url' => route('admin.stories.edit', $s)];
                }
            }
            if ($a->can('polls.view')) {
                foreach (Poll::where('question', 'like', "%{$q}%")->limit(4)->get() as $p) {
                    $out[] = ['type' => 'Poll', 'label' => $p->question, 'url' => route('admin.polls.show', $p)];
                }
            }
            if ($a->can('users.view')) {
                foreach (User::where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('id', ctype_digit($q) ? (int) $q : 0)->limit(4)->get() as $u) {
                    $out[] = ['type' => 'Reader', 'label' => ($u->name ?: 'Reader').' · #'.$u->id, 'url' => route('admin.users.show', $u)];
                }
            }
            if ($a->can('categories.view')) {
                foreach (Category::where('name_en', 'like', "%{$q}%")->orWhere('name_hi', 'like', "%{$q}%")->limit(3)->get() as $c) {
                    $out[] = ['type' => 'Category', 'label' => $c->name_en, 'url' => route('admin.categories.show', $c)];
                }
            }
            if ($a->can('pages.manage')) {
                foreach (\App\Models\Page::where('name', 'like', "%{$q}%")->limit(3)->get() as $p) {
                    $out[] = ['type' => 'Page', 'label' => $p->name, 'url' => route('admin.pages.edit', $p)];
                }
            }
        }

        return response()->json(array_slice($out, 0, 20));
    }
}
