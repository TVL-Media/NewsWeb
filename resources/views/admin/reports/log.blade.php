@extends('admin.layouts.app')
@section('title', 'Moderation decision log')
@section('content')
<x-admin.ph title="Moderation decision log" :crumbs="['Reports' => route('admin.reports.index')]" sub="Every moderation decision, who made it and why.">
  <a class="btn" href="{{ route('admin.reports.log', array_merge(request()->query(), ['export' => 1])) }}">{!! icon('download', 14) !!} Export CSV</a>
</x-admin.ph>
<div class="card">
  <form class="filters" method="get"><select name="action" style="width:auto" onchange="this.form.submit()"><option value="">All actions</option>@foreach(\App\Http\Controllers\Admin\ReportController::ACTIONS + ['hide' => 'Comment hidden', 'restore' => 'Comment restored', 'reject' => 'Poll rejected'] as $k=>$l)<option value="{{ $k }}" @selected(request('action')===$k)>{{ $l }}</option>@endforeach</select></form>
  <table class="t"><thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Note</th></tr></thead><tbody>
  @forelse($items as $m)<tr><td class="small mono nowrap">{{ fmt_dt($m->created_at) }}</td><td class="small">{{ $m->admin?->name }}</td><td class="small">{{ (\App\Http\Controllers\Admin\ReportController::ACTIONS + ['hide' => 'Comment hidden', 'restore' => 'Comment restored', 'reject' => 'Poll rejected'])[$m->action] ?? $m->action }}</td><td class="small">@if($m->report_id)<a href="{{ route('admin.reports.show', $m->report_id) }}">{{ ucfirst($m->target_type) }} #{{ $m->target_id }}</a>@else{{ ucfirst($m->target_type) }} #{{ $m->target_id }}@endif</td><td class="small">{{ $m->note }}</td></tr>
  @empty <tr><td colspan="5"><x-admin.empty title="No decisions yet" /></td></tr>@endforelse
  </tbody></table>{{ $items->links() }}
</div>
@endsection
