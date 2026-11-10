@extends('admin.layouts.app')
@section('title', $m->original_name)
@section('content')
<x-admin.ph :title="$m->original_name" :sub="ucfirst($m->kind).' · '.$m->humanSize().' · uploaded '.fmt_dt($m->created_at)" :crumbs="['Media library' => route('admin.media.index')]">
  @can('media.delete')<form method="post" action="{{ route('admin.media.destroy', $m) }}" data-confirm="Delete this file?" data-confirm-detail="This cannot be undone." data-confirm-danger>@csrf @method('delete')<button class="btn danger" @disabled($m->usageCount() > 0)>Delete</button></form>@endcan
</x-admin.ph>
<div class="grid g2">
  <div class="card"><div class="card-b">
    @if($m->kind === 'image')<img src="{{ $m->url('hero') ?? $m->url() }}" alt="{{ $m->alt_text }}" style="max-width:100%;border-radius:4px">
    @elseif($m->kind === 'video')<video controls style="width:100%" @if($m->thumbUrl()) poster="{{ $m->thumbUrl() }}" @endif><source src="{{ $m->url() }}"></video>
    @elseif($m->kind === 'audio')<audio controls style="width:100%"><source src="{{ $m->url() }}"></audio>
    @else<a href="{{ $m->url() }}" class="btn" target="_blank">Open file</a>@endif
    <dl class="kv mt-3"><dt>Type</dt><dd class="mono">{{ $m->mime_type }}</dd>@if($m->width)<dt>Dimensions</dt><dd>{{ $m->width }}×{{ $m->height }}</dd>@endif @if($m->duration_seconds)<dt>Duration</dt><dd>{{ gmdate('H:i:s', $m->duration_seconds) }}</dd>@endif
      @if($m->conversions)<dt>Versions</dt><dd>{{ implode(', ', array_keys($m->conversions)) }}</dd>@endif<dt>URL</dt><dd class="mono" style="word-break:break-all">{{ $m->url() }}</dd></dl>
  </div></div>
  <div class="stack">
    <form class="card" method="post" action="{{ route('admin.media.update', $m) }}">@csrf @method('put')
      <div class="card-h"><h2>Details</h2></div>
      <div class="card-b">
        <x-admin.field label="Alt text" name="alt_text" :required="$m->kind==='image'" help="Describe the image for screen readers."><input name="alt_text" value="{{ old('alt_text', $m->alt_text) }}"></x-admin.field>
        <x-admin.field label="Caption" name="caption"><textarea name="caption" rows="2">{{ old('caption', $m->caption) }}</textarea></x-admin.field>
        <x-admin.field label="Credit" name="credit"><input name="credit" value="{{ old('credit', $m->credit) }}" placeholder="Photographer / agency"></x-admin.field>
      </div><div class="card-f"><button class="btn primary">Save</button></div>
    </form>
    <div class="card"><div class="card-h"><h2>Used in</h2></div><div class="card-b flush"><table class="t compact"><tbody>
      @foreach($usage as $c)<tr><td><a href="{{ route('admin.stories.edit', $c) }}">{{ $c->title() }}</a></td><td class="small muted">story photo</td></tr>@endforeach
      @if($usage->isEmpty())<tr><td class="muted">Not used by any story.</td></tr>@endif
    </tbody></table></div></div>
  </div>
</div>
@endsection
