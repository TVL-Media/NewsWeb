{{-- Red "N" mark + NextGenNews in Inter 900. `light` = light red for the black footer. --}}
@props(['wordmark' => true, 'light' => false, 'size' => 28, 'text' => 'text-[20px]'])
@php $fill = $light ? '#FF6B74' : '#D7141E'; @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 24" width="{{ $size }}" height="{{ round($size * 24 / 28) }}"
         @if ($wordmark) aria-hidden="true" @else role="img" aria-label="NextGenNews" @endif>
        <path d="M2 22 L8 2 H13 L18 15 L22 2 H27 L21 22 H16 L11 9 L7 22 Z" fill="{{ $fill }}"/>
    </svg>
    @if ($wordmark)
        <span class="{{ $text }} font-black tracking-[-0.5px] {{ $light ? 'text-white' : 'text-ink' }}">NextGenNews</span>
    @endif
</span>
