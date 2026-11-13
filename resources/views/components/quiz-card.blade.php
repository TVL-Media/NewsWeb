{{-- Black Daily quiz card: yellow "?" tile, streak line, white Start pill (login required). --}}
@props(['streak' => 0, 'button' => null])
<section {{ $attributes->merge(['class' => 'card-dark flex items-center gap-3 px-4 py-3.5']) }} aria-labelledby="quiz-card-title">
    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[13px] bg-dark-card text-[22px] font-black text-quiz-yellow" aria-hidden="true">?</span>
    <div class="min-w-0 flex-1">
        <h2 id="quiz-card-title" class="text-[16px] font-black leading-tight text-white">{{ __('Daily quiz') }}</h2>
        <p class="text-[12.5px] leading-snug text-white/70">
            {{ __('5 questions') }} ·
            @if ($streak > 0)
                {{ __('keeps your :n-day streak', ['n' => $streak]) }}
            @else
                {{ __('start a streak') }}
            @endif
        </p>
    </div>
    <a href="{{ route('quiz') }}"
       @click="if (!$store.auth.require('visit', { url: '{{ route('quiz') }}' })) $event.preventDefault()"
       class="btn h-9 min-h-0 shrink-0 rounded-full bg-white px-4 text-[13px] text-ink no-underline hover:bg-brand-tint hover:text-ink">
        {{ $button ?? __('Start') }}
    </a>
</section>
