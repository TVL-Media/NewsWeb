@extends('admin.layouts.app')
@section('title', 'Analytics')
@section('content')
@php $fmtDays = fn ($labels) => array_map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'), $labels); @endphp
<x-admin.ph title="Analytics" :sub="$label.' · '.tz_label().($cmp ? ' · compared with '.(request('compare') === 'last_year' ? 'the same period last year' : 'the previous period') : '')">
  @can('analytics.export')<form method="post" action="{{ route('admin.analytics.export', $tab) }}">@csrf @foreach(request()->only(['range','from','to','category']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<button class="btn">{!! icon('download', 14) !!} Export CSV</button></form>@endcan
</x-admin.ph>
<nav class="tabs">@foreach($tabs as $k => $t)<a href="{{ route('admin.analytics', array_merge(request()->except('page'), ['tab' => $k])) }}" class="{{ $tab===$k ? 'on' : '' }}">{{ $t[0] }}</a>@endforeach</nav>
<div class="card mb-4"><form class="filters" method="get" style="border:0">
  <select name="range" style="width:auto">@foreach(['today'=>'Today','7d'=>'Last 7 days','30d'=>'Last 30 days','month'=>'This month','90d'=>'Last 90 days','custom'=>'Custom'] as $k=>$l)<option value="{{ $k }}" @selected($preset===$k)>{{ $l }}</option>@endforeach</select>
  <input type="date" name="from" value="{{ $from }}" style="width:auto" title="From (custom)"><input type="date" name="to" value="{{ $to }}" style="width:auto" title="To (custom)">
  @if($tab === 'content')<select name="category" style="width:auto"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category')==$c->id)>{{ $c->name_en }}</option>@endforeach</select>@endif
  @if(in_array($tab, ['content', 'readers', 'newsroom', 'moderation']))<select name="compare" style="width:auto"><option value="">No comparison</option><option value="previous" @selected(request('compare')==='previous')>Compare: previous period</option><option value="last_year" @selected(request('compare')==='last_year')>Compare: same period last year</option></select>@endif
  <button class="btn sm primary">Apply</button>
</form></div>

@switch($tab)
@case('content')
  <div class="kpis mb-4">@include('admin.analytics._kpi', ['args' => ['Stories published', $d['published'], $d['published_cmp']]])@include('admin.analytics._kpi', ['args' => ['Reads', $d['reads'], $d['reads_cmp'], '3 s on a card or 8 s on a story']])@include('admin.analytics._kpi', ['args' => ['Likes', $d['likes'], $d['likes_cmp']]])@include('admin.analytics._kpi', ['args' => ['Shares', $d['shares'], $d['shares_cmp']]])</div>
  <div class="grid g-wide">
    <div class="card"><div class="card-h"><h2>Reads &amp; engagement over time</h2></div><div class="card-b">
      @php $ds = [['label' => 'Likes, comments, shares', 'data' => $d['series']['engagement'], 'color' => '#BDB7B0', 'type' => 'bar'], ['label' => 'Reads', 'data' => $d['series']['reads'], 'color' => '#C8102E', 'type' => 'bar']]; if ($d['series_cmp']) $ds[] = ['label' => 'Reads (comparison)', 'data' => array_slice($d['series_cmp']['reads'], 0, count($d['series']['reads'])), 'color' => '#245BA8', 'type' => 'line', 'borderDash' => [4,4]]; @endphp
      <x-admin.chart :config="['type' => 'bar', 'legend' => true, 'labels' => $fmtDays($d['series']['labels']), 'datasets' => $ds]" /></div></div>
    <div class="card"><div class="card-h"><h2>Shares by channel</h2></div><div class="card-b">@if($d['channels'])<x-admin.chart :config="['type' => 'doughnut', 'legend' => true, 'labels' => array_map('ucfirst', array_keys($d['channels'])), 'datasets' => [['label' => 'Shares', 'data' => array_values($d['channels'])]]]" />@else<x-admin.empty title="No shares yet" />@endif</div></div>
  </div>
  <div class="card mt-4"><div class="card-h"><h2>Publishing</h2><div class="legend"><span><i style="background:#C8102E"></i>By an editor</span><span><i style="background:#245BA8"></i>Auto-published</span></div></div><div class="card-b">
    <x-admin.chart size="sm" :config="['type' => 'bar', 'stacked' => true, 'labels' => $fmtDays($d['publishing']['labels']), 'datasets' => [['label' => 'By an editor', 'data' => $d['publishing']['manual'], 'color' => '#C8102E'], ['label' => 'Auto-published', 'data' => $d['publishing']['auto'], 'color' => '#245BA8']]]" /></div></div>
  <div class="grid g2 mt-4">
    <div class="card"><div class="card-h"><h2>By category</h2></div><div class="card-b flush"><table class="t compact"><thead><tr><th>Category</th><th class="num">Published</th><th class="num">Reads</th><th class="num">vs prev.</th><th class="num">Likes</th><th class="num">Comments</th><th class="num">Shares</th></tr></thead><tbody>
      @forelse($d['by_category'] as $r)<tr><td><span class="swatch" style="background:{{ $r['color'] }}"></span> {{ $r['name'] }}</td><td class="num">{{ $r['published'] }}</td><td class="num">{{ num($r['reads']) }}</td><td class="num">@if(!is_null($r['change']))<span class="{{ $r['change'] >= 0 ? 'up' : 'down' }}">{{ $r['change'] >= 0 ? '▲' : '▼' }}{{ abs($r['change']) }}%</span>@else<span class="muted">—</span>@endif</td><td class="num">{{ num($r['likes']) }}</td><td class="num">{{ num($r['comments']) }}</td><td class="num">{{ num($r['shares']) }}</td></tr>@empty<tr><td colspan="7" class="muted">No data.</td></tr>@endforelse</tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Reactions</h2></div><div class="card-b">@if($d['reactions'])<x-admin.chart size="sm" :config="['type' => 'bar', 'horizontal' => true, 'labels' => array_map(fn ($k) => ucfirst(str_replace('_', ' ', $k)), array_keys($d['reactions'])), 'datasets' => [['label' => 'Reactions', 'data' => array_values($d['reactions'])]]]" />@else<x-admin.empty title="No reactions yet" />@endif</div></div>
  </div>
  <div class="grid g2 mt-4">
    <div class="card"><div class="card-h"><h2>Most read</h2></div><div class="card-b flush">@include('admin.partials.top-table', ['rows' => $d['top'], 'colLabel' => 'Reads'])</div></div>
    <div class="card"><div class="card-h"><h2>Most engaging</h2><span class="meta">reads + 2×likes + 3×comments + 3×shares</span></div><div class="card-b flush">@include('admin.partials.top-table', ['rows' => $d['engaged'], 'colLabel' => 'Score'])</div></div>
  </div>
  @break

@case('newsroom')
  @php $n = $d['n']; $nc = $d['n_cmp']; @endphp
  <div class="kpis mb-4">
    @include('admin.analytics._kpi', ['args' => ['AI drafts', $n['drafts'], $nc['drafts'] ?? null]])
    @include('admin.analytics._kpi', ['args' => ['Auto-published', $n['auto'], $nc['auto'] ?? null, $n['drafts'] ? round($n['auto'] / $n['drafts'] * 100).'% of drafts' : null]])
    @include('admin.analytics._kpi', ['args' => ['Sent to an editor', $n['to_editor'], $nc['to_editor'] ?? null, $n['sensitive'].' sensitive']])
    @include('admin.analytics._kpi', ['args' => ['Median time to decision', $n['median_minutes'] !== null ? ($n['median_minutes'] >= 60 ? round($n['median_minutes'] / 60, 1).' h' : $n['median_minutes'].' min') : '—', null, $n['approved'].' approved · '.$n['rejected'].' rejected']])
  </div>
  <div class="card"><div class="card-h"><h2>Routing decisions per day</h2><div class="legend"><span><i style="background:#245BA8"></i>Auto-published</span><span><i style="background:#A86400"></i>To an editor</span></div></div><div class="card-b">
    <x-admin.chart :config="['type' => 'bar', 'stacked' => true, 'labels' => $fmtDays($n['series']['labels']), 'datasets' => [['label' => 'Auto-published', 'data' => $n['series']['auto'], 'color' => '#245BA8'], ['label' => 'To an editor', 'data' => $n['series']['to_editor'], 'color' => '#A86400']]]" /></div></div>
  @break

@case('readers')
  <div class="kpis mb-4">@include('admin.analytics._kpi', ['args' => ['Active readers', $d['active'], $d['active_cmp'], 'read, liked, commented, voted or played']])@include('admin.analytics._kpi', ['args' => ['New sign-ups', $d['new'], $d['new_cmp']]])@include('admin.analytics._kpi', ['args' => ['Returning', $d['returning'], null, 'joined before the period']])@include('admin.analytics._kpi', ['args' => ['All accounts', $d['total']]])</div>
  <div class="card"><div class="card-h"><h2>Active readers and sign-ups per day</h2></div><div class="card-b">
    <x-admin.chart :config="['type' => 'line', 'legend' => true, 'labels' => $fmtDays($d['series']['labels']), 'datasets' => [['label' => 'Active readers', 'data' => $d['series']['active'], 'fill' => 'rgba(200,16,46,.08)'], ['label' => 'New sign-ups', 'data' => $d['series']['new'], 'color' => '#245BA8']]]" /></div></div>
  <div class="grid g2 mt-4">
    <div class="card"><div class="card-h"><h2>Reading language</h2></div><div class="card-b"><x-admin.chart size="sm" :config="['type' => 'doughnut', 'legend' => true, 'labels' => array_map(fn ($k) => $k === 'hi' ? 'Hindi' : 'English', array_keys($d['languages'])), 'datasets' => [['label' => 'Readers', 'data' => array_values($d['languages'])]]]" /></div></div>
    <div class="card"><div class="card-h"><h2>Top cities</h2></div><div class="card-b flush"><table class="t compact"><tbody>@forelse($d['cities'] as $city => $n)<tr><td>{{ $city }}</td><td class="num">{{ num($n) }}</td></tr>@empty<tr><td class="muted">No cities chosen yet.</td></tr>@endforelse</tbody></table></div></div>
  </div>
  @break

@case('habits')
  <div class="kpis mb-4">
    @include('admin.analytics._kpi', ['args' => ['Poll votes', $d['polls']['votes'], null, $d['polls']['voters'].' readers']])
    @include('admin.analytics._kpi', ['args' => ['Quiz plays', $d['quiz']['players'], null, $d['quiz']['avg_score'] !== null ? 'avg score '.$d['quiz']['avg_score'] : null]])
    @include('admin.analytics._kpi', ['args' => ['Active streaks', $d['streaks']['active'], null, 'avg '.$d['streaks']['avg'].' days']])
    @include('admin.analytics._kpi', ['args' => ['Freezes used this month', $d['streaks']['freezes_used']]])
  </div>
  <div class="grid g-wide">
    <div class="card"><div class="card-h"><h2>Readers whose day counted for their streak</h2></div><div class="card-b"><x-admin.chart :config="['type' => 'bar', 'labels' => $fmtDays($d['streak_days']['labels']), 'datasets' => [['label' => 'Readers', 'data' => $d['streak_days']['values']]]]" /></div></div>
    <div class="card"><div class="card-h"><h2>Current streak lengths</h2></div><div class="card-b"><x-admin.chart :config="['type' => 'bar', 'labels' => array_keys($d['dist']), 'datasets' => [['label' => 'Readers', 'data' => array_values($d['dist'])]]]" /></div></div>
  </div>
  <div class="grid g2 mt-4">
    <div class="card"><div class="card-h"><h2>Poll votes per day</h2></div><div class="card-b"><x-admin.chart size="sm" :config="['type' => 'line', 'labels' => $fmtDays($d['polls']['series']['labels']), 'datasets' => [['label' => 'Votes', 'data' => $d['polls']['series']['values'], 'fill' => 'rgba(200,16,46,.08)']]]" /></div></div>
    <div class="card"><div class="card-h"><h2>Most voted polls</h2></div><div class="card-b flush"><table class="t compact"><tbody>@forelse($d['top_polls'] as $p)<tr><td><a href="{{ route('admin.polls.show', $p['id']) }}">{{ \Illuminate\Support\Str::limit($p['question'], 70) }}</a></td><td class="num">{{ num($p['votes']) }}</td></tr>@empty<tr><td class="muted">No votes in this period.</td></tr>@endforelse</tbody></table></div></div>
  </div>
  <div class="card mt-4"><div class="card-h"><h2>Quiz plays per day</h2></div><div class="card-b"><x-admin.chart size="sm" :config="['type' => 'bar', 'labels' => $fmtDays($d['quiz']['series']['labels']), 'datasets' => [['label' => 'Plays', 'data' => $d['quiz']['series']['values']]]]" /></div></div>
  @break

@case('moderation')
  @php $m = $d['m']; $mc = $d['m_cmp']; @endphp
  <div class="kpis mb-4">
    @include('admin.analytics._kpi', ['args' => ['Reports received', $m['reports'], $mc['reports'] ?? null]])
    @include('admin.analytics._kpi', ['args' => ['Taken down', $m['actioned'], $mc['actioned'] ?? null, $m['dismissed'].' dismissed']])
    @include('admin.analytics._kpi', ['args' => ['Open now', $m['open']]])
    @include('admin.analytics._kpi', ['args' => ['Median time to decision', $m['median_hours'] !== null ? $m['median_hours'].' h' : '—']])
  </div>
  <div class="grid g-wide">
    <div class="card"><div class="card-h"><h2>Reports per day</h2></div><div class="card-b"><x-admin.chart :config="['type' => 'bar', 'labels' => $fmtDays($m['series']['labels']), 'datasets' => [['label' => 'Reports', 'data' => $m['series']['values']]]]" /></div></div>
    <div class="card"><div class="card-h"><h2>By reason</h2></div><div class="card-b">@if($m['by_reason'])<x-admin.chart :config="['type' => 'doughnut', 'legend' => true, 'labels' => array_map('ucfirst', array_keys($m['by_reason'])), 'datasets' => [['label' => 'Reports', 'data' => array_values($m['by_reason'])]]]" />@else<x-admin.empty title="No reports" />@endif
      <div class="small muted mt-2">@foreach($m['by_type'] as $t => $n){{ ucfirst($t) }}: {{ $n }} @endforeach · {{ $m['comments_hidden'] }} comments hidden</div></div></div>
  </div>
  @break
@endswitch
@endsection
