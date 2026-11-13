@extends('admin.layouts.app')
@section('title', 'Audit record')
@section('content')
<x-admin.ph :title="$log->action" :crumbs="['Audit log' => route('admin.audit.index')]" :sub="fmt_dt($log->created_at).' · '.($log->admin?->name ?? 'System')" />
<div class="grid g-main">
  <div class="card"><div class="card-h"><h2>What changed</h2></div><div class="card-b">
    @php $keys = collect(array_keys((array) $log->before))->merge(array_keys((array) $log->after))->unique(); @endphp
    @if($keys->isEmpty())<p class="muted">No field changes recorded for this action.</p>@else
    <table class="t compact"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
      @foreach($keys as $k)<tr><td class="mono small">{{ $k }}</td><td class="small" style="background:#FDF6F7">{{ is_scalar($log->before[$k] ?? null) || is_null($log->before[$k] ?? null) ? var_export($log->before[$k] ?? null, true) : json_encode($log->before[$k], JSON_UNESCAPED_UNICODE) }}</td><td class="small" style="background:#F3FAF6">{{ is_scalar($log->after[$k] ?? null) || is_null($log->after[$k] ?? null) ? var_export($log->after[$k] ?? null, true) : json_encode($log->after[$k], JSON_UNESCAPED_UNICODE) }}</td></tr>@endforeach
    </tbody></table>@endif
  </div></div>
  <div class="card"><div class="card-b"><dl class="kv" style="grid-template-columns:90px 1fr">
    <dt>Action</dt><dd><code>{{ $log->action }}</code></dd><dt>Module</dt><dd>{{ $log->module }}</dd><dt>Subject</dt><dd>{{ $log->subject_type }} #{{ $log->subject_id }}<br>{{ $log->subject_label }}</dd>
    <dt>Admin</dt><dd>{{ $log->admin?->name ?? 'System' }}</dd><dt>Reason</dt><dd>{{ $log->reason ?: '—' }}</dd><dt>IP</dt><dd class="mono">{{ $log->ip }}</dd><dt>Device</dt><dd class="small">{{ $log->user_agent }}</dd>
  </dl></div></div>
</div>
@endsection
