@extends('admin.layouts.app')
@section('title', $s->exists ? 'Edit: '.\Illuminate\Support\Str::limit($s->title(), 40) : 'Write a story')
@section('suppress_errors', '1')
@section('content')
@php
  $st = $s->status ?? 'draft';
  $en = $v('en'); $hi = $v('hi');
  $val = fn ($lang, $field, $default = '') => old("{$lang}.{$field}", ($lang === 'en' ? $en : $hi)?->{$field} ?? $default);
  $kp = fn ($lang, $i) => old("{$lang}.key_points.{$i}", (($lang === 'en' ? $en : $hi)?->key_points ?? [])[$i] ?? '');
  $canPublish = $admin->can('content.publish');
  $canSchedule = $admin->can('content.schedule');
  $canSubmit = $admin->can('content.submit');
  $editable = ! $readonly;
  $action = $s->exists ? route('admin.stories.update', $s) : route('admin.stories.store');
  $lastDecision = $s->exists ? $s->approvals->sortByDesc('id')->first() : null;
  $check = $s->exists ? $s->aiCheck : null;
@endphp

<x-admin.ph :title="$s->exists ? $s->title() : 'Write a story'" :crumbs="['Stories' => route('admin.stories.index')]">
  @if($s->exists)
    @if($s->isLive())<a class="btn" href="{{ route('story.show', $s) }}" target="_blank">{!! icon('external-link', 14) !!} Open on site</a>@endif
    <a class="btn" href="{{ route('admin.stories.preview', $s) }}" target="_blank">{!! icon('eye', 14) !!} Preview</a>
    <a class="btn" href="{{ route('admin.stories.history', $s) }}">{!! icon('history', 14) !!} History</a>
    <div class="dd" x-data="{o:false}" @click.outside="o=false">
      <button class="btn" @click="o=!o">More {!! icon('chevron-down', 14) !!}</button>
      <div class="dd-menu" x-show="o" x-cloak>
        @if($s->isLive() && $canPublish)
          <form method="post" action="{{ route('admin.stories.unpublish', $s) }}" data-confirm="Unpublish this story?" data-confirm-detail="It disappears from the website straight away. You can publish it again later." data-confirm-reason="optional">@csrf<button>{!! icon('eye-off') !!} Unpublish</button></form>
        @endif
        @if(in_array($st, ['rejected', 'removed']))<form method="post" action="{{ route('admin.stories.reopen', $s) }}">@csrf<button>{!! icon('rotate-ccw') !!} Reopen as draft</button></form>@endif
        @can('content.delete')
          <div class="sep"></div>
          <form method="post" action="{{ route('admin.stories.destroy', $s) }}" data-confirm="Delete this story?" data-confirm-detail="This permanently deletes the story in both languages, with its comments, likes and audio. It cannot be undone." data-confirm-word="DELETE" data-confirm-password data-confirm-danger data-confirm-reason="optional">@csrf @method('delete')<button class="danger">{!! icon('trash-2') !!} Delete</button></form>
        @endcan
      </div>
    </div>
  @endif
</x-admin.ph>

@if($readonly)
  <div class="alert">{{ $s->isLive() || $st === 'scheduled' ? 'This story is live or scheduled. Your role can view it but not change it.' : 'You can view this story but your role cannot edit it.' }}</div>
@endif
@if($s->exists && $s->publish_mode === 'auto' && $s->isLive())
  <div class="alert blue"><span class="t">Auto-published</span>This story was written by AI from the feeds and passed every check. See <a href="#checks">the checks</a> below.</div>
@endif
@if($similar->isNotEmpty())
  <div class="alert amber"><span class="t">Possible duplicate</span>Similar headlines in the last {{ setting('content.duplicate_window_days', 3) }} days:
    <ul>@foreach($similar as $x)<li><a href="{{ route('admin.stories.edit', $x) }}">{{ $x->title() }}</a> · {{ \App\Models\Story::STATUSES[$x->status] ?? $x->status }}</li>@endforeach</ul></div>
@endif

<form id="editor" method="post" action="{{ $action }}" enctype="multipart/form-data" data-dirty-guard x-data="storyEditor()">
  @csrf @if($s->exists) @method('put') @endif
  <input type="hidden" name="action" x-ref="action" value="">
