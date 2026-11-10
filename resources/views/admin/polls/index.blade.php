@extends('admin.layouts.app')
@section('title', 'Polls')
@section('content')
@php $label = ['pending' => ['Waiting for approval', 'amber'], 'live' => ['Live', 'green'], 'closed' => ['Closed', 'neutral'], 'rejected' => ['Rejected', 'red']]; @endphp
<x-admin.ph title="Polls" sub="Editorial polls and polls readers create on the website. Reader polls wait here until approved.">
  @can('polls.manage')<a href="{{ route('admin.polls.create') }}" class="btn primary">{!! icon('plus', 14) !!} New poll</a>@endcan
</x-admin.ph>
<nav class="tabs">
  <a href="{{ route('admin.polls.index', ['tab' => 'pending']) }}" class="{{ $tab === 'pending' ? 'on' : '' }}">Waiting for approval <span class="n">{{ $counts['pending'] ?? 0 }}</span></a>
  <a href="{{ route('admin.polls.index', ['tab' => 'all']) }}" class="{{ $tab === 'all' && ! request('status') ? 'on' : '' }}">All <span class="n">{{ $counts->sum() }}</span></a>
  @foreach(['live', 'closed', 'rejected'] as $s)<a href="{{ route('admin.polls.index', ['tab' => 'all', 'status' => $s]) }}" class="{{ request('status') === $s ? 'on' : '' }}">{{ $label[$s][0] }} <span class="n">{{ $counts[$s] ?? 0 }}</span></a>@endforeach
</nav>
<div class="card">
  <form class="filters" method="get"><input type="hidden" name="tab" value="{{ $tab }}">@if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search questions"></div>
    <select name="source" style="width:auto" onchange="this.form.submit()"><option value="">Editorial &amp; readers</option><option value="editorial" @selected(request('source')==='editorial')>Editorial</option><option value="readers" @selected(request('source')==='readers')>Created by readers</option></select>
  </form>
  <div class="table-wrap"><table class="t"><thead><tr><th>Question</th><th>By</th><th>Story</th><th class="num">Votes</th><th>Ends</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($polls as $p)
    <tr>
      <td><a class="t1" href="{{ route('admin.polls.show', $p) }}">{{ $p->question }}</a>
        <div class="t2">{{ $p->options->pluck('label')->join(' · ') }}</div>
        @if($lowIds->contains($p->id))<div class="t2 amber">Low participation (median {{ $median }})</div>@endif
        @if($reported[$p->id] ?? 0)<div class="t2 red">{{ $reported[$p->id] }} open report(s)</div>@endif</td>
      <td class="small nowrap">@if($p->created_by)<span class="pill">Reader</span> {{ $p->creator?->name }}@else{{ $p->admin?->name ?? 'Newsroom' }}@endif</td>
      <td class="small" style="max-width:220px">{{ $p->story ? \Illuminate\Support\Str::limit($p->story->title(), 60) : '—' }}</td>
      <td class="num">{{ num($p->totalVotes()) }}</td>
      <td class="small nowrap muted">{{ $p->ends_at ? fmt_dt($p->ends_at) : '—' }}</td>
      <td><x-admin.pill :tone="$label[$p->status][1] ?? 'neutral'">{{ $label[$p->status][0] ?? $p->status }}</x-admin.pill></td>
      <td class="right nowrap">
        @can('polls.manage')
          @if($p->status === 'pending')
            <form method="post" action="{{ route('admin.polls.approve', $p) }}" style="display:inline">@csrf<button class="btn xs primary">Approve</button></form>
            <form method="post" action="{{ route('admin.polls.reject', $p) }}" style="display:inline" data-confirm="Reject this poll?" data-confirm-reason="optional">@csrf<button class="btn xs">Reject</button></form>
          @endif
        @endcan
        <a class="btn xs" href="{{ route('admin.polls.show', $p) }}">Results</a>
      </td>
    </tr>
  @empty <tr><td colspan="7"><x-admin.empty :title="$tab === 'pending' ? 'No polls waiting' : 'No polls'">Reader polls appear here for approval.</x-admin.empty></td></tr>@endforelse
  </tbody></table></div>
  {{ $polls->links() }}
</div>
@endsection
