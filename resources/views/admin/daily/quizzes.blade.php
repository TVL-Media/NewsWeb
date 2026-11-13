@extends('admin.layouts.app')
@section('title', 'Daily quiz')
@section('content')
<x-admin.ph title="Daily" sub="Tomorrow’s quiz is drafted at 21:00 IST from today’s top stories. It is approved automatically only when every source story is low-risk.">
  <form method="post" action="{{ route('admin.daily.quizzes.draft') }}">@csrf<button class="btn">{!! icon('zap', 14) !!} Draft tomorrow’s now</button></form>
  <a href="{{ route('admin.daily.quizzes.create') }}" class="btn primary">{!! icon('plus', 14) !!} New quiz</a>
</x-admin.ph>
@include('admin.daily._tabs')
<div class="card">
  <table class="t"><thead><tr><th>Date</th><th>Status</th><th class="num">Questions</th><th class="num">Players</th><th></th></tr></thead><tbody>
  @forelse($items as $q)
    <tr>
      <td class="nowrap strong">{{ $q->date->format('D, j M Y') }}@if($q->date->toDateString() === $today) <x-admin.pill tone="green">Today</x-admin.pill>@endif</td>
      <td><x-admin.pill :tone="$q->status === 'draft' ? 'amber' : 'green'">{{ $q->status === 'draft' ? 'Draft (hidden)' : ucfirst($q->status) }}</x-admin.pill></td>
      <td class="num">{{ $q->questions_count }}</td><td class="num">{{ num($q->attempts_count) }}</td>
      <td class="right nowrap">
        @if($q->status === 'draft')<form method="post" action="{{ route('admin.daily.quizzes.approve', $q) }}" style="display:inline">@csrf<button class="btn xs primary">Approve</button></form>@endif
        <a class="btn xs" href="{{ route('admin.daily.quizzes.edit', $q) }}">Edit</a>
        @if(! $q->attempts_count)<form method="post" action="{{ route('admin.daily.quizzes.destroy', $q) }}" style="display:inline" data-confirm="Delete this quiz?" data-confirm-danger>@csrf @method('delete')<button class="btn xs danger">Delete</button></form>@endif
      </td>
    </tr>
  @empty <tr><td colspan="5"><x-admin.empty title="No quizzes yet">Draft tomorrow’s quiz now or write one.</x-admin.empty></td></tr>@endforelse
  </tbody></table>{{ $items->links() }}
</div>
@endsection
