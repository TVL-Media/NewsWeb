@extends('admin.layouts.app')
@section('title', 'Morning 5')
@section('content')
@php $hasToday = $items->contains(fn ($b) => $b->date->toDateString() === $today); @endphp
<x-admin.ph title="Daily" sub="The Morning 5 is picked at 06:00 IST from the last 24 hours. Edit a day to choose and order its five stories yourself; the audio is rebuilt when you save.">
  @unless($hasToday)<form method="post" action="{{ route('admin.daily.briefings.pick') }}">@csrf<button class="btn">{!! icon('zap', 14) !!} Pick today’s now</button></form>@endunless
  <a href="{{ route('admin.daily.briefings.create') }}" class="btn primary">{!! icon('plus', 14) !!} New briefing</a>
</x-admin.ph>
@include('admin.daily._tabs')
<div class="card">
  <table class="t"><thead><tr><th>Date</th><th>Stories, in playing order</th><th>Audio</th><th></th></tr></thead><tbody>
  @forelse($items as $b)
    <tr>
      <td class="nowrap strong">{{ $b->date->format('D, j M Y') }}@if($b->date->toDateString() === $today) <x-admin.pill tone="green">Today</x-admin.pill>@endif</td>
      <td class="small"><ol style="margin:0;padding-left:18px">@foreach($b->story_ids ?? [] as $id)<li>{{ \Illuminate\Support\Str::limit($titles[$id] ?? 'Story #'.$id.' (deleted)', 90) }}</li>@endforeach</ol></td>
      <td class="small nowrap">{{ $b->audio_seconds ? intdiv($b->audio_seconds, 60).':'.str_pad($b->audio_seconds % 60, 2, '0', STR_PAD_LEFT) : 'building…' }}</td>
      <td class="right nowrap"><a class="btn xs" href="{{ route('admin.daily.briefings.edit', $b) }}">Edit</a>
        <form method="post" action="{{ route('admin.daily.briefings.audio', $b) }}" style="display:inline">@csrf<button class="btn xs">Rebuild audio</button></form>
        @if($b->date->toDateString() === $today)<a class="btn xs" href="{{ route('briefing') }}" target="_blank">Open</a>@endif</td>
    </tr>
  @empty <tr><td colspan="4"><x-admin.empty title="No briefings yet">The first one is picked at 06:00 IST, or pick today’s now.</x-admin.empty></td></tr>@endforelse
  </tbody></table>{{ $items->links() }}
</div>
@endsection
