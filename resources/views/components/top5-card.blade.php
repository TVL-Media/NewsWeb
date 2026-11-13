{{--
    "Top 5": the five trending stories (App\Support\Rails::trending), each linking to its story.
    variant="card": Home (right column on desktop, under the hero on mobile).
    variant="mini": story page rail, more compact.
--}}
@props(['stories', 'variant' => 'card'])
@php $mini = $variant === 'mini'; @endphp
@if ($stories->isNotEmpty())
    <section {{ $attributes->merge(['class' => 'player-bg relative flex flex-col overflow-hidden '.($mini ? 'rounded-[22px] p-4' : 'rounded-[22px] p-4 md:p-5')]) }} aria-labelledby="top5-title-{{ $variant }}">
        <div class="flex items-center gap-3">
            <span class="logo-tile {{ $mini ? 'h-11 w-11 !rounded-[13px]' : 'h-[52px] w-[52px] !rounded-[15px]' }} shrink-0"><span class="text-[22px] font-black text-brand">5</span></span>
            <div class="min-w-0 flex-1">
                <h2 id="top5-title-{{ $variant }}" class="{{ $mini ? 'text-[17px]' : 'text-[19px]' }} font-black leading-tight tracking-[-0.3px] text-white">{{ __('Top 5') }}</h2>
                <p class="flex items-center gap-1 text-[12.5px] text-brand-pink-text"><x-icon name="trending-up" size="14" /> {{ __('Trending right now') }}</p>
            </div>
        </div>

        <ol class="mt-3 border-t border-white/15">
            @foreach ($stories->take(5) as $i => $story)
                <li>
                    <a href="{{ route('story.show', $story) }}" class="group flex gap-2.5 border-b border-white/10 py-2.5 text-white no-underline last:border-b-0 hover:text-white">
                        <span class="w-4 shrink-0 text-[15px] font-black leading-snug text-brand-pink-text">{{ $i + 1 }}</span>
                        <span class="line-clamp-2 {{ $mini ? 'text-[13px]' : 'text-[13.5px]' }} font-semibold leading-snug group-hover:underline">{{ $story->version()?->headline }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endif
