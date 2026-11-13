@extends('admin.layouts.app')
@section('title', 'Admin team')
@section('content')
<div x-data="{open:false}">
<x-admin.ph title="Admin team" sub="People who can sign in to this panel. Invitations expire after 48 hours.">
  <button class="btn primary" @click="open=true">{!! icon('plus', 14) !!} Invite admin</button>
</x-admin.ph>
@if(session('invite_url'))<div class="alert blue"><span class="t">Invitation link</span>Email delivery depends on your mail settings. You can also share this link directly: <code style="word-break:break-all">{{ session('invite_url') }}</code></div>@endif
<div class="card">
  <form class="filters" method="get">
    <div class="search">{!! icon('search') !!}<input type="search" name="q" value="{{ request('q') }}" placeholder="Name or email"></div>
    <select name="role" style="width:auto" onchange="this.form.submit()"><option value="">All roles</option>@foreach($roles as $r)<option @selected(request('role')===$r->name)>{{ $r->name }}</option>@endforeach</select>
    <select name="status" style="width:auto" onchange="this.form.submit()"><option value="">Any status</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option><option value="invited" @selected(request('status')==='invited')>Invited</option></select>
  </form>
  <table class="t"><thead><tr><th>Admin</th><th>Role</th><th>Status</th><th>2FA</th><th>Last sign-in</th><th>Added</th><th></th></tr></thead><tbody>
  @foreach($admins as $a)
    <tr><td><div class="title-cell" style="min-width:220px"><span class="avatar sm">{{ $a->initials() }}</span><div><a class="t1" href="{{ route('admin.team.edit', $a) }}">{{ $a->name }}</a>@if($a->id === auth('admin')->id()) <span class="muted small">(you)</span>@endif<div class="t2">{{ $a->email }}</div></div></div></td>
      <td class="small">{{ $a->roleNames() }}</td>
      <td><x-admin.pill :tone="['active'=>'green','inactive'=>'neutral','invited'=>'blue'][$a->status] ?? 'neutral'">{{ ucfirst($a->status) }}</x-admin.pill></td>
      <td>@if($a->hasTwoFactor())<span class="green small">{!! icon('shield-check', 14) !!} On</span>@else<span class="muted small">Off</span>@endif</td>
      <td class="small muted nowrap">{{ $a->last_login_at ? fmt_dt($a->last_login_at) : 'Never' }}</td><td class="small muted">{{ fmt_date($a->created_at) }}</td>
      <td class="right nowrap"><a class="btn xs" href="{{ route('admin.team.edit', $a) }}">Manage</a>@if($a->status==='invited')<form method="post" action="{{ route('admin.team.resend', $a) }}" style="display:inline">@csrf<button class="btn xs">Resend</button></form>@endif</td></tr>
  @endforeach
  </tbody></table>{{ $admins->links() }}
</div>
<template x-if="open"><div><div class="drawer-ov" @click="open=false"></div>
  <form class="drawer" method="post" action="{{ route('admin.team.invite') }}" data-confirm="Send this invitation?" data-confirm-password>@csrf
    <div class="modal-h"><h2>Invite an admin</h2><button type="button" class="icon-btn" @click="open=false">{!! icon('x') !!}</button></div>
    <div class="modal-b">
      <x-admin.field label="Name" name="name" required><input name="name" required></x-admin.field>
      <x-admin.field label="Email" name="email" required><input type="email" name="email" required></x-admin.field>
      <div class="field"><label>Role <span class="req">*</span></label><div class="stack-sm">@foreach($roles as $r)<label class="cb"><input type="checkbox" name="roles[]" value="{{ $r->name }}"> {{ $r->name }}</label>@endforeach</div></div>
    </div>
    <div class="modal-f"><button type="button" class="btn" @click="open=false">Cancel</button><button class="btn primary">Send invitation</button></div>
  </form></div></template>
</div>
@endsection
