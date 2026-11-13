@extends('admin.layouts.auth')
@section('title', 'Accept invitation')
@section('content')
<h2>Set up your account</h2>
<p class="muted mb-4">You've been invited as <strong>{{ $admin->roleNames() }}</strong> ({{ $admin->email }}).</p>
@if($errors->any())<div class="alert red">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.invite.accept', $token) }}">
  @csrf
  <div class="field"><label>Your name</label><input name="name" value="{{ old('name', $admin->name) }}" required></div>
  <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="new-password"><div class="help">At least {{ setting('security.password_min_length', 10) }} characters with upper and lower case letters and a number.</div></div>
  <div class="field"><label>Confirm password</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
  <button class="btn primary w-full">Create account</button>
</form>
@endsection
