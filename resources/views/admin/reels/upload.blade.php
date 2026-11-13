@extends('admin.layouts.app')
@section('title', 'Upload a reel')
@section('content')
<x-admin.ph title="Upload a reel" :crumbs="['Reels' => route('admin.reels.index')]" sub="Licensed agency footage or our own video. It is converted to 720p and 480p, gets a thumbnail, and waits for approval before it goes live." />
<form method="post" action="{{ route('admin.reels.store') }}" enctype="multipart/form-data" class="grid g2" x-data="{ busy: false }" @submit="busy = true">@csrf
  <div class="card"><div class="card-b stack">
    <x-admin.field label="Video" name="video" required :help="'MP4, MOV, WebM or MKV, up to '.$maxMb.' MB and '.$maxSeconds.' seconds. Vertical 9:16 works best; landscape video is fitted over a blurred background.'">
      <input id="f-video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-matroska" required>
    </x-admin.field>
    <x-admin.field label="Title" name="title" required help="Shown on the reel and in search results (max 200 characters).">
      <input id="f-title" name="title" value="{{ old('title') }}" maxlength="200" required>
    </x-admin.field>
    <x-admin.field label="Language" name="language" required>
      <select id="f-language" name="language"><option value="en" @selected(old('language') !== 'hi')>English</option><option value="hi" @selected(old('language') === 'hi')>Hindi</option></select>
    </x-admin.field>
  </div></div>
  <div class="card"><div class="card-b stack">
    <x-admin.field label="Story (optional)" name="story_id" help="Links “Read full story” on the reel. The page and category are taken from the story unless you choose them below.">
      <select id="f-story_id" name="story_id"><option value="">No story</option>@foreach($stories as $id => $title)<option value="{{ $id }}" @selected(old('story_id') == $id)>{{ \Illuminate\Support\Str::limit($title, 90) }}</option>@endforeach</select>
    </x-admin.field>
    <x-admin.field label="Page" name="page_id" help="The page shown on the reel (avatar, name, Follow).">
      <select id="f-page_id" name="page_id"><option value="">—</option>@foreach($pages as $p)<option value="{{ $p->id }}" @selected(old('page_id') == $p->id)>{{ $p->name }}</option>@endforeach</select>
    </x-admin.field>
    <x-admin.field label="Category" name="category_id">
      <select id="f-category_id" name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name_en }}</option>@endforeach</select>
    </x-admin.field>
  </div>
  <div class="card-f"><a href="{{ route('admin.reels.index') }}" class="btn ghost">Cancel</a><button class="btn primary" :disabled="busy"><span x-show="!busy">{!! icon('upload', 14) !!} Upload</span><span x-show="busy" x-cloak>Uploading…</span></button></div>
  </div>
</form>
@endsection
