@extends('admin.layouts.app')
@section('title', 'Streaks')
@section('content')
<x-admin.ph title="Streaks" sub="A reader’s day counts when they read enough stories or finish the daily quiz (IST days). Missed days use a freeze automatically.">@include('admin.partials.range')</x-admin.ph>
<div class="kpis mb-4">
  <x-admin.kpi label="Readers with an active streak" :value="number_format($summary['active'])" />
  <x-admin.kpi label="Average streak" :value="$summary['avg'].' days'" />
  <x-admin.kpi label="Longest streak ever" :value="$summary['longest'].' days'" />
  <x-admin.kpi label="Freezes used this month" :value="number_format($summary['freezes_used'])" />
</div>
<div class="grid g-wide">
  <div class="stack">
    <div class="card"><div class="card-h"><h2>Readers whose day counted</h2><span class="meta">{{ $label }}</span></div>
      <div class="card-b">@php $cfg = ['type' => 'bar', 'labels' => array_map(fn($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'), $series['labels']), 'datasets' => [['label' => 'Readers', 'data' => $series['values']]]]; @endphp<x-admin.chart :config="$cfg" /></div></div>
    <div class="grid g2">
      <div class="card"><div class="card-h"><h2>Longest streaks</h2></div><div class="card-b flush"><table class="t compact"><thead><tr><th>Reader</th><th class="num">Longest</th><th class="num">Current</th></tr></thead><tbody>
        @forelse($leaders as $l)<tr><td>@can('users.view')<a href="{{ route('admin.users.show', $l->user_id) }}">{{ $l->user?->name ?: 'Reader #'.$l->user_id }}</a>@else{{ $l->user?->name ?: 'Reader' }}@endcan</td><td class="num strong">{{ $l->longest }}</td><td class="num">{{ $l->current }}</td></tr>@empty<tr><td class="muted" colspan="3">No streaks yet.</td></tr>@endforelse
      </tbody></table></div></div>
      <div class="card"><div class="card-h"><h2>Active right now</h2></div><div class="card-b flush"><table class="t compact"><thead><tr><th>Reader</th><th class="num">Days</th><th class="num">Freezes left</th></tr></thead><tbody>
        @forelse($activeNow as $l)<tr><td>{{ $l->user?->name ?: 'Reader #'.$l->user_id }}</td><td class="num strong">{{ $l->current }}</td><td class="num">{{ $l->freezes_left }}</td></tr>@empty<tr><td class="muted" colspan="3">No active streaks.</td></tr>@endforelse
      </tbody></table></div></div>
    </div>
    <div class="card"><div class="card-h"><h2>Current streak lengths</h2></div><div class="card-b"><x-admin.chart size="sm" :config="['type' => 'bar', 'labels' => array_keys($dist), 'datasets' => [['label' => 'Readers', 'data' => array_values($dist)]]]" /></div></div>
  </div>
  <div class="stack">
    <form class="card" method="post" action="{{ route('admin.streaks.save') }}" data-confirm="Change the streak rules?" data-confirm-detail="Readers see the new rule from today. Past days are not recounted." data-confirm-password>@csrf @method('put')
      <div class="card-h"><h2>Rules</h2></div>
      <fieldset class="card-b" {{ $canManage ? '' : 'disabled' }} style="border:0;margin:0">
        <x-admin.field label="Stories to read for a day to count" name="reads_per_day" help="Finishing the daily quiz also counts the day."><input type="number" name="reads_per_day" min="1" max="20" value="{{ \App\Services\StreakService::readsPerDay() }}"></x-admin.field>
        <x-admin.field label="Freezes per calendar month" name="freezes_per_month" help="Used automatically at 00:05 IST for a missed day. 0 turns freezes off."><input type="number" name="freezes_per_month" min="0" max="10" value="{{ \App\Services\StreakService::freezesPerMonth() }}"></x-admin.field>
        <x-admin.field label="Note" name="change_note"><input name="change_note" placeholder="Why the change"></x-admin.field>
      </fieldset>
      @if($canManage)<div class="card-f"><button class="btn primary">Save rules</button></div>@endif
    </form>
    <div class="card"><div class="card-h"><h2>Rule changes</h2></div><div class="card-b">
      @if($history->isEmpty())<p class="muted small">The defaults are 3 stories a day and 2 freezes a month.</p>@else<ul class="tl">@foreach($history as $h)<li><div class="what">{{ $h->after['reads_per_day'] ?? '?' }} reads/day · {{ $h->after['freezes_per_month'] ?? '?' }} freezes</div><div class="when">{{ $h->admin?->name }} · {{ fmt_dt($h->created_at) }}</div>@if($h->reason)<div class="note">{{ $h->reason }}</div>@endif</li>@endforeach</ul>@endif
    </div></div>
  </div>
</div>
@endsection
