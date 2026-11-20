@extends('install.layout')
@section('content')
  @php $ok = \App\Support\Install\Requirements::passes($folders); @endphp
  <h1>Folder permissions</h1>
  <p class="muted">The site saves uploads, logs and caches in these folders, so the web server must be able to write to them.</p>
  @if ($ok)
    <div class="alert alert-ok">All folders are writable.</div>
  @else
    <div class="alert alert-error">Make the red folders writable (permission 775, owned by the web server user), then press Check again. On shared hosting use File Manager → Permissions.</div>
  @endif
  @include('install._checks', ['rows' => $folders])
  <form method="post" action="{{ route('install.save', $step) }}">@csrf
    <div class="actions">@include('install._back')
      @if ($ok)<button class="btn btn-primary">Next →</button>@else<a class="btn btn-outline" href="{{ route('install.step', $step) }}">Check again</a>@endif
    </div>
  </form>
@endsection
