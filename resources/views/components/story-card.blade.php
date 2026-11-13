{{--
    Story card. Expects $story with `versions` and `category` eager-loaded.
    variant="grid": white card, 160px photo on top, red tag, 16px/800 headline, meta (Latest News grid).
    variant="row":  horizontal, photo on the left (thumb sets its size), text on the right.
                    96×80 = mobile Latest News / search, 104×84 = topic grid, 86×66 = related stories.
--}}
@props(['story', 'variant' => 'grid', 'thumb' => 'w-[96px] h-[80px]', 'card' => true])
@php
    $v = $story->version();
    $tag = $story->category?->name();
    $url = route('story.show', $story);
@endphp

@if ($variant === 'grid')
    <article {{ $attributes->merge(['class' => 'card group relative flex flex-col']) }}>
        <div class="h-[160px] overflow-hidden bg-chip">
            @if ($story->photoUrl())
                <img src="{{ $story->photoUrl() }}" alt="" loading="lazy" decoding="async" width="480" height="320"
                     class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @endif
        </div>
        <div class="flex flex-1 flex-col px-3.5 pb-3 pt-3">
            @if ($tag)<p class="tag">{{ $tag }}</p>@endif
            <h3 class="mt-1 line-clamp-3 text-[16px] font-extrabold leading-[1.3]">
                <a href="{{ $url }}" class="text-ink no-underline after:absolute after:inset-0 hover:text-brand">{{ $v?->headline }}</a>
            </h3>
            <p class="meta mt-auto pt-2">{{ $story->ago() }} · {{ trans_choice(':count min read', $story->read_minutes) }}</p>
        </div>
    </article>
@else
    <article {{ $attributes->merge(['class' => ($card ? 'card p-3 ' : '').'group relative flex items-center gap-3']) }}>
        <div class="{{ $thumb }} shrink-0 overflow-hidden rounded-[14px] bg-chip">
            @if ($story->photoUrl())
                <img src="{{ $story->photoUrl() }}" alt="" loading="lazy" decoding="async" width="208" height="168"
                     class="h-full w-full object-cover">
            @endif
        </div>
        <div class="min-w-0 flex-1">
            @if ($tag)<p class="tag">{{ $tag }}</p>@endif
            <h3 class="mt-0.5 line-clamp-3 text-[15px] font-extrabold leading-[1.3]">
                <a href="{{ $url }}" class="text-ink no-underline after:absolute after:inset-0 hover:text-brand">{{ $v?->headline }}</a>
            </h3>
            <p class="meta mt-1">{{ $story->ago() }}</p>
        </div>
    </article>
@endif
