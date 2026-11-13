<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Admin') · {{ setting('general.platform_name', 'NextGenNews Newsroom') }}</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' fill='%23D7141E'/%3E%3C/svg%3E">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=4">
@stack('head')
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
<script src="{{ asset('vendor/sortable.min.js') }}"></script>
<script src="{{ asset('vendor/chart.umd.js') }}"></script>
<script src="{{ asset('js/admin.js') }}?v=4"></script>
</head>
<body x-data="{ side:false }">
@php
  $me = auth('admin')->user();
  $nav = [
    'Overview' => [
      ['admin.dashboard', 'Dashboard', 'layout-dashboard', 'dashboard.view', 'admin.dashboard'],
    ],
    'Newsroom' => [
      ['admin.review.index', 'Approval queue', 'inbox', 'review.view', 'admin.review.*', $navCounts['review'] ?? 0],
      ['admin.stories.index', 'Stories', 'newspaper', 'content.view', 'admin.stories.index|admin.stories.edit|admin.stories.history'],
      ['admin.stories.create', 'Write a story', 'file-plus', 'content.create', 'admin.stories.create'],
      ['admin.schedule.index', 'Schedule', 'calendar-days', 'content.view', 'admin.schedule.*'],
      ['admin.newsroom.feeds', 'Feeds & auto-publish', 'rss', 'feeds.manage|autopublish.manage|review.view', 'admin.newsroom.*'],
      ['admin.translations.index', 'Translations', 'languages', 'translation.review|translation.request', 'admin.translations.*|admin.glossary.*', $navCounts['translations'] ?? 0],
      ['admin.media.index', 'Media library', 'image', 'media.upload', 'admin.media.*'],
    ],
    'Daily' => [
      ['admin.daily.briefings', 'Morning 5', 'sunrise', 'briefing.manage', 'admin.daily.briefing*'],
      ['admin.daily.quizzes', 'Daily quiz', 'circle-help', 'quiz.manage', 'admin.daily.quiz*'],
      ['admin.polls.index', 'Polls', 'vote', 'polls.view', 'admin.polls.*', $navCounts['polls'] ?? 0],
    ],
    'Site' => [
      ['admin.categories.index', 'Categories', 'folder-tree', 'categories.view', 'admin.categories.*'],
      ['admin.pages.index', 'Pages', 'map-pin', 'pages.manage', 'admin.pages.*'],
      ['admin.tags.index', 'Tags', 'tags', 'categories.manage', 'admin.tags.*'],
    ],
    'Readers' => [
      ['admin.users.index', 'Readers', 'users', 'users.view', 'admin.users.*'],
      ['admin.comments.index', 'Comments', 'message-square', 'comments.moderate', 'admin.comments.*'],
      ['admin.reports.index', 'Reports', 'flag', 'reports.view', 'admin.reports.*', $navCounts['reports'] ?? 0],
      ['admin.streaks.index', 'Streaks', 'flame', 'streaks.manage|analytics.view', 'admin.streaks.*'],
    ],
    'Insights' => [
      ['admin.analytics', 'Analytics', 'bar-chart-3', 'analytics.view', 'admin.analytics*'],
    ],
    'Administration' => [
      ['admin.team.index', 'Admin team', 'shield-check', 'admins.manage', 'admin.team.*'],
      ['admin.roles.index', 'Roles & permissions', 'key-round', 'roles.manage', 'admin.roles.*'],
      ['admin.settings', 'Platform settings', 'settings', 'settings.manage', 'admin.settings*'],
      ['admin.audit.index', 'Audit log', 'scroll-text', 'audit.view', 'admin.audit.*'],
    ],
  ];
  $canAny = fn ($perm) => collect(explode('|', $perm))->contains(fn ($p) => $me->can($p));
