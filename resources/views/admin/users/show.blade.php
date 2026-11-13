@extends('admin.layouts.app')
@section('title', $u->name ?: 'Reader #'.$u->id)
@section('content')
@php
  $me = auth('admin')->user(); $reasons = \App\Http\Controllers\Admin\UserController::REASONS; $S = \App\Http\Controllers\Admin\UserController::STATUSES;
  $eff = $u->effectiveStatus();
@endphp
<x-admin.ph :title="$u->name ?: 'Reader #'.$u->id" :crumbs="['Readers' => route('admin.users.index')]" :sub="'#'.$u->id.' · joined '.fmt_date($u->created_at).' · last active '.($u->last_active_at?->diffForHumans() ?? 'never')">
  <x-admin.pill :tone="$S[$eff][1] ?? 'neutral'">{{ $S[$eff][0] ?? $u->status }}@if($eff === 'restricted' && $u->restricted_until) until {{ fmt_dt($u->restricted_until) }}@endif</x-admin.pill>
  @if($me->isSuperAdmin())
    <a class="btn" href="{{ route('admin.users.export', $u) }}">{!! icon('download', 14) !!} Export data</a>
    <form method="post" action="{{ route('admin.users.anonymise', $u) }}" data-confirm="Erase this reader’s personal data?" data-confirm-detail="Their name and phone number are removed, they are signed out and the account is closed. Counts are kept without personal data. This cannot be undone." data-confirm-word="ERASE" data-confirm-password data-confirm-danger>@csrf<button class="btn danger">Erase personal data</button></form>
  @endif
