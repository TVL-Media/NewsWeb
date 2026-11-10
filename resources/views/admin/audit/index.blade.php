@extends('admin.layouts.app')
@section('title', 'Audit log')
@section('content')
<x-admin.ph title="Audit log" sub="Every sensitive action: who did it, what changed and when. Records cannot be edited or deleted.">
  <a class="btn" href="{{ route('admin.audit.export', request()->query()) }}">{!! icon('download', 14) !!} Export CSV</a>
</x-admin.ph>
<div class="card">
  <form class="filters" method="get">
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Action, subject or ID"></div>
    <select name="admin" style="width:auto" onchange="this.form.submit()"><option value="">Any admin</option>@foreach($admins as $a)<option value="{{ $a->id }}" @selected(request('admin')==$a->id)>{{ $a->name }}</option>@endforeach</select>
    <select name="module" style="width:auto" onchange="this.form.submit()"><option value="">Any module</option>@foreach($modules as $m)<option @selected(request('module')===$m)>{{ $m }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ request('from') }}" style="width:auto"><input type="date" name="to" value="{{ request('to') }}" style="width:auto"><button class="btn sm">Apply</button>
  </form>
  <table class="t"><thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Subject</th><th>Changes</th><th>IP</th></tr></thead><tbody>
  @forelse($items as $l)
    <tr><td class="small mono nowrap"><a href="{{ route('admin.audit.show', $l) }}">{{ fmt_dt($l->created_at) }}</a></td><td class="small">{{ $l->admin?->name ?? 'System' }}</td><td><code>{{ $l->action }}</code></td>
      <td class="small">{{ \Illuminate\Support\Str::limit($l->subject_label, 50) }} @if($l->subject_id)<span class="muted">#{{ $l->subject_id }}</span>@endif</td>
      <td class="small muted">@if($l->after){{ \Illuminate\Support\Str::limit(collect($l->after)->keys()->join(', '), 50) }}@endif @if($l->reason)<div class="t2">“{{ \Illuminate\Support\Str::limit($l->reason, 60) }}”</div>@endif</td>
      <td class="small mono muted">{{ $l->ip }}</td></tr>
  @empty <tr><td colspan="6"><x-admin.empty title="No records match" /></td></tr> @endforelse
  </tbody></table>{{ $items->links() }}
</div>
@endsection
