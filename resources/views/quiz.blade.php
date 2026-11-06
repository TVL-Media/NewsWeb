{{-- Daily quiz (/quiz). Focused full-screen flow; answers are checked by the server one at a time. --}}
<x-layouts.app :title="__('Daily quiz')" bare>
    <div class="mx-auto flex min-h-screen max-w-[640px] flex-col px-4 pb-8 pt-4 md:pt-8">
        @if (! $quiz)
            <div class="flex items-center"><a href="{{ route('briefing') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-[var(--shadow-card)] text-ink" aria-label="{{ __('Close') }}"><x-icon name="x" size="20" /></a></div>
            <div class="card mt-10 p-8 text-center">
                <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-[16px] bg-ink text-[26px] font-black text-quiz-yellow">?</span>
                <h1 class="mt-4 text-[24px] font-black">{{ __('Daily quiz') }}</h1>
                <p class="mt-2 text-muted">{{ __('Today\'s quiz is being prepared. Check back soon.') }}</p>
                <a href="{{ route('home') }}" class="btn-primary mt-6">{{ __('Back to Home') }}</a>
            </div>
        @else
            <div class="flex flex-1 flex-col"
                 x-data="{
                    questions: @js($questions),
                    answered: @js((object) $answered),
                    done: @js($done),
                    streak: {{ $streak }},
                    streakUp: false,
                    i: 0, result: null, choice: null, busy: false,
                    seconds: {{ $seconds }}, left: {{ $seconds }}, timer: null,
                    shareImage: null, sharing: false,
                    init() {
                        const first = this.questions.findIndex(q => !(String(q.id) in this.answered));
                        if (!this.done) { this.i = first === -1 ? this.questions.length - 1 : first; this.start(); }
                    },
                    get q() { return this.questions[this.i]; },
                    get last() { return this.i === this.questions.length - 1; },
                    start() {
                        this.result = null; this.choice = null; this.left = this.seconds;
                        clearInterval(this.timer);
                        this.timer = setInterval(() => { if (--this.left <= 0) { clearInterval(this.timer); this.answer(null); } }, 1000);
                    },
                    async answer(choice) {
                        if (this.result || this.busy) return;
                        clearInterval(this.timer);
                        this.busy = true; this.choice = choice;
                        const r = await window.ngnFetch('{{ route('quiz.answer') }}', { body: { question_id: this.q.id, choice } });
                        this.busy = false;
                        if (!r.ok) { $store.toast.show(@js(__('Something went wrong. Please try again.'))); this.start(); return; }
                        this.result = r.data;
                        this.answered[this.q.id] = choice;
                        if (r.data.done) {
                            this.pendingDone = { score: r.data.score, total: r.data.total };
                            this.streakUp = r.data.streak_increased; this.streak = r.data.streak ?? this.streak;
                        }
                    },
                    next() {
                        if (this.pendingDone) { this.done = this.pendingDone; return; }
                        this.i++; this.start();
                        window.scrollTo({ top: 0 });
                    },
                    optionClass(n) {
                        if (!this.result) return 'border-line-2 bg-white hover:border-ink';
                        if (n === this.result.correct_index) return 'border-success bg-success-tint';
                        if (n === this.choice) return 'border-brand bg-brand-tint';
                        return 'border-line-2 bg-white opacity-60';
                    },
                    async share() {
                        this.sharing = true;
                        const r = await window.ngnFetch('{{ route('quiz.share') }}');
                        this.sharing = false;
                        if (r.ok) this.shareImage = r.data.image_url;
                    },
                    get whatsapp() {
                        return 'https://wa.me/?text=' + encodeURIComponent(`I scored ${this.done.score}/${this.done.total} in today's NextGenNews quiz. Can you beat me? {{ route('quiz') }}`);
                    },
                    pendingDone: null,
                 }">

                {{-- ===== Question ===== --}}
                <template x-if="!done">
                    <div class="flex flex-1 flex-col">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('briefing') }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-ink shadow-[var(--shadow-card)]" aria-label="{{ __('Close quiz') }}"><x-icon name="x" size="20" /></a>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-bold text-muted" x-text="'{{ __('Question') }} ' + (i + 1) + ' {{ __('of') }} ' + questions.length"></p>
                                <div class="mt-1.5 flex gap-1" aria-hidden="true">
                                    <template x-for="(qq, n) in questions" :key="n">
                                        <span class="h-1.5 flex-1 rounded-full" :class="n < i || (n === i && result) ? 'bg-brand' : 'bg-line'"></span>
                                    </template>
                                </div>
                            </div>
                            <span class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-brand-tint px-3 text-[14px] font-extrabold tabular-nums text-brand-dark"
                                  role="timer" :aria-label="left + ' {{ __('seconds left') }}'">
                                <x-icon name="timer" size="16" /> <span x-text="'0:' + String(Math.max(0, left)).padStart(2, '0')"></span>
                            </span>
                        </div>

                        <div class="mt-5 h-[190px] overflow-hidden rounded-[22px] bg-ink">
                            <img :src="q.photo" alt="" class="h-full w-full object-cover" x-show="q.photo">
                        </div>
                        <p class="tag mt-4" x-text="q.tag"></p>
                        <h1 class="mt-1.5 text-[22px] font-extrabold leading-[1.3] tracking-[-0.3px]" x-text="q.question" id="quiz-q"></h1>

                        <div class="mt-5 space-y-2.5" role="group" aria-labelledby="quiz-q">
                            <template x-for="(opt, n) in q.options" :key="n">
                                <button type="button" @click="answer(n)" :disabled="!!result || busy"
                                        class="flex h-14 w-full items-center gap-3 rounded-[16px] border-2 px-3 text-left text-[15.5px] font-bold text-ink transition"
                                        :class="optionClass(n)" :aria-pressed="(choice === n).toString()">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] text-[14px] font-black"
                                          :class="result && n === result.correct_index ? 'bg-success text-white' : (result && n === choice ? 'bg-brand text-white' : 'bg-chip text-ink-2')"
                                          x-text="'ABCD'[n]"></span>
                                    <span class="flex-1" x-text="opt"></span>
                                    <span x-show="result && n === result.correct_index && n === choice" class="text-[13px] font-extrabold text-success-text">{{ __('Correct') }}</span>
                                    <x-icon name="check" size="18" :stroke="3" class="text-success" x-show="result && n === result.correct_index" />
                                    <x-icon name="x" size="18" :stroke="3" class="text-brand" x-show="result && n === choice && n !== result.correct_index" />
                                </button>
                            </template>
                        </div>

                        <div x-show="result" x-cloak class="mt-5" aria-live="polite">
                            <p x-show="result && choice === null" class="mb-2 text-[14px] font-bold text-brand-dark">{{ __('Time\'s up!') }}</p>
                            <div class="rounded-[18px] p-4" :class="result?.correct ? 'bg-success-tint' : 'bg-brand-tint'">
                                <p class="text-[15px] font-extrabold" :class="result?.correct ? 'text-success-text' : 'text-brand-dark'" x-text="result?.correct ? '{{ __('Correct!') }}' : '{{ __('Not quite') }}'"></p>
                                <p class="mt-1 text-[15px] leading-relaxed text-ink" x-text="result?.explanation"></p>
                                <a x-show="q.story_url" :href="q.story_url" class="mt-2 inline-block text-[14px] font-bold no-underline">{{ __('Read the story') }} ›</a>
                            </div>
                            <button type="button" @click="next()" class="btn-primary-lg mt-5">
                                <span x-text="pendingDone ? '{{ __('See your score') }}' : '{{ __('Next question') }}'"></span> <x-icon name="arrow-right" size="19" />
                            </button>
                        </div>
                    </div>
                </template>

                {{-- ===== End screen ===== --}}
                <template x-if="done">
                    <div class="flex flex-1 flex-col items-center pt-6 text-center">
                        <div class="player-bg w-full rounded-[28px] px-6 py-10">
                            <p class="text-[13px] font-extrabold tracking-[0.12em] text-brand-pink-text">{{ __('DAILY QUIZ') }}</p>
                            <p class="mt-2 text-[84px] font-black leading-none text-white" x-text="done.score + '/' + done.total"></p>
                            <p class="mt-2 text-[20px] font-black text-white" x-text="done.score === done.total ? '{{ __('Perfect score!') }}' : (done.score >= done.total - 1 ? '{{ __('So close!') }}' : '{{ __('Nice try!') }}')"></p>
                            <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-brand-deep" :class="streakUp && 'animate-[ngn-pop_700ms_ease-out]'">
                                <x-icon name="flame" size="20" class="text-brand" />
                                <span class="text-[18px] font-black" x-text="streak + '{{ __('-day streak') }}'"></span>
                                <span x-show="streakUp" class="rounded-full bg-brand px-2 py-0.5 text-[12px] font-extrabold text-white">+1</span>
                            </div>
                        </div>

                        <div class="mt-6 grid w-full gap-2 sm:grid-cols-2">
                            <button type="button" @click="share()" class="btn-primary" :disabled="sharing">
                                <x-icon name="share" size="17" /> <span x-text="sharing ? '{{ __('Making your image…') }}' : '{{ __('Share result') }}'"></span>
                            </button>
                            <a href="{{ route('home') }}" class="btn-ghost">{{ __('Back to Home') }}</a>
                        </div>

                        <div x-show="shareImage" x-cloak class="card mt-5 w-full p-4">
                            <img :src="shareImage" alt="{{ __('Your quiz result image') }}" class="mx-auto aspect-[4/5] w-full max-w-[260px] rounded-[16px] object-cover">
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <a :href="whatsapp" target="_blank" rel="noopener" class="btn-primary text-[14px]"><x-icon name="send" size="16" /> WhatsApp</a>
                                <a :href="shareImage" download="nextgennews-quiz.jpg" class="btn-ghost text-[14px]"><x-icon name="image" size="16" /> {{ __('Download') }}</a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        @endif
    </div>
</x-layouts.app>
