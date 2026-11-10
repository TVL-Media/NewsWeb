<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · {{ setting('general.platform_name', 'Newsroom') }} Admin</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' fill='%23C8102E'/%3E%3C/svg%3E">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=4"></head>
<body>
<div class="auth">
  <div class="auth-l">
    <div class="row" style="gap:10px"><span class="brand-mark"></span><strong style="font-size:15px">{{ setting('general.platform_name', 'Newsroom') }} Admin</strong></div>
    <div>
      <h1>Every story, poll and translation, from one desk.</h1>
      <p class="mt-3">Publish, review and schedule content, run polls, manage translations and keep the community safe.</p>
    </div>
    <p class="xsmall" style="color:#8A8D93">Authorised staff only. Sign-ins are logged.</p>
  </div>
  <div class="auth-r"><div class="auth-box">@yield('content')</div></div>
</div>
</body></html>
