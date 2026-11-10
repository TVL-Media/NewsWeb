@extends('admin.layouts.app')
@section('title', 'Morning 5')
@section('content')
@php $ids = old('story_ids', $b->story_ids ?? []); @endphp
<x-admin.ph :title="$b->exists ? 'Morning 5 · '.$b->date->format('D, j M Y') : 'New Morning 5'" :crumbs="['Morning 5' => route('admin.daily.briefings')]" sub="Five stories from the last three days, in the order they play. Drag to reorder." />
<form method="post" action="{{ $b->exists ? route('admin.daily.briefings.update', $b) : route('admin.daily.briefings.store') }}" class="card" style="max-width:820px" x-data="{ ids: @js(array_pad(array_map('strval', $ids), 5, '')) }">
  @csrf @if($b->exists) @method('put') @endif
  <div class="card-b">
    <x-admin.field label="Date" name="date" required><input type="date" name="date" value="{{ old('date', $b->date?->toDateString()) }}" required style="max-width:200px"></x-admin.field>
    <div class="lbl">Stories <span class="req">*</span></div>
    <div class="stack-sm" x-ref="list" x-init="Sortable.create($refs.list, { handle: '.handle', animation: 150, onEnd: e => { const it = ids.splice(e.oldIndex, 1)[0]; ids.splice(e.newIndex, 0, it); } })">
      <template x-for="(id, i) in ids" :key="i + '-' + id">
        <div class="row"><span class="handle" title="Drag">{!! icon('grip-vertical') !!}</span><strong class="small" x-text="i + 1" style="width:16px"></strong>
          <select :name="`story_ids[${i}]`" x-model="ids[i]" required style="flex:1"><option value="">Choose a story…</option>@foreach($stories as $id => $title)<option value="{{ $id }}">{{ \Illuminate\Support\Str::limit($title, 110) }}</option>@endforeach</select></div>
      </template>
    </div>
    @error('story_ids')<div class="err">{{ $message }}</div>@enderror @error('story_ids.*')<div class="err">{{ $message }}</div>@enderror @error('date')<div class="err">{{ $message }}</div>@enderror
    @if($stories->isEmpty())<div class="alert amber mt-3">No stories were published in the last three days.</div>@endif
  </div>
  <div class="card-f"><a href="{{ route('admin.daily.briefings') }}" class="btn">Cancel</a><button class="btn primary">Save and rebuild audio</button></div>
</form>
@endsection
