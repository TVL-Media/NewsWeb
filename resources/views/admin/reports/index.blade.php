@extends('admin.layouts.app')
@section('title', 'Reports & moderation')
@section('content')
<x-admin.ph title="Reports & moderation" sub="Reports about the same item are grouped. A decision applies to every open report on that item."><a class="btn" href="{{ route('admin.reports.log') }}">{!! icon('scroll-text', 14) !!} Decision log</a></x-admin.ph>
<nav class="tabs">
  @foreach(['open' => 'Open', 'escalated' => 'Escalated', 'actioned' => 'Taken down', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $l)
    <a href="{{ route('admin.reports.index', array_merge(request()->except(['status','page']), ['status' => $k])) }}" class="{{ $status===$k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $k==='all' ? $counts->sum() : ($k === 'open' ? ($counts['open'] ?? 0) + ($counts['escalated'] ?? 0) : ($counts[$k] ?? 0)) }}</span></a>
  @endforeach
</nav>
<div class="card">
  <form class="filters" method="get"><input type="hidden" name="status" value="{{ $status }}">
    <select name="type" style="width:auto" onchange="this.form.submit()"><option value="">All types</option>@foreach(['story' => 'Stories', 'comment' => 'Comments', 'poll' => 'Polls', 'reel' => 'Reels'] as $k=>$l)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $l }}</option>@endforeach</select>
    <select name="reason" style="width:auto" onchange="this.form.submit()"><option value="">All reasons</option>@foreach(\App\Http\Controllers\Admin\ReportController::REASONS as $k=>$l)<option value="{{ $k }}" @selected(request('reason')===$k)>{{ $l }}</option>@endforeach</select>
  </form>
  <table class="t"><thead><tr><th>Reported item</th><th>Type</th><th>Reason</th><th class="num">Reports</th><th>Latest</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($groups as $g)
    @php $r = $reports[$g->id]; @endphp
    <tr><td><a class="t1" href="{{ route('admin.reports.show', $r) }}">{{ \Illuminate\Support\Str::limit($r->targetLabel(), 70) }}</a></td>
      <td class="small">{{ ucfirst($r->target_type) }}</td>
      <td class="small">{{ \App\Http\Controllers\Admin\ReportController::REASONS[$r->reason] ?? $r->reason }}</td>
      <td class="num">@if($g->dupes > 1)<span class="count-badge">{{ $g->dupes }}</span>@else 1 @endif</td>
      <td class="small muted nowrap">{{ \Illuminate\Support\Carbon::parse($g->last_at)->diffForHumans() }}</td>
      <td><x-admin.pill :tone="['open' => 'amber', 'escalated' => 'red', 'actioned' => 'green', 'dismissed' => 'neutral'][$r->status] ?? 'neutral'">{{ ['open' => 'Open', 'escalated' => 'Escalated', 'actioned' => 'Taken down', 'dismissed' => 'Dismissed'][$r->status] ?? $r->status }}</x-admin.pill></td>
      <td class="right"><a class="btn xs primary" href="{{ route('admin.reports.show', $r) }}">Review</a></td></tr>
  @empty <tr><td colspan="7"><x-admin.empty title="No reports here">Reports from readers on the website appear here.</x-admin.empty></td></tr> @endforelse
  </tbody></table>{{ $groups->links() }}
</div>
@endsection
