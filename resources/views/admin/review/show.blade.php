@extends('admin.layouts.app')
@section('title', 'Review: '.\Illuminate\Support\Str::limit($s->title(), 40))
@section('content')
@php
  $me = auth('admin')->user();
  $waiting = $s->status === 'pending_approval';
  $own = $s->author_id === $me->id && ! $me->isSuperAdmin();
  $ck = $s->aiCheck;
  $en = $s->versions->firstWhere('language', 'en'); $hi = $s->versions->firstWhere('language', 'hi');
@endphp
<x-admin.ph :title="$s->title()" :crumbs="['Approval queue' => route('admin.review.index')]" :sub="($s->author ? 'Written by '.$s->author->name : 'AI draft from the feeds').' · '.($s->category?->name_en ?? 'no category').' · waiting since '.fmt_dt($s->submitted_at ?? $s->updated_at)">
  <x-admin.status :content="$s" />
  <a class="btn" href="{{ route('admin.stories.edit', $s) }}">{!! icon('pencil', 14) !!} Edit then publish</a>
</x-admin.ph>
<div class="grid g2">
  <div class="stack">
    @if($ck)
    <div class="card">
      <div class="card-h"><h2>AI checks</h2><span class="meta">{{ fmt_dt($ck->created_at) }}</span></div>
      <div class="card-b">
        <div class="kpis" style="grid-template-columns:repeat(4,1fr)">
          <div class="kpi"><div class="l">Risk</div><div class="v {{ $ck->risk_level === 'sensitive' ? 'red' : '' }}">{{ ucfirst($ck->risk_level) }}</div></div>
          <div class="kpi"><div class="l">Confidence</div><div class="v">{{ number_format($ck->confidence, 2) }}</div></div>
          <div class="kpi"><div class="l">Copy score</div><div class="v">{{ number_format($ck->copy_score, 2) }}</div></div>
          <div class="kpi"><div class="l">Importance</div><div class="v">{{ $s->importance }}</div></div>
        </div>
        @if($ck->reasons)<div class="alert red mt-3" style="margin-bottom:8px"><span class="t">Why it needs an editor</span><ul>@foreach($ck->reasons as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
        @if($ck->sensitive_reasons)<div class="small"><strong>Sensitive because:</strong> {{ implode(' · ', $ck->sensitive_reasons) }}</div>@endif
        <div class="small mt-1"><strong>Facts not found in the sources:</strong>
          @if($ck->fact_mismatches)<ul>@foreach($ck->fact_mismatches as $m)<li>{{ ucfirst($m['type'] ?? '') }}: {{ $m['value'] ?? '' }}</li>@endforeach</ul>@else<span class="green">none, every name, number and date was found</span>@endif</div>
      </div>
    </div>
    @endif

    @foreach(['en' => ['English', $en], 'hi' => ['हिंदी', $hi]] as $lang => [$label, $v])
    <div class="card" x-data="{ open: {{ $lang === 'en' ? 'true' : 'false' }} }">
      <div class="card-h" style="cursor:pointer" @click="open=!open"><h2>Draft · {{ $label }}</h2>@if($v?->translation_status === 'ai')<x-admin.pill tone="amber">Machine translation</x-admin.pill>@endif<span class="meta" x-text="open ? 'hide' : 'show'"></span></div>
      <div class="card-b" x-show="open" @if($lang === 'hi') x-cloak @endif lang="{{ $lang }}">
        @if(! $v)<p class="muted">No {{ $label }} version.</p>@else
          @if($lang === 'en' && $s->photo_path)<img src="{{ $s->photoUrl() }}" alt="" style="width:100%;max-height:300px;object-fit:cover;border-radius:6px" class="mb-3">@endif
          <h2 style="font-size:20px;margin:0 0 8px">{{ $v->headline }}</h2>
          <p style="font-weight:500">{{ $v->card_text }} <span class="xsmall muted">({{ count(preg_split('/\s+/u', trim($v->card_text))) }} words)</span></p>
          @if($v->key_points)<ul>@foreach($v->key_points as $p)<li>{{ $p }}</li>@endforeach</ul>@endif
          @if($v->why_it_matters)<p><strong>Why it matters:</strong> {{ $v->why_it_matters }}</p>@endif
          @if($v->full_text)<details><summary class="small">Full story</summary><div class="pv-body mt-2">@foreach($v->paragraphs() as $p)<p>{{ $p }}</p>@endforeach</div></details>@endif
        @endif
      </div>
    </div>
    @endforeach
  </div>

  <div class="stack">
    @if($waiting)
    @can('review.decide')
    <form class="card" method="post" action="{{ route('admin.review.decide', $s) }}" x-data="{ d: '{{ $own ? 'changes' : 'approve' }}' }">@csrf
      <div class="card-h"><h2>Decision</h2></div>
      <div class="card-b">
        @if($own)<div class="alert amber">You wrote this story, so another editor must approve it. You can still request changes or reject it.</div>@endif
        @if($missing)<div class="alert amber"><span class="t">Can’t publish yet</span><ul>@foreach($missing as $m)<li>{{ $m }}</li>@endforeach</ul><a href="{{ route('admin.stories.edit', $s) }}">Fix in the editor</a></div>@endif
        <div class="stack-sm mb-3">
          @unless($own)
          <label class="cb"><input type="radio" name="decision" value="approve" x-model="d"> Approve and publish now</label>
          @can('content.schedule')<label class="cb"><input type="radio" name="decision" value="approve_schedule" x-model="d"> Approve and schedule</label>@endcan
          @endunless
          <label class="cb"><input type="radio" name="decision" value="changes" x-model="d"> Request changes @if(! $s->author_id)<span class="muted small">(AI drafts: edit it yourself instead)</span>@endif</label>
          <label class="cb"><input type="radio" name="decision" value="reject" x-model="d"> Reject</label>
        </div>
        <div x-show="d==='approve_schedule'" x-cloak class="field"><label>Publish at</label><input type="datetime-local" name="scheduled_at" min="{{ now(ptz())->format('Y-m-d\TH:i') }}"><div class="help">{{ tz_label() }}</div></div>
        <div x-show="d==='reject'" x-cloak class="field"><label>Reason <span class="req">*</span></label><select name="reason"><option value="">Choose…</option>@foreach($reasons as $x)<option>{{ $x }}</option>@endforeach</select></div>
        <div class="field" x-show="d!=='approve'"><label>Comment <span class="req" x-show="d==='reject'||d==='changes'">*</span></label><textarea name="comment" rows="3" placeholder="Kept in the approvals log"></textarea></div>
      </div>
      <div class="card-f"><button class="btn primary" x-text="{approve:'Approve & publish',approve_schedule:'Approve & schedule',changes:'Request changes',reject:'Reject'}[d]">Submit</button></div>
    </form>
    @endcan
    <div class="card"><div class="card-h"><h2>Reviewer</h2></div><div class="card-b">
      @can('review.assign')
        <form method="post" action="{{ route('admin.review.assign', $s) }}" class="row">@csrf<select name="reviewer_id"><option value="">Unassigned</option>@foreach($reviewers as $rv)<option value="{{ $rv->id }}" @selected($s->assigned_reviewer_id===$rv->id)>{{ $rv->name }}</option>@endforeach</select><button class="btn sm">Assign</button></form>
      @else
        <p class="mb-2">{{ $s->reviewer?->name ?? 'Unassigned' }}</p>@if(!$s->assigned_reviewer_id)<form method="post" action="{{ route('admin.review.take', $s) }}">@csrf<button class="btn sm">Take it</button></form>@endif
      @endcan
    </div></div>
    @if($own)
    @can('review.override')
    <form class="card" method="post" action="{{ route('admin.review.override', $s) }}" data-confirm="Publish your own story?" data-confirm-detail="This skips the second editor. It is recorded in the audit log with your reason." data-confirm-password>@csrf
      <div class="card-h"><h2>Override</h2></div>
      <div class="card-b"><div class="field"><label>Reason <span class="req">*</span></label><textarea name="reason" rows="2" required></textarea></div></div>
      <div class="card-f"><button class="btn">Publish with override</button></div>
    </form>
    @endcan
    @endif
    @endif

    <div class="card">
      <div class="card-h"><h2>Sources</h2></div>
      <div class="card-b">
        @if($s->cluster && $s->cluster->items->isNotEmpty())
          @foreach($s->cluster->items as $item)
            <div class="fieldset mb-2"><div class="small"><x-admin.pill>{{ $item->feedSource?->name }}</x-admin.pill> {{ fmt_dt($item->published_at) }} · <a href="{{ $item->url }}" target="_blank" rel="noopener">open original</a></div>
              <div class="strong mt-1">{{ $item->title }}</div>
              <div class="small muted mt-1" style="white-space:pre-line;max-height:260px;overflow:auto">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item->body), 3000) }}</div></div>
          @endforeach
        @else
          <p class="muted">No source items (written by the newsroom).@if($s->source_url) Original: <a href="{{ $s->source_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($s->source_url, 60) }}</a>@endif</p>
        @endif
      </div>
    </div>

    <div class="card"><div class="card-h"><h2>Decisions so far</h2></div><div class="card-b">
      @if($s->approvals->isEmpty())<p class="muted">None yet.</p>@else<ul class="tl">@foreach($s->approvals->sortByDesc('id') as $a)<li class="{{ in_array($a->action, ['rejected','changes_requested']) ? 'r' : '' }}"><div class="what">{{ ucfirst(str_replace('_', ' ', $a->action)) }}</div><div class="when">{{ $a->editor?->name }} · {{ fmt_dt($a->acted_at) }}</div>@if($a->reason)<div class="note">{{ $a->reason }}</div>@endif</li>@endforeach</ul>@endif
    </div></div>
  </div>
</div>
@endsection
