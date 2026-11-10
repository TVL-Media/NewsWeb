{{--
    Top story hero: full-bleed photo + scrim, "Cricket · Top story" pill, 34px/900 white headline,
    meta, and a white "▶ Listen" pill. 420px tall on desktop, 260px on mobile.
    Listen dispatches `ngn-listen` (the docked mini player is built in Milestone 2).
--}}
@props(['story', 'label' => null])
@php
    $v = $story->version();
    $label ??= ($story->is_breaking && $story->published_at?->gt(now()->subHours(12)) ? __('Breaking').' · ' : '').($story->category?->name() ?? '').' · '.__('Top story');
@endphp
<article {{ $attributes->merge(['class' => 'hero-card min-h-[260px] bg-ink md:min-h-[420px]']) }}>
    @if ($story->photoUrl())
        <img src="{{ $story->photoUrl() }}" alt="" fetchpriority="high" decoding="async" width="1200" height="800"
             class="absolute inset-0 h-full w-full object-cover">
    @endif
    <div class="scrim"></div>

    <span class="photo-pill absolute left-4 top-4 md:left-5 md:top-5">{{ $label }}</span>

    <div class="absolute inset-x-0 bottom-0 flex items-end gap-4 p-5 md:p-7">
        <div class="min-w-0 flex-1">
            <h2 class="text-[22px] font-black leading-[1.15] tracking-[-0.3px] text-white md:text-[34px] md:tracking-[-0.8px]">
                <a href="{{ route('story.show', $story) }}" class="text-white no-underline after:absolute after:inset-0 hover:text-white hover:underline">{{ $v?->headline }}</a>
            </h2>
            <p class="mt-2 text-[12.5px] text-white/85 md:text-[13.5px]">
                {{ $story->ago() }} · {{ trans_choice(':count min read', $story->read_minutes) }}@if ($story->source) · {{ __('Source') }}: {{ $story->source->name }}@endif
            </p>
        </div>
        <button type="button"
                class="relative z-10 hidden h-11 shrink-0 items-center gap-1.5 rounded-full bg-white px-4 text-sm font-bold text-ink shadow-[var(--shadow-float)] hover:bg-brand-tint sm:inline-flex"
                @click="$dispatch('ngn-listen', { src: @js($v?->audioUrl()), title: @js($v?->headline) })"
                aria-label="{{ __('Listen') }}: {{ $v?->headline }}">
            <x-icon name="play" size="15" class="fill-current" /> {{ __('Listen') }}
        </button>
    </div>
</article>
