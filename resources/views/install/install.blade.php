@extends('install.layout')
@section('content')
  @php
    $site = $data['site'] ?? [];
    $url = rtrim($site['url'] ?? url('/'), '/');
    $base = base_path();
    $cron = '* * * * * cd '.$base.' && php artisan schedule:run >> /dev/null 2>&1';
    $queue = 'cd '.$base.' && php artisan queue:work --tries=3 --timeout=900';
  @endphp
  <div x-data="installer">
    {{-- Before: summary --}}
    <div x-show="state === 'ready'">
      <h1>Ready to install</h1>
      <p class="muted">Please check these details. Nothing has been changed yet.</p>
      <ul class="checks">
        <li><span class="dot ok">✓</span><div><div class="name">{{ $site['name'] ?? '' }}</div><div class="note">{{ $url }} · {{ ($site['locale'] ?? 'en') === 'hi' ? 'Hindi' : 'English' }} · {{ $site['timezone'] ?? '' }} · {{ ($site['env'] ?? '') === 'local' ? 'testing on this computer' : 'live website' }}</div></div></li>
        <li><span class="dot ok">✓</span><div><div class="name">Database {{ $data['db']['database'] ?? '' }}</div><div class="note">{{ $data['db']['username'] ?? '' }} at {{ $data['db']['host'] ?? '' }}:{{ $data['db']['port'] ?? '' }}</div></div></li>
        <li><span class="dot ok">✓</span><div><div class="name">Admin: {{ $data['admin']['email'] ?? '' }}</div><div class="note">Super Admin</div></div></li>
        <li><span class="dot {{ empty($data['keys']) ? 'warn' : 'ok' }}">{{ empty($data['keys']) ? '!' : '✓' }}</span><div><div class="name">{{ empty($data['keys']) ? 'No API keys yet' : count(array_filter(['ai_key', 'tts_google_key', 'tts_azure_key', 'sms_key'], fn ($k) => isset($data['keys'][$k]))).' API key(s)' }}</div><div class="note">Add or change keys any time in Admin → Settings.</div></div></li>
        <li><span class="dot {{ ! empty($data['sample']) ? 'ok' : 'wait' }}">{{ ! empty($data['sample']) ? '✓' : '–' }}</span><div><div class="name">{{ ! empty($data['sample']) ? 'With sample data' : 'Without sample data' }}</div></div></li>
      </ul>
      <div class="actions">@include('install._back')<button type="button" class="btn btn-primary" @click="start()">Install now</button></div>
    </div>

    {{-- During: progress list --}}
    <div x-show="state === 'running' || state === 'failed'" x-cloak>
      <h1 x-text="state === 'failed' ? 'Installation paused' : 'Installing…'"></h1>
      <p class="muted" x-show="state === 'running'">Keep this page open. Adding sample data can take a few minutes.</p>
      <ul class="checks" aria-live="polite">
        <template x-for="t in tasks" :key="t.key">
          <li>
            <span class="dot" :class="{ ok: t.status === 'ok', no: t.status === 'error', run: t.status === 'running', wait: t.status === 'waiting' }" x-text="t.status === 'ok' ? '✓' : (t.status === 'error' ? '✕' : '')"></span>
            <div><div class="name" x-text="t.label"></div><div class="note" x-show="t.message" x-text="t.message"></div></div>
          </li>
        </template>
      </ul>
      <div x-show="state === 'failed'" x-cloak>
        <div class="alert alert-error" role="alert">Fix the problem above, then press Retry. Steps that already worked are not repeated, and your site stays offline until the installation is complete.</div>
        <div class="actions"><a class="btn btn-outline" href="{{ route('install.step', 'database') }}">Change database details</a><button type="button" class="btn btn-primary" @click="start()">Retry</button></div>
      </div>
    </div>

    {{-- After: finish --}}
    <div x-show="state === 'done'" x-cloak>
      <h1>Your site is ready 🎉</h1>
      <p class="muted">The installer is now locked: /install no longer exists.</p>
      <div class="actions" style="justify-content:flex-start;margin-top:12px">
        <a class="btn btn-primary" href="{{ $url }}/admin">Open the admin panel</a>
        <a class="btn btn-outline" href="{{ $url }}">View the website</a>
      </div>

      <h2>Two things to set up on the server</h2>
      <p class="small muted" style="margin:0">1. <strong>Cron job</strong>: runs scheduled tasks every minute (news feeds, quiz, Morning 5, scheduled stories). Add it in your hosting panel → Cron Jobs, or with <code>crontab -e</code>.</p>
      <div class="code"><code>{{ $cron }}</code><button type="button" @click="copy($event, @js($cron))">Copy</button></div>
      <p class="small muted" style="margin:14px 0 0">2. <strong>Queue worker</strong>: makes AI drafts, audio and reels in the background. On a VPS run it with Supervisor (see INSTALL.md); on shared hosting add it as a second cron job every minute with <code>--stop-when-empty</code>.</p>
      <div class="code"><code>{{ $queue }}</code><button type="button" @click="copy($event, @js($queue))">Copy</button></div>
      <div class="alert alert-warn" style="margin-top:18px">
        Sign in with the email and password you chose.
        @if (($site['env'] ?? 'production') === 'local')
          Two-step sign-in is off on this test install; turn it on in Admin → Settings → Security.
        @else
          You will be asked to set up two-step sign-in (an authenticator app such as Google Authenticator) on first login.
        @endif
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('alpine:init', () => Alpine.data('installer', () => ({
      state: 'ready',
      tasks: @js(collect($tasks)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'status' => in_array($key, $data['done'] ?? [], true) ? 'ok' : 'waiting', 'message' => ''])->values()),
      async start() {
        this.state = 'running';
        for (const t of this.tasks) {
          if (t.status === 'ok') continue;
          t.status = 'running'; t.message = '';
          let res;
          // A lost connection (e.g. a development server restarting after .env was written) is tried once more by itself.
          for (let attempt = 1; attempt <= 2; attempt++) {
            try {
              const r = await fetch(@js(url('install/run')) + '/' + t.key, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
              });
              res = r.headers.get('content-type')?.includes('json') ? await r.json() : { ok: false, message: r.status === 419 ? 'Your session expired. Reload this page and press Retry.' : 'The server stopped with error ' + r.status + '. Check storage/logs/laravel.log, then retry.' };
              break;
            } catch (e) {
              res = { ok: false, message: 'The connection to the server was lost. Press Retry.' };
              if (attempt === 1) { t.message = 'Reconnecting…'; await new Promise(r => setTimeout(r, 3000)); }
            }
          }
          t.status = res.ok ? 'ok' : 'error';
          t.message = res.message || '';
          if (!res.ok) { this.state = 'failed'; return; }
        }
        this.state = 'done';
      },
      async copy(e, text) {
        try { await navigator.clipboard.writeText(text); } catch (err) {
          const a = document.createElement('textarea'); a.value = text; document.body.appendChild(a); a.select(); document.execCommand('copy'); a.remove();
        }
        e.target.textContent = 'Copied'; setTimeout(() => e.target.textContent = 'Copy', 1500);
      },
    })));
  </script>
@endsection
