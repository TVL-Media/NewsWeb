@extends('admin.layouts.app')
@section('title', 'Stories')
@section('content')
@php
  $me = auth('admin')->user();
  $tabs = ['' => 'All'] + \App\Models\Story::STATUSES;
  $total = $counts->sum();
@endphp
<x-admin.ph title="Stories" sub="Every story on the website, in English and Hindi: written by the newsroom or drafted by AI from the feeds.">
  @can('content.create')<a href="{{ route('admin.stories.create') }}" class="btn primary">{!! icon('plus', 14) !!} Write a story</a>@endcan
</x-admin.ph>

<nav class="tabs" aria-label="Status">
  @foreach($tabs as $k => $label)
    @if($k === '' || ($counts[$k] ?? 0) > 0 || in_array($k, ['draft', 'pending_approval', 'published']))
    <a href="{{ route('admin.stories.index', array_merge(request()->except(['status','page']), $k ? ['status' => $k] : [])) }}" class="{{ request('status', '') === $k ? 'on' : '' }}">{{ $label }} <span class="n">{{ $k === '' ? $total : ($counts[$k] ?? 0) }}</span></a>
    @endif
  @endforeach
</nav>

<div class="card" x-data="{ sel: [], all:false, toggleAll(ids){ this.all=!this.all; this.sel = this.all ? ids : []; } }">
  <form class="filters" method="get">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search headline, tag or ID"></div>
    <select name="category" style="width:auto" onchange="this.form.submit()"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category')==$c->id)>{{ $c->name_en }}{{ $c->is_active ? '' : ' (hidden)' }}</option>@endforeach</select>
    <select name="mode" style="width:auto" onchange="this.form.submit()"><option value="">AI &amp; newsroom</option><option value="auto" @selected(request('mode')==='auto')>Auto-published</option><option value="manual" @selected(request('mode')==='manual')>Published by an editor</option></select>
    <div x-data="{more: {{ request()->hasAny(['author','from','to','page','breaking','no_hindi']) ? 'true' : 'false' }} }" class="row wrap">
      <button type="button" class="btn sm ghost" @click="more=!more">{!! icon('filter', 14) !!} More filters</button>
      <template x-if="more"><span class="row wrap">
        <select name="page" style="width:auto"><option value="">Any page</option>@foreach($pages as $p)<option value="{{ $p->id }}" @selected(request('page')==$p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="author" style="width:auto"><option value="">Any writer</option>@foreach($authors as $a)<option value="{{ $a->id }}" @selected(request('author')==$a->id)>{{ $a->name }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" style="width:auto" title="Created from"><input type="date" name="to" value="{{ request('to') }}" style="width:auto" title="Created to">
        <label class="cb"><input type="checkbox" name="breaking" value="1" @checked(request('breaking'))> Breaking</label>
        <label class="cb"><input type="checkbox" name="no_hindi" value="1" @checked(request('no_hindi'))> No Hindi yet</label>
        <button class="btn sm">Apply</button>
      </span></template>
    </div>
    <select name="sort" style="width:auto;margin-left:auto" onchange="this.form.submit()" aria-label="Sort"><option value="updated">Recently updated</option><option value="created" @selected(request('sort')==='created')>Newest</option><option value="published" @selected(request('sort')==='published')>Recently published</option><option value="importance" @selected(request('sort')==='importance')>Most important</option><option value="likes" @selected(request('sort')==='likes')>Most liked</option></select>
    @if(request()->except(['page','status','per_page']))<a href="{{ route('admin.stories.index', request()->only('status')) }}" class="btn ghost sm">Reset</a>@endif
  </form>

  @if($canBulk)
  <form method="post" action="{{ route('admin.stories.bulk') }}" class="bulk" x-show="sel.length" x-cloak x-data="{op:''}" data-confirm="Apply to the selected stories?">
    @csrf
    <template x-for="id in sel"><input type="hidden" name="ids[]" :value="id"></template>
    <strong x-text="sel.length + ' selected'"></strong>
    <select name="op" x-model="op" style="width:auto;height:30px" required><option value="">Choose action…</option>
      @can('content.publish')<option value="unpublish">Unpublish</option>@endcan
      @can('content.edit.any')<option value="category">Move to category</option><option value="importance">Set importance</option><option value="tag">Add tag</option>@endcan
      @can('content.submit')<option value="submit">Submit for review</option>@endcan
      @if($me->isSuperAdmin())<option value="delete">Delete permanently</option>@endif
    </select>
    <select name="category_id" x-show="op==='category'" style="width:auto;height:30px">@foreach($categories->where('is_active', true) as $c)<option value="{{ $c->id }}">{{ $c->name_en }}</option>@endforeach</select>
    <input type="number" name="importance" min="1" max="10" value="5" x-show="op==='importance'" style="width:70px;height:30px">
    <input name="tag" x-show="op==='tag'" placeholder="Tag name" style="width:180px;height:30px">
    <button class="btn sm primary">Apply</button><button type="button" class="btn sm ghost" @click="sel=[];all=false">Clear</button>
  </form>
  @endif

  <div class="table-wrap">
  <table class="t">
    <thead><tr>
      @if($canBulk)<th style="width:34px"><input type="checkbox" class="check" @click='toggleAll(@json($items->pluck("id")))' :checked="all" aria-label="Select all"></th>@endif
      <th>Headline</th><th>Category</th><th>Lang</th><th>By</th><th class="num">Imp.</th><th class="num">Likes</th><th>Published</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    @forelse($items as $s)
      @php $hasHi = $s->versions->contains('language', 'hi'); @endphp
      <tr :class="sel.includes({{ $s->id }}) && 'sel'">
        @if($canBulk)<td><input type="checkbox" class="check" value="{{ $s->id }}" x-model.number="sel" aria-label="Select"></td>@endif
        <td><div class="title-cell">
          <span class="thumb" @if($s->photo_path) style="background-image:url('{{ $s->photoUrl() }}')" @endif>@unless($s->photo_path){!! icon('newspaper') !!}@endunless</span>
          <div style="min-width:0"><a href="{{ route('admin.stories.edit', $s) }}" class="t1">{{ \Illuminate\Support\Str::limit($s->title(), 90) }}</a>
            <div class="t2">#{{ $s->id }} @if($s->is_breaking) · <strong class="red">breaking</strong>@endif @if($s->aiCheck?->risk_level === 'sensitive') · <span class="red">sensitive</span>@endif @if($s->correction_label) · corrected @endif</div></div>
        </div></td>
        <td class="small nowrap">@if($s->category)<span class="swatch" style="background:{{ $s->category->color_hex ?: '#D7141E' }}"></span> {{ $s->category->name_en }}@else<span class="red">None</span>@endif</td>
        <td class="small nowrap">EN{!! $hasHi ? ' · <span lang="hi">हि</span>' : ' <span class="muted">· no HI</span>' !!}</td>
        <td class="small nowrap">{{ $s->author?->name ?? ($s->publish_mode === 'auto' || ! $s->author_id ? 'AI draft' : '—') }}</td>
        <td class="num">{{ $s->importance }}</td>
        <td class="num">{{ num($s->likes_count) }}</td>
        <td class="small nowrap">@if($s->status === 'scheduled'){!! icon('clock', 13) !!} {{ fmt_dt($s->scheduled_at) }}@else{{ fmt_dt($s->published_at) }}@endif</td>
        <td><x-admin.status :content="$s" />@if($s->publish_mode === 'auto' && $s->isLive()) <span class="pill blue">Auto</span>@endif</td>
        <td class="right">
          <div class="dd" x-data="{o:false}" @click.outside="o=false">
            <button class="icon-btn" @click="o=!o" aria-label="Actions">{!! icon('more-horizontal') !!}</button>
            <div class="dd-menu" x-show="o" x-cloak>
              <a href="{{ route('admin.stories.edit', $s) }}">{!! icon('pencil') !!} Edit / view</a>
              @if($s->isLive())<a href="{{ route('story.show', $s) }}" target="_blank">{!! icon('external-link') !!} Open on site</a>@else<a href="{{ route('admin.stories.preview', $s) }}" target="_blank">{!! icon('eye') !!} Preview</a>@endif
              <a href="{{ route('admin.stories.history', $s) }}">{!! icon('history') !!} History</a>
              @if($s->status === 'pending_approval')<a href="{{ route('admin.review.show', $s) }}">{!! icon('check-square') !!} Review</a>@endif
              @can('content.publish')
                @if($s->isLive())
                  <div class="sep"></div>
                  <form method="post" action="{{ route('admin.stories.unpublish', $s) }}" data-confirm="Unpublish this story?" data-confirm-detail="It disappears from the website straight away." data-confirm-reason="optional">@csrf<button>{!! icon('eye-off') !!} Unpublish</button></form>
                @endif
              @endcan
              @can('content.delete')
                <div class="sep"></div>
                <form method="post" action="{{ route('admin.stories.destroy', $s) }}" data-confirm="Delete “{{ \Illuminate\Support\Str::limit($s->title(), 50) }}”?" data-confirm-detail="This permanently deletes the story in both languages, with its comments and likes. It cannot be undone." data-confirm-word="DELETE" data-confirm-password data-confirm-danger data-confirm-reason="optional">@csrf @method('delete')<button class="danger">{!! icon('trash-2') !!} Delete</button></form>
              @endcan
            </div>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="10"><x-admin.empty title="No stories match these filters">@can('content.create')<a href="{{ route('admin.stories.create') }}">Write a story</a> or @endcan<a href="{{ route('admin.stories.index') }}">clear the filters</a>.</x-admin.empty></td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  {{ $items->links() }}
</div>
@endsection
