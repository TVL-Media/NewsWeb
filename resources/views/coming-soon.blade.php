{{--
    Generic "coming soon" page for features that are a later phase (Reels, Videos, Podcast).
    PROMPT.md: "Don't build ... video or podcasts. Those are later phases." These routes exist
    only so the Explore tiles have somewhere to go; no media backend is built behind them.
--}}
@props([])
<x-layouts.app :title="$title">
    <div class="mx-auto max-w-[640px] px-4 py-16 md:px-6">
        <div class="card p-8 text-center">
            <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-[16px] bg-brand-tint text-brand">
                <x-icon :name="$icon" size="26" />
            </span>
            <h1 class="mt-4 text-[26px] font-black tracking-[-0.5px]">{{ $title }}</h1>
            <p class="mt-2 text-muted">{{ $description }}</p>
            <a href="{{ route('home') }}" class="btn-primary mt-6">{{ __('Back to Home') }}</a>
        </div>
    </div>
</x-layouts.app>