</x-admin.ph>
<div class="grid g-main">
  <div class="stack">
    <div class="kpis">
      <x-admin.kpi label="Stories read" :value="number_format($summary['reads'])" />
      <x-admin.kpi label="Likes · saves · shares" :value="$summary['likes'].' · '.$summary['saves'].' · '.$summary['shares']" />
      <x-admin.kpi label="Comments" :value="$summary['comments']" :note="$summary['hidden'] ? $summary['hidden'].' hidden' : null" />
      <x-admin.kpi label="Streak" :value="($u->streak?->current ?? 0).' days'" :note="'longest '.($u->streak?->longest ?? 0).' · '.($u->streak?->freezes_left ?? \App\Services\StreakService::freezesPerMonth()).' freezes left'" />
    </div>
    <div class="card"><div class="card-h"><h2>This week</h2><span class="meta">a day counts at {{ \App\Services\StreakService::readsPerDay() }} reads or a finished quiz</span></div>
      <div class="card-b row">@foreach($week as $d)<div style="text-align:center;flex:1"><div class="xsmall muted">{{ $d['label'] }}</div><div style="width:26px;height:26px;margin:4px auto;border-radius:50%;{{ $d['done'] ? 'background:var(--red)' : 'border:1px solid var(--line)' }}"></div></div>@endforeach</div></div>
    <div class="card"><div class="card-h"><h2>Recent reads</h2></div>
      <div class="card-b flush"><table class="t compact"><tbody>
        @forelse($recentReads as $e)<tr><td class="small mono nowrap">{{ fmt_dt($e->read_at) }}</td><td class="small">@if($e->story)<a href="{{ route('admin.stories.edit', $e->story) }}">{{ \Illuminate\Support\Str::limit($e->story->title(), 80) }}</a>@endif</td></tr>
        @empty<tr><td class="muted">No reads yet.</td></tr>@endforelse
      </tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Recent comments</h2>@can('comments.moderate')<a class="small" href="{{ route('admin.comments.index', ['tab' => 'all', 'user' => $u->id]) }}">Moderate</a>@endcan</div>
      <div class="card-b flush"><table class="t compact"><tbody>
        @forelse($recentComments as $c)<tr><td class="small" style="max-width:420px">{{ \Illuminate\Support\Str::limit($c->body, 140) }}</td><td class="small muted">{{ \Illuminate\Support\Str::limit($c->story?->title(), 40) }}</td><td><x-admin.pill :tone="$c->status === 'hidden' ? 'red' : 'green'">{{ $c->status }}</x-admin.pill></td></tr>
        @empty<tr><td class="muted">No comments.</td></tr>@endforelse
      </tbody></table></div></div>
  </div>
  <aside class="stack">
    <div class="card"><div class="card-h"><h2>Profile</h2></div><div class="card-b"><dl class="kv" style="grid-template-columns:100px 1fr">
      <dt>Phone</dt><dd class="mono">{{ $pii ? $u->phone : \App\Models\User::mask($u->phone) }}</dd>
      <dt>Language</dt><dd>{{ $u->language === 'hi' ? 'हिंदी' : 'English' }}</dd>
      <dt>City</dt><dd>{{ $u->city?->name_en ?? '—' }}</dd>
      <dt>Topics</dt><dd>{{ $u->categories->pluck('name_en')->join(', ') ?: '—' }}</dd>
      <dt>Follows</dt><dd>{{ $u->followedPages->pluck('name')->join(', ') ?: '—' }}</dd>
      <dt>Polls</dt><dd>{{ $summary['votes'] }} votes · {{ $summary['polls'] }} created</dd>
      <dt>Quizzes</dt><dd>{{ $summary['quizzes'] }} finished</dd>
      <dt>Reports</dt><dd>{{ $summary['reports_made'] }} made</dd>
    </dl></div></div>
    @if($me->can('users.restrict') || $me->can('users.suspend'))
    <form class="card" method="post" action="{{ route('admin.users.status', $u) }}" x-data="{ to: '{{ $eff === 'active' ? 'restricted' : 'active' }}' }" data-confirm="Change this account’s status?" data-confirm-detail="The website applies it on the reader’s next page view. The reason is kept in their history and the audit log.">@csrf
      <div class="card-h"><h2>Account action</h2></div>
      <div class="card-b">
        <x-admin.field label="Set status to"><select name="to" x-model="to">
          <option value="active">Active (reinstate)</option>
          @can('users.restrict')<option value="restricted">Restricted (can read; no likes, comments, votes or polls)</option>@endcan
          @can('users.suspend')<option value="suspended">Suspended (signed out, can’t sign in)</option>@endcan
        </select></x-admin.field>
        <div x-show="to==='restricted'"><x-admin.field label="For how many days?" help="Leave empty for no end date."><input type="number" name="days" min="1" max="365" value="7"></x-admin.field></div>
        <x-admin.field label="Reason" name="reason" required><select name="reason" required><option value="">Choose…</option>@foreach($reasons as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-admin.field>
        <x-admin.field label="Note" name="note"><textarea name="note" rows="2"></textarea></x-admin.field>
      </div><div class="card-f"><button class="btn primary">Apply</button></div>
    </form>
    @endif
    @can('streaks.manage')
      <div class="card"><div class="card-h"><h2>Streak support</h2></div><div class="card-b row wrap">
        <form method="post" action="{{ route('admin.users.streak.recalc', $u) }}">@csrf<button class="btn sm">Recalculate from reads</button></form>
        <form method="post" action="{{ route('admin.users.streak.freeze', $u) }}">@csrf<button class="btn sm">Give one freeze</button></form>
      </div></div>
    @endcan
    <div class="card"><div class="card-h"><h2>Status history</h2></div><div class="card-b">@if($u->statusLogs->isEmpty())<p class="muted">No changes.</p>@else<ul class="tl">@foreach($u->statusLogs as $l)<li class="{{ $l->to_status !== 'active' ? 'r' : '' }}"><div class="what">{{ ucfirst($l->from_status ?? '—') }} → {{ ucfirst($l->to_status) }}</div><div class="when">{{ $l->admin?->name ?? 'System' }} · {{ fmt_dt($l->created_at) }}</div><div class="note">{{ $reasons[$l->reason] ?? $l->reason }}{{ $l->note ? ' — '.$l->note : '' }}@if($l->until) · until {{ fmt_dt($l->until) }}@endif</div></li>@endforeach</ul>@endif</div></div>
    @if($reports->isNotEmpty())<div class="card"><div class="card-h"><h2>Reports about their comments and polls</h2></div><div class="card-b flush"><table class="t compact"><tbody>@foreach($reports as $r)<tr><td><a href="{{ route('admin.reports.show', $r) }}">{{ ucfirst($r->target_type) }} · {{ \App\Http\Controllers\Admin\ReportController::REASONS[$r->reason] ?? $r->reason }}</a></td><td class="small">{{ $r->status }}</td></tr>@endforeach</tbody></table></div></div>@endif
  </aside>
</div>
@endsection
