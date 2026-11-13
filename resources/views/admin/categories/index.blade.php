@extends('admin.layouts.app')
@section('title', 'Categories')
@section('content')
@php $canManage = auth('admin')->user()->can('categories.manage'); @endphp
<div x-data="{ open:false, edit:null, blank:{id:null,name_en:'',name_hi:'',slug:'',description:'',visual_type:'image',color_hex:'#D7141E',status:'active',is_sensitive:false,visual:null} }">
<x-admin.ph title="Categories" sub="The category chips and Explore tiles on the website. Drag rows to set their order. Sensitive categories never auto-publish.">
  @if($canManage)<button class="btn primary" @click="edit=JSON.parse(JSON.stringify(blank)); open=true">{!! icon('plus', 14) !!} New category</button>@endif
</x-admin.ph>

<div class="card">
  <form class="filters" method="get">
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search categories"></div>
    <select name="status" onchange="this.form.submit()" style="width:auto"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select>
    @if(request()->hasAny(['q','status']))<a href="{{ route('admin.categories.index') }}" class="btn ghost sm">Reset</a>@endif
  </form>
  <div class="table-wrap">
  <table class="t">
    <thead><tr><th style="width:30px"></th><th>Category</th><th>Colour</th><th class="num">Published</th><th class="num">Stories</th><th class="num">Pages</th><th>Status</th><th>Updated</th><th></th></tr></thead>
    <tbody @if($canManage && !request()->hasAny(['q','status'])) data-sortable data-sort-url="{{ route('admin.categories.reorder') }}" @endif>
    @forelse($categories as $c)
      <tr data-id="{{ $c->id }}">
        <td class="handle" title="Drag to reorder">{!! icon('grip-vertical') !!}</td>
        <td><div class="title-cell">
          <span class="thumb" style="width:32px;height:32px;{{ $c->visualUrl() ? 'background-image:url('.$c->visualUrl().');background-size:'.($c->visual_type==='icon'?'60%':'cover').';background-repeat:no-repeat;background-position:center' : '' }}">@unless($c->visualUrl()){!! icon('folder-tree') !!}@endunless</span>
          <div><a class="t1" href="{{ route('admin.stories.index', ['category' => $c->id]) }}">{{ $c->name_en }}</a> <span class="muted" lang="hi">{{ $c->name_hi }}</span> @if($c->is_sensitive)<x-admin.pill tone="amber">Sensitive</x-admin.pill>@endif<div class="t2">{{ \Illuminate\Support\Str::limit($c->description, 70) ?: '/'.$c->slug }}</div></div>
        </div></td>
        <td>@if($c->color_hex)<span class="swatch" style="background:{{ $c->color_hex }}"></span> <span class="mono muted">{{ $c->color_hex }}</span>@else<span class="muted">—</span>@endif</td>
        <td class="num"><a href="{{ route('admin.stories.index', ['category' => $c->id, 'status' => 'published']) }}">{{ $c->published_count }}</a></td>
        <td class="num"><a href="{{ route('admin.stories.index', ['category' => $c->id]) }}">{{ $c->total_count }}</a></td>
        <td class="num">{{ $c->pages_count }}</td>
        <td><x-admin.pill :tone="$c->is_active ? 'green' : 'neutral'">{{ $c->is_active ? 'On' : 'Hidden' }}</x-admin.pill></td>
        <td class="small muted nowrap">{{ fmt_date($c->updated_at) }}</td>
        <td class="right nowrap">
          @if($canManage)
          @php $payload = array_merge($c->only('id', 'name_en', 'name_hi', 'slug', 'description', 'visual_type', 'color_hex', 'is_sensitive'), ['status' => $c->is_active ? 'active' : 'inactive', 'visual' => $c->visualUrl()]); @endphp
          <button class="btn xs" @click='edit=@json($payload); open=true'>Edit</button>
          <form method="post" action="{{ route('admin.categories.toggle', $c) }}" class="hide" id="tg{{ $c->id }}" @if($c->is_active) data-confirm="Hide “{{ $c->name_en }}” on the website?" data-confirm-detail="Its chip and Explore tile disappear. Its {{ $c->total_count }} stories keep the category and stay published." @endif>@csrf</form>
          <button class="btn xs" form="tg{{ $c->id }}">{{ $c->is_active ? 'Hide' : 'Show' }}</button>
          @can('categories.delete')
            @if($c->total_count === 0 && $c->pages_count === 0)
            <form method="post" action="{{ route('admin.categories.destroy', $c) }}" style="display:inline" data-confirm="Delete “{{ $c->name_en }}”?" data-confirm-detail="The category has no stories and will be removed." data-confirm-password data-confirm-danger>@csrf @method('delete')<button class="btn xs danger">Delete</button></form>
            @endif
          @endcan
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="9"><x-admin.empty title="No categories match">Try a different search.</x-admin.empty></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</div>

