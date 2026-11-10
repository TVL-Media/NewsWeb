@php [$label, $val] = $args; $cmpVal = $args[2] ?? null; $note = $args[3] ?? null; $change = ($cmp ?? null) && is_numeric($val) && ! is_null($cmpVal) ? pct_change($val, $cmpVal) : null; @endphp
<div class="kpi"><div class="l"><span>{{ $label }}</span></div><div class="v">{{ is_numeric($val) ? num($val) : $val }}</div>
<div class="d">@if(!is_null($change))<span class="{{ $change >= 0 ? 'up' : 'down' }}">{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%</span> vs {{ num($cmpVal) }} @endif @if($note)<span>{{ $note }}</span>@endif</div></div>
