@extends('admin.layouts.app')
@section('title', 'Auto-publish rules')
@section('content')
<x-admin.ph title="Newsroom" sub="When an AI draft may go live without an editor. Everything else waits in the approval queue." />
@include('admin.newsroom._tabs')
@unless($aiReady)<div class="alert amber"><span class="t">AI drafting is in sample mode</span><code>ANTHROPIC_API_KEY</code> is empty in <code>.env</code>, so drafts come from seeded samples and always go to an editor.</div>@endunless
<form method="post" action="{{ route('admin.newsroom.autopublish.save') }}" class="grid g2" data-confirm="Save auto-publish rules?" data-confirm-detail="The new rules apply to the next AI drafts.">@csrf @method('put')
  <div class="card">
    <div class="card-h"><h2>Always send to an editor</h2></div>
    <div class="card-b">
      <div class="lbl">Sensitive categories</div>
      <div class="stack-sm mb-3" style="max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:4px;padding:8px">
        @foreach($categories as $c)<label class="cb"><input type="checkbox" name="sensitive_categories[]" value="{{ $c->slug }}" @checked(in_array($c->slug, $s->sensitive_categories ?? []))> {{ $c->name_en }}</label>@endforeach
      </div>
      <x-admin.field label="Sensitive words and phrases" name="sensitive_keywords" help="One per line (or comma separated). Matched in the English and Hindi drafts and in the source text; whole words for English.">
        <textarea name="sensitive_keywords" rows="10">{{ implode("\n", $s->sensitive_keywords ?? []) }}</textarea>
      </x-admin.field>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Thresholds</h2></div>
      <div class="card-b">
        <x-admin.field label="Minimum confidence to auto-publish" name="confidence_threshold" help="0–1. The AI’s confidence minus 0.15 per missing fact and 0.3 when too much text is copied."><input type="number" step="0.01" min="0" max="1" name="confidence_threshold" value="{{ $s->confidence_threshold }}" required></x-admin.field>
        <x-admin.field label="Maximum copy score" name="copy_threshold" help="0–1. Share of the draft’s 5-word phrases found in the sources. Above this, an editor rewrites it."><input type="number" step="0.01" min="0" max="1" name="copy_threshold" value="{{ $s->copy_threshold }}" required></x-admin.field>
        <p class="small muted">A draft auto-publishes only when it is low risk, every fact is found in the sources, the copy score is under the limit, the confidence is high enough, and every source feed is <a href="{{ route('admin.newsroom.feeds') }}">trusted</a>.</p>
      </div>
      <div class="card-f"><button class="btn primary">Save rules</button></div>
    </div>
    <div class="card"><div class="card-h"><h2>Last 7 days</h2></div><div class="card-b">
      <div class="kpis" style="grid-template-columns:1fr 1fr">
        <div class="kpi"><div class="l">Auto-published</div><div class="v">{{ $stats['auto'] ?? 0 }}</div></div>
        <div class="kpi"><div class="l">Sent to an editor</div><div class="v">{{ $stats['approval'] ?? 0 }}</div></div>
      </div>
      <a class="small" href="{{ route('admin.newsroom.log') }}">See every decision →</a>
    </div></div>
  </div>
</form>
@endsection
