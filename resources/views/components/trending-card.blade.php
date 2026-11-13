{{-- Trending Now: red flame, 5 rows with red number badge, title and red ↗. Expects stories with versions loaded. --}}
@props(['stories'])
<section {{ $attributes->merge(['class' => 'card p-4']) }} aria-labelledby="trending-title">
    <h2 id="trending-title" class="flex items-center gap-2 text-[16px] font-black">
        <x-icon name="flame" size="20" class="text-brand" /> {{ __('Trending Now') }}
    </h2>
    <ol class="mt-1.5">
        @foreach ($stories as $i => $story)
            <li class="{{ $loop->first ? '' : 'border-t border-line' }}">
                <a href="{{ route('story.show', $story) }}" class="group flex items-center gap-3 py-2.5 text-ink no-underline">
                    <span class="num-badge">{{ $i + 1 }}</span>
                    <span class="line-clamp-2 flex-1 text-[13.5px] font-bold leading-snug group-hover:text-brand">{{ $story->version()?->headline }}</span>
                    <x-icon name="arrow-up-right" size="18" class="text-brand" />
                </a>
            </li>
        @endforeach
    </ol>
</section>
