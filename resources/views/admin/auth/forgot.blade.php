@extends('admin.layouts.auth')
@section('title', 'Reset password')
@section('content')
<h2>Reset your password</h2>
<p class="muted mb-4">We'll email you a link to choose a new password.</p>
@if(session('status'))<div class="alert green">{{ session('status') }}</div>@endif
<form method="post" action="{{ route('admin.password.email') }}">
  @csrf
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
  <button class="btn primary w-full">Send reset link</button>
</form>
<p class="mt-4 small"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
