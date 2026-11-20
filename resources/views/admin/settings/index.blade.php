@extends('admin.layouts.app')
@section('title', 'Platform settings')
@section('content')
<x-admin.ph title="Platform settings" sub="Changes are recorded in the audit log. Saving asks for your password." />
<nav class="tabs">@foreach($tabs as $k => $t)<a href="{{ route('admin.settings', $k) }}" class="{{ $tab===$k ? 'on' : '' }}">{{ $t[0] }}</a>@endforeach
  @can('roles.manage')<a href="{{ route('admin.roles.index') }}">Roles &amp; permissions</a>@endcan
  @can('translation.settings')<a href="{{ route('admin.translations.settings') }}">Translation</a>@endcan
</nav>
<div class="grid g-main">
  <form class="card" method="post" action="{{ route('admin.settings.update', $tab) }}" enctype="multipart/form-data" data-confirm="Save {{ strtolower($tabs[$tab][0]) }} settings?" data-confirm-password>
    @csrf @method('put')
    <div class="card-h"><h2>{{ $tabs[$tab][0] }}</h2></div>
    @php
      $featureKeys = collect($tabs[$tab][1])->filter(fn ($f) => $f[0] === 'feature')->keys()->map(fn ($k) => substr($k, 9));
    @endphp
    {{-- Features tab: the On / Off / Needs setup pill follows the switch as soon as it is clicked. --}}
    <div class="card-b" @if($featureKeys->isNotEmpty()) x-data="{
        on: @js($featureKeys->mapWithKeys(fn ($k) => [$k => (bool) old('features__'.$k, setting('features.'.$k))])),
        block: @js($featureKeys->mapWithKeys(fn ($k) => [$k => \App\Support\Features::blocker($k)])),
        why(k) {
          if (this.block[k]) return this.block[k];
          if (k === 'morning5' && !(this.on.listen && !this.block.listen)) return 'Morning 5 is an audio briefing, so it needs Listen to be working.';
          return null;
        },
      }" @endif>
      @foreach($tabs[$tab][1] as $key => $f)
        @php $n = str_replace('.', '__', $key); $v = old($n, setting($key)); [$type, $label, $help] = $f; @endphp
        @switch($type)
          @case('bool')<div class="field"><label class="toggle"><input type="checkbox" name="{{ $n }}" value="1" @checked($v)><span class="tr"></span><span><strong>{{ $label }}</strong>@if($help)<br><span class="small muted">{{ $help }}</span>@endif</span></label></div>@break
          @case('select')<x-admin.field :label="$label" :name="$n" :help="$help"><select name="{{ $n }}">@foreach($f[4] as $ok => $ol)<option value="{{ $ok }}" @selected($v==$ok)>{{ $ol }}</option>@endforeach</select></x-admin.field>@break
          @case('textarea')<x-admin.field :label="$label" :name="$n" :help="$help"><textarea name="{{ $n }}" rows="5">{{ $v }}</textarea></x-admin.field>@break
          @case('lines')<x-admin.field :label="$label" :name="$n" :help="$help"><textarea name="{{ $n }}" rows="5">{{ is_array($v) ? implode("\n", $v) : $v }}</textarea></x-admin.field>@break
          @case('number')<x-admin.field :label="$label" :name="$n" :help="$help"><input type="number" name="{{ $n }}" value="{{ $v }}" step="{{ $f[5] ?? 1 }}" style="max-width:180px"></x-admin.field>@break
          @case('color')<x-admin.field :label="$label" :name="$n" :help="$help"><div class="row" x-data="{c:'{{ $v }}'}"><input type="color" x-model="c"><input name="{{ $n }}" x-model="c" class="mono" style="max-width:120px"></div></x-admin.field>@break
          @case('file')<x-admin.field :label="$label" :name="$n" :help="$help">@if($v)<img src="{{ asset('storage/'.$v) }}" alt="" style="height:40px;margin-bottom:6px;display:block">@endif<input type="file" name="{{ $n }}" accept="image/*">@if($v)<label class="cb small mt-1"><input type="checkbox" name="{{ $n }}__clear" value="1"> Remove</label>@endif</x-admin.field>@break
          @case('secret')<x-admin.field :label="$label" :name="$n" :help="$help"><input type="password" name="{{ $n }}" placeholder="{{ \App\Support\Settings::masked($key) ?: 'Not set' }}" autocomplete="new-password">@if(\App\Support\Settings::masked($key))<label class="cb small mt-1"><input type="checkbox" name="{{ $n }}__clear" value="1"> Remove the saved key</label>@endif</x-admin.field>@break
          @case('feature')@php $fk = substr($key, 9); @endphp<div class="field"><label class="toggle"><input type="checkbox" name="{{ $n }}" value="1" x-model="on.{{ $fk }}"><span class="tr"></span><span><strong>{{ $label }}</strong> <span class="pill green" x-show="on.{{ $fk }} && !why('{{ $fk }}')" x-cloak>On</span><span class="pill amber" x-show="on.{{ $fk }} && why('{{ $fk }}')" x-cloak>Needs setup</span><span class="pill" x-show="!on.{{ $fk }}" x-cloak>Off</span><br><span class="small muted">{{ $help }}</span><span x-show="on.{{ $fk }} && why('{{ $fk }}')" x-cloak><br><span class="small" style="color:var(--amber)" x-text="why('{{ $fk }}')"></span></span></span></label></div>@break
          @case('html')@case('json')<x-admin.field :label="$label" :name="$n" :help="$help"><textarea name="{{ $n }}" rows="{{ $type === 'html' ? 10 : 6 }}" class="mono" spellcheck="false">{{ $v }}</textarea></x-admin.field>@break
          @case('roles')<x-admin.field :label="$label" :name="$n" :help="$help"><div class="row wrap">@foreach($roles as $r)<label class="cb"><input type="checkbox" name="{{ $n }}[]" value="{{ $r }}" @checked(in_array($r, (array) $v))> {{ $r }}</label>@endforeach</div></x-admin.field>@break
          @default<x-admin.field :label="$label" :name="$n" :help="$help"><input type="{{ $type === 'email' ? 'email' : 'text' }}" name="{{ $n }}" value="{{ $v }}"></x-admin.field>
        @endswitch
      @endforeach
    </div>
    <div class="card-f"><button class="btn primary">Save changes</button></div>
  </form>
  <aside class="stack">
    @if($tab === 'content')
      <div class="card"><div class="card-b small muted">
        <strong class="strong">Newsroom automation</strong>
        <p class="mt-1">Feed fetching, AI drafts and auto-publish rules live under <a href="{{ route('admin.newsroom.autopublish') }}">Newsroom → Auto-publish rules</a>.</p>
      </div></div>
    @endif
    <div class="card"><div class="card-b small muted">
      <strong class="strong">About these settings</strong>
      <p class="mt-1">Settings are cached and take effect immediately. Every change is written to the <a href="{{ route('admin.audit.index', ['module' => 'settings']) }}">audit log</a> with the old and new values. Secrets are stored encrypted and never shown again.</p>
    </div></div>
  </aside>
</div>
@endsection
