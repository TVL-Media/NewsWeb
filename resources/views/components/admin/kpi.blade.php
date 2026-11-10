@props(['label', 'value', 'href' => null, 'change' => null, 'note' => null, 'attn' => false, 'invert' => false])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="kpi">
  <div class="l"><span>{{ $label }}</span>@if($href)<span class="muted">{!! icon('chevron-right', 14) !!}</span>@endif</div>
  <div class="v {{ $attn ? 'attn' : '' }}">{{ $value }}</div>
  <div class="d">
    @if(!is_null($change))
      @php $good = $invert ? $change < 0 : $change >= 0; @endphp
      <span class="{{ $good ? 'up' : 'down' }}">{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%</span> vs previous
    @endif
    @if($note) <span>{{ $note }}</span>@endif
  </div>
</{{ $tag }}>
