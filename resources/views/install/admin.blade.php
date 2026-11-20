@extends('install.layout')
@section('content')
  @php $admin = $data['admin'] ?? []; @endphp
  <h1>Your admin account</h1>
  <p class="muted">The Super Admin can do everything in the newsroom panel at <strong>/admin</strong>, including adding your team.</p>
  <form method="post" action="{{ route('install.save', $step) }}">@csrf
    <label class="f" for="name">Your name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $admin['name'] ?? '') }}" required maxlength="80" autocomplete="name">
    <label class="f" for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email', $admin['email'] ?? '') }}" required autocomplete="email">
    <div class="grid2">
      <div><label class="f" for="password">Password</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
      <div><label class="f" for="password_confirmation">Password again</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <div class="hint">At least 10 characters, with letters and numbers.</div>
    <div class="actions">@include('install._back')<button class="btn btn-primary">Next →</button></div>
  </form>
@endsection
