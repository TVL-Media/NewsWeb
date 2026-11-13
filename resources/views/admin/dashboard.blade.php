@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('content')
@php $me = auth('admin')->user(); $nr = $data['newsroom']; $fmtDays = fn ($l) => array_map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'), $l); @endphp
<x-admin.ph title="Dashboard" :sub="now(ptz())->format('l, j F').' · '.$label.' · all times '.tz_label()">
  @include('admin.partials.range')
</x-admin.ph>

<div class="kpis">
  <x-admin.kpi label="Stories published" :value="number_format($data['published_period'])" :href="$me->can('content.view') ? route('admin.stories.index', ['status' => 'published']) : null" :change="$data['published_change']" :note="$data['published_auto'].' auto-published · '.number_format($data['published_total']).' live in total'" />
  <x-admin.kpi label="Waiting for approval" :value="$live['pending_review']" :attn="$live['pending_review'] > 0" :href="$me->can('review.view') ? route('admin.review.index') : null" :note="$live['review_overdue'] ? $live['review_overdue'].' over '.setting('editorial.review_sla_hours', 2).' h' : 'none overdue'" />
  <x-admin.kpi label="Stories read" :value="num($data['reads'])" :change="$data['reads_change']" :href="$me->can('analytics.view') ? route('admin.analytics', ['tab' => 'content']) : null" />
  <x-admin.kpi label="Active readers" :value="num($data['active_users'])" :href="$me->can('users.view') ? route('admin.users.index', ['activity' => '7d']) : null" :change="$data['active_change']" :note="'+'.num($data['new_users']).' new'" />
  <x-admin.kpi label="Likes · comments · shares" :value="num($data['likes']).' · '.num($data['comments']).' · '.num($data['shares'])" />
  <x-admin.kpi label="Poll votes" :value="num($data['polls']['votes'])" :href="$me->can('polls.view') ? route('admin.polls.index') : null" :note="$live['polls_pending'] ? $live['polls_pending'].' reader polls to approve' : $data['polls']['live'].' live'" :attn="$live['polls_pending'] > 0" />
  <x-admin.kpi label="Active streaks" :value="num($data['streaks']['active'])" :href="$me->can('analytics.view') ? route('admin.streaks.index') : null" :note="'avg '.$data['streaks']['avg'].' days · '.num($data['quiz']['players']).' quiz plays'" />
  <x-admin.kpi label="Open reports" :value="$live['reports_open']" :attn="$live['reports_open'] > 0" :href="$me->can('reports.view') ? route('admin.reports.index') : null" :note="$live['reports_escalated'] ? $live['reports_escalated'].' escalated' : 'none escalated'" />
</div>

<div class="grid g-wide mt-4">
  <div class="card">
    <div class="card-h"><h2>Reads &amp; engagement</h2><div class="legend"><span><i style="background:#C8102E"></i>Reads</span><span><i style="background:#BDB7B0"></i>Likes, comments, shares</span></div></div>
    @php $cfg = ['type' => 'bar', 'aria' => 'Reads and engagement per day', 'labels' => $fmtDays($data['series']['labels']), 'datasets' => [['label' => 'Likes, comments, shares', 'data' => $data['series']['engagement'], 'color' => '#BDB7B0'], ['label' => 'Reads', 'data' => $data['series']['reads'], 'color' => '#C8102E']]]; @endphp
    <div class="card-b"><x-admin.chart :config="$cfg" /></div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Needs your attention</h2></div>
    <div class="card-b flush">
      <table class="t compact"><tbody>
        @can('review.view')<tr><td><a href="{{ route('admin.review.index') }}">Approval queue</a></td><td class="num"><span class="pill {{ $live['pending_review'] ? 'amber' : '' }}">{{ $live['pending_review'] }}</span></td></tr>@endcan
        @can('content.view')<tr><td><a href="{{ route('admin.schedule.index', ['view' => 'list', 'failed' => 1]) }}">Failed to publish</a></td><td class="num"><span class="pill {{ $live['failed_publish'] ? 'red' : '' }}">{{ $live['failed_publish'] }}</span></td></tr>@endcan
        @can('polls.view')<tr><td><a href="{{ route('admin.polls.index', ['tab' => 'pending']) }}">Reader polls to approve</a></td><td class="num"><span class="pill {{ $live['polls_pending'] ? 'amber' : '' }}">{{ $live['polls_pending'] }}</span></td></tr>@endcan
        @can('reports.view')<tr><td><a href="{{ route('admin.reports.index') }}">Open reports</a></td><td class="num"><span class="pill {{ $live['reports_open'] ? 'red' : '' }}">{{ $live['reports_open'] }}</span></td></tr>@endcan
        @if($me->can('translation.review'))<tr><td><a href="{{ route('admin.translations.index', ['status' => 'ai_completed']) }}">Translations to review</a></td><td class="num"><span class="pill {{ $live['translations_review'] ? 'blue' : '' }}">{{ $live['translations_review'] }}</span></td></tr>@endif
        @can('content.view')<tr><td><a href="{{ route('admin.stories.index', ['no_hindi' => 1, 'status' => 'published']) }}">Live stories (24 h) without Hindi</a></td><td class="num"><span class="pill">{{ $live['no_hindi'] }}</span></td></tr>@endcan
        @can('briefing.manage')<tr><td><a href="{{ route('admin.daily.briefings') }}">Today’s Morning 5</a></td><td class="num">{!! $live['briefing_today'] ? '<span class="pill green">ready</span>' : '<span class="pill amber">missing</span>' !!}</td></tr>@endcan
        @can('quiz.manage')<tr><td><a href="{{ route('admin.daily.quizzes') }}">Tomorrow’s quiz</a></td><td class="num">{!! $live['quiz_tomorrow'] ? '<span class="pill '.($live['quiz_tomorrow'] === 'draft' ? 'amber' : 'green').'">'.e($live['quiz_tomorrow']).'</span>' : '<span class="pill">not drafted</span>' !!}</td></tr>@endcan
        @can('feeds.manage')@if($live['feeds_stale'])<tr><td><a href="{{ route('admin.newsroom.feeds') }}">Feeds not fetched in 30 min</a></td><td class="num"><span class="pill red">{{ $live['feeds_stale'] }}</span></td></tr>@endif @endcan
      </tbody></table>
    </div>
  </div>
