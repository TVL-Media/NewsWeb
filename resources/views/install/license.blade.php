@extends('install.layout')
@section('content')
  <h1>Purchase code</h1>
  <p class="muted">Find it on Envato: <strong>Downloads → NextGenNews → License certificate &amp; purchase code</strong>.</p>
  <form method="post" action="{{ route('install.license') }}">@csrf
    <label class="f" for="purchase_code">Purchase code</label>
    <input id="purchase_code" type="text" name="purchase_code" value="{{ old('purchase_code', $data['license'] ?? '') }}" placeholder="1a2b3c4d-1234-5678-9abc-1234567890ab" required autocomplete="off" spellcheck="false">
    <div class="actions">@include('install._back')<button class="btn btn-primary">Check and continue →</button></div>
  </form>
@endsection