@endphp
<div class="app">
  <aside class="side" :class="side && 'open'">
    <div class="brand"><span class="brand-mark"></span><div>{{ setting('general.platform_name', 'Newsroom') }}<small>Admin</small></div></div>
    <nav class="nav" aria-label="Main">
      @foreach($nav as $group => $items)
        @php $visible = array_filter($items, fn ($i) => $canAny($i[3])); @endphp
        @if($visible)
          <div class="nav-g">{{ $group }}</div>
          @foreach($visible as $i)
            @php $on = collect(explode('|', $i[4]))->contains(fn ($p) => request()->routeIs($p)); @endphp
            <a href="{{ route($i[0]) }}" class="{{ $on ? 'on' : '' }}" @if($on) aria-current="page" @endif>{!! icon($i[2]) !!}<span>{{ $i[1] }}</span>@if(!empty($i[5]))<span class="count">{{ $i[5] }}</span>@endif</a>
          @endforeach
        @endif
      @endforeach
    </nav>
    <div class="side-foot">All times {{ tz_label() }}</div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="icon-btn menu-toggle" @click="side=!side" aria-label="Menu">{!! icon('menu', 18) !!}</button>
      <div class="top-search" x-data @click="$dispatch('cmdk')">
        {!! icon('search') !!}<input type="search" placeholder="Search stories, readers, polls…" readonly aria-label="Search"><kbd>Ctrl K</kbd>
      </div>
      <div class="top-spacer"></div>
      <a class="btn sm" href="{{ route('home') }}" target="_blank">{!! icon('external-link', 14) !!} Website</a>
      @can('content.create')<a class="btn primary sm" href="{{ route('admin.stories.create') }}">{!! icon('plus', 14) !!} Write a story</a>@endcan
      <div class="dd" x-data="{o:false}" @click.outside="o=false">
        <button class="icon-btn" @click="o=!o" aria-label="Notifications">{!! icon('bell', 18) !!}@if(($navCounts['unread'] ?? 0) > 0)<span class="dot"></span>@endif</button>
        <div class="dd-menu wide" x-show="o" x-cloak>
          <div class="row between" style="padding:6px 10px"><strong class="small">Notifications</strong>
            <form method="post" action="{{ route('admin.notifications.read') }}">@csrf<button class="link-btn small">Mark all read</button></form></div>
          <div class="sep"></div>
          @forelse($navNotifications ?? [] as $n)
            <a href="{{ route('admin.notifications.open', $n) }}" style="display:block;{{ $n->read_at ? '' : 'background:var(--red-50)' }}">
              <div class="strong small">{{ $n->title }}</div>
              @if($n->body)<div class="small muted trunc">{{ $n->body }}</div>@endif
              <div class="xsmall muted">{{ $n->created_at->diffForHumans() }}</div>
            </a>
          @empty
            <div class="empty small">You're all caught up.</div>
          @endforelse
          <div class="sep"></div><a href="{{ route('admin.notifications') }}" class="small">View all</a>
        </div>
      </div>
      <div class="dd" x-data="{o:false}" @click.outside="o=false">
        <button class="row" style="background:none;border:0;cursor:pointer;gap:8px" @click="o=!o">
          <span class="avatar">@if($me->avatar_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($me->avatar_path) }}" alt="">@else{{ $me->initials() }}@endif</span>
          <span class="small who" style="text-align:left;line-height:1.2"><span class="strong">{{ $me->name }}</span><br><span class="muted xsmall">{{ $me->roleNames() }}</span></span>
          {!! icon('chevron-down', 14) !!}
        </button>
        <div class="dd-menu" x-show="o" x-cloak>
          <a href="{{ route('admin.profile') }}">{!! icon('user') !!} My profile & security</a>
          <div class="sep"></div>
          <form method="post" action="{{ route('admin.logout') }}">@csrf<button>{!! icon('log-out') !!} Sign out</button></form>
        </div>
      </div>
    </header>
    @if(session('warning'))<div class="notice-strip">{{ session('warning') }}</div>@endif
    <main class="content" id="main">
      @if($errors->any() && !View::hasSection('suppress_errors'))
        <div class="alert red"><span class="t">Please fix the following</span><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      @endif
      @yield('content')
    </main>
  </div>
</div>

{{-- Command palette (Ctrl/Cmd+K) --}}
<div x-data="cmdk()" @keydown.window.ctrl.k.prevent="toggle()" @keydown.window.meta.k.prevent="toggle()" @cmdk.window="toggle()">
  <div class="overlay" x-show="open" x-cloak @click.self="open=false" @keydown.escape="open=false">
    <div class="modal cmdk">
      <input x-ref="q" x-model="q" @input.debounce.200ms="search()" @keydown.down.prevent="i=Math.min(i+1,items.length-1)" @keydown.up.prevent="i=Math.max(i-1,0)" @keydown.enter.prevent="go()" placeholder="Search or jump to…" aria-label="Command palette">
      <div class="res" style="max-height:380px;overflow:auto;padding:6px 0">
        <template x-for="(it,idx) in items" :key="it.url">
          <a :href="it.url" :class="idx===i && 'on'"><span class="pill" x-text="it.type"></span><span x-text="it.label" class="trunc"></span></a>
        </template>
        <div class="empty small" x-show="!items.length">Type to search stories, readers, polls, categories or pages.</div>
      </div>
    </div>
  </div>
</div>

@if(session('ok'))<span data-flash="{{ session('ok') }}" data-type="ok"></span>@endif
@if(session('error'))<span data-flash="{{ session('error') }}" data-type="err"></span>@endif
@stack('scripts')
</body>
</html>
