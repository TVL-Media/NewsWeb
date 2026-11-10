@extends('admin.layouts.app')
@section('title', $a->name)
@section('content')
@php $self = $a->id === auth('admin')->id(); @endphp
<x-admin.ph :title="$a->name" :crumbs="['Admin team' => route('admin.team.index')]" :sub="$a->email.' · added '.fmt_date($a->created_at)">
  <x-admin.pill :tone="['active'=>'green','inactive'=>'neutral','invited'=>'blue'][$a->status] ?? 'neutral'">{{ ucfirst($a->status) }}</x-admin.pill>
  @unless($self)
    @if($a->status !== 'invited')<form method="post" action="{{ route('admin.team.toggle', $a) }}" data-confirm="{{ $a->status==='active' ? 'Deactivate' : 'Activate' }} {{ $a->name }}?" data-confirm-detail="{{ $a->status==='active' ? 'They will be signed out immediately.' : '' }}" data-confirm-password>@csrf<button class="btn {{ $a->status==='active' ? 'danger' : '' }}">{{ $a->status==='active' ? 'Deactivate' : 'Activate' }}</button></form>@endif
    @if($a->hasTwoFactor())<form method="post" action="{{ route('admin.team.reset2fa', $a) }}" data-confirm="Reset two-factor for {{ $a->name }}?" data-confirm-password>@csrf<button class="btn">Reset 2FA</button></form>@endif
  @endunless
</x-admin.ph>
<div class="grid g-main">
  <div class="stack">
    <div class="card"><div class="card-h"><h2>Recent actions</h2><a class="small" href="{{ route('admin.audit.index', ['admin' => $a->id]) }}">Full audit trail</a></div><div class="card-b flush"><table class="t compact"><tbody>
      @forelse($actions as $l)<tr><td class="mono small">{{ $l->action }}</td><td class="small">{{ \Illuminate\Support\Str::limit($l->subject_label, 50) }}</td><td class="small muted nowrap">{{ fmt_dt($l->created_at) }}</td></tr>@empty<tr><td class="muted">No actions yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Sign-in history</h2></div><div class="card-b flush"><table class="t compact"><tbody>
      @forelse($logins as $l)<tr><td><x-admin.pill :tone="$l->success?'green':'red'">{{ $l->success ? 'Success' : str_replace('_',' ',$l->failure_reason) }}</x-admin.pill></td><td class="small mono">{{ $l->ip }}</td><td class="small muted trunc" style="max-width:260px">{{ $l->user_agent }}</td><td class="small muted nowrap">{{ fmt_dt($l->created_at) }}</td></tr>@empty<tr><td class="muted">No sign-ins.</td></tr>@endforelse
    </tbody></table></div></div>
  </div>
  <form class="card" method="post" action="{{ route('admin.team.update', $a) }}" data-confirm="Save changes to {{ $a->name }}?" data-confirm-password>@csrf @method('put')
    <div class="card-h"><h2>Details &amp; role</h2></div>
    <div class="card-b">
      <x-admin.field label="Name" name="name"><input name="name" value="{{ old('name', $a->name) }}"></x-admin.field>
      <x-admin.field label="Email" name="email"><input type="email" name="email" value="{{ old('email', $a->email) }}"></x-admin.field>
      <div class="field"><label>Roles</label>@if($self)<div class="help mb-2">You cannot change your own role.</div>@endif
        <div class="stack-sm">@foreach($roles as $r)<label class="cb"><input type="checkbox" name="roles[]" value="{{ $r->name }}" @checked($a->hasRole($r->name)) @if($self) onclick="return false" @endif> {{ $r->name }}</label>@endforeach</div></div>
      <dl class="kv mt-3"><dt>2FA</dt><dd>{{ $a->hasTwoFactor() ? 'On since '.fmt_date($a->two_factor_confirmed_at) : 'Off' }}</dd><dt>Last sign-in</dt><dd>{{ fmt_dt($a->last_login_at) }} {{ $a->last_login_ip }}</dd></dl>
    </div><div class="card-f"><button class="btn primary">Save</button></div>
  </form>
</div>
@endsection
