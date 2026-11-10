@extends('admin.layouts.app')
@section('title', $poll->exists ? 'Edit poll' : 'New poll')
@section('content')
@php $opts = old('options', $poll->exists ? $poll->options->pluck('label')->all() : ['', '']); @endphp
<x-admin.ph :title="$poll->exists ? 'Edit poll' : 'New poll'" :crumbs="['Polls' => route('admin.polls.index')]" sub="Readers vote once. Results show on the website after they vote." />
@if($locked)<div class="alert amber">Readers already voted, so the question and options are locked. You can still change the end time and the linked story.</div>@endif
<form method="post" action="{{ $poll->exists ? route('admin.polls.update', $poll) : route('admin.polls.store') }}" class="card" style="max-width:760px" x-data="{ opts: @js(array_values($opts)) }">
  @csrf @if($poll->exists) @method('put') @endif
  <div class="card-b">
    <x-admin.field label="Question" name="question" required help="10–150 characters."><input name="question" value="{{ old('question', $poll->question) }}" maxlength="150" data-count="150" @disabled($locked) required></x-admin.field>
    <div class="field"><label class="lbl">Options <span class="req">*</span></label>
      <template x-for="(o, i) in opts" :key="i"><div class="row mb-1"><input :name="`options[${i}]`" x-model="opts[i]" maxlength="60" required @disabled($locked) :placeholder="'Option ' + (i + 1)"><button type="button" class="icon-btn" x-show="opts.length > 2 && {{ $locked ? 'false' : 'true' }}" @click="opts.splice(i, 1)" aria-label="Remove">{!! icon('x') !!}</button></div></template>
      @unless($locked)<button type="button" class="btn sm" x-show="opts.length < 4" @click="opts.push('')">{!! icon('plus', 14) !!} Add option</button>@endunless
      <div class="help">2 to 4 options.</div>@error('options')<div class="err">{{ $message }}</div>@enderror @error('options.*')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="form-grid">
      <x-admin.field label="Show with story" name="story_id" class="full" help="The poll appears in the story’s side rail."><select name="story_id"><option value="">None</option>@foreach($stories as $id => $t)<option value="{{ $id }}" @selected(old('story_id', $poll->story_id) == $id)>{{ \Illuminate\Support\Str::limit($t, 100) }}</option>@endforeach</select></x-admin.field>
      <x-admin.field label="Category" name="category_id"><select name="category_id"><option value="">None</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $poll->category_id) == $c->id)>{{ $c->name_en }}</option>@endforeach</select></x-admin.field>
      <x-admin.field label="Ends" name="ends_at" :help="tz_label()"><input type="datetime-local" name="ends_at" value="{{ old('ends_at', to_local_input($poll->ends_at)) }}"></x-admin.field>
    </div>
  </div>
  <div class="card-f">
    <a href="{{ route('admin.polls.index') }}" class="btn">Cancel</a>
    @if($poll->exists)<button class="btn primary">Save</button>
    @else<button class="btn" name="go" value="draft">Save for later</button><button class="btn primary" name="go" value="live">Put live now</button>@endif
  </div>
</form>
@endsection
