@extends('admin.layouts.app')
@section('title', 'Reel')
@section('content')
@php
  $mmss = fn ($s) => $s ? intdiv((int) $s, 60).':'.str_pad((string) ((int) $s % 60), 2, '0', STR_PAD_LEFT) : '—';
  $live = $reel->status === 'published';
@endphp
<x-admin.ph :title="$reel->title" :crumbs="['Reels' => route('admin.reels.index')]"
  :sub="($reel->source_type === 'upload' ? 'Uploaded video'.($reel->creator ? ' by '.$reel->creator->name : '') : 'Made from a story').' · '.($reel->language === 'hi' ? 'Hindi' : 'English').' · '.fmt_dt($reel->created_at)">
  <x-admin.pill :tone="$labels[$reel->status][1] ?? 'neutral'">{{ $labels[$reel->status][0] ?? $reel->status }}</x-admin.pill>
  @if($live)<a href="{{ route('reels.show', $reel) }}" target="_blank" class="btn">{!! icon('external-link', 14) !!} Open on site</a>@endif
  @can('reels.manage')
    @if(in_array($reel->status, ['pending_approval', 'rejected']) && $reel->video_720_path)
      <form method="post" action="{{ route('admin.reels.approve', $reel) }}" data-confirm="Approve this reel?" data-confirm-detail="It goes live on the website straight away.">@csrf<button class="btn primary">{!! icon('check', 14) !!} Approve</button></form>
    @endif
    @if($reel->status === 'pending_approval')
      <form method="post" action="{{ route('admin.reels.reject', $reel) }}" data-confirm="Reject this reel?" data-confirm-detail="It will not go live. The reason is kept with the reel." data-confirm-reason="required">@csrf<button class="btn">{!! icon('x', 14) !!} Reject</button></form>
    @endif
    @if($live)
      <form method="post" action="{{ route('admin.reels.unpublish', $reel) }}" data-confirm="Unpublish this reel?" data-confirm-detail="It disappears from the website. You can approve it again later." data-confirm-reason="optional">@csrf<button class="btn">{!! icon('eye-off', 14) !!} Unpublish</button></form>
    @endif
    @if($reel->status !== 'processing')
      <form method="post" action="{{ route('admin.reels.regenerate', $reel) }}" data-confirm="Re-generate this reel?" data-confirm-detail="{{ $reel->source_type === 'upload' ? 'The uploaded video is processed again.' : 'The video is rebuilt from the story’s current headline, key points and photo.' }}{{ $live ? ' It stays live until the new version is ready.' : '' }}">@csrf<button class="btn">{!! icon('refresh-cw', 14) !!} Re-generate</button></form>
    @endif
  @endcan
</x-admin.ph>

@if($reel->status === 'failed')
  <div class="alert red"><span class="t">Processing failed</span><span style="white-space:pre-line;font-family:ui-monospace,monospace;font-size:12.5px">{{ $reel->error_message ?: 'No error message was saved.' }}</span></div>
@elseif($reel->status === 'processing')
  <div class="alert blue">This reel is being made. Refresh in a minute. (Locally the queue worker must be running: <code>php artisan queue:work</code>.)</div>
@elseif($reel->status === 'rejected' && $reel->rejection_reason)
  <div class="alert">{{ $reel->rejection_reason }}@if($reel->reviewer) · {{ $reel->reviewer->name }}, {{ fmt_dt($reel->reviewed_at) }}@endif</div>
@elseif($reel->status === 'pending_approval')
  <div class="alert amber">Waiting for approval: {{ $reel->source_type === 'upload' ? 'every uploaded video is checked by an editor.' : 'the story was marked sensitive'.(($r = $reel->story?->aiCheck?->sensitive_reasons ?: $reel->story?->aiCheck?->reasons) ? ' ('.implode(' · ', (array) $r).')' : '').'.' }}</div>
@endif
@if($reports)<div class="alert red">This reel has {{ $reports }} open report(s). <a href="{{ route('admin.reports.index', ['type' => 'reel']) }}">Review reports</a>.</div>@endif

<div class="grid g-main">
  <div class="card" x-data><div class="card-h"><h2>Preview</h2>
      @if($reel->video_720_path)<div class="row small"><span class="muted">Quality:</span>
        <button type="button" class="link-btn" @click="$refs.v.src = @js($reel->videoUrl('720')); $refs.v.play()">720p</button>
        <button type="button" class="link-btn" @click="$refs.v.src = @js($reel->videoUrl('480')); $refs.v.play()">480p</button></div>@endif
    </div>
    <div class="card-b" style="display:flex;justify-content:center;background:#111;border-radius:0 0 12px 12px">
      @if($reel->video_720_path)
        <video x-ref="v" src="{{ $reel->videoUrl('720') }}" poster="{{ $reel->thumbnailUrl() }}" controls playsinline preload="metadata"
               style="width:min(360px,100%);aspect-ratio:9/16;border-radius:18px;background:#000;object-fit:cover"></video>
      @else
        <div style="width:min(360px,100%);aspect-ratio:9/16;border-radius:18px;background:#1c1c1c;display:flex;align-items:center;justify-content:center;color:#888;text-align:center;padding:20px">
          {{ $reel->status === 'failed' ? 'No video: processing failed.' : 'The video appears here when processing finishes.' }}
        </div>
      @endif
    </div>
  </div>

  <aside class="stack">
    <div class="kpis" style="grid-template-columns:repeat(2,1fr)">
      <x-admin.kpi label="Views" :value="number_format($views->n ?? 0)" />
      <x-admin.kpi label="Avg. watched" :value="($views->n ?? 0) ? round($views->avg_s).' s' : '—'" />
      <x-admin.kpi label="Watched to the end" :value="($views->n ?? 0) ? round($views->done * 100 / $views->n).'%' : '—'" />
      <x-admin.kpi label="Likes · comments" :value="number_format($reel->likes_count).' · '.number_format($reel->comments_count)" />
    </div>
    <div class="card"><div class="card-b"><dl class="kv">
      <dt>Duration</dt><dd>{{ $mmss($reel->duration_seconds) }}</dd>
      <dt>Story</dt><dd>@if($reel->story)<a href="{{ route('admin.stories.edit', $reel->story) }}">{{ $reel->story->title() }}</a>@else—@endif</dd>
      <dt>Page</dt><dd>{{ $reel->page?->name ?? '—' }}</dd>
      <dt>Category</dt><dd>{{ $reel->category?->name_en ?? '—' }}</dd>
      <dt>Published</dt><dd>{{ $reel->published_at ? fmt_dt($reel->published_at) : '—' }}</dd>
      <dt>Shares</dt><dd>{{ number_format($reel->shares_count) }}</dd>
      @if($other->isNotEmpty())<dt>Other language</dt><dd>@foreach($other as $o)<a href="{{ route('admin.reels.show', $o) }}">{{ $o->language === 'hi' ? 'Hindi' : 'English' }}</a> <x-admin.pill :tone="$labels[$o->status][1] ?? 'neutral'">{{ $labels[$o->status][0] ?? $o->status }}</x-admin.pill>@endforeach</dd>@endif
      @if($reel->video_720_path)<dt>Files</dt><dd class="small"><a href="{{ $reel->videoUrl('720') }}" target="_blank">720p MP4</a> · <a href="{{ $reel->videoUrl('480') }}" target="_blank">480p MP4</a> · <a href="{{ $reel->thumbnailUrl() }}" target="_blank">Thumbnail</a></dd>@endif
    </dl></div></div>
  </aside>
</div>
@endsection
