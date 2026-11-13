@extends('admin.layouts.app')
@section('title', 'Tags')
@section('content')
<x-admin.ph title="Tags" sub="Tags are added in the story editor and help staff search stories. Readers don’t see them. Rename a tag to an existing name to merge them." />
<div class="card">
  <form class="filters" method="get"><div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Search tags"></div></form>
  <table class="t"><thead><tr><th>Tag</th><th class="num">Stories</th><th></th></tr></thead><tbody>
  @forelse($tags as $t)
    <tr x-data="{e:false}"><td>
      <span x-show="!e"><a class="t1" href="{{ route('admin.stories.index', ['q' => $t->name]) }}">{{ $t->name }}</a></span>
      <form x-show="e" x-cloak method="post" action="{{ route('admin.tags.update', $t) }}" class="row">@csrf @method('put')<input name="name" value="{{ $t->name }}" style="max-width:260px"><button class="btn sm primary">Save</button><button type="button" class="btn sm" @click="e=false">Cancel</button></form>
    </td><td class="num">{{ $t->stories_count }}</td>
    <td class="right"><button class="btn xs" @click="e=true">Rename</button>
      <form method="post" action="{{ route('admin.tags.destroy', $t) }}" style="display:inline" data-confirm="Remove tag “{{ $t->name }}”?" data-confirm-detail="It will be removed from {{ $t->stories_count }} stories." data-confirm-danger>@csrf @method('delete')<button class="btn xs danger">Remove</button></form></td></tr>
  @empty <tr><td colspan="3"><x-admin.empty title="No tags yet">Add tags while writing a story.</x-admin.empty></td></tr> @endforelse
  </tbody></table>
  {{ $tags->links() }}
</div>
@endsection
