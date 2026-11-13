<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Preview · {{ $s->title() }}</title><link rel="stylesheet" href="{{ asset('css/admin.css') }}"><script defer src="{{ asset('vendor/alpine.min.js') }}"></script></head>
<body style="background:#EDEBE8">
<div style="max-width:960px;margin:0 auto;padding:24px" x-data="{ lang: 'en' }">
  <div class="row between mb-4"><div><div class="xsmall muted">PREVIEW · not public</div><x-admin.status :content="$s" /></div>
    <div class="row"><div class="seg"><button class="btn sm" :class="lang==='en' && 'primary'" @click="lang='en'">English</button><button class="btn sm" :class="lang==='hi' && 'primary'" @click="lang='hi'">हिंदी</button></div><button class="btn sm" onclick="window.close()">Close</button></div></div>
  @foreach(['en', 'hi'] as $lang)
  @php $v = $s->versions->firstWhere('language', $lang) ?? $s->versions->firstWhere('language', 'en'); @endphp
  <div class="phone" x-show="lang==='{{ $lang }}'" @if($lang === 'hi') x-cloak @endif><div class="bar"></div><div class="scr" lang="{{ $lang }}">
    @if($s->photo_path)<div class="pv-cover" style="background-image:url('{{ $s->photoUrl() }}')"></div>@endif
    <div class="pv-b">
      @if($s->correction_label)<div class="small red mb-2">{{ $s->correction_label }}</div>@endif
      <div class="pv-cat">{{ $s->is_breaking ? 'Breaking · ' : '' }}{{ $s->page?->name ?? ($lang === 'hi' ? $s->category?->name_hi : $s->category?->name_en) }}</div>
      <div class="pv-title">{{ $v?->headline }}</div>
      <div class="pv-meta">{{ $s->read_minutes }} min read{{ $s->photo_credit ? ' · Photo: '.$s->photo_credit : '' }}</div>
      <div class="pv-body">
        <p>{{ $v?->card_text }}</p>
        @if($v && $v->key_points)<ul>@foreach($v->key_points as $p)<li>{{ $p }}</li>@endforeach</ul>@endif
        @if($v?->why_it_matters)<div class="alert red"><span class="t">{{ $lang === 'hi' ? 'यह क्यों मायने रखता है' : 'Why it matters' }}</span>{{ $v->why_it_matters }}</div>@endif
        @foreach($v?->paragraphs() ?? [] as $p)<p>{{ $p }}</p>@endforeach
      </div>
      @if($lang === 'hi' && ! $s->versions->firstWhere('language', 'hi'))<div class="alert amber">No Hindi version yet: Hindi readers see the English text.</div>@endif
    </div>
  </div></div>
  @endforeach
</div></body></html>
