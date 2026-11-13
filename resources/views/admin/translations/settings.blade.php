@extends('admin.layouts.app')
@section('title', 'Translation settings')
@section('content')
<x-admin.ph title="Translations" sub="Which AI writes the translations, the house style, and a monthly cap." />
@include('admin.translations._tabs')
<form method="post" action="{{ route('admin.translations.settings.save') }}" class="grid g2">@csrf @method('put')
  <div class="card"><div class="card-h"><h2>Provider</h2></div><div class="card-b" x-data="{ p: '{{ setting('translation.provider', 'auto') }}' }">
    <x-admin.field label="Provider" name="provider"><select name="provider" x-model="p">
      <option value="auto">Automatic: the newsroom’s Claude key if set, else Test mode</option>
      <option value="claude">Newsroom Claude (ANTHROPIC_API_KEY in .env){{ $aiReady ? '' : ' · key not set' }}</option>
      <option value="anthropic">Anthropic with a separate key</option>
      <option value="openai">OpenAI-compatible API</option>
      <option value="fake">Test mode (no API calls)</option></select></x-admin.field>
    <div x-show="p === 'anthropic' || p === 'openai'">
      <x-admin.field label="Model" name="model"><input name="model" value="{{ setting('translation.model') }}"></x-admin.field>
      <x-admin.field label="API key" name="api_key" help="Stored encrypted. Leave blank to keep the current key."><input type="password" name="api_key" placeholder="{{ \App\Support\Settings::masked('translation.api_key') ?: 'Not set' }}" autocomplete="off"></x-admin.field>
      <div x-show="p==='openai'"><x-admin.field label="API base URL" name="base_url"><input name="base_url" value="{{ setting('translation.base_url', 'https://api.openai.com/v1') }}"></x-admin.field></div>
    </div>
    <x-admin.field label="Style instructions" name="style_prompt"><textarea name="style_prompt" rows="3">{{ setting('translation.style_prompt') }}</textarea></x-admin.field>
  </div></div>
  <div class="stack">
    <div class="card"><div class="card-h"><h2>Cost</h2></div><div class="card-b">
      <x-admin.field label="Monthly character limit" name="monthly_char_limit" :help="'Used this month: '.number_format($used).'. 0 means no limit.'"><input type="number" min="0" name="monthly_char_limit" value="{{ setting('translation.monthly_char_limit') }}"></x-admin.field>
      <p class="small muted">Every translation waits for an editor before readers see it. That can’t be turned off.</p>
    </div></div>
    <div class="row"><button class="btn primary">Save settings</button></div>
  </div>
</form>
@endsection
