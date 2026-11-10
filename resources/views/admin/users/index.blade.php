@extends('admin.layouts.app')
@section('title', 'Readers')
@section('content')
@php $S = \App\Http\Controllers\Admin\UserController::STATUSES; @endphp
<x-admin.ph title="Readers" sub="Website accounts (phone + OTP). Phone numbers are masked unless your role allows full access." />
<nav class="tabs">
  <a href="{{ route('admin.users.index', request()->except(['status','page'])) }}" class="{{ !request('status') ? 'on' : '' }}">All <span class="n">{{ $counts->sum() }}</span></a>
  @foreach($S as $k => [$l, $t])<a href="{{ route('admin.users.index', array_merge(request()->except('page'), ['status' => $k])) }}" class="{{ request('status')===$k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $counts[$k] ?? 0 }}</span></a>@endforeach
</nav>
<div class="card">
  <form class="filters" method="get">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Name, phone or reader ID"></div>
    <select name="activity" style="width:auto" onchange="this.form.submit()"><option value="">Any activity</option><option value="7d" @selected(request('activity')==='7d')>Active in last 7 days</option><option value="30d" @selected(request('activity')==='30d')>Active in last 30 days</option><option value="dormant" @selected(request('activity')==='dormant')>Dormant (30+ days)</option></select>
    <select name="engagement" style="width:auto" onchange="this.form.submit()"><option value="">Any engagement</option><option value="high" @selected(request('engagement')==='high')>High (10+ reads/week)</option><option value="medium" @selected(request('engagement')==='medium')>Medium (3–9)</option><option value="low" @selected(request('engagement')==='low')>Low (&lt; 3)</option></select>
    <select name="language" style="width:auto" onchange="this.form.submit()"><option value="">Any language</option><option value="en" @selected(request('language')==='en')>English</option><option value="hi" @selected(request('language')==='hi')>हिंदी</option></select>
    <select name="city" style="width:auto" onchange="this.form.submit()"><option value="">Any city</option>@foreach($cities as $c)<option value="{{ $c->id }}" @selected(request('city')==$c->id)>{{ $c->name_en }}</option>@endforeach</select>
  </form>
  <div class="table-wrap"><table class="t"><thead><tr><th>Reader</th><th>Phone</th><th>City</th><th>Status</th><th>Joined</th><th>Last active</th><th class="num">Reads</th><th class="num">Comments</th><th class="num">Streak</th></tr></thead><tbody>
  @forelse($users as $u)
    <tr><td><div class="title-cell" style="min-width:180px"><span class="avatar sm">{{ $u->initial() }}</span><div><a class="t1" href="{{ route('admin.users.show', $u) }}">{{ $u->name ?: 'Reader #'.$u->id }}</a><div class="t2">#{{ $u->id }} · {{ $u->language === 'hi' ? 'हिंदी' : 'EN' }}{{ $u->onboarded_at ? '' : ' · not onboarded' }}</div></div></div></td>
      <td class="small mono">{{ $pii ? $u->phone : \App\Models\User::mask($u->phone) }}</td>
      <td class="small">{{ $u->city?->name_en ?? '—' }}</td>
      <td><x-admin.pill :tone="$S[$u->effectiveStatus()][1] ?? 'neutral'">{{ $S[$u->effectiveStatus()][0] ?? $u->status }}</x-admin.pill></td>
      <td class="small muted nowrap">{{ fmt_date($u->created_at) }}</td><td class="small muted nowrap">{{ $u->last_active_at?->diffForHumans() ?? 'never' }}</td>
      <td class="num">{{ $reads[$u->id] ?? 0 }}</td><td class="num">{{ $comments[$u->id] ?? 0 }}</td><td class="num">{{ $u->streak?->current ?? 0 }}</td></tr>
  @empty <tr><td colspan="9"><x-admin.empty title="No readers match" /></td></tr> @endforelse
  </tbody></table></div>{{ $users->links() }}
</div>
@endsection
