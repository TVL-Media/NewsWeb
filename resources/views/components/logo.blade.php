{{-- Site logo. Uploaded logo (Admin → Settings → General) if there is one; otherwise the "N" mark in the
     brand colour + the site name in Inter 900. `light` = light variant for the black footer. --}}
@props(['wordmark' => true, 'light' => false, 'size' => 28, 'text' => 'text-[20px]'])
@php
    $logo = site_logo_url();
    $fill = site_color() === '#D7141E' ? ($light ? '#FF6B74' : '#D7141E') : site_color();
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $wordmark ? '' : site_name() }}" style="height: {{ $size + 4 }}px; width: auto"
             class="{{ $light ? 'rounded bg-white px-1' : '' }}">
        @if ($wordmark)
            <span class="{{ $text }} font-black tracking-[-0.5px] {{ $light ? 'text-white' : 'text-ink' }}">{{ site_name() }}</span>
        @endif
    @else
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 24" width="{{ $size }}" height="{{ round($size * 24 / 28) }}"
             @if ($wordmark) aria-hidden="true" @else role="img" aria-label="{{ site_name() }}" @endif>
            <path d="M2 22 L8 2 H13 L18 15 L22 2 H27 L21 22 H16 L11 9 L7 22 Z" fill="{{ $fill }}"/>
        </svg>
        @if ($wordmark)
            <span class="{{ $text }} font-black tracking-[-0.5px] {{ $light ? 'text-white' : 'text-ink' }}">{{ site_name() }}</span>
        @endif
    @endif
</span>
