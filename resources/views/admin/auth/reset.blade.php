@extends('admin.layouts.auth')
@section('title', 'Choose a new password')
@section('content')
<h2>Choose a new password</h2>
@if($errors->any())<div class="alert red">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.password.update') }}">
  @csrf <input type="hidden" name="token" value="{{ $token }}">
  <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $email) }}" required></div>
  <div class="field"><label>New password</label><input type="password" name="password" required autocomplete="new-password"><div class="help">At least {{ setting('security.password_min_length', 10) }} characters with upper and lower case letters and a number.</div></div>
  <div class="field"><label>Confirm password</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
  <button class="btn primary w-full">Update password</button>
</form>
@endsection
