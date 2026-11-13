@extends('admin.layouts.app')
@section('title', 'Comments')
@section('content')
<x-admin.ph title="Comments" sub="Reported comments first. Hiding a comment removes it from the story and closes its reports." />
<nav class="tabs">
  @foreach(['reported' => 'Reported', 'hidden' => 'Hidden', 'all' => 'All'] as $k => $l)
    <a href="{{ route('admin.comments.index', ['tab' => $k]) }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $counts[$k] }}</span></a>
  @endforeach
</nav>
<div class="card">
  <form class="filters" method="get"><input type="hidden" name="tab" value="{{ $tab }}"><div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search comment text"></div></form>
  <div class="table-wrap"><table class="t">
    <thead><tr><th>Comment</th><th>By</th><th>On</th><th class="num">Reports</th><th>Status</th><th>When</th><th></th></tr></thead>
    <tbody>
    @forelse($comments as $c)
      <tr>
        <td style="max-width:420px"><div class="small" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($c->body, 300) }}</div>@if($c->parent_id)<div class="t2">reply</div>@endif</td>
        <td class="small nowrap">@if($c->user)@can('users.view')<a href="{{ route('admin.users.show', $c->user) }}">{{ $c->user->name ?: 'Reader #'.$c->user_id }}</a>@else{{ $c->user->name ?: 'Reader' }}@endcan @if($c->user->status !== 'active') <x-admin.pill tone="red">{{ $c->user->status }}</x-admin.pill>@endif @endif</td>
        <td class="small" style="max-width:240px">@if($c->story)<a href="{{ route('story.show', $c->story) }}#comments" target="_blank">{{ \Illuminate\Support\Str::limit($c->story->title(), 60) }}</a>@elseif($c->reel)<span class="pill">Reel</span> <a href="{{ route('admin.reels.show', $c->reel) }}">{{ \Illuminate\Support\Str::limit($c->reel->title, 60) }}</a>@endif</td>
        <td class="num">@if($c->open_reports)<span class="pill red">{{ $c->open_reports }}</span>@else<span class="muted">0</span>@endif</td>
        <td><x-admin.pill :tone="$c->status === 'hidden' ? 'red' : 'green'">{{ ucfirst($c->status) }}</x-admin.pill></td>
        <td class="small muted nowrap">{{ $c->created_at->diffForHumans() }}</td>
        <td class="right nowrap">
          @if($c->status === 'visible')
            <form method="post" action="{{ route('admin.comments.hide', $c) }}" style="display:inline" data-confirm="Hide this comment?" data-confirm-detail="It disappears from the story and its open reports are closed." data-confirm-reason="optional">@csrf<button class="btn xs danger">Hide</button></form>
          @else
            <form method="post" action="{{ route('admin.comments.restore', $c) }}" style="display:inline">@csrf<button class="btn xs">Restore</button></form>
          @endif
        </td>
      </tr>
    @empty <tr><td colspan="7"><x-admin.empty :title="$tab === 'reported' ? 'No reported comments' : 'No comments'" /></td></tr>@endforelse
    </tbody>
  </table></div>
  {{ $comments->links() }}
</div>
@endsection
