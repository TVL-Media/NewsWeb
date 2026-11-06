{{-- Decorative waveform bars (.wave-bar). Deterministic heights so it doesn't change between renders. --}}
@props(['bars' => 34, 'height' => 34])
@php
    $heights = collect(range(0, $bars - 1))->map(fn ($i) => 0.28 + 0.72 * abs(sin($i * 0.73) * cos($i * 0.31)));
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-[3px]']) }} style="height: {{ $height }}px" aria-hidden="true">
    @foreach ($heights as $h)
        <span class="wave-bar" style="height: {{ round($h * $height) }}px"></span>
    @endforeach
</div>
