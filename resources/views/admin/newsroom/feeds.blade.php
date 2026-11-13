@extends('admin.layouts.app')
@section('title', 'Feeds')
@section('content')
<div x-data="{ open:false, edit:null, blank:{id:null,name:'',url:'',type:'rss',trusted:false,active:true} }">
<x-admin.ph title="Newsroom" sub="Agency and publisher feeds are fetched every 5 minutes, clustered into events and drafted by AI in English and Hindi.">
  <button class="btn primary" @click="edit=JSON.parse(JSON.stringify(blank)); open=true">{!! icon('plus', 14) !!} Add feed</button>
</x-admin.ph>
@include('admin.newsroom._tabs')
@if($pending)<div class="alert blue">{{ $pending }} fetched {{ \Illuminate\Support\Str::plural('item', $pending) }} waiting to be clustered and drafted. The pipeline runs every 5 minutes.</div>@endif
<div class="card">
  <div class="table-wrap"><table class="t">
    <thead><tr><th>Feed</th><th>Type</th><th class="num">Items (24 h)</th><th class="num">All items</th><th>Trusted</th><th>Active</th><th>Last fetched</th><th></th></tr></thead>
    <tbody>
    @forelse($feeds as $f)
      <tr>
        <td><div class="t1">{{ $f->name }}</div><div class="t2 trunc" style="max-width:360px">{{ $f->url }}</div></td>
        <td class="small">{{ $f->type === 'agency' ? 'Licensed agency' : 'Publisher RSS' }}</td>
        <td class="num">{{ $f->items_today }}</td><td class="num">{{ num($f->items_count) }}</td>
        <td><form method="post" action="{{ route('admin.newsroom.feeds.toggle', $f) }}" @if(! $f->trusted) data-confirm="Trust “{{ $f->name }}”?" data-confirm-detail="Low-risk stories drafted only from trusted feeds can auto-publish without an editor." @endif>@csrf<input type="hidden" name="field" value="trusted"><button class="btn xs {{ $f->trusted ? 'primary' : '' }}">{{ $f->trusted ? 'Trusted' : 'Not trusted' }}</button></form></td>
        <td><form method="post" action="{{ route('admin.newsroom.feeds.toggle', $f) }}">@csrf<input type="hidden" name="field" value="active"><button class="btn xs">{{ $f->active ? 'On' : 'Off' }}</button></form></td>
        <td class="small muted nowrap">{{ $f->last_fetched_at ? $f->last_fetched_at->diffForHumans() : 'never' }}</td>
        <td class="right nowrap">
          <form method="post" action="{{ route('admin.newsroom.feeds.fetch', $f) }}" style="display:inline">@csrf<button class="btn xs">{!! icon('refresh-cw', 12) !!} Fetch now</button></form>
          @php $payload = $f->only('id', 'name', 'url', 'type', 'trusted', 'active'); @endphp
          <button class="btn xs" @click="edit={{ \Illuminate\Support\Js::from($payload) }}; open=true">Edit</button>
          <form method="post" action="{{ route('admin.newsroom.feeds.destroy', $f) }}" style="display:inline" data-confirm="Remove “{{ $f->name }}”?" data-confirm-detail="Its fetched items are deleted. Stories already written from it stay." data-confirm-danger>@csrf @method('delete')<button class="btn xs danger">Remove</button></form>
        </td>
      </tr>
    @empty
      <tr><td colspan="8"><x-admin.empty title="No feeds yet">Add a licensed agency feed or a publisher’s RSS, Atom or JSON feed.</x-admin.empty></td></tr>
    @endforelse
    </tbody>
  </table></div>
</div>

<div class="grid g-main mt-4">
  <div class="card"><div class="card-h"><h2>Sources</h2><span class="meta">credited on stories</span></div>
    <div class="card-b flush"><table class="t compact"><tbody>
      @forelse($sources as $s)<tr><td>{{ $s->name }}</td><td class="small muted trunc" style="max-width:300px">{{ $s->url }}</td><td class="num small">{{ $s->stories_count }} stories</td></tr>
      @empty<tr><td class="muted">No sources yet.</td></tr>@endforelse
    </tbody></table></div></div>
  <form class="card" method="post" action="{{ route('admin.newsroom.sources.store') }}">@csrf
    <div class="card-h"><h2>Add source</h2></div>
    <div class="card-b"><x-admin.field label="Name" name="name" required><input name="name" required placeholder="PTI"></x-admin.field><x-admin.field label="Website" name="url"><input type="url" name="url" placeholder="https://"></x-admin.field></div>
    <div class="card-f"><button class="btn primary">Add</button></div>
  </form>
</div>

<template x-if="open">
  <div>
  <div class="drawer-ov" @click="open=false"></div>
  <form class="drawer" method="post" :action="edit.id ? '{{ url('admin/newsroom/feeds') }}/' + edit.id : '{{ route('admin.newsroom.feeds.store') }}'">
    @csrf <template x-if="edit.id"><input type="hidden" name="_method" value="put"></template>
    <div class="modal-h"><h2 x-text="edit.id ? 'Edit feed' : 'Add feed'"></h2><button type="button" class="icon-btn" @click="open=false" aria-label="Close">{!! icon('x') !!}</button></div>
    <div class="modal-b">
      <div class="field"><label>Name <span class="req">*</span></label><input name="name" x-model="edit.name" required maxlength="120"></div>
      <div class="field"><label>Feed URL <span class="req">*</span></label><input type="url" name="url" x-model="edit.url" required placeholder="RSS, Atom or JSON Feed"></div>
      <div class="field"><label>Type</label><select name="type" x-model="edit.type"><option value="agency">Licensed agency</option><option value="rss">Publisher RSS</option></select></div>
      <label class="cb"><input type="checkbox" name="trusted" value="1" x-model="edit.trusted"> Trusted: low-risk stories from it may auto-publish</label>
      <label class="cb mt-2"><input type="checkbox" name="active" value="1" x-model="edit.active"> Active</label>
    </div>
    <div class="modal-f"><button type="button" class="btn" @click="open=false">Cancel</button><button class="btn primary">Save</button></div>
  </form>
  </div>
</template>
</div>
@endsection
