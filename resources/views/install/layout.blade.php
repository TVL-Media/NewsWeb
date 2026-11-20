{{-- Web installer layout. Self-contained (no Vite build, no database): inline styles + Alpine from public/vendor. --}}
@php
    $i = array_search($step, $steps, true);
    $percent = (int) round(($i + 1) / count($steps) * 100);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>{{ \App\Http\Controllers\InstallController::LABELS[$step] }} · Install NextGenNews</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
<style>
  :root{--red:#D7141E;--red-dark:#A80F17;--tint:#FDF1F1;--ink:#111;--muted:#62666D;--line:#E7E4E0;--bg:#F6F5F3;--green:#15803D;--green-tint:#ECFDF3;--amber:#B45309;--amber-tint:#FFF8EB}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
  [x-cloak]{display:none!important}
  a{color:var(--red)}
  .wrap{max-width:760px;margin:0 auto;padding:32px 16px 64px}
  .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:20px;letter-spacing:-.4px}
  .brand svg{flex:none}
  .progress{margin:24px 0 8px;height:8px;border-radius:99px;background:#E9E6E2;overflow:hidden}
  .progress span{display:block;height:100%;background:var(--red);border-radius:99px;transition:width .3s}
  .steps{display:flex;flex-wrap:wrap;gap:4px 14px;font-size:12.5px;color:var(--muted);margin-bottom:22px}
  .steps .on{color:var(--red);font-weight:700}.steps .done{color:var(--ink)}
  .card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:28px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
  h1{font-size:28px;line-height:1.2;font-weight:900;letter-spacing:-.6px;margin:0 0 6px}
  h2{font-size:17px;font-weight:800;margin:22px 0 8px}
  p{margin:0 0 12px}.muted{color:var(--muted)}.small{font-size:13px}
  label.f{display:block;font-weight:700;font-size:13.5px;margin:16px 0 6px}
  .hint{font-size:12.5px;color:var(--muted);margin-top:5px}
  input[type=text],input[type=email],input[type=password],input[type=url],input[type=number],select{width:100%;height:46px;border:1.5px solid var(--line);border-radius:14px;padding:0 14px;font:inherit;background:#fff;color:var(--ink)}
  input:focus,select:focus{outline:none;border-color:var(--ink)}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
  @media (max-width:560px){.grid2{grid-template-columns:1fr}.card{padding:20px}}
  .actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;margin-top:26px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:46px;padding:0 22px;border-radius:99px;font:inherit;font-weight:800;border:1.5px solid transparent;cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--red);color:#fff}.btn-primary:hover{background:var(--red-dark)}
  .btn-outline{background:#fff;color:var(--ink);border-color:var(--line)}.btn-outline:hover{border-color:var(--ink)}
  .btn-link{background:none;color:var(--muted);padding:0 6px}
  .btn[disabled]{opacity:.55;cursor:not-allowed}
  .alert{border-radius:14px;padding:12px 14px;font-size:14px;font-weight:600;margin:14px 0}
  .alert-error{background:var(--tint);color:var(--red-dark)}
  .alert-ok{background:var(--green-tint);color:var(--green)}
  .alert-warn{background:var(--amber-tint);color:var(--amber)}
  .checks{list-style:none;margin:8px 0 0;padding:0;border:1px solid var(--line);border-radius:16px;overflow:hidden}
  .checks li{display:flex;gap:12px;align-items:flex-start;padding:11px 14px;border-top:1px solid var(--line)}
  .checks li:first-child{border-top:0}
  .checks .name{font-weight:700}.checks .note{font-size:12.5px;color:var(--muted)}
  .dot{flex:none;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:900;margin-top:1px}
  .dot.ok{background:var(--green)}.dot.no{background:var(--red)}.dot.warn{background:#D97706}.dot.wait{background:#D4D0CB}
  .dot.run{background:#fff;border:3px solid #F1D1D3;border-top-color:var(--red);animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  .cb{display:flex;gap:10px;align-items:flex-start;margin-top:16px;font-size:14px}
  .cb input{width:18px;height:18px;margin-top:2px;accent-color:var(--red)}
  .code{display:flex;gap:8px;align-items:center;background:#111;color:#F3F3F3;border-radius:14px;padding:10px 10px 10px 14px;font:12.5px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;margin-top:6px}
  .code code{flex:1;overflow-x:auto;white-space:nowrap}
  .code button{flex:none;height:32px;border-radius:99px;border:0;padding:0 14px;font:700 12.5px Inter,sans-serif;background:#fff;color:#111;cursor:pointer}
  .foot{margin-top:18px;text-align:center;font-size:12.5px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <svg viewBox="0 0 28 24" width="28" height="24" aria-hidden="true"><path d="M2 22 L8 2 H13 L18 15 L22 2 H27 L21 22 H16 L11 9 L7 22 Z" fill="#D7141E"/></svg>
    NextGenNews <span class="muted" style="font-weight:600;font-size:14px">installer · v{{ config('app-version.version') }}</span>
  </div>

  <div class="progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Install progress"><span style="width: {{ $percent }}%"></span></div>
  <nav class="steps" aria-label="Steps">
    @foreach ($steps as $n => $s)
      <span class="{{ $n === $i ? 'on' : ($n < $i ? 'done' : '') }}">{{ $n + 1 }}. {{ \App\Http\Controllers\InstallController::LABELS[$s] }}</span>
    @endforeach
  </nav>

  <main class="card">
    @error('step')<div class="alert alert-error" role="alert">{{ $message }}</div>@enderror
    @yield('content')
  </main>
  <p class="foot">Need help? See <strong>documentation/INSTALL.md</strong> in the download.</p>
</div>
</body>
</html>
