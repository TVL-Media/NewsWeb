@extends('admin.layouts.app')
@section('title', 'Review translation')
@section('content')
@php
  $labels = ['headline' => 'Headline', 'card_text' => 'Card text', 'key_point_0' => 'Key point 1', 'key_point_1' => 'Key point 2', 'key_point_2' => 'Key point 3', 'why_it_matters' => 'Why it matters', 'simple_text' => 'Explain simply', 'full_text' => 'Full story'];
  $to = $job->target_language; $from = $job->source_language;
  $open = $job->status === 'ai_completed';
  $result = $job->result ?? [];
@endphp
<x-admin.ph :title="'Translation into '.\App\Models\TranslationJob::LANGS[$to]" :crumbs="['Translations' => route('admin.translations.index')]" :sub="'Story: '.$s->title()">
  <x-admin.pill :tone="['ai_completed' => 'amber', 'approved' => 'green', 'failed' => 'red', 'rejected' => 'neutral'][$job->status] ?? 'blue'">{{ ['ai_completed' => 'Waiting for review', 'approved' => 'Approved', 'failed' => 'Failed', 'rejected' => 'Discarded', 'queued' => 'Queued', 'running' => 'Translating…'][$job->status] ?? $job->status }}</x-admin.pill>
  <a class="btn" href="{{ route('admin.stories.edit', $s) }}">Open story</a>
</x-admin.ph>
@if($current && $open)<div class="alert amber">This story already has a {{ \App\Models\TranslationJob::LANGS[$to] }} version. Approving replaces it.</div>@endif
@if($job->error)<div class="alert red">{{ $job->error }}</div>@endif
@if($glossary->isNotEmpty())<div class="alert"><span class="t">Glossary</span>@foreach($glossary as $g)<span class="chip" style="margin:2px">{{ $g->source_term }} → {{ $g->do_not_translate ? 'keep as is' : $g->target_term }}</span>@endforeach</div>@endif

<form method="post" action="{{ route('admin.translations.save', $job) }}" x-data="{ busy:'' }">@csrf @method('put')
<div class="card">
  <div class="table-wrap"><table class="t" style="table-layout:fixed">
    <thead><tr><th style="width:140px"></th><th>{{ \App\Models\TranslationJob::LANGS[$from] }} (original)</th><th>{{ \App\Models\TranslationJob::LANGS[$to] }} (AI, edit freely)</th></tr></thead>
    <tbody>
    @foreach($labels as $f => $l)
      @continue(! isset($src[$f]) && ! isset($result[$f]))
      <tr style="vertical-align:top">
        <td class="small strong">{{ $l }}@if($open)<br><button type="button" class="link-btn xsmall" @click="busy='{{ $f }}'; fetch('{{ route('admin.translations.field', $job) }}', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}, body: JSON.stringify({field:'{{ $f }}'})}).then(r=>r.json()).then(j=>{ if(j.text!==undefined){ $refs['f_{{ $f }}'].value=j.text } else toast(j.message||'Failed','err'); busy=''; })" x-text="busy==='{{ $f }}' ? 'Translating…' : 'Re-run AI'"></button>@endif</td>
        <td class="small" lang="{{ $from }}" style="white-space:pre-line">{{ $src[$f] ?? '' }}</td>
        <td lang="{{ $to }}">
          @if(in_array($f, ['headline', 'key_point_0', 'key_point_1', 'key_point_2']))
            <input name="f[{{ $f }}]" x-ref="f_{{ $f }}" value="{{ old('f.'.$f, $result[$f] ?? '') }}" @disabled(! $open)>
          @else
            <textarea name="f[{{ $f }}]" x-ref="f_{{ $f }}" rows="{{ $f === 'full_text' ? 10 : 3 }}" @disabled(! $open) @if($f === 'card_text') data-count="60" data-count-mode="words" @endif>{{ old('f.'.$f, $result[$f] ?? '') }}</textarea>
          @endif
          @error('f.'.$f)<div class="err">{{ $message }}</div>@enderror
        </td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @if($open)
  <div class="card-f">
    <button class="btn" name="then" value="">Save edits</button>
    <button class="btn primary" name="then" value="approve">Save &amp; approve</button>
    <button type="submit" form="reject" class="btn ghost" style="margin-left:auto">Discard</button>
  </div>
  @endif
</div>
</form>
<form id="reject" method="post" action="{{ route('admin.translations.reject', $job) }}" data-confirm="Discard this translation?" data-confirm-detail="The story is not changed.">@csrf</form>
@endsection
