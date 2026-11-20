@extends('admin.layouts.app')
@section('title', 'System update')
@section('content')
<x-admin.ph title="System update" sub="Apply a new version after you upload its files. Your stories, readers, settings and uploads are kept." />
<div class="grid g-main">
  <div class="stack">
    @if ($result)
      <div class="card">
        <div class="card-h"><h2>Last update</h2><span class="pill {{ $result['ok'] ? 'green' : 'red' }}">{{ $result['ok'] ? 'Complete' : 'Stopped' }}</span></div>
        <div class="card-b">
          @foreach ($result['steps'] as $s)
            <div class="row" style="gap:10px;align-items:flex-start;margin-bottom:8px">
              <span class="pill {{ $s['ok'] ? 'green' : 'red' }}">{{ $s['ok'] ? '✓' : '✕' }}</span>
              <div><strong>{{ $s['label'] }}</strong><div class="small muted">{{ $s['message'] }}</div></div>
            </div>
          @endforeach
          @unless ($result['ok'])
            <p class="small mt-1">Fix the problem (details are in <code>storage/logs/laravel.log</code>), then run the update again. Finished changes are not repeated. If you are stuck, restore your backup.</p>
          @endunless
        </div>
      </div>
    @endif

    <div class="card">
      <div class="card-h"><h2>Version</h2>
        @if ($needed)<span class="pill amber">Update ready</span>@else<span class="pill green">Up to date</span>@endif
      </div>
      <div class="card-b">
        <table class="t">
          <tr><td class="muted">Running now</td><td><strong>{{ $installed }}</strong></td></tr>
          <tr><td class="muted">Files on the server</td><td><strong>{{ $code }}</strong></td></tr>
          <tr><td class="muted">Database changes waiting</td><td>{{ count($pending) ?: 'None' }}</td></tr>
          @if (! empty($lock['installed_at']))<tr><td class="muted">Installed</td><td>{{ fmt_dt(\Illuminate\Support\Carbon::parse($lock['installed_at'])) }}</td></tr>@endif
          @if (! empty($lock['updated_at']))<tr><td class="muted">Last updated</td><td>{{ fmt_dt(\Illuminate\Support\Carbon::parse($lock['updated_at'])) }}</td></tr>@endif
          <tr><td class="muted">PHP</td><td>{{ PHP_VERSION }}</td></tr>
        </table>
        @if ($pending)
          <details class="mt-2"><summary class="small">Show the {{ count($pending) }} database {{ \Illuminate\Support\Str::plural('change', count($pending)) }}</summary>
            <ul class="small mono">@foreach ($pending as $m)<li>{{ $m }}</li>@endforeach</ul></details>
        @endif
      </div>
    </div>

    <form class="card" method="post" action="{{ route('admin.system.update.run') }}" data-confirm="Run the update now? The site shows a short maintenance page while it runs." data-confirm-password>
      @csrf
      <div class="card-h"><h2>Run update</h2></div>
      <div class="card-b">
        <div class="alert" style="background:#FFF8EB;color:#92400E;border-radius:10px;padding:12px 14px">
          {!! icon('alert-triangle', 15) !!} <strong>Back up first.</strong> Download a copy of your database (phpMyAdmin → Export, or <code>mysqldump</code>) and of your <code>.env</code> file and <code>storage/app</code> folder. If anything goes wrong you can put them back.
        </div>
        <label class="cb mt-2"><input type="checkbox" name="backup" value="1" required> I have a backup of the database and files</label>
        @error('backup')<div class="small" style="color:var(--red)">{{ $message }}</div>@enderror
        <p class="small muted mt-2">What happens: maintenance mode on → database changes → caches rebuilt → new version recorded → site back online. Usually under a minute. Nothing is deleted.</p>
      </div>
      <div class="card-f"><button class="btn primary">{!! icon('refresh-cw', 14) !!} Run update</button></div>
    </form>
  </div>

  <aside class="stack">
    <div class="card"><div class="card-b small muted">
      <strong class="strong">How to update</strong>
      <ol class="mt-1" style="padding-left:18px">
        <li>Back up the database and files.</li>
        <li>Upload the new version's files over the old ones. Keep your <code>.env</code> and <code>storage</code> folder.</li>
        <li>Open this page and press <strong>Run update</strong>.</li>
      </ol>
      <p class="mt-1">Full steps are in <strong>UPGRADE.md</strong>. On a server with SSH you can also run <code>php artisan nextgennews:update</code>.</p>
    </div></div>
  </aside>
</div>
@endsection
