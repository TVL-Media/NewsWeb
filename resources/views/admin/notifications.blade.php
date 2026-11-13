@extends('admin.layouts.app')
@section('title', 'Notifications')
@section('content')
<x-admin.ph title="Notifications"><form method="post" action="{{ route('admin.notifications.read') }}">@csrf<button class="btn sm">Mark all read</button></form></x-admin.ph>
<div class="card"><div class="card-b flush">
  @if($items->isEmpty())<x-admin.empty title="No notifications">Decisions on your content, assignments and alerts will appear here.</x-admin.empty>@else
  <table class="t"><tbody>
    @foreach($items as $n)
      <tr @if(!$n->read_at) style="background:var(--red-50)" @endif><td><a class="t1" href="{{ route('admin.notifications.open', $n) }}">{{ $n->title }}</a>@if($n->body)<div class="t2">{{ $n->body }}</div>@endif</td><td class="num small muted nowrap">{{ fmt_dt($n->created_at) }}</td></tr>
    @endforeach
  </tbody></table>{{ $items->links() }}@endif
</div></div>
@endsection
