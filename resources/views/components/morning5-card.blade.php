{{--
    Morning 5 entry points (playback itself lives on /briefing, Milestone 4).
    variant="card":  home right column — player gradient, tilted logo tile, waveform, "2 of 5 read today", white play button.
    variant="mini":  story page rail — compact gradient card.
    variant="strip": mobile home — slim red strip with logo tile, title and play button.
--}}
@props(['variant' => 'card', 'read' => 0, 'briefing' => null])
@php
    $total = $briefing ? count($briefing->story_ids) : 5;
    $progress = auth()->check() ? __(':read of :total read today', ['read' => $read, 'total' => $total]) : __(':total stories · 5 min', ['total' => $total]);
@endphp

@if ($variant === 'strip')
    <a href="{{ route('briefing') }}" {{ $attributes->merge(['class' => 'player-bg flex items-center gap-3 rounded-[20px] p-3 pr-3.5 no-underline hover:text-white']) }}>
        <span class="logo-tile h-12 w-12 shrink-0 !rounded-[14px]"><x-logo :wordmark="false" :size="22" /></span>
        <span class="min-w-0 flex-1">
            <span class="block text-[16px] font-black text-white">{{ __('Morning 5') }}</span>
            <span class="block text-[12.5px] text-brand-pink-text">{{ __('Top 5 stories in 5 minutes') }}</span>
        </span>
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white text-brand-deep" aria-hidden="true">
            <x-icon name="play" size="18" class="ml-0.5 fill-current" />
        </span>
        <span class="sr-only">{{ __('Play Morning 5') }}</span>
    </a>
@elseif ($variant === 'mini')
    <a href="{{ route('briefing') }}" {{ $attributes->merge(['class' => 'player-bg flex items-center gap-4 rounded-[22px] p-4 no-underline hover:text-white']) }}>
        <span class="logo-tile h-14 w-14 shrink-0 !rounded-[16px]"><x-logo :wordmark="false" :size="26" /></span>
        <span class="min-w-0 flex-1">
            <span class="block text-[18px] font-black leading-tight text-white">{{ __('Morning 5') }}</span>
            <span class="block text-[12.5px] text-brand-pink-text">{{ __('Top 5 stories in 5 minutes') }}</span>
        </span>
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white text-brand-deep shadow-[var(--shadow-float)]" aria-hidden="true">
            <x-icon name="play" size="20" class="ml-0.5 fill-current" />
        </span>
        <span class="sr-only">{{ __('Play Morning 5') }}</span>
    </a>
@else
    <section {{ $attributes->merge(['class' => 'player-bg relative overflow-hidden rounded-[26px] p-5']) }} aria-labelledby="m5-title">
        <div class="flex items-center gap-4">
            <span class="logo-tile h-[72px] w-[72px] shrink-0 !rounded-[20px]"><x-logo :wordmark="false" :size="34" /></span>
            <div>
                <h2 id="m5-title" class="text-[24px] font-black leading-tight tracking-[-0.5px] text-white">{{ __('Morning 5') }}</h2>
                <p class="text-[13px] text-brand-pink-text">{{ __('Top 5 stories in 5 minutes') }}</p>
            </div>
        </div>
        <x-waveform class="mt-5" :bars="40" :height="30" />
        <div class="mt-4 flex items-center justify-between gap-3">
            <p class="text-[13px] font-semibold text-brand-pink-text">{{ $progress }}</p>
            <a href="{{ route('briefing') }}" class="player-play !h-14 !w-14 no-underline" aria-label="{{ __('Play Morning 5') }}">
                <x-icon name="play" size="24" class="ml-1 fill-current" />
            </a>
        </div>
    </section>
@endif
