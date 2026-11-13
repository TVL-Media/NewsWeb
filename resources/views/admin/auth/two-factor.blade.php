@extends('admin.layouts.auth')
@section('title', 'Two-factor check')
@section('content')
<h2>Two-factor check</h2>
<p class="muted mb-4">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
@if($errors->any())<div class="alert red">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.2fa.verify') }}">
  @csrf
  <div class="field"><label for="code">Code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus style="font-size:18px;letter-spacing:.2em"></div>
  <button class="btn primary w-full">Verify</button>
</form>
<p class="mt-4 small"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
