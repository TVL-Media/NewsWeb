@extends('install.layout')
@section('content')
  @php $db = $data['db'] ?? []; @endphp
  <h1>Database</h1>
  <p class="muted">Enter the details of an <strong>empty</strong> MySQL 8 (or MariaDB 10.4+) database. The installer never changes a database that already has data.</p>
  <form method="post" action="{{ route('install.save', $step) }}" x-data="dbTest">
    @csrf
    <div class="grid2">
      <div><label class="f" for="db_host">Host</label><input id="db_host" type="text" name="db_host" value="{{ old('db_host', $db['host'] ?? '127.0.0.1') }}" required></div>
      <div><label class="f" for="db_port">Port</label><input id="db_port" type="number" name="db_port" value="{{ old('db_port', $db['port'] ?? 3306) }}" required></div>
    </div>
    <label class="f" for="db_database">Database name</label>
    <input id="db_database" type="text" name="db_database" value="{{ old('db_database', $db['database'] ?? '') }}" required autocomplete="off">
    <div class="grid2">
      <div><label class="f" for="db_username">Username</label><input id="db_username" type="text" name="db_username" value="{{ old('db_username', $db['username'] ?? '') }}" required autocomplete="off"></div>
      <div><label class="f" for="db_password">Password</label>
        <input id="db_password" type="password" name="db_password" autocomplete="new-password" placeholder="{{ isset($db['password']) ? 'Saved. Leave empty to keep it' : '' }}">
        <div class="hint">Laragon's default user is <strong>root</strong> with no password.</div></div>
    </div>
    <div x-show="result" x-cloak class="alert" :class="result && result.ok ? 'alert-ok' : 'alert-error'" role="status" x-text="result && result.message"></div>
    <div class="actions">@include('install._back')
      <span style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="btn btn-outline" @click="test()" :disabled="busy"><span x-text="busy ? 'Testing…' : 'Test connection'">Test connection</span></button>
        <button class="btn btn-primary">Next →</button>
      </span>
    </div>
  </form>
  <script>
    document.addEventListener('alpine:init', () => Alpine.data('dbTest', () => ({
      busy: false, result: null,
      async test() {
        this.busy = true; this.result = null;
        try {
          const r = await fetch(@js(route('install.database.test')), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: new FormData(this.$root),
          });
          this.result = r.status === 429 ? { ok: false, message: 'Too many tries. Wait a minute, then test again.' } : await r.json();
        } catch (e) {
          this.result = { ok: false, message: 'The test did not finish. Check your connection and try again.' };
        }
        this.busy = false;
      },
    })));
  </script>
@endsection
