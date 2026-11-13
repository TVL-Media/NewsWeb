@extends('admin.layouts.app')
@section('title', 'Report #'.$r->id)
@section('content')
@php
  $me = auth('admin')->user(); $open = in_array($r->status, ['open', 'escalated']);
  $reasons = \App\Http\Controllers\Admin\ReportController::REASONS;
  $allowed = collect(\App\Http\Controllers\Admin\ReportController::ACTIONS)->filter(fn ($l, $k) => match ($k) {
      'restrict_user' => $author && $me->can('users.restrict'),
      'suspend_user' => $author && $me->can('users.suspend'),
      'escalate' => $me->can('reports.escalate') && $r->status !== 'escalated',
      default => true,
  });
  $statusLabel = ['open' => ['Open', 'amber'], 'escalated' => ['Escalated', 'red'], 'actioned' => ['Taken down', 'green'], 'dismissed' => ['Dismissed', 'neutral']];
@endphp
<x-admin.ph :title="'Report: '.\Illuminate\Support\Str::limit($r->targetLabel(), 80)" :crumbs="['Reports' => route('admin.reports.index')]" :sub="ucfirst($r->target_type).' · '.($reasons[$r->reason] ?? $r->reason).' · '.($siblings->count() + 1).' report(s)'">
  <x-admin.pill :tone="$statusLabel[$r->status][1] ?? 'neutral'">{{ $statusLabel[$r->status][0] ?? $r->status }}</x-admin.pill>
</x-admin.ph>
@if($offences >= 3)<div class="alert red"><span class="t">Repeat offender</span>This reader’s comments or polls were taken down {{ $offences }} times before. Consider restricting or suspending them.</div>@endif
<div class="grid g-main">
  <div class="stack">
    <div class="card"><div class="card-h"><h2>Reported item</h2></div><div class="card-b">
      @if(! $target)<p class="muted">It was deleted.</p>
      @elseif($target instanceof \App\Models\Story)
        <div class="pv-cat">Story · <x-admin.status :content="$target" /></div><h2 class="mt-1 mb-2" style="font-size:18px">{{ $target->title() }}</h2><p>{{ $target->version('en')?->card_text }}</p>
        <a href="{{ route('admin.stories.edit', $target) }}" class="small">Open in the editor</a>@if($target->isLive()) · <a href="{{ route('story.show', $target) }}" target="_blank" class="small">Open on site</a>@endif
      @elseif($target instanceof \App\Models\Comment)
        <div class="pv-cat">Comment · <x-admin.pill :tone="$target->status === 'hidden' ? 'red' : 'green'">{{ ucfirst($target->status) }}</x-admin.pill></div>
        <blockquote style="margin:8px 0;padding:10px 14px;border-left:3px solid var(--line);background:var(--canvas);white-space:pre-line">{{ $target->body }}</blockquote>
        <div class="small">On <a href="{{ route('story.show', $target->story) }}#comments" target="_blank">{{ $target->story?->title() }}</a></div>
      @elseif($target instanceof \App\Models\Poll)
        <div class="pv-cat">Poll · {{ $target->status }}</div><strong>{{ $target->question }}</strong><ul class="mt-2">@foreach($target->options as $o)<li>{{ $o->label }}</li>@endforeach</ul><a href="{{ route('admin.polls.show', $target) }}" class="small">Open poll</a>
      @endif
      @if($author)<p class="mt-3 small">Written by @can('users.view')<a href="{{ route('admin.users.show', $author) }}">{{ $author->name ?: 'Reader #'.$author->id }}</a>@else{{ $author->name ?: 'a reader' }}@endcan @if($author->status !== 'active')<x-admin.pill tone="red">{{ $author->status }}</x-admin.pill>@endif</p>@endif
    </div></div>
    <div class="card"><div class="card-h"><h2>Reports ({{ $siblings->count() + 1 }})</h2></div><div class="card-b flush"><table class="t compact"><tbody>
      @foreach($siblings->prepend($r) as $s)<tr><td class="small">{{ $s->reporter?->name ?? 'Reader #'.$s->reporter_id }}</td><td class="small">{{ $reasons[$s->reason] ?? $s->reason }}</td><td class="small">{{ $s->details }}</td><td class="small muted nowrap">{{ fmt_dt($s->created_at) }}</td></tr>@endforeach
    </tbody></table></div></div>
    @if($history->isNotEmpty())<div class="card"><div class="card-h"><h2>Earlier decisions on this item</h2></div><div class="card-b"><ul class="tl">@foreach($history as $h)<li><div class="what">{{ \App\Http\Controllers\Admin\ReportController::ACTIONS[$h->action] ?? ucfirst($h->action) }}</div><div class="when">{{ $h->admin?->name }} · {{ fmt_dt($h->created_at) }}</div>@if($h->note)<div class="note">{{ $h->note }}</div>@endif</li>@endforeach</ul></div></div>@endif
  </div>
  <aside class="stack">
    @if($open)
    @can('reports.handle')
    <form class="card" method="post" action="{{ route('admin.reports.act', $r) }}" data-confirm="Apply this decision?" data-confirm-detail="It applies to every open report on this item and is recorded in the decision log.">@csrf
      <div class="card-h"><h2>Decision</h2></div>
      <div class="card-b">
        @if($r->status === 'escalated' && ! $me->isSuperAdmin())<div class="alert amber">Escalated reports are decided by a Super Admin.</div>@endif
        <div class="stack-sm mb-3">@foreach($allowed as $k => $l)<label class="cb"><input type="radio" name="action" value="{{ $k }}" required> {{ $l }}</label>@endforeach</div>
        <x-admin.field label="Note" name="note" help="Required unless you dismiss. Kept in the decision log."><textarea name="note" rows="3"></textarea></x-admin.field>
        <div class="help">Guidelines: {!! strip_tags(setting('editorial.moderation_guidelines'), '<strong><em>') !!}</div>
      </div><div class="card-f"><button class="btn primary">Apply decision</button></div>
    </form>
    @endcan
    @else
      <div class="card"><div class="card-h"><h2>Resolution</h2></div><div class="card-b"><dl class="kv" style="grid-template-columns:90px 1fr"><dt>Result</dt><dd>{{ $statusLabel[$r->status][0] ?? $r->status }}</dd><dt>By</dt><dd>{{ $r->handler?->name ?? '—' }}</dd><dt>When</dt><dd>{{ fmt_dt($r->handled_at) }}</dd></dl></div></div>
    @endif
  </aside>
</div>
@endsection
