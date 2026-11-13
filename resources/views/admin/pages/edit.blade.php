@extends('admin.layouts.app')
@section('title', $p->exists ? $p->name : 'New page')
@section('content')
@php $up = old('upcoming', $p->upcoming ?? []); $up = array_values(array_pad($up, max(3, count($up) + 1), ['date' => '', 'title' => '', 'subtitle' => ''])); @endphp
<x-admin.ph :title="$p->exists ? $p->name : 'New page'" :crumbs="['Pages' => route('admin.pages.index')]">
  @if($p->exists)<a class="btn" href="{{ route('page.show', $p) }}" target="_blank">{!! icon('external-link', 14) !!} Open on site</a>@endif
</x-admin.ph>
<form method="post" enctype="multipart/form-data" action="{{ $p->exists ? route('admin.pages.update', $p) : route('admin.pages.store') }}" class="grid g2">
  @csrf @if($p->exists) @method('put') @endif
  <div class="card"><div class="card-h"><h2>Page</h2></div><div class="card-b">
    <div class="form-grid">
      <x-admin.field label="Name" name="name" required><input name="name" value="{{ old('name', $p->getAttributes()['name'] ?? '') }}" required maxlength="80"></x-admin.field>
      <x-admin.field label="Name (हिंदी)" name="name_hi"><input name="name_hi" value="{{ old('name_hi', $p->name_hi) }}" maxlength="80" lang="hi"></x-admin.field>
      <x-admin.field label="Slug" name="slug" help="Generated from the name if empty."><input name="slug" value="{{ old('slug', $p->slug) }}" class="mono"></x-admin.field>
      <x-admin.field label="Type" name="type"><select name="type">@foreach(['topic' => 'Topic', 'city' => 'City', 'show' => 'Show'] as $k => $l)<option value="{{ $k }}" @selected(old('type', $p->type) === $k)>{{ $l }}</option>@endforeach</select></x-admin.field>
      <x-admin.field label="Category" name="category_id"><select name="category_id"><option value="">None</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $p->category_id) == $c->id)>{{ $c->name_en }}</option>@endforeach</select></x-admin.field>
      <x-admin.field label="City" name="city_id"><select name="city_id"><option value="">None</option>@foreach($cities as $c)<option value="{{ $c->id }}" @selected(old('city_id', $p->city_id) == $c->id)>{{ $c->name_en }}</option>@endforeach</select></x-admin.field>
      <x-admin.field label="Desk label" name="desk_label" help="Shown in the breadcrumb, e.g. SPORTS DESK."><input name="desk_label" value="{{ old('desk_label', $p->desk_label) }}"></x-admin.field>
      <div class="field"><label class="cb" style="margin-top:28px"><input type="checkbox" name="verified" value="1" @checked(old('verified', $p->verified))> Verified page</label></div>
      <x-admin.field label="Description" name="description" class="full"><textarea name="description" rows="3">{{ old('description', $p->description) }}</textarea></x-admin.field>
    </div>
    <div class="form-grid">
      <div class="field"><label>Avatar</label>@if($p->avatar_path)<img src="{{ $p->avatarUrl() }}" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;display:block;margin-bottom:6px">@endif<input type="file" name="avatar" accept="image/*"></div>
      <div class="field"><label>Cover (wide)</label>@if($p->cover_path)<img src="{{ $p->coverUrl() }}" alt="" style="max-height:56px;border-radius:4px;display:block;margin-bottom:6px">@endif<input type="file" name="cover" accept="image/*"></div>
    </div>
  </div><div class="card-f">@if($p->exists)<button type="submit" form="del" class="btn danger" style="margin-right:auto">Delete</button>@endif<button class="btn primary">Save page</button></div></div>

  <div class="stack">
    <div class="card"><div class="card-h"><h2>Result strip</h2><span class="meta">black bar on the page · leave empty to hide</span></div><div class="card-b">
      <div class="form-grid">
        <x-admin.field label="Label" name="pinned_label"><input name="pinned_label" value="{{ old('pinned_label', $p->pinned_label) }}" placeholder="RESULT"></x-admin.field>
        <x-admin.field label="Text" name="pinned_text"><input name="pinned_text" value="{{ old('pinned_text', $p->pinned_text) }}" placeholder="IND 312 & 148/3 beat NZ…"></x-admin.field>
        <x-admin.field label="“Match report” story" name="pinned_story_id" class="full"><select name="pinned_story_id"><option value="">None</option>@foreach($stories as $id => $t)<option value="{{ $id }}" @selected(old('pinned_story_id', $p->pinned_story_id) == $id)>{{ \Illuminate\Support\Str::limit($t, 90) }}</option>@endforeach</select></x-admin.field>
      </div>
    </div></div>
    <div class="card"><div class="card-h"><h2>Upcoming</h2><span class="meta">date tiles in the right rail</span></div><div class="card-b">
      @foreach($up as $i => $u)
        <div class="row mb-2"><input type="date" name="upcoming[{{ $i }}][date]" value="{{ $u['date'] ?? '' }}" style="max-width:160px"><input name="upcoming[{{ $i }}][title]" value="{{ $u['title'] ?? '' }}" placeholder="Title"><input name="upcoming[{{ $i }}][subtitle]" value="{{ $u['subtitle'] ?? '' }}" placeholder="Subtitle"></div>
      @endforeach
      <div class="help">Rows without a date and title are ignored.</div>
    </div></div>
  </div>
</form>
@if($p->exists)<form id="del" method="post" action="{{ route('admin.pages.destroy', $p) }}" data-confirm="Delete “{{ $p->name }}”?" data-confirm-detail="Readers who follow it stop following it. Pages with stories can’t be deleted." data-confirm-danger>@csrf @method('delete')</form>@endif
@endsection
