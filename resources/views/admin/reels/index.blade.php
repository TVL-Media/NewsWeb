@extends('admin.layouts.app')
@section('title', 'Reels')
@section('content')
@php $mmss = fn ($s) => $s ? intdiv((int) $s, 60).':'.str_pad((string) ((int) $s % 60), 2, '0', STR_PAD_LEFT) : '—'; @endphp
<x-admin.ph title="Reels" sub="Short vertical videos. Reels are made automatically from published stories (one per language); reels from sensitive stories and every uploaded video wait here for approval.">
  @can('reels.manage')<a href="{{ route('admin.reels.create') }}" class="btn primary">{!! icon('upload', 14) !!} Upload video</a>@endcan
</x-admin.ph>
@unless(config('reels.enabled'))<div class="alert amber">Reels are switched off on the website (<code>REELS_ENABLED=false</code>). New stories don't get reels and readers can't see any.</div>@endunless
<nav class="tabs" aria-label="Status">
  <a href="{{ route('admin.reels.index', ['tab' => 'pending']) }}" class="{{ $tab === 'pending' ? 'on' : '' }}">Waiting for approval <span class="n">{{ $counts['pending_approval'] ?? 0 }}</span></a>
  <a href="{{ route('admin.reels.index', ['tab' => 'all']) }}" class="{{ $tab === 'all' && ! request('status') ? 'on' : '' }}">All <span class="n">{{ $counts->sum() }}</span></a>
  @foreach(['published', 'processing', 'failed', 'rejected'] as $s)<a href="{{ route('admin.reels.index', ['tab' => 'all', 'status' => $s]) }}" class="{{ request('status') === $s ? 'on' : '' }}">{{ $labels[$s][0] }} <span class="n">{{ $counts[$s] ?? 0 }}</span></a>@endforeach
</nav>
<div class="card">
  <form class="filters" method="get"><input type="hidden" name="tab" value="{{ $tab }}">@if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search titles"></div>
    <select name="language" style="width:auto" onchange="this.form.submit()" aria-label="Language"><option value="">English &amp; Hindi</option><option value="en" @selected(request('language')==='en')>English</option><option value="hi" @selected(request('language')==='hi')>Hindi</option></select>
    <select name="source" style="width:auto" onchange="this.form.submit()" aria-label="Source"><option value="">Auto &amp; uploads</option><option value="auto" @selected(request('source')==='auto')>Made from stories</option><option value="upload" @selected(request('source')==='upload')>Uploaded videos</option></select>
  </form>
  <div class="table-wrap"><table class="t"><thead><tr><th style="width:64px"></th><th>Title</th><th>Language</th><th>Status</th><th class="num">Duration</th><th class="num">Views</th><th></th></tr></thead><tbody>
  @forelse($reels as $reel)
    <tr>
      <td><a href="{{ route('admin.reels.show', $reel) }}" style="display:block;width:54px;aspect-ratio:9/16;border-radius:8px;overflow:hidden;background:#111">@if($reel->thumbnailUrl())<img src="{{ $reel->thumbnailUrl() }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">@endif</a></td>
      <td><a class="t1" href="{{ route('admin.reels.show', $reel) }}" @if($reel->language === 'hi') lang="hi" @endif>{{ $reel->title }}</a>
        <div class="t2">{{ $reel->source_type === 'upload' ? 'Uploaded'.($reel->creator ? ' by '.$reel->creator->name : '') : 'Made from story' }}@if($reel->page) · {{ $reel->page->name }}@endif · {{ fmt_dt($reel->created_at) }}</div>
        @if($reel->status === 'failed' && $reel->error_message)<div class="t2 red">{!! icon('alert-triangle', 12) !!} {{ \Illuminate\Support\Str::limit($reel->error_message, 140) }}</div>@endif
        @if($reel->status === 'rejected' && $reel->rejection_reason)<div class="t2 muted">{{ \Illuminate\Support\Str::limit($reel->rejection_reason, 120) }}</div>@endif
        @if($reported[$reel->id] ?? 0)<div class="t2 red">{{ $reported[$reel->id] }} open report(s)</div>@endif</td>
      <td class="small">{{ $reel->language === 'hi' ? 'Hindi' : 'English' }}</td>
      <td><x-admin.pill :tone="$labels[$reel->status][1] ?? 'neutral'">{{ $labels[$reel->status][0] ?? $reel->status }}</x-admin.pill></td>
      <td class="num">{{ $mmss($reel->duration_seconds) }}</td>
      <td class="num">{{ num($reel->views_count) }}</td>
      <td class="right nowrap">
        @can('reels.manage')
          @if($reel->status === 'pending_approval')
            <form method="post" action="{{ route('admin.reels.approve', $reel) }}" style="display:inline">@csrf<button class="btn xs primary">Approve</button></form>
            <form method="post" action="{{ route('admin.reels.reject', $reel) }}" style="display:inline" data-confirm="Reject this reel?" data-confirm-reason="required">@csrf<button class="btn xs">Reject</button></form>
          @elseif($reel->status === 'failed')
            <form method="post" action="{{ route('admin.reels.regenerate', $reel) }}" style="display:inline">@csrf<button class="btn xs">{!! icon('refresh-cw', 12) !!} Retry</button></form>
          @endif
        @endcan
        <a class="btn xs" href="{{ route('admin.reels.show', $reel) }}">Preview</a>
      </td>
    </tr>
  @empty <tr><td colspan="7"><x-admin.empty :title="$tab === 'pending' ? 'No reels waiting' : 'No reels'">Reels appear here when stories are published or a video is uploaded.</x-admin.empty></td></tr>@endforelse
  </tbody></table></div>
  {{ $reels->links() }}
</div>
@endsection
