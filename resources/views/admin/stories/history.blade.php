@extends('admin.layouts.app')
@section('title', 'History')
@section('content')
<x-admin.ph :title="'History: '.\Illuminate\Support\Str::limit($s->title(), 60)" sub="Saved versions, editor decisions, scheduled publishing and audit records for this story." :crumbs="['Stories' => route('admin.stories.index'), \Illuminate\Support\Str::limit($s->title(), 30) => route('admin.stories.edit', $s)]" />
<div class="grid g2">
  <div class="card">
    <div class="card-h"><h2>Versions</h2><span class="meta">current: v{{ $s->version_no }}</span></div>
    <div class="card-b flush">
      @if($s->snapshots->isEmpty())<x-admin.empty title="No saved versions yet">A version is saved when the story is submitted, published, or edited while live.</x-admin.empty>@else
      <table class="t compact"><thead><tr><th>Version</th><th>Note</th><th>By</th><th>When</th><th></th></tr></thead><tbody>
      @foreach($s->snapshots as $v)
        <tr x-data="{o:false}"><td class="mono">v{{ $v->version_no }}</td><td class="small">{{ $v->change_note }} <button class="link-btn xsmall" @click="o=!o" x-text="o ? 'hide' : 'show text'"></button>
            <div x-show="o" x-cloak class="small mt-1"><strong>{{ $v->snapshot['versions']['en']['headline'] ?? '' }}</strong><br>{{ $v->snapshot['versions']['en']['card_text'] ?? '' }}@if(!empty($v->snapshot['versions']['hi']['headline']))<br><span lang="hi" class="muted">{{ $v->snapshot['versions']['hi']['headline'] }}</span>@endif</div></td>
          <td class="small">{{ $v->author?->name ?? 'System' }}</td><td class="small nowrap muted">{{ fmt_dt($v->created_at) }}</td>
          <td class="right nowrap"><form method="post" action="{{ route('admin.stories.version.restore', [$s, $v]) }}" style="display:inline" data-confirm="Restore version {{ $v->version_no }}?" data-confirm-detail="Its text and details replace the current ones{{ $s->isLive() ? ' on the live story' : '' }}. The current text is saved as a version first, so nothing is lost.">@csrf<button class="btn xs">Restore</button></form></td></tr>
      @endforeach
      </tbody></table>@endif
    </div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Editor decisions</h2></div>
    <div class="card-b">@if($s->approvals->isEmpty())<p class="muted">No decisions yet.</p>@else<ul class="tl">
      @foreach($s->approvals->sortByDesc('id') as $a)
        <li class="{{ in_array($a->action, ['rejected','changes_requested']) ? 'r' : '' }}"><div class="what">{{ ucfirst(str_replace('_', ' ', $a->action)) }}</div>
          <div class="when">{{ $a->editor?->name ?? 'System' }} · {{ fmt_dt($a->acted_at) }}</div>@if($a->reason)<div class="note">{{ $a->reason }}</div>@endif</li>
      @endforeach</ul>@endif</div>
  </div>
  <div class="card">
    <div class="card-h"><h2>AI checks</h2></div>
    <div class="card-b">@if(! $s->aiCheck)<p class="muted">Written by the newsroom: no automatic checks.</p>@else
      <p class="small">Risk <strong>{{ $s->aiCheck->risk_level }}</strong> · confidence {{ number_format($s->aiCheck->confidence, 2) }} · copy {{ number_format($s->aiCheck->copy_score, 2) }} · decision <strong>{{ $s->aiCheck->decision === 'auto' ? 'auto-publish' : 'editor approval' }}</strong> · {{ fmt_dt($s->aiCheck->created_at) }}</p>
      @if($s->aiCheck->reasons)<ul class="small">@foreach($s->aiCheck->reasons as $x)<li>{{ $x }}</li>@endforeach</ul>@endif
    @endif</div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Scheduled publishing</h2></div>
    <div class="card-b flush">@if($s->publishLogs->isEmpty())<x-admin.empty title="Never scheduled" />@else<table class="t compact"><tbody>@foreach($s->publishLogs as $l)<tr><td><x-admin.pill :tone="$l->result==='success'?'green':'red'">{{ $l->result }}</x-admin.pill></td><td class="small">{{ $l->error_message }}</td><td class="small muted nowrap">{{ fmt_dt($l->attempted_at) }}</td></tr>@endforeach</tbody></table>@endif</div>
  </div>
</div>
<div class="card mt-4">
  <div class="card-h"><h2>Audit records</h2></div>
  <div class="card-b flush"><table class="t compact"><thead><tr><th>Action</th><th>By</th><th>Reason</th><th>When</th></tr></thead><tbody>
    @forelse($audit as $a)<tr><td class="mono small">{{ $a->action }}</td><td class="small">{{ $a->admin?->name ?? 'System' }}</td><td class="small">{{ $a->reason }}</td><td class="small muted nowrap">{{ fmt_dt($a->created_at) }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No audit records.</td></tr>@endforelse
  </tbody></table></div>
</div>
@endsection
