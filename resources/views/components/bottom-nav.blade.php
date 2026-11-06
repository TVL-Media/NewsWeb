{{-- Mobile web only (the theme's .bottom-nav is md:hidden). --}}
@props(['active' => null])
@php
    $items = [
        'home' => ['label' => __('Home'), 'icon' => 'house', 'url' => route('home')],
        'for-you' => ['label' => __('For You'), 'icon' => 'zap', 'url' => route('for-you')],
        'briefing' => ['label' => __('Briefing'), 'icon' => 'layers', 'url' => route('briefing')],
        'explore' => ['label' => __('Explore'), 'icon' => 'layout-grid', 'url' => route('explore')],
    ];
@endphp
<nav class="bottom-nav z-40 pb-[env(safe-area-inset-bottom)]" aria-label="{{ __('Main') }}">
    @foreach ($items as $key => $item)
        <a href="{{ $item['url'] }}" class="{{ $active === $key ? 'active' : '' }}"
           @if ($active === $key) aria-current="page" @endif
           @if ($key === 'for-you') @click="if (!$store.auth.require('visit', { url: '{{ $item['url'] }}' })) $event.preventDefault()" @endif>
            <x-icon :name="$item['icon']" size="22" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
