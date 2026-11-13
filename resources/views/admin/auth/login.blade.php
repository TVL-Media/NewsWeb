@extends('admin.layouts.auth')
@section('title', 'Sign in')
@section('content')
<h2>Sign in</h2>
<p class="muted mb-4">Use your newsroom admin account.</p>
@if(session('status'))<div class="alert green">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert red">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.login.attempt') }}">
  @csrf
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
  <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  <div class="row between mb-4"><label class="cb"><input type="checkbox" name="remember"> Keep me signed in</label><a href="{{ route('admin.password.request') }}" class="small">Forgot password?</a></div>
  <button class="btn primary w-full">Sign in</button>
</form>
@endsection
