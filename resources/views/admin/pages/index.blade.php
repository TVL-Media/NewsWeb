@extends('admin.layouts.app')
@section('title', 'Pages')
@section('content')
<x-admin.ph title="Pages" sub="Followable pages on the website. Topic pages back the Explore tiles; city pages are followed automatically by readers in that city.">
  <a href="{{ route('admin.pages.create') }}" class="btn primary">{!! icon('plus', 14) !!} New page</a>
</x-admin.ph>
<div class="card">
  <form class="filters" method="get">
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search pages"></div>
    <select name="type" style="width:auto" onchange="this.form.submit()"><option value="">All types</option>@foreach(['topic' => 'Topics', 'city' => 'Cities', 'show' => 'Shows'] as $k => $l)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $l }}</option>@endforeach</select>
  </form>
  <div class="table-wrap"><table class="t">
    <thead><tr><th>Page</th><th>Type</th><th>Category / city</th><th class="num">Followers</th><th class="num">Stories</th><th></th></tr></thead>
    <tbody>
    @forelse($pages as $p)
      <tr>
        <td><div class="title-cell"><span class="thumb" style="border-radius:50%;{{ $p->avatar_path ? 'background-image:url('.$p->avatarUrl().')' : '' }}">@unless($p->avatar_path){!! icon('map-pin') !!}@endunless</span>
          <div><a class="t1" href="{{ route('admin.pages.edit', $p) }}">{{ $p->name }}</a> @if($p->verified){!! icon('shield-check', 13, 'red') !!}@endif @if($p->name_hi)<span class="muted small" lang="hi">{{ $p->name_hi }}</span>@endif<div class="t2">/page/{{ $p->slug }}{{ $p->desk_label ? ' · '.$p->desk_label : '' }}</div></div></div></td>
        <td class="small">{{ ucfirst($p->type) }}</td>
        <td class="small">{{ $p->category?->name_en ?? $p->city?->name_en ?? '—' }}</td>
        <td class="num">{{ num($p->followers_count) }}</td>
        <td class="num"><a href="{{ route('admin.stories.index', ['page' => $p->id]) }}">{{ $p->stories_count }}</a></td>
        <td class="right nowrap"><a class="btn xs" href="{{ route('page.show', $p) }}" target="_blank">Open</a> <a class="btn xs" href="{{ route('admin.pages.edit', $p) }}">Edit</a></td>
      </tr>
    @empty <tr><td colspan="6"><x-admin.empty title="No pages match" /></td></tr>@endforelse
    </tbody>
  </table></div>
  {{ $pages->links() }}
</div>
@endsection
