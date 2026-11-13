@php
    $lang = app()->getLocale();
    $rows = $stories->map(fn ($s) => [
        'title' => $s->version($lang)?->headline,
        'url' => route('story.show', $s),
        'thumb' => $s->photoUrl(),
        'tag' => $s->category?->name(),
    ])->values();
    $player = $briefing ? [
        'src' => $briefing->audioUrl($lang),
        'offsets' => $briefing->offsets($lang),
        'duration' => (int) $briefing->audio_seconds,
    ] : null;
@endphp
<x-layouts.app :title="__('Briefing')" active="briefing">
    <div class="mx-auto max-w-[1280px] px-4 pt-5 md:px-6 md:pt-8">
        <h1 class="sr-only">{{ __('Briefing') }}</h1>

        {{-- ===== Morning 5 player ===== --}}
        <section class="player-bg overflow-hidden rounded-[28px] p-5 md:rounded-[32px] md:p-9" aria-labelledby="m5-player-title"
                 x-data="{
                    ...@js($player ?? ['src' => null, 'offsets' => [], 'duration' => 0]),
                    rows: @js($rows),
                    playing: false, current: 0, rate: 1, rates: [1, 1.25, 1.5], showList: true,
                    get total() { return this.duration || 300; },
                    get index() { let i = 0; this.offsets.forEach((o, n) => { if (this.current >= o) i = n; }); return i; },
                    fmt(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); },
                    toggle() { if (!this.src) return; this.playing ? this.$refs.audio.pause() : this.$refs.audio.play(); },
                    seekTo(sec) { if (!this.src) return; this.$refs.audio.currentTime = Math.max(0, sec); this.$refs.audio.play(); },
                    playRow(i) { this.seekTo(this.offsets[i] ?? 0); },
                    prev() { const start = this.offsets[this.index] ?? 0; this.seekTo(this.current - start > 3 ? start : (this.offsets[this.index - 1] ?? 0)); },
                    next() { if (this.index < this.offsets.length - 1) this.seekTo(this.offsets[this.index + 1]); },
                    cycleRate() { this.rate = this.rates[(this.rates.indexOf(this.rate) + 1) % this.rates.length]; this.$refs.audio.playbackRate = this.rate; },
                    seekBar(e) { const r = e.currentTarget.getBoundingClientRect(); this.seekTo((e.clientX - r.left) / r.width * this.total); },
                 }">
            @if (! $briefing)
                <div class="py-10 text-center">
                    <span class="logo-tile mx-auto h-20 w-20"><x-logo :wordmark="false" :size="36" /></span>
                    <h2 id="m5-player-title" class="mt-6 text-[30px] font-black text-white">{{ __('Morning 5') }}</h2>
                    <p class="mt-1 text-brand-pink-text">{{ __('Today\'s briefing is being prepared. It is ready by 6 AM.') }}</p>
                </div>
            @else
                <audio x-ref="audio" :src="src" preload="metadata"
                       @play="playing = true" @pause="playing = false" @ended="playing = false"
                       @timeupdate="current = $event.target.currentTime"
                       @loadedmetadata="if ($event.target.duration && isFinite($event.target.duration)) duration = $event.target.duration"></audio>

                <div class="flex flex-wrap gap-8">
                    {{-- Left --}}
                    <div class="flex min-w-0 flex-col" style="flex: 1 1 380px">
                        <p class="flex items-center gap-2 text-[14px] font-semibold text-brand-pink-text">
                            <x-icon name="calendar" size="17" />
                            <span>{{ __("Today's Briefing") }}</span> ·
                            <span>{{ $briefing->date->translatedFormat('l, j F') }}</span>
                        </p>

                        <div class="mt-6 flex items-center gap-5 md:mt-8">
                            <span class="logo-tile h-[88px] w-[88px] shrink-0 md:h-[120px] md:w-[120px]"><x-logo :wordmark="false" :size="52" /></span>
                            <div>
                                <h2 id="m5-player-title" class="text-[34px] font-black leading-none tracking-[-1px] text-white md:text-[46px]">{{ __('Morning 5') }}</h2>
                                <p class="mt-2 text-[14px] text-brand-pink-text md:text-[16px]">{{ __('Top 5 stories in 5 minutes') }}</p>
                            </div>
                        </div>

                        {{-- Waveform: played part turns white --}}
                        @php $bars = collect(range(0, 59))->map(fn ($i) => 0.25 + 0.75 * abs(sin($i * 0.61) * cos($i * 0.27))); @endphp
                        <div class="mt-8 flex h-[46px] items-center gap-[3px]" aria-hidden="true">
                            @foreach ($bars as $i => $h)
                                <span class="wave-bar flex-1 transition-colors" style="height: {{ round($h * 46) }}px"
                                      :style="{{ $i }} / 60 < current / total ? { background: 'rgba(255,255,255,.9)' } : {}"></span>
                            @endforeach
                        </div>

                        {{-- Progress --}}
                        <div class="mt-5">
                            <div class="relative h-1.5 cursor-pointer rounded-full bg-white/20" @click="seekBar($event)"
                                 role="slider" tabindex="0" aria-label="{{ __('Progress') }}" aria-valuemin="0" :aria-valuemax="Math.round(total)" :aria-valuenow="Math.round(current)" :aria-valuetext="fmt(current)"
                                 @keydown.arrow-right.prevent="seekTo(current + 10)" @keydown.arrow-left.prevent="seekTo(current - 10)">
                                <div class="absolute inset-y-0 left-0 rounded-full bg-white" :style="`width: ${Math.min(100, current / total * 100)}%`"></div>
                            </div>
                            <div class="mt-2 flex justify-between text-[12.5px] font-semibold tabular-nums text-brand-pink-text">
                                <span x-text="fmt(current)">0:00</span><span x-text="fmt(total)">{{ intdiv((int) $briefing->audio_seconds, 60) }}:{{ str_pad((string) ((int) $briefing->audio_seconds % 60), 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>

                        {{-- Controls --}}
                        <div class="mt-5 flex items-center justify-between gap-2 md:justify-start md:gap-5">
                            <button type="button" @click="cycleRate()" class="h-11 min-w-[56px] rounded-full bg-white/10 px-3 text-[14px] font-extrabold text-white hover:bg-white/20"
                                    :aria-label="'{{ __('Playback speed') }} ' + rate + 'x'" x-text="rate + 'x'">1x</button>
                            <button type="button" @click="prev()" class="inline-flex h-12 w-12 items-center justify-center rounded-full text-white hover:bg-white/10" aria-label="{{ __('Previous story') }}">
                                <x-icon name="skip-back" size="24" class="fill-current" />
                            </button>
                            <button type="button" @click="toggle()" class="player-play" :aria-label="playing ? '{{ __('Pause') }}' : '{{ __('Play Morning 5') }}'">
                                <x-icon name="play" size="30" class="ml-1 fill-current" x-show="!playing" />
                                <x-icon name="pause" size="30" class="fill-current" x-show="playing" x-cloak />
                            </button>
                            <button type="button" @click="next()" class="inline-flex h-12 w-12 items-center justify-center rounded-full text-white hover:bg-white/10" aria-label="{{ __('Next story') }}">
                                <x-icon name="skip-forward" size="24" class="fill-current" />
                            </button>
                            <button type="button" @click="showList = !showList" :aria-pressed="showList.toString()" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-white hover:bg-white/10" aria-label="{{ __('Show the story list') }}" aria-controls="briefing-list">
                                <x-icon name="list" size="22" />
                            </button>
                        </div>
                    </div>

                    {{-- Right: In this briefing --}}
                    <div id="briefing-list" x-show="showList" class="briefing-list min-w-0 self-start" style="flex: 1 1 340px; max-width: 460px">
                        <h3 class="px-1 pb-2 text-[16px] font-black">{{ __('In this briefing') }}</h3>
                        <ol>
                            <template x-for="(row, i) in rows" :key="i">
                                <li class="flex items-center gap-1 rounded-[14px] transition"
                                    :class="playing || current > 0 ? (index === i ? 'bg-white shadow-[var(--shadow-card)]' : '') : ''"
                                    :aria-current="(index === i && (playing || current > 0)) ? 'true' : null">
                                    <button type="button" @click="playRow(i)" class="flex min-w-0 flex-1 items-center gap-3 border-t border-[#F3DADC] px-1.5 py-2.5 text-left"
                                            :class="i === 0 && 'border-t-0'">
                                        <span class="num-badge" x-text="i + 1"></span>
                                        <img :src="row.thumb" alt="" class="h-10 w-[52px] shrink-0 rounded-[10px] object-cover">
                                        <span class="line-clamp-2 text-[14px] font-bold leading-snug" :class="index === i && (playing || current > 0) ? 'text-brand' : 'text-ink'" x-text="row.title"></span>
                                    </button>
                                    <a :href="row.url" class="inline-flex h-11 w-9 shrink-0 items-center justify-center text-muted-2 hover:text-brand" :aria-label="'{{ __('Open story') }}: ' + row.title">
                                        <x-icon name="chevron-right" size="18" />
                                    </a>
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>
            @endif
        </section>

        {{-- ===== Quiz + This week ===== --}}
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            @if ($hasQuiz)
                <x-quiz-card :streak="$streak" :button="$quizDone ? __('Done').' · '.$quizDone : __('Start quiz')" />
            @else
                <section class="card-dark flex items-center gap-4 p-5">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-[14px] bg-dark-card text-[24px] font-black text-quiz-yellow" aria-hidden="true">?</span>
                    <div><h2 class="text-[17px] font-black text-white">{{ __('Daily quiz') }}</h2><p class="text-[13px] text-white/70">{{ __('Today\'s quiz is being prepared.') }}</p></div>
                </section>
            @endif

            <section class="card p-5" aria-labelledby="week-title">
                <div class="flex items-center justify-between">
                    <h2 id="week-title" class="text-[17px] font-black">{{ __('This week') }}</h2>
                    <p class="flex items-center gap-1 text-[14px] font-extrabold text-brand"><x-icon name="flame" size="17" /> {{ trans_choice(':count-day streak', $streak, ['count' => $streak]) }}</p>
                </div>
                @if ($week)
                    <ol class="mt-4 grid grid-cols-7 gap-1 text-center">
                        @foreach ($week as $day)
                            <li class="flex flex-col items-center gap-1.5">
                                @if ($day['done'])
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand text-white"><x-icon name="check" size="17" :stroke="3" /></span>
                                @elseif ($day['today'])
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-tint"><span class="h-2.5 w-2.5 rounded-full bg-brand"></span></span>
                                @else
                                    <span class="inline-flex h-9 w-9 rounded-full border-2 border-line"></span>
                                @endif
                                <span class="text-[11.5px] font-bold {{ $day['today'] ? 'text-brand' : 'text-muted-2' }}">{{ $day['label'] }}</span>
                                <span class="sr-only">{{ $day['done'] ? __('done') : ($day['today'] ? __('today') : __('missed')) }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="meta mt-3">{{ __('Read :reads stories or finish the quiz to keep your streak. :freezes freezes a month cover missed days.', ['reads' => \App\Services\StreakService::readsPerDay(), 'freezes' => \App\Services\StreakService::freezesPerMonth()]) }}</p>
                @else
                    <p class="mt-3 text-[14px] text-muted">{{ __('Log in to start a daily streak: read :reads stories or finish the quiz each day.', ['reads' => \App\Services\StreakService::readsPerDay()]) }}</p>
                    <button type="button" class="btn-primary mt-3 text-sm" @click="$store.auth.show()">{{ __('Log in') }}</button>
                @endif
            </section>
        </div>

        {{-- ===== Polls ===== --}}
        <section class="mt-10" aria-labelledby="polls-title">
            <div class="flex items-center justify-between">
                <h2 id="polls-title" class="text-[22px] font-black tracking-[-0.4px]">{{ __('Polls') }}</h2>
                <button type="button" class="btn-outline" @click="if ($store.auth.require('create_poll')) $dispatch('ngn-create-poll')">
                    <x-icon name="plus" size="15" /> {{ __('Create poll') }}
                </button>
            </div>
            @if ($polls->isEmpty())
                <div class="card mt-4 p-8 text-center text-muted">{{ __('No polls right now.') }}</div>
            @else
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($polls as $poll)
                        <x-poll-card :poll="$poll" :voted-option-id="$votes[$poll->id] ?? null" :show-create="false" :title="$poll->isOpen() ? __('Poll') : __('Results')" />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