<fieldset {{ $readonly ? 'disabled' : '' }} style="border:0;padding:0;margin:0;min-width:0">
<div class="editor">
  <div class="stack">
    <div class="card">
      <div class="card-b" style="padding-bottom:0">
        <nav class="tabs" style="margin-bottom:0">
          <button type="button" :class="tab==='en' && 'on'" @click="tab='en'">English</button>
          <button type="button" :class="tab==='hi' && 'on'" @click="tab='hi'">हिंदी @if($hi?->translation_status === 'ai')<span class="n">AI</span>@elseif(! $hi)<span class="n">empty</span>@endif</button>
          <button type="button" :class="tab==='details' && 'on'" @click="tab='details'">Photo &amp; source</button>
          @if($s->exists && ($check || $s->cluster))<button type="button" :class="tab==='checks' && 'on'" @click="tab='checks'" id="checks">AI checks &amp; sources</button>@endif
        </nav>
      </div>

      @foreach(['en' => 'English', 'hi' => 'हिंदी'] as $lang => $label)
      <div class="card-b" x-show="tab==='{{ $lang }}'" @if($lang === 'hi') x-cloak @endif>
        @if($lang === 'hi')
          <div class="help mb-2">Optional. Readers who pick Hindi see the English version until this is filled in.
            @if($s->exists && $admin->can('translation.request'))<button type="submit" form="tr-hi" class="link-btn">Translate from English with AI</button>@endif
            @if($hi?->translation_status === 'ai') <x-admin.pill tone="amber">Machine translation, not yet reviewed</x-admin.pill>@endif
          </div>
        @elseif($s->exists && $hi && $admin->can('translation.request'))
          <div class="help mb-2"><button type="submit" form="tr-en" class="link-btn">Translate the Hindi version into English with AI</button></div>
        @endif
        <x-admin.field label="Headline" :name="$lang.'.headline'" :required="$lang === 'en'">
          <input name="{{ $lang }}[headline]" value="{{ $val($lang, 'headline') }}" maxlength="250" data-count="120" @if($lang === 'en') required @endif lang="{{ $lang }}" style="font-size:16px;height:42px;font-weight:600">
        </x-admin.field>
        <x-admin.field label="Card text" :name="$lang.'.card_text'" :required="$lang === 'en'" help="The 60-word card readers see in the feed. 60 words or fewer.">
          <textarea name="{{ $lang }}[card_text]" rows="4" data-count="60" data-count-mode="words" lang="{{ $lang }}">{{ $val($lang, 'card_text') }}</textarea>
        </x-admin.field>
        <div class="field"><label class="lbl">Key points @if($lang === 'en')<span class="req">*</span>@endif</label>
          @for($i = 0; $i < 3; $i++)
            <input name="{{ $lang }}[key_points][{{ $i }}]" value="{{ $kp($lang, $i) }}" maxlength="250" placeholder="Point {{ $i + 1 }}" lang="{{ $lang }}" class="{{ $i ? 'mt-1' : '' }}">
          @endfor
          <div class="help">Exactly three short points, shown under the card on the story page.</div>
        </div>
        <x-admin.field label="Why it matters" :name="$lang.'.why_it_matters'"><textarea name="{{ $lang }}[why_it_matters]" rows="2" lang="{{ $lang }}">{{ $val($lang, 'why_it_matters') }}</textarea></x-admin.field>
        <x-admin.field label="Explain simply" :name="$lang.'.simple_text'" help="The “Simple” tab on the story page."><textarea name="{{ $lang }}[simple_text]" rows="4" lang="{{ $lang }}">{{ $val($lang, 'simple_text') }}</textarea></x-admin.field>
        <x-admin.field label="Full story" :name="$lang.'.full_text'" help="Leave a blank line between paragraphs."><textarea name="{{ $lang }}[full_text]" rows="12" lang="{{ $lang }}">{{ $val($lang, 'full_text') }}</textarea></x-admin.field>
      </div>
      @endforeach

      <div class="card-b" x-show="tab==='details'" x-cloak>
        <div class="field" x-data="{ remove:false }">
          <label class="lbl">Photo</label>
          @if($s->photo_path)<div x-show="!remove"><img src="{{ $s->photoUrl() }}" alt="" style="max-height:180px;border-radius:6px;border:1px solid var(--line)"></div>@endif
          <div class="row wrap mt-2">
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" style="max-width:280px">
            <span class="muted small">or</span>
            <x-admin.media-field name="photo_media_id" label="" help="" />
            @if($s->photo_path)<label class="cb small"><input type="checkbox" name="remove_photo" value="1" x-model="remove"> Remove photo</label>@endif
          </div>
          <div class="help">Fills the card. Landscape, at least 1200 px wide. Up to {{ setting('content.max_image_mb', 8) }} MB.</div>
          @error('photo')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="form-grid">
          <x-admin.field label="Photo credit" name="photo_credit"><input name="photo_credit" value="{{ old('photo_credit', $s->photo_credit) }}" placeholder="e.g. PTI, Reuters"></x-admin.field>
          <x-admin.field label="Source" name="source_id"><select name="source_id"><option value="">None</option>@foreach($sources as $x)<option value="{{ $x->id }}" @selected(old('source_id', $s->source_id) == $x->id)>{{ $x->name }}</option>@endforeach</select></x-admin.field>
          <x-admin.field label="Original URL" name="source_url" class="full"><input type="url" name="source_url" value="{{ old('source_url', $s->source_url) }}" placeholder="https://"></x-admin.field>
          <x-admin.field label="Correction label" name="correction_label" class="full" help="Shown above the headline. Publishing with a label marks the story “Corrected”."><input name="correction_label" value="{{ old('correction_label', $s->correction_label) }}" placeholder="Corrected: an earlier version gave the wrong venue."></x-admin.field>
        </div>
      </div>

      @if($s->exists && ($check || $s->cluster))
      <div class="card-b" x-show="tab==='checks'" x-cloak>
        @if($check)
          <div class="kpis" style="grid-template-columns:repeat(4,1fr)">
            <div class="kpi"><div class="l">Risk</div><div class="v {{ $check->risk_level === 'sensitive' ? 'red' : '' }}">{{ ucfirst($check->risk_level) }}</div></div>
            <div class="kpi"><div class="l">Confidence</div><div class="v">{{ number_format($check->confidence, 2) }}</div></div>
            <div class="kpi"><div class="l">Copy score</div><div class="v">{{ number_format($check->copy_score, 2) }}</div></div>
            <div class="kpi"><div class="l">Decision</div><div class="v">{{ $check->decision === 'auto' ? 'Auto-publish' : 'Editor' }}</div></div>
          </div>
          @if($check->reasons)<div class="alert amber mt-3"><span class="t">Why it needs an editor</span><ul>@foreach($check->reasons as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
          @if($check->fact_mismatches)<div class="alert red"><span class="t">Facts not found in the sources</span><ul>@foreach($check->fact_mismatches as $m)<li>{{ ucfirst($m['type'] ?? '') }}: {{ $m['value'] ?? '' }}</li>@endforeach</ul></div>@endif
        @endif
        @if($s->cluster && $s->cluster->items->isNotEmpty())
          <h3 class="h3 mt-3">Source reports</h3>
          @foreach($s->cluster->items as $item)
            <div class="fieldset mb-2"><div class="small"><x-admin.pill>{{ $item->feedSource?->name }}</x-admin.pill> {{ fmt_dt($item->published_at) }} · <a href="{{ $item->url }}" target="_blank" rel="noopener">open</a></div>
              <div class="strong mt-1">{{ $item->title }}</div>
              <div class="small muted mt-1" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item->body), 1200) }}</div></div>
          @endforeach
        @endif
      </div>
      @endif
    </div>
  </div>

  <aside class="publish-panel">
    <div class="card">
      <div class="card-h"><h2>Publish</h2>@if($s->exists)<x-admin.status :content="$s" />@else<x-admin.pill>New</x-admin.pill>@endif</div>
      <div class="card-b">
        @if($s->exists)<div class="small muted" x-text="savedText">Version {{ $s->version_no }} · saved {{ $s->updated_at->diffForHumans() }}@if($s->author) · by {{ $s->author->name }}@elseif($s->publish_mode === 'auto' || $s->cluster) · AI draft @endif</div>@endif
        @if($errors->any())
          <div class="alert red" style="margin:0"><span class="t">Not ready yet</span><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @elseif($missing && in_array($st, ['draft', 'changes_requested', 'rejected', 'removed']))
          <div class="alert amber" style="margin:0"><span class="t">Before you submit or publish</span><ul>@foreach($missing as $m)<li>{{ $m }}</li>@endforeach</ul></div>
        @endif
        @if($lastDecision && in_array($st, ['changes_requested', 'rejected']) && in_array($lastDecision->action, ['changes_requested', 'rejected']))
          <div class="alert {{ $st === 'rejected' ? 'red' : 'amber' }}" style="margin:0"><span class="t">{{ $st === 'rejected' ? 'Rejected' : 'Changes requested' }} by {{ $lastDecision->editor?->name }}</span>{{ $lastDecision->reason }}</div>
        @endif
        @if($st === 'scheduled' && $s->publish_attempts > 0)
          <div class="alert red" style="margin:0"><span class="t">Failed to publish ({{ $s->publish_attempts }} attempt{{ $s->publish_attempts > 1 ? 's' : '' }})</span>{{ $s->last_publish_error }}</div>
        @endif

        <x-admin.field label="Category" name="category_id" required>
          <select name="category_id">
            <option value="">Choose…</option>
            @foreach($categories as $cat)
              @if($cat->is_active || $s->category_id == $cat->id)<option value="{{ $cat->id }}" @selected(old('category_id', $s->category_id) == $cat->id)>{{ $cat->name_en }}{{ $cat->is_sensitive ? ' · sensitive' : '' }}{{ $cat->is_active ? '' : ' (hidden)' }}</option>@endif
            @endforeach
          </select>
        </x-admin.field>
        <x-admin.field label="Page" name="page_id" help="The followable page it appears on (topic, city or show).">
          <select name="page_id"><option value="">None</option>@foreach($pages as $p)<option value="{{ $p->id }}" @selected(old('page_id', $s->page_id) == $p->id)>{{ $p->name }} ({{ $p->type }})</option>@endforeach</select>
        </x-admin.field>
        <x-admin.field label="City" name="city_id">
          <select name="city_id"><option value="">None</option>@foreach($cities as $c)<option value="{{ $c->id }}" @selected(old('city_id', $s->city_id) == $c->id)>{{ $c->name_en }}</option>@endforeach</select>
        </x-admin.field>
        <div class="row" style="gap:12px">
          <x-admin.field label="Importance" name="importance" help="1–10. Drives the hero and Morning 5." style="flex:1"><input type="number" name="importance" min="1" max="10" value="{{ old('importance', $s->importance ?? 5) }}" required></x-admin.field>
          <x-admin.field label="Read time" name="read_minutes" help="Minutes" style="flex:1"><input type="number" name="read_minutes" min="1" max="30" value="{{ old('read_minutes', $s->read_minutes ?? 2) }}" required></x-admin.field>
        </div>
        <x-admin.field label="Tags" name="tags" help="Comma separated. For search in this panel."><input name="tags" value="{{ old('tags', $s->exists ? $s->tags->pluck('name')->join(', ') : '') }}" placeholder="budget, tax"></x-admin.field>
        <label class="cb"><input type="checkbox" name="is_breaking" value="1" @checked(old('is_breaking', $s->is_breaking))> Breaking news</label>

        @if($editable)
        <div class="stack-sm" style="border-top:1px solid var(--line-2);padding-top:12px;margin-top:12px">
          <button type="submit" class="btn w-full" data-save-shortcut @click="$refs.action.value=''">{{ $s->isLive() ? 'Save changes (live)' : 'Save draft' }}</button>
          @if($canSubmit && ! $s->exists || $canSubmit && in_array($st, ['draft', 'changes_requested', 'rejected', 'removed']))
            <button type="submit" class="btn w-full {{ $canPublish ? '' : 'primary' }}" @click="$refs.action.value='submit'">{!! icon('send', 14) !!} {{ $st === 'changes_requested' ? 'Resubmit for review' : 'Submit for review' }}</button>
          @endif
          @if($canPublish && in_array($st, ['draft', 'pending_approval', 'changes_requested', 'rejected', 'removed', 'scheduled']))
            <button type="submit" class="btn primary w-full" @click="$refs.action.value='publish'">{{ $st === 'removed' ? 'Publish again' : 'Publish now' }}</button>
          @endif
          @if($canSchedule && in_array($st, ['draft', 'pending_approval', 'changes_requested', 'removed']))
            <div x-data="{ open:false }">
              <button type="button" class="btn w-full" @click="open=!open">{!! icon('clock', 14) !!} Schedule…</button>
              <div x-show="open" x-cloak class="mt-2">
                <input type="datetime-local" name="scheduled_at" min="{{ now(ptz())->format('Y-m-d\TH:i') }}" value="{{ old('scheduled_at') }}">
                <div class="help">Timezone: {{ tz_label() }}</div>
                <button type="submit" class="btn primary w-full mt-2" @click="$refs.action.value='schedule'">Confirm schedule</button>
              </div>
            </div>
          @endif
          @if($st === 'scheduled')
            <div class="small">Goes live <strong>{{ fmt_dt($s->scheduled_at) }}</strong> {{ tz_label() }}</div>
            @if($canSchedule)<button type="submit" form="cancel-schedule" class="btn w-full">Cancel schedule</button>@endif
          @endif
          @if($st === 'pending_approval')<div class="small muted">Waiting in the <a href="{{ route('admin.review.show', $s) }}">approval queue</a>{{ $s->reviewer ? ' for '.$s->reviewer->name : '' }}.</div>@endif
          @if($s->isLive())<div class="small muted">Live since {{ fmt_dt($s->published_at) }}. Saving updates the website straight away and rebuilds the audio.</div>@endif
        </div>
        @endif
      </div>
    </div>
    @if($s->exists && $s->approvals->isNotEmpty())
    <div class="card mt-4">
      <div class="card-h"><h2>Decisions</h2><a class="small" href="{{ route('admin.stories.history', $s) }}">All</a></div>
      <div class="card-b"><ul class="tl">
        @foreach($s->approvals->sortByDesc('id')->take(5) as $a)
          <li class="{{ in_array($a->action, ['rejected', 'changes_requested']) ? 'r' : '' }}"><div class="what">{{ ucfirst(str_replace('_', ' ', $a->action)) }}</div><div class="when">{{ $a->editor?->name ?? 'System' }} · {{ fmt_dt($a->acted_at) }}</div>@if($a->reason)<div class="note">{{ $a->reason }}</div>@endif</li>
        @endforeach
      </ul></div>
    </div>
    @endif
  </aside>
