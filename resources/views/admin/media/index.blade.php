@extends('admin.layouts.app')
@section('title', 'Media library')
@section('content')
<x-admin.ph title="Media library" :sub="'Images get WebP versions automatically. '.number_format($usedBytes / 1048576, 1).' MB used.'" />
<div class="card" x-data="{ drag:false, busy:0 }">
  <div class="card-b" style="border-bottom:1px solid var(--line-2)">
    <label class="dropzone" :class="drag && 'drag'" @dragover.prevent="drag=true" @dragleave="drag=false"
      @drop.prevent="drag=false; upload($event.dataTransfer.files)" style="display:block">
      <input type="file" hidden multiple @change="upload($event.target.files)">
      {!! icon('upload', 20) !!}<div class="strong mt-1">Drop files here or click to upload</div>
      <div class="small">Images up to {{ setting('content.max_image_mb', 10) }} MB · video up to {{ setting('content.max_video_mb', 1024) }} MB · audio up to {{ setting('content.max_audio_mb', 300) }} MB · captions (.vtt, .srt)</div>
      <div class="small mt-1 red" x-show="busy" x-text="'Uploading ' + busy + ' file(s)…'"></div>
    </label>
  </div>
  <form class="filters" method="get">
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, caption, credit"></div>
    <select name="kind" style="width:auto" onchange="this.form.submit()"><option value="">All types</option>@foreach(['image'=>'Images','video'=>'Videos','audio'=>'Audio','caption'=>'Captions','document'=>'Documents'] as $k=>$l)<option value="{{ $k }}" @selected(request('kind')===$k)>{{ $l }}</option>@endforeach</select>
    <label class="cb"><input type="checkbox" name="unused" value="1" @checked(request('unused')) onchange="this.form.submit()"> Not used anywhere</label>
  </form>
  @if($items->isEmpty())<x-admin.empty title="No media yet">Upload images, video and audio to use them in content.</x-admin.empty>@else
  <div class="media-grid">
    @foreach($items as $m)
      <a class="mcard" href="{{ route('admin.media.show', $m) }}" style="text-decoration:none;color:inherit">
        <div class="img" @if($m->thumbUrl()) style="background-image:url('{{ $m->thumbUrl() }}')" @endif>@unless($m->thumbUrl()){!! icon($m->kind === 'video' ? 'video' : ($m->kind === 'audio' ? 'headphones' : 'file-text'), 26) !!}@endunless</div>
        <div class="b"><div class="n trunc">{{ $m->original_name }}</div><div class="muted">{{ $m->kind }} · {{ $m->humanSize() }}@if($m->usages->isEmpty()) · <span class="red">unused</span>@endif</div></div>
      </a>
    @endforeach
  </div>
  @endif
  {{ $items->links() }}
</div>
@push('scripts')
<script>
async function upload(files) {
  const root = document.querySelector('[x-data]').__x ? null : null;
  for (const f of files) {
    const fd = new FormData(); fd.append('file', f);
    const r = await fetch('{{ route('admin.media.store') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' }, body: fd });
    if (!r.ok) { const j = await r.json().catch(() => ({})); toast((j.message || 'Upload failed') + ' — ' + f.name, 'err'); }
  }
  location.reload();
}
</script>
@endpush
@endsection
