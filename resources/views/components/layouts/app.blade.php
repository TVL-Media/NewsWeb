{{--
    Site layout. Usage:
    <x-layouts.app title="…" description="…" active="home" :chips="true" active-chip="all"> … </x-layouts.app>
--}}
@props([
    'title' => null,
    'description' => null,
    'active' => null,        // home | for-you | reels | briefing | explore
    'chips' => false,        // show the category chips row under the header
    'activeChip' => 'all',
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'noReplay' => false,     // onboarding: don't replay a guest's pending action yet
    'bare' => false,         // no header/footer/nav (onboarding, full-screen flows)
    'immersive' => false,    // reels: dark page, no footer; mobile also hides the header (bottom nav stays)
])
@php
    $pageTitle = $title ? $title.' · NextGenNews' : 'NextGenNews · '.__('Short news with depth, in Hindi and English');
    $metaDescription = $description ?? __('India\'s news in 60 words, with key points, simple explainers and audio. In Hindi and English.');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-text-size="{{ auth()->user()?->text_size ?? 'medium' }}" data-theme="{{ auth()->user()?->theme ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#D7141E">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:site_name" content="NextGenNews">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title ?? 'NextGenNews' }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    <link rel="icon" href="{{ asset('images/logo.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{ $head ?? '' }}

    <script>window.NGN = { loggedIn: @json(auth()->check()), lang: @json(app()->getLocale()), noReplay: @json((bool) $noReplay) };</script>

    @if (config('app.ga4_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga4_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json(config('app.ga4_id')));
        </script>
    @endif
</head>
<body class="min-h-screen flex flex-col {{ $bare ? '' : 'pb-[calc(66px+env(safe-area-inset-bottom))] md:pb-0' }} {{ $immersive ? 'bg-[#111111]' : '' }}"
      x-data x-init="@if (request()->boolean('login')) $store.auth.show() @endif">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-white">{{ __('Skip to content') }}</a>

    @unless ($bare)
        @if ($immersive)
            <div class="hidden md:block"><x-site-header :active="$active" /></div>
        @else
            <x-site-header :active="$active" />
            <x-app-banner />
        @endif
        @if ($chips)
            <x-category-chips :active="$activeChip" />
        @endif
    @endunless

    <main id="main" class="flex-1">
        @if (session('account_notice'))
            <div class="mx-auto max-w-[1280px] px-4 pt-4 md:px-6"><p class="rounded-[14px] bg-brand-tint px-4 py-3 text-[14px] font-semibold text-brand" role="status">{{ session('account_notice') }}</p></div>
        @endif
        {{ $slot }}
    </main>

    @unless ($bare)
        @unless ($immersive)<x-site-footer />@endunless
        <x-bottom-nav :active="$active" />
    @endunless
    <x-audio-player />
    <x-login-sheet />
    <x-reader-sheets />

    {{ $scripts ?? '' }}
    @livewireScriptConfig
</body>
</html>
