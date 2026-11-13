{{-- "You're all caught up ✓" — after the last new card. $hasEarlier: stories already seen follow below. --}}
<section class="flex h-full snap-start items-center justify-center p-6" aria-labelledby="caught-up-title">
    <div class="text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-success-tint text-success"><x-icon name="check" size="26" :stroke="3" /></span>
        <h2 id="caught-up-title" class="mt-3 text-[22px] font-black tracking-[-0.4px]">{{ __("You're all caught up") }} ✓</h2>
        <p class="mt-1 text-[14.5px] text-muted">{{ __('New stories from your topics and pages will appear here.') }}</p>
        <div class="mt-4 flex justify-center gap-2">
            <a href="{{ route('home') }}" class="btn-primary h-10 min-h-0 text-[14px]">{{ __('Latest News') }}</a>
            <a href="{{ route('explore') }}" class="btn-ghost h-10 min-h-0 text-[14px]">{{ __('Explore') }}</a>
        </div>
        @if ($hasEarlier)
            <button type="button" class="mx-auto mt-6 flex flex-col items-center gap-0.5 text-[13px] font-bold text-muted-2 hover:text-ink"
                    @click="next(1)">
                {{ __('Scroll for earlier stories') }}
                <x-icon name="chevron-down" size="20" />
            </button>
        @endif
    </div>
</section>
