@props(['name', 'media' => null, 'kind' => 'image', 'label' => null, 'required' => false, 'help' => null, 'shape' => 'wide'])
@php
  $m = $media instanceof \App\Models\Media ? $media : ($media ? \App\Models\Media::find($media) : null);
@endphp
<div class="field" x-data="mediaField({ id: '{{ $m?->id }}', url: '{{ $m?->thumbUrl() }}', name: @js($m?->original_name ?? ''), kind: '{{ $kind }}' })">
  @if($label)<label class="lbl">{{ $label }} @if($required)<span class="req">*</span>@endif</label>@endif
  <input type="hidden" name="{{ $name }}" :value="id">
  <div class="media-slot {{ $shape }}" @click="pick()" :style="url ? `background-image:url('${url}')` : ''" role="button" tabindex="0" @keydown.enter="pick()">
    <span x-show="!url" class="center">{!! icon('upload', 18) !!}<br><span x-text="name || 'Choose or upload {{ $kind }}'"></span></span>
  </div>
  <div class="row mt-1 small" x-show="id" x-cloak><span class="muted trunc" x-text="name" style="max-width:260px"></span><button type="button" class="link-btn" @click="pick()">Replace</button><button type="button" class="link-btn" @click="clear()">Remove</button></div>
  @if($help)<div class="help">{{ $help }}</div>@endif
  @error($name)<div class="err">{{ $message }}</div>@enderror
</div>