</div>

<div class="grid g-wide mt-4">
  <div class="card">
    <div class="card-h"><h2>Newsroom pipeline</h2><a class="small" href="{{ route('admin.newsroom.log') }}">Auto-publish log</a></div>
    <div class="card-b">
      <div class="kpis" style="grid-template-columns:repeat(4,1fr)">
        <div class="kpi"><div class="l">AI drafts</div><div class="v">{{ num($nr['drafts']) }}</div></div>
        <div class="kpi"><div class="l">Auto-published</div><div class="v">{{ num($nr['auto']) }}</div></div>
        <div class="kpi"><div class="l">Sent to an editor</div><div class="v">{{ num($nr['to_editor']) }}</div><div class="d"><span>{{ $nr['sensitive'] }} sensitive</span></div></div>
        <div class="kpi"><div class="l">Time to decision</div><div class="v">{{ $nr['median_minutes'] !== null ? ($nr['median_minutes'] >= 60 ? round($nr['median_minutes'] / 60, 1).' h' : $nr['median_minutes'].' min') : '—' }}</div><div class="d"><span>median</span></div></div>
      </div>
    </div>
  </div>
  @can('content.view')
  <div class="card">
    <div class="card-h"><h2>Upcoming publishing</h2><a class="small" href="{{ route('admin.schedule.index') }}">Calendar</a></div>
    <div class="card-b flush">
      @if($upcoming->isEmpty())<x-admin.empty title="Nothing scheduled in the next 7 days" />@else
      <table class="t compact"><tbody>
        @foreach($upcoming as $s)<tr><td class="nowrap mono small">{{ fmt_dt($s->scheduled_at) }}</td><td><a href="{{ route('admin.stories.edit', $s) }}" class="t1">{{ \Illuminate\Support\Str::limit($s->title(), 60) }}</a><div class="t2">{{ $s->category?->name_en }}</div></td></tr>@endforeach
      </tbody></table>@endif
    </div>
  </div>
  @endcan
</div>

<div class="grid g2 mt-4">
  <div class="card">
    <div class="card-h"><h2>Most read</h2>@can('analytics.view')<a class="small" href="{{ route('admin.analytics', ['tab' => 'content']) }}">View all</a>@endcan</div>
    <div class="card-b flush">@include('admin.partials.top-table', ['rows' => $data['top_read'], 'colLabel' => 'Reads'])</div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Most engaging</h2><span class="meta" title="reads + 2×likes + 3×comments + 3×shares">engagement score</span></div>
    <div class="card-b flush">@include('admin.partials.top-table', ['rows' => $data['top_engaged'], 'colLabel' => 'Score'])</div>
  </div>
</div>

<div class="grid g2 mt-4">
  <div class="card">
    <div class="card-h"><h2>Categories</h2><span class="meta">reads vs previous period</span></div>
    <div class="card-b flush"><table class="t compact"><tbody>
      @forelse($data['categories'] as $c)
        <tr><td><span class="swatch" style="background:{{ $c['color'] }}"></span> {{ $c['name'] }}</td><td class="num">{{ num($c['reads']) }}</td><td class="num">@if(!is_null($c['change']))<span class="{{ $c['change'] >= 0 ? 'up' : 'down' }}">{{ $c['change'] >= 0 ? '▲' : '▼' }} {{ abs($c['change']) }}%</span>@else<span class="muted">new</span>@endif</td></tr>
      @empty <tr><td class="muted">No reads in this period.</td></tr> @endforelse
    </tbody></table></div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Readers</h2></div>
    <div class="card-b"><dl class="kv">
      <dt>Total accounts</dt><dd class="strong">{{ number_format($data['total_users']) }}</dd>
      <dt>Active in period</dt><dd>{{ number_format($data['active_users']) }}</dd>
      <dt>New sign-ups</dt><dd>{{ number_format($data['new_users']) }}</dd>
      <dt>Active streaks</dt><dd>{{ number_format($data['streaks']['active']) }} · longest ever {{ $data['streaks']['longest'] }} days</dd>
      <dt>Daily quiz</dt><dd>{{ num($data['quiz']['players']) }} plays{{ $data['quiz']['avg_score'] !== null ? ' · avg score '.$data['quiz']['avg_score'] : '' }}</dd>
      <dt>Polls</dt><dd>{{ $data['polls']['live'] }} live · {{ num($data['polls']['votes']) }} votes · {{ $data['polls']['reader_polls'] }} created by readers</dd>
    </dl></div>
  </div>
</div>
<p class="xsmall muted mt-4">Figures refresh every 5 minutes (last {{ fmt_dt(\Illuminate\Support\Carbon::parse($data['computed_at'])) }}). Queue counts are live. A read is counted after 3 seconds on a card or 8 seconds on a story.</p>
@endsection
