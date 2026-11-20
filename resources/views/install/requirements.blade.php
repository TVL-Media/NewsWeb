@extends('install.layout')
@section('content')
  @php $ok = \App\Support\Install\Requirements::passes($php); @endphp
  <h1>Server requirements</h1>
  <p class="muted">Red items must be fixed before you continue. Orange items are optional.</p>
  @if ($ok)
    <div class="alert alert-ok">Everything required is here.</div>
  @else
    <div class="alert alert-error">Your hosting is missing something. Turn the missing PHP extensions on in your hosting panel (often "Select PHP version" → Extensions), then press Check again.</div>
  @endif
  @include('install._checks', ['rows' => $php])
  <form method="post" action="{{ route('install.save', $step) }}">@csrf
    <div class="actions">@include('install._back')
      @if ($ok)<button class="btn btn-primary">Next →</button>@else<a class="btn btn-outline" href="{{ route('install.step', $step) }}">Check again</a>@endif
    </div>
  </form>
@endsection