</div>
</fieldset>
</form>

@if($s->exists)
  <form id="cancel-schedule" method="post" action="{{ route('admin.stories.schedule.cancel', $s) }}">@csrf</form>
  <form id="tr-hi" method="post" action="{{ route('admin.stories.translate', $s) }}" class="hide">@csrf<input type="hidden" name="language" value="hi"></form>
  <form id="tr-en" method="post" action="{{ route('admin.stories.translate', $s) }}" class="hide">@csrf<input type="hidden" name="language" value="en"></form>
@endif

@push('scripts')
<script>
function storyEditor() {
  return {
    tab: @json($errors->has('photo') ? 'details' : (collect($errors->keys())->first(fn ($k) => str_starts_with($k, 'hi.')) ? 'hi' : 'en')),
    savedText: '',
    init() {
      @if($s->exists && $editable && ! $s->isLive())
      // Autosave drafts every 30 s while there are unsaved changes (never for live stories).
      let dirty = false; this.$el.addEventListener('input', () => dirty = true);
      setInterval(() => {
        if (!dirty) return; dirty = false;
        const fd = new FormData(this.$el); fd.set('_autosave', '1'); fd.set('action', ''); fd.delete('photo');
        fetch(this.$el.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
          .then(r => r.ok ? r.json() : Promise.reject()).then(j => this.savedText = 'Autosaved at ' + j.saved_at).catch(() => this.savedText = 'Autosave failed — save manually');
      }, 30000);
      @endif
    },
  };
}
</script>
@endpush
@endsection
