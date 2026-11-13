{{-- "You're all caught up ✓" — after the last new card. $hasEarlier: stories already seen follow below. --}}
<section class="card flex flex-col items-center px-6 py-7 text-center" x-show="!filter" aria-labelledby="caught-up-title">
    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-success-tint text-success"><x-icon name="check" size="24" :stroke="3" /></span>
    <h2 id="caught-up-title" class="mt-3 text-[20px] font-black tracking-[-0.4px]">{{ __("You're all caught up") }} ✓</h2>
    <p class="mt-1 text-[14px] text-muted">{{ __('New stories from your topics and pages will appear here.') }}</p>
    <div class="mt-4 flex justify-center gap-2">
        <a href="{{ route('home') }}" class="btn-primary h-10 min-h-0 text-[14px]">{{ __('Latest News') }}</a>
        <a href="{{ route('explore') }}" class="btn-ghost h-10 min-h-0 text-[14px]">{{ __('Explore') }}</a>
    </div>
    @if ($hasEarlier)
        <p class="mt-5 flex items-center gap-1 text-[13px] font-bold text-muted-2">{{ __('Earlier stories') }} <x-icon name="chevron-down" size="18" /></p>
    @endif
</section>
