@extends('admin.layouts.app')
@section('title', 'Approval queue')
@section('content')
<x-admin.ph title="Approval queue" :sub="'AI drafts that need an editor, and stories submitted by writers. Waiting longer than '.$sla.' h is highlighted.'" />
<nav class="tabs">
  @foreach(['all' => 'All waiting', 'ai' => 'AI drafts', 'newsroom' => 'From writers', 'sensitive' => 'Sensitive', 'unassigned' => 'Unassigned', 'mine' => 'Assigned to me'] as $k => $l)
    <a href="{{ route('admin.review.index', ['tab' => $k]) }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $counts[$k] }}</span></a>
  @endforeach
</nav>
@if($changes)<div class="alert">{{ $changes }} {{ \Illuminate\Support\Str::plural('story', $changes) }} waiting for the writer to make changes. <a href="{{ route('admin.stories.index', ['status' => 'changes_requested']) }}">See them</a>.</div>@endif
<div class="card">
  <div class="table-wrap">
  <table class="t"><thead><tr><th>Story</th><th>From</th><th>Category</th><th>Risk</th><th class="num">Confidence</th><th>Why it is here</th><th>Waiting</th><th>Reviewer</th><th></th></tr></thead><tbody>
  @forelse($items as $s)
    @php $since = $s->submitted_at ?? $s->updated_at; $over = $since->lt(now()->subHours($sla)); $ck = $s->aiCheck; @endphp
    <tr @if($over) style="background:#FFF9F9" @endif>
      <td><div class="title-cell"><span class="thumb" @if($s->photo_path) style="background-image:url('{{ $s->photoUrl() }}')" @endif>@unless($s->photo_path){!! icon('newspaper') !!}@endunless</span>
        <div><a class="t1" href="{{ route('admin.review.show', $s) }}">{{ \Illuminate\Support\Str::limit($s->title(), 80) }}</a><div class="t2">#{{ $s->id }} @if($s->is_breaking)· <strong class="red">breaking</strong>@endif</div></div></div></td>
      <td class="small nowrap">{{ $s->author?->name ?? 'AI draft' }}</td>
      <td class="small">{{ $s->category?->name_en ?? '—' }}</td>
      <td>@if($ck)<x-admin.pill :tone="$ck->risk_level === 'sensitive' ? 'red' : 'green'">{{ ucfirst($ck->risk_level) }}</x-admin.pill>@else<span class="muted small">—</span>@endif</td>
      <td class="num">{{ $ck ? number_format($ck->confidence, 2) : '—' }}</td>
      <td class="small" style="max-width:280px">{{ \Illuminate\Support\Str::limit(implode(' · ', $ck?->reasons ?? []), 140) ?: ($s->author_id ? 'Submitted for review' : '') }}</td>
      <td class="small nowrap {{ $over ? 'red strong' : 'muted' }}">{{ $since->diffForHumans(null, true) }}</td>
      <td class="small">{{ $s->reviewer?->name ?? 'Unassigned' }}</td>
      <td class="right nowrap">@if(! $s->assigned_reviewer_id)<form method="post" action="{{ route('admin.review.take', $s) }}" style="display:inline">@csrf<button class="btn xs">Take</button></form>@endif <a href="{{ route('admin.review.show', $s) }}" class="btn xs primary">Review</a></td>
    </tr>
  @empty <tr><td colspan="9"><x-admin.empty title="Nothing waiting">Every new draft passed the checks or has been reviewed.</x-admin.empty></td></tr> @endforelse
  </tbody></table>
  </div>
  {{ $items->links() }}
</div>
@endsection
