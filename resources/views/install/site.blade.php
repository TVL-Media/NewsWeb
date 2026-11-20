@extends('install.layout')
@section('content')
  @php
    $site = $data['site'] ?? [];
    $tz = old('timezone', $site['timezone'] ?? 'Asia/Kolkata');
    $env = old('env', $site['env'] ?? 'production');
  @endphp
  <h1>Your site</h1>
  <p class="muted">You can change all of this later in Admin → Settings.</p>
  <form method="post" action="{{ route('install.save', $step) }}">@csrf
    <label class="f" for="name">Website name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $site['name'] ?? 'NextGenNews') }}" required maxlength="60">

    <label class="f" for="tagline">Tagline <span class="muted" style="font-weight:500">(optional)</span></label>
    <input id="tagline" type="text" name="tagline" value="{{ old('tagline', $site['tagline'] ?? '') }}" maxlength="120" placeholder="Short news with depth, in Hindi and English">

    <label class="f" for="url">Website address</label>
    <input id="url" type="url" name="url" value="{{ old('url', $site['url'] ?? request()->getSchemeAndHttpHost().request()->getBasePath()) }}" required>
    <div class="hint">Use https:// once your SSL certificate is active.</div>

    <div class="grid2">
      <div><label class="f" for="locale">Default language</label>
        <select id="locale" name="locale">
          @foreach (['en' => 'English', 'hi' => 'हिंदी (Hindi)'] as $k => $l)
            <option value="{{ $k }}" @selected(old('locale', $site['locale'] ?? 'en') === $k)>{{ $l }}</option>
          @endforeach
        </select></div>
      <div><label class="f" for="timezone">Timezone</label>
        <select id="timezone" name="timezone">
          @foreach (\DateTimeZone::listIdentifiers() as $z)
            <option value="{{ $z }}" @selected($tz === $z)>{{ $z }}</option>
          @endforeach
        </select></div>
    </div>

    <label class="f" for="env">This installation is</label>
    <select id="env" name="env">
      <option value="production" @selected($env === 'production')>A live website (recommended)</option>
      <option value="local" @selected($env === 'local')>For testing on my computer (error details, test OTP 123456, sample AI drafts)</option>
    </select>

    <label class="cb"><input type="checkbox" name="sample" value="1" @checked(old('sample', $data['sample'] ?? false))>
      <span><strong>Install sample data</strong><br><span class="muted small">30 example stories in English and Hindi, polls, a quiz and demo readers, with placeholder images. Good for a first look; delete them before you go live.</span></span></label>

    <div class="actions">@include('install._back')<button class="btn btn-primary">Next →</button></div>
  </form>
@endsection
