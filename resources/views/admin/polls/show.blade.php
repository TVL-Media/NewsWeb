@extends('admin.layouts.app')
@section('title', 'Poll results')
@section('content')
@php $lead = collect($results)->max('votes'); $label = ['pending' => ['Waiting for approval', 'amber'], 'live' => ['Live', 'green'], 'closed' => ['Closed', 'neutral'], 'rejected' => ['Rejected', 'red']]; @endphp
<x-admin.ph :title="$poll->question" :crumbs="['Polls' => route('admin.polls.index')]" :sub="($poll->created_by ? 'Created by reader '.($poll->creator?->name ?: '#'.$poll->created_by) : 'Editorial poll'.($poll->admin ? ' by '.$poll->admin->name : '')).' · '.fmt_dt($poll->created_at)">
  <x-admin.pill :tone="$label[$poll->status][1] ?? 'neutral'">{{ $label[$poll->status][0] ?? $poll->status }}</x-admin.pill>
  <a href="{{ route('admin.polls.export', $poll) }}" class="btn">{!! icon('download', 14) !!} CSV</a>
  @can('polls.manage')
    @if($poll->status === 'pending')<form method="post" action="{{ route('admin.polls.approve', $poll) }}">@csrf<button class="btn primary">Approve</button></form>@endif
    @if(in_array($poll->status, ['pending', 'live']))<a href="{{ route('admin.polls.edit', $poll) }}" class="btn">Edit</a>
      <form method="post" action="{{ route('admin.polls.reject', $poll) }}" data-confirm="Reject this poll?" data-confirm-detail="{{ $poll->status === 'live' ? 'It is taken off the website.' : 'It will not go live.' }}" data-confirm-reason="optional">@csrf<button class="btn">Reject</button></form>@endif
    <form method="post" action="{{ route('admin.polls.duplicate', $poll) }}">@csrf<button class="btn">Duplicate</button></form>
    @if(! $total)<form method="post" action="{{ route('admin.polls.destroy', $poll) }}" data-confirm="Delete this poll?" data-confirm-danger>@csrf @method('delete')<button class="btn danger">Delete</button></form>@endif
  @endcan
  @can('polls.close')@if($poll->status === 'live')<form method="post" action="{{ route('admin.polls.close', $poll) }}" data-confirm="Close this poll now?" data-confirm-detail="It stops accepting votes immediately." data-confirm-reason="optional">@csrf<button class="btn primary">Close poll</button></form>@endif @endcan
</x-admin.ph>
@if($reports)<div class="alert red">This poll has {{ $reports }} open report(s). <a href="{{ route('admin.reports.index') }}">Review reports</a>.</div>@endif
@if($poll->rejection_reason)<div class="alert">Rejected: {{ $poll->rejection_reason }}</div>@endif
<div class="kpis mb-4" style="grid-template-columns:repeat(3,1fr)">
  <x-admin.kpi label="Votes" :value="number_format($total)" />
  <x-admin.kpi label="Created" :value="$poll->created_at->setTimezone(ptz())->format('d M, H:i')" />
  <x-admin.kpi :label="$poll->status === 'closed' ? 'Closed' : 'Ends'" :value="$poll->ends_at ? $poll->ends_at->setTimezone(ptz())->format('d M, H:i') : '—'" />
</div>
<div class="grid g2">
  <div class="card"><div class="card-h"><h2>Results by option</h2></div>
    <div class="card-b">
      @foreach($results as $r)
        <div class="bar-row {{ $r['votes'] && $r['votes'] === $lead ? 'lead' : '' }}"><div class="small {{ $r['votes'] === $lead && $lead ? 'strong' : '' }}">{{ $r['option']->label }}</div><div class="bar"><span style="width:{{ $r['percent'] }}%"></span></div><div class="v">{{ $r['percent'] }}% <span class="muted" style="font-weight:400">· {{ number_format($r['votes']) }}</span></div></div>
      @endforeach
      <div class="xsmall muted mt-2">One vote per reader. Percentages always total 100%.</div>
    </div></div>
  <div class="card"><div class="card-h"><h2>Votes over time</h2></div>
    <div class="card-b">@if($series->isEmpty())<x-admin.empty title="No votes yet" />@else
      @php $cfg = ['type' => 'line', 'labels' => $series->keys()->map(fn ($k) => strlen($k) > 10 ? substr($k, 11, 5) : \Illuminate\Support\Carbon::parse($k)->format('d M'))->all(), 'datasets' => [['label' => 'Votes', 'data' => $series->values()->all(), 'fill' => 'rgba(200,16,46,.08)']]]; @endphp
      <x-admin.chart :config="$cfg" />@endif</div></div>
</div>
<div class="card mt-4"><div class="card-b"><dl class="kv">
  <dt>Shown with</dt><dd>@if($poll->story)<a href="{{ route('admin.stories.edit', $poll->story) }}">{{ $poll->story->title() }}</a>@else—@endif</dd>
  <dt>Category</dt><dd>{{ $poll->category?->name_en ?? '—' }}</dd>
</dl></div></div>
@endsection
