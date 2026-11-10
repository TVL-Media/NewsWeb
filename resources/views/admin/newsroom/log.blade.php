@extends('admin.layouts.app')
@section('title', 'Auto-publish log')
@section('content')
<x-admin.ph title="Newsroom" sub="Every AI draft, the checks it went through, and whether it went live or to an editor." />
@include('admin.newsroom._tabs')
<div class="card">
  <form class="filters" method="get">
    <select name="decision" style="width:auto" onchange="this.form.submit()"><option value="">All decisions</option><option value="auto" @selected(request('decision')==='auto')>Auto-published</option><option value="approval" @selected(request('decision')==='approval')>Sent to an editor</option></select>
    <select name="risk" style="width:auto" onchange="this.form.submit()"><option value="">Any risk</option><option value="sensitive" @selected(request('risk')==='sensitive')>Sensitive only</option></select>
  </form>
  <div class="table-wrap"><table class="t">
    <thead><tr><th>Story</th><th>Decision</th><th>Risk</th><th class="num">Confidence</th><th class="num">Copy</th><th>Why</th><th>When</th><th></th></tr></thead>
    <tbody>
    @forelse($checks as $c)
      @php $st = $c->story; @endphp
      <tr>
        <td>@if($st)<a class="t1" href="{{ route('admin.stories.edit', $st) }}">{{ \Illuminate\Support\Str::limit($st->title(), 80) }}</a><div class="t2">{{ $st->category?->name_en }} · <x-admin.status :content="$st" /></div>@else<span class="muted">Deleted story</span>@endif</td>
        <td><x-admin.pill :tone="$c->decision === 'auto' ? 'green' : 'amber'">{{ $c->decision === 'auto' ? 'Auto-published' : 'Editor' }}</x-admin.pill></td>
        <td><x-admin.pill :tone="$c->risk_level === 'sensitive' ? 'red' : 'neutral'">{{ ucfirst($c->risk_level) }}</x-admin.pill></td>
        <td class="num">{{ number_format($c->confidence, 2) }}</td>
        <td class="num">{{ number_format($c->copy_score, 2) }}</td>
        <td class="small" style="max-width:320px">{{ implode(' · ', $c->reasons ?? []) ?: 'All checks passed' }}</td>
        <td class="small muted nowrap">{{ fmt_dt($c->created_at) }}</td>
        <td class="right nowrap">@if($st && $st->isLive())
          <a class="btn xs" href="{{ route('story.show', $st) }}" target="_blank">Open</a>
          @can('content.publish')<form method="post" action="{{ route('admin.newsroom.log.unpublish', $c) }}" style="display:inline" data-confirm="Unpublish this story?" data-confirm-detail="It disappears from the website straight away.">@csrf<button class="btn xs danger">Unpublish</button></form>@endcan
        @endif</td>
      </tr>
    @empty
      <tr><td colspan="8"><x-admin.empty title="No AI drafts yet">They appear here after the first feed fetch.</x-admin.empty></td></tr>
    @endforelse
    </tbody>
  </table></div>
  {{ $checks->links() }}
</div>
@endsection
