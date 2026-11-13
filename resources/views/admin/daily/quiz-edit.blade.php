@extends('admin.layouts.app')
@section('title', 'Daily quiz')
@section('content')
@php
  $qs = old('questions', $questions->map(fn ($x) => ['question' => $x->question, 'options' => $x->options, 'correct_index' => $x->correct_index, 'explanation' => $x->explanation, 'story_id' => $x->story_id])->all());
  if (! $qs) { $qs = [['question' => '', 'options' => ['', '', '', ''], 'correct_index' => 0, 'explanation' => '', 'story_id' => null]]; }
@endphp
<x-admin.ph :title="$q->exists ? 'Quiz · '.$q->date->format('D, j M Y') : 'New quiz'" :crumbs="['Daily quiz' => route('admin.daily.quizzes')]" sub="Up to five questions, four options each. Readers get 30 seconds per question." />
<form method="post" action="{{ $q->exists ? route('admin.daily.quizzes.update', $q) : route('admin.daily.quizzes.store') }}" x-data="{ qs: @js(array_values($qs)) }" style="max-width:900px">
  @csrf @if($q->exists) @method('put') @endif
  <div class="card mb-4"><div class="card-b row wrap" style="gap:16px">
    <x-admin.field label="Date" name="date" required><input type="date" name="date" value="{{ old('date', $q->date?->toDateString()) }}" required></x-admin.field>
    <x-admin.field label="Status" name="status"><select name="status">@foreach(['draft' => 'Draft (hidden)', 'approved' => 'Approved', 'published' => 'Published'] as $k => $l)<option value="{{ $k }}" @selected(old('status', $q->status) === $k)>{{ $l }}</option>@endforeach</select></x-admin.field>
  </div></div>
  <template x-for="(item, i) in qs" :key="i">
    <div class="card mb-3"><div class="card-h"><h2 x-text="'Question ' + (i + 1)"></h2><button type="button" class="link-btn small" x-show="qs.length > 1" @click="qs.splice(i, 1)">Remove</button></div>
      <div class="card-b">
        <div class="field"><label>Question <span class="req">*</span></label><input :name="`questions[${i}][question]`" x-model="item.question" required maxlength="255"></div>
        <div class="form-grid">
          <template x-for="(o, k) in ['A','B','C','D']" :key="k">
            <div class="field"><label class="cb"><input type="radio" :name="`questions[${i}][correct_index]`" :value="k" :checked="item.correct_index == k" @change="item.correct_index = k"> <span x-text="o"></span> <span class="muted small" x-show="item.correct_index == k">(correct)</span></label>
              <input :name="`questions[${i}][options][${k}]`" x-model="item.options[k]" required maxlength="120"></div>
          </template>
        </div>
        <div class="field"><label>Explanation</label><textarea :name="`questions[${i}][explanation]`" x-model="item.explanation" rows="2" placeholder="Shown after the reader answers"></textarea></div>
        <div class="field"><label>From story</label><select :name="`questions[${i}][story_id]`" x-model="item.story_id"><option value="">None</option>@foreach($stories as $id => $title)<option value="{{ $id }}">{{ \Illuminate\Support\Str::limit($title, 100) }}</option>@endforeach</select></div>
      </div></div>
  </template>
  @if($errors->any())<div class="alert red"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="row"><button type="button" class="btn" x-show="qs.length < 5" @click="qs.push({question:'', options:['','','',''], correct_index:0, explanation:'', story_id:''})">{!! icon('plus', 14) !!} Add question</button>
    <span style="flex:1"></span><a href="{{ route('admin.daily.quizzes') }}" class="btn">Cancel</a><button class="btn primary">Save quiz</button></div>
</form>
@endsection
