{{-- All · India · World · Business · Cricket · Tech · Health · {user city} · Good News --}}
@props(['active' => 'all'])
@php
    $order = ['india', 'world', 'business', 'cricket', 'tech', 'health', '__city', 'good-news'];
    $bySlug = $navCategories->keyBy('slug');
    $city = auth()->user()?->city;
    $cityPage = $city?->page;
@endphp
<nav aria-label="{{ __('Categories') }}" {{ $attributes->merge(['class' => 'border-b border-line bg-white']) }}>
    <div class="no-scrollbar mx-auto flex max-w-[1280px] gap-2 overflow-x-auto px-4 py-2 md:px-6">
        <a href="{{ route('home') }}" class="{{ $active === 'all' ? 'chip-active' : 'chip hover:bg-line' }}" @if ($active === 'all') aria-current="page" @endif>{{ __('All') }}</a>
        @foreach ($order as $slug)
            @if ($slug === '__city')
                @if ($city)
                    <a href="{{ $cityPage ? route('page.show', $cityPage) : route('search', ['q' => $city->name_en]) }}"
                       class="{{ $active === 'city' ? 'chip-active' : 'chip hover:bg-line' }}">
                        <x-icon name="map-pin" size="13" class="mr-1" />{{ $city->name() }}
                    </a>
                @endif
            @elseif ($category = $bySlug->get($slug))
                <a href="{{ route('home', ['category' => $slug]) }}"
                   class="{{ $active === $slug ? 'chip-active' : 'chip hover:bg-line' }}"
                   @if ($active === $slug) aria-current="page" @endif>{{ $category->name() }}</a>
            @endif
        @endforeach
    </div>
</nav>
