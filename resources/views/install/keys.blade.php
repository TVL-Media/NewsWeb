@extends('install.layout')
@section('content')
  @php
    $keys = $data['keys'] ?? [];
    $saved = fn ($k) => isset($keys[$k]) ? 'Saved. Leave empty to keep it' : '';
  @endphp
  <h1>API keys <span class="muted" style="font-size:16px;font-weight:600">(optional)</span></h1>
  <p class="muted">Each key turns on a feature. Skip this step and add them later in <strong>Admin → Settings</strong>. On a live site, features without a key stay hidden.</p>
  <form method="post" action="{{ route('install.save', $step) }}" x-data="{ tts: @js(old('tts_provider', $keys['tts_provider'] ?? 'none')) }">@csrf
    <h2>AI drafts (Claude)</h2>
    <label class="f" for="ai_key">Claude API key</label>
    <input id="ai_key" type="password" name="ai_key" autocomplete="new-password" placeholder="{{ $saved('ai_key') }}">
    <div class="hint">console.anthropic.com → API keys. Writes short summaries from your news feeds.</div>

    <h2>Audio (text-to-speech)</h2>
    <label class="f" for="tts_provider">Provider</label>
    <select id="tts_provider" name="tts_provider" x-model="tts">
      <option value="none">None for now</option>
      <option value="google">Google Cloud Text-to-Speech</option>
      <option value="azure">Azure AI Speech</option>
    </select>
    <div x-show="tts === 'google'" x-cloak>
      <label class="f" for="tts_google_key">Google API key</label>
      <input id="tts_google_key" type="password" name="tts_google_key" autocomplete="new-password" placeholder="{{ $saved('tts_google_key') }}">
    </div>
    <div x-show="tts === 'azure'" x-cloak class="grid2">
      <div><label class="f" for="tts_azure_key">Azure Speech key</label><input id="tts_azure_key" type="password" name="tts_azure_key" autocomplete="new-password" placeholder="{{ $saved('tts_azure_key') }}"></div>
      <div><label class="f" for="tts_azure_region">Region</label><input id="tts_azure_region" type="text" name="tts_azure_region" value="{{ old('tts_azure_region', $keys['tts_azure_region'] ?? '') }}" placeholder="centralindia"></div>
    </div>

    <h2>Phone login (MSG91 SMS)</h2>
    <label class="f" for="sms_key">MSG91 auth key</label>
    <input id="sms_key" type="password" name="sms_key" autocomplete="new-password" placeholder="{{ $saved('sms_key') }}">
    <div class="grid2">
      <div><label class="f" for="sms_sender">DLT sender ID</label><input id="sms_sender" type="text" name="sms_sender" value="{{ old('sms_sender', $keys['sms_sender'] ?? '') }}" placeholder="NGNEWS"></div>
      <div><label class="f" for="sms_template">DLT template ID</label><input id="sms_template" type="text" name="sms_template" value="{{ old('sms_template', $keys['sms_template'] ?? '') }}"></div>
    </div>

    <div class="actions">@include('install._back')
      <span style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-outline" name="skip" value="1" formnovalidate>Skip</button>
        <button class="btn btn-primary">Save and continue →</button>
      </span>
    </div>
  </form>
@endsection
