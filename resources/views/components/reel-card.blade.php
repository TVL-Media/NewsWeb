{{-- One 9:16 reel thumbnail (rounded 16px, duration badge, title under it). Opens /reels at this reel. --}}
@props(['reel', 'query' => []])
@php
    $seconds = (int) $reel->duration_seconds;
    $duration = intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
@endphp
<a href="{{ route('reels.show', [$reel, ...$query]) }}" class="group block text-ink no-underline hover:text-ink" lang="{{ $reel->language }}">
    <span class="relative block aspect-[9/16] overflow-hidden rounded-[16px] bg-ink">
        @if ($reel->thumbnailUrl())
            <img src="{{ $reel->thumbnailUrl() }}" alt="" loading="lazy" width="148" height="263" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]">
        @endif
        <span class="absolute right-2 top-2 rounded-full bg-black/60 px-2 py-0.5 text-[11px] font-bold text-white">{{ $duration }}</span>
        <span class="absolute bottom-2 left-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-brand opacity-90 transition group-hover:opacity-100" aria-hidden="true">
            <x-icon name="play" size="15" class="ml-0.5 fill-current" />
        </span>
    </span>
    <span class="mt-2 line-clamp-2 text-[13px] font-bold leading-snug">{{ $reel->title }}</span>
</a>