@if($canManage)
<template x-if="open">
  <div>
  <div class="drawer-ov" @click="open=false"></div>
  <form class="drawer" method="post" enctype="multipart/form-data" :action="edit.id ? '{{ url('admin/categories') }}/' + edit.id : '{{ route('admin.categories.store') }}'">
    @csrf <template x-if="edit.id"><input type="hidden" name="_method" value="put"></template>
    <div class="modal-h"><h2 x-text="edit.id ? 'Edit category' : 'New category'"></h2><button type="button" class="icon-btn" @click="open=false" aria-label="Close">{!! icon('x') !!}</button></div>
    <div class="modal-b">
      <div class="grid g2"><div class="field"><label>Name (English) <span class="req">*</span></label><input name="name_en" x-model="edit.name_en" required minlength="2" maxlength="60"></div>
      <div class="field"><label>Name (हिंदी) <span class="req">*</span></label><input name="name_hi" x-model="edit.name_hi" required maxlength="60" lang="hi"></div></div>
      <div class="field"><label>Slug</label><input name="slug" x-model="edit.slug" placeholder="generated from the name"></div>
      <div class="field"><label>Description</label><textarea name="description" x-model="edit.description" maxlength="300" rows="3"></textarea></div>
      <div class="field"><label>Visual</label>
        <div class="row mb-2"><label class="cb"><input type="radio" name="visual_type" value="image" x-model="edit.visual_type"> Photo tile</label><label class="cb"><input type="radio" name="visual_type" value="icon" x-model="edit.visual_type"> Icon</label></div>
        <template x-if="edit.visual"><img :src="edit.visual" alt="" style="height:48px;border:1px solid var(--line);border-radius:4px;padding:4px;margin-bottom:6px"></template>
        <div x-show="edit.visual_type==='icon'"><input type="file" name="icon" accept=".svg,.png,.webp"><div class="help">SVG, PNG or WebP, square, up to 512 KB. SVGs are cleaned of scripts.</div></div>
        <div x-show="edit.visual_type==='image'"><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><div class="help">JPG, PNG or WebP, up to 4 MB. Used for the Explore and onboarding tiles.</div></div>
      </div>
      <div class="field"><label>Display colour</label><div class="row"><input type="color" x-model="edit.color_hex"><input name="color_hex" x-model="edit.color_hex" class="mono" style="max-width:120px" pattern="#[0-9A-Fa-f]{6}"></div>
        <div class="help" x-show="edit.color_hex && (parseInt(edit.color_hex.slice(1,3),16)*299+parseInt(edit.color_hex.slice(3,5),16)*587+parseInt(edit.color_hex.slice(5,7),16)*114)/1000 > 200">This colour is very light and may be hard to see on white.</div></div>
      <div class="field"><label>On the website</label><select name="status" x-model="edit.status"><option value="active">Shown</option><option value="inactive">Hidden</option></select></div>
      <label class="cb"><input type="checkbox" name="is_sensitive" value="1" x-model="edit.is_sensitive"> Sensitive: stories in this category always go to an editor</label>
    </div>
    <div class="modal-f"><button type="button" class="btn" @click="open=false">Cancel</button><button class="btn primary">Save category</button></div>
  </form>
  </div>
</template>
@endif
</div>
@endsection
