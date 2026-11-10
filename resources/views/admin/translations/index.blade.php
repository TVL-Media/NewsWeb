@extends('admin.layouts.app')
@section('title', 'Translations')
@section('content')
@php $states = ['queued' => ['Queued', 'blue'], 'running' => ['Translating', 'blue'], 'ai_completed' => ['Waiting for review', 'amber'], 'approved' => ['Approved', 'green'], 'failed' => ['Failed', 'red'], 'rejected' => ['Discarded', 'neutral']]; @endphp
<x-admin.ph title="Translations" sub="AI translations between English and Hindi. Readers only see a translation after an editor approves it.">
  @can('translation.request')@if($missingHi)<form method="post" action="{{ route('admin.translations.backfill') }}" data-confirm="Translate recent stories into Hindi?" data-confirm-detail="{{ $missingHi }} stories published in the last 48 hours have no Hindi version. Up to 20 are sent now.">@csrf<button class="btn">{!! icon('languages', 14) !!} Translate {{ min(20, $missingHi) }} stories into Hindi</button></form>@endif @endcan
</x-admin.ph>
@include('admin.translations._tabs')
<div class="card mb-4"><div class="card-b row between wrap"><div><strong>This month</strong> <span class="muted small">{{ number_format($used) }}{{ $limit ? ' of '.number_format($limit) : '' }} characters · provider: {{ $provider === 'fake' ? 'Test mode' : $provider }}</span></div>
  @if($limit)<div style="flex:1;max-width:420px" class="progress"><span style="width:{{ min(100, $used / $limit * 100) }}%"></span></div>@endif</div></div>
@if($provider === 'fake')<div class="alert amber">Translations are in <strong>Test mode</strong>: text is only tagged with the language code. Set <code>ANTHROPIC_API_KEY</code> in <code>.env</code> (the same key the newsroom uses for AI drafts), or choose a provider in <a href="{{ route('admin.translations.settings') }}">Settings</a>.</div>@endif
<div class="card">
  <form class="filters" method="get">
    <select name="status" style="width:auto" onchange="this.form.submit()"><option value="">All statuses</option>@foreach($states as $k => [$l, $t])<option value="{{ $k }}" @selected(request('status')===$k)>{{ $l }} ({{ $counts[$k] ?? 0 }})</option>@endforeach</select>
    <select name="language" style="width:auto" onchange="this.form.submit()"><option value="">Both directions</option><option value="hi" @selected(request('language')==='hi')>Into Hindi</option><option value="en" @selected(request('language')==='en')>Into English</option></select>
  </form>
  <table class="t"><thead><tr><th>Story</th><th>Into</th><th>Status</th><th>Provider</th><th>Requested</th><th>Reviewed</th><th></th></tr></thead><tbody>
  @forelse($jobs as $j)
    <tr><td><a class="t1" href="{{ route('admin.stories.edit', $j->story_id) }}">{{ \Illuminate\Support\Str::limit($j->story?->title(), 70) }}</a>@if($j->isOutOfDate())<div class="t2 red">The original changed after this was approved</div>@endif @if($j->error && $j->status === 'failed')<div class="t2 red">{{ \Illuminate\Support\Str::limit($j->error, 100) }}</div>@endif</td>
      <td class="small">{{ \App\Models\TranslationJob::LANGS[$j->target_language] }}</td>
      <td><x-admin.pill :tone="$states[$j->status][1] ?? 'neutral'">{{ $states[$j->status][0] ?? $j->status }}</x-admin.pill></td>
      <td class="small muted">{{ $j->provider }}</td>
      <td class="small muted nowrap">{{ $j->requester?->name ?? 'Auto' }} · {{ fmt_dt($j->created_at) }}</td>
      <td class="small muted nowrap">{{ $j->reviewer ? $j->reviewer->name.' · '.fmt_dt($j->reviewed_at) : '—' }}</td>
      <td class="right nowrap">
        @can('translation.review')
          @if(in_array($j->status, ['ai_completed', 'approved']))<a class="btn xs {{ $j->status === 'ai_completed' ? 'primary' : '' }}" href="{{ route('admin.translations.review', $j) }}">{{ $j->status === 'ai_completed' ? 'Review' : 'View' }}</a>@endif
          @if($j->status === 'failed')<form method="post" action="{{ route('admin.translations.retry', $j) }}" style="display:inline">@csrf<button class="btn xs">Retry</button></form>@endif
        @endcan
      </td></tr>
  @empty <tr><td colspan="7"><x-admin.empty title="No translations yet">Open a story and use “Translate from English with AI” on the हिंदी tab.</x-admin.empty></td></tr> @endforelse
  </tbody></table>{{ $jobs->links() }}
</div>
@endsection
