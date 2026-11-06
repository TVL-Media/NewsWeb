@props(['active' => null])
@php
    $user = auth()->user();
    $lang = app()->getLocale();
    $nav = [
        'home' => ['label' => __('Home'), 'url' => route('home')],
        'for-you' => ['label' => __('For You'), 'url' => route('for-you')],
        'briefing' => ['label' => __('Briefing'), 'url' => route('briefing')],
        'explore' => ['label' => __('Explore'), 'url' => route('explore')],
    ];
@endphp
<header class="sticky top-0 z-40 border-b border-line bg-white">
    {{-- Desktop (≥ 768px) --}}
    <div class="mx-auto hidden h-[72px] max-w-[1280px] items-center gap-3 px-6 md:flex">
        <a href="{{ route('home') }}" class="no-underline" aria-label="NextGenNews home">
            <x-logo />
        </a>

        <nav aria-label="{{ __('Main') }}" class="ml-3 flex items-center gap-1">
            @foreach ($nav as $key => $item)
                <a href="{{ $item['url'] }}"
                   class="{{ $active === $key ? 'nav-link-active' : 'nav-link hover:bg-chip hover:text-ink' }}"
                   @if ($active === $key) aria-current="page" @endif
                   @if ($key === 'for-you') @click="if (!$store.auth.require('visit', { url: '{{ $item['url'] }}' })) $event.preventDefault()" @endif>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <form action="{{ route('search') }}" method="GET" role="search" class="ml-auto w-full max-w-[400px] lg:max-w-[440px]">
            <label for="header-search" class="sr-only">{{ __('Search') }}</label>
            <div class="relative">
                <x-icon name="search" size="18" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-2" />
                <input id="header-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('Search news, topics, pages') }}"
                       class="h-[42px] w-full rounded-full bg-chip pl-11 pr-4 text-sm text-ink outline-none placeholder:text-muted-2 focus:bg-white focus:ring-2 focus:ring-ink">
            </div>
        </form>

        <x-lang-toggle />

        {{-- Shown in phase 1 but not wired up yet (no notifications until a later phase). --}}
        <span class="relative inline-flex h-11 w-11 items-center justify-center rounded-full text-ink" role="img" aria-label="{{ __('Notifications') }}">
            <x-icon name="bell" size="22" />
            <span class="absolute right-[11px] top-[10px] h-2 w-2 rounded-full bg-brand ring-2 ring-white"></span>
        </span>

        @auth
            <a href="{{ route('account') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[12px] bg-brand text-base font-extrabold text-white no-underline hover:text-white" aria-label="{{ __('Your account') }}">
                {{ $user->initial() }}
            </a>
        @else
            <button type="button" class="btn-primary text-sm" @click="$store.auth.show()">{{ __('Log in') }}</button>
        @endauth
    </div>

    {{-- Mobile web (< 768px) --}}
    <div class="flex h-[58px] items-center gap-1.5 px-4 md:hidden">
        <a href="{{ route('home') }}" class="mr-auto no-underline" aria-label="NextGenNews home">
            <x-logo :size="24" text="text-[18px]" />
        </a>
        <x-lang-toggle compact />
        <a href="{{ route('search') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-ink" aria-label="{{ __('Search') }}">
            <x-icon name="search" size="21" />
        </a>
        @auth
            <a href="{{ route('account') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand text-sm font-extrabold text-white no-underline hover:text-white" aria-label="{{ __('Your account') }}">
                {{ $user->initial() }}
            </a>
        @else
            <button type="button" class="btn-primary h-9 px-4 text-[13px]" @click="$store.auth.show()">{{ __('Log in') }}</button>
        @endauth
    </div>
</header>
