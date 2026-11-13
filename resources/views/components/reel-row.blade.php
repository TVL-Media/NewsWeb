{{--
    Horizontal row of 9:16 reel cards (Home). Each card opens /reels at that reel.
    $query: extra query string for the feed (e.g. ['topic' => 'cricket']).
--}}
@props(['reels', 'title' => null, 'query' => []])
@if ($reels->isNotEmpty())
    <section {{ $attributes->merge(['class' => '']) }} aria-labelledby="reels-row-title">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-[9px] bg-brand text-white"><x-icon name="square-play" size="16" /></span>
            <h2 id="reels-row-title" class="text-[19px] font-black tracking-[-0.3px]">{{ $title ?? __('Reels') }}</h2>
            <a href="{{ route('reels', $query) }}" class="ml-auto inline-flex items-center gap-0.5 text-[13.5px] font-bold text-brand no-underline hover:text-brand-dark">
                {{ __('See all') }} <x-icon name="chevron-right" size="15" />
            </a>
        </div>
        <ul class="no-scrollbar -mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pb-1 md:mx-0 md:px-0">
            @foreach ($reels as $reel)
                <li class="w-[128px] shrink-0 md:w-[148px]">
                    <x-reel-card :reel="$reel" :query="$query" />
                </li>
            @endforeach
        </ul>
    </section>
@endif
