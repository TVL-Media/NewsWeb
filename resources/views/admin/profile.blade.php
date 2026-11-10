@extends('admin.layouts.app')
@section('title', 'My profile')
@section('content')
<x-admin.ph title="My profile & security" sub="Your account details, password and two-factor authentication." />
<div class="grid g2">
  <div class="stack">
    <form class="card" method="post" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">@csrf @method('put')
      <div class="card-h"><h2>Profile</h2></div>
      <div class="card-b">
        <div class="row mb-3"><span class="avatar" style="width:48px;height:48px;font-size:16px">@if($admin->avatar_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($admin->avatar_path) }}" alt="">@else{{ $admin->initials() }}@endif</span><input type="file" name="avatar" accept="image/*"></div>
        <x-admin.field label="Name" name="name" required><input name="name" value="{{ old('name', $admin->name) }}" required></x-admin.field>
        <x-admin.field label="Email"><input value="{{ $admin->email }}" disabled><div class="help">Ask a Super Admin to change your sign-in email.</div></x-admin.field>
        <x-admin.field label="Phone" name="phone"><input name="phone" value="{{ old('phone', $admin->phone) }}"></x-admin.field>
        <x-admin.field label="Role"><input value="{{ $admin->roleNames() }}" disabled></x-admin.field>
      </div>
      <div class="card-f"><button class="btn primary">Save profile</button></div>
    </form>
    <form class="card" method="post" action="{{ route('admin.profile.password') }}">@csrf @method('put')
      <div class="card-h"><h2>Change password</h2></div>
      <div class="card-b">
        <x-admin.field label="Current password" name="current_password"><input type="password" name="current_password" autocomplete="current-password"></x-admin.field>
        <x-admin.field label="New password" name="password" :help="'At least '.setting('security.password_min_length', 10).' characters, upper and lower case letters and a number.'"><input type="password" name="password" autocomplete="new-password"></x-admin.field>
        <x-admin.field label="Confirm new password"><input type="password" name="password_confirmation" autocomplete="new-password"></x-admin.field>
      </div>
      <div class="card-f"><button class="btn">Change password</button></div>
    </form>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Two-factor authentication</h2>@if($admin->hasTwoFactor())<x-admin.pill tone="green">On</x-admin.pill>@else<x-admin.pill tone="amber">Off</x-admin.pill>@endif</div>
      <div class="card-b">
        @if($codes)
          <div class="alert amber"><span class="t">Save these recovery codes now</span>Each code works once if you lose your phone. They won't be shown again.</div>
          <div class="codes mb-3">@foreach($codes as $c)<span>{{ $c }}</span>@endforeach</div>
        @endif
        @if($admin->hasTwoFactor())
          <p class="muted">Enabled {{ fmt_dt($admin->two_factor_confirmed_at) }}. You'll be asked for a code each time you sign in.</p>
          <div class="row wrap">
            <form method="post" action="{{ route('admin.profile.2fa.codes') }}">@csrf<button class="btn sm">Generate new recovery codes</button></form>
            <form method="post" action="{{ route('admin.profile.2fa.disable') }}" data-confirm="Turn off two-factor authentication?" data-confirm-detail="Your account will be protected by your password only." data-confirm-password data-confirm-danger>@csrf @method('delete')<button class="btn sm danger">Turn off</button></form>
          </div>
        @elseif($secret)
          <p>Scan this code with Google Authenticator, Microsoft Authenticator or 1Password, then enter the 6-digit code.</p>
          <div class="row" style="align-items:flex-start;gap:16px"><div class="qr">{!! $qr !!}</div><div><div class="small muted">Or enter this key manually:</div><code style="word-break:break-all">{{ $secret }}</code></div></div>
          <form method="post" action="{{ route('admin.profile.2fa.confirm') }}" class="row mt-3">@csrf<input name="code" inputmode="numeric" placeholder="123456" style="max-width:140px" required><button class="btn primary">Confirm</button></form>
        @else
          <p class="muted">Add a second step at sign-in using an authenticator app. Required for: {{ implode(', ', (array) setting('security.enforce_2fa_roles', ['Super Admin'])) }}.</p>
          <form method="post" action="{{ route('admin.profile.2fa.start') }}">@csrf<button class="btn primary">Set up two-factor</button></form>
        @endif
      </div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Sessions</h2></div>
      <div class="card-b">
        <p class="muted">Last sign-in {{ fmt_dt($admin->last_login_at) }} from {{ $admin->last_login_ip ?? '—' }}. Idle sessions end after {{ setting('security.session_timeout_minutes', 60) }} minutes.</p>
        <form method="post" action="{{ route('admin.profile.logout-others') }}" data-confirm="Sign out of all other devices?" data-confirm-password>@csrf<button class="btn sm">Sign out other devices</button></form>
      </div>
    </div>
  </div>
</div>
@endsection
