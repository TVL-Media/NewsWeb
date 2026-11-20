@extends('install.layout')
@section('content')
  <h1>Let's set up your news site</h1>
  <p class="muted">This takes about five minutes. Have these ready:</p>
  <ul class="checks">
    <li><span class="dot ok">1</span><div><div class="name">An empty MySQL database</div><div class="note">Its name, username and password (create it in your hosting panel, phpMyAdmin or Laragon).</div></div></li>
    <li><span class="dot ok">2</span><div><div class="name">Your website address</div><div class="note">For example https://news.example.com</div></div></li>
    <li><span class="dot ok">3</span><div><div class="name">API keys (optional)</div><div class="note">Claude for AI drafts, Google or Azure for audio, MSG91 for phone login. You can skip them and add them later in Admin → Settings.</div></div></li>
  </ul>
  <p class="small muted" style="margin-top:14px">Nothing is changed until the last step. If something fails, you can fix it and retry.</p>
  <form method="post" action="{{ route('install.save', $step) }}">@csrf
    <div class="actions"><span></span><button class="btn btn-primary">Start →</button></div>
  </form>
@endsection
