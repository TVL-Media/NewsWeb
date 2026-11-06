{{--
    Mini player docked at the bottom (above the mobile nav). Any element can start it with
    $dispatch('ngn-listen', { src, title }). HTML5 <audio>, speed 1x / 1.25x / 1.5x.
--}}
<div x-data="{
        src: null, title: '', playing: false, current: 0, duration: 0, rate: 1, error: false,
        rates: [1, 1.25, 1.5],
        fmt(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); },
        start(detail) {
            this.error = !detail.src;
            this.title = detail.title || '';
            if (!detail.src) { this.src = 'none'; return; }
            if (this.src !== detail.src) { this.src = detail.src; this.$refs.audio.src = detail.src; }
            this.$refs.audio.playbackRate = this.rate;
            this.$refs.audio.play().catch(() => { this.error = true; });
        },
        toggle() { this.playing ? this.$refs.audio.pause() : this.$refs.audio.play(); },
        cycleRate() { this.rate = this.rates[(this.rates.indexOf(this.rate) + 1) % this.rates.length]; this.$refs.audio.playbackRate = this.rate; },
        seek(e) { const r = e.currentTarget.getBoundingClientRect(); this.$refs.audio.currentTime = ((e.clientX - r.left) / r.width) * this.duration; },
        close() { this.$refs.audio.pause(); this.src = null; },
     }"
     @ngn-listen.window="start($event.detail)"
     x-show="src" x-cloak
     x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full opacity-0"
     class="fixed inset-x-0 bottom-[calc(66px+env(safe-area-inset-bottom))] z-50 px-3 pb-2 md:bottom-0 md:px-6 md:pb-4"
     role="region" aria-label="{{ __('Audio player') }}">
    <audio x-ref="audio" preload="none"
           @play="playing = true" @pause="playing = false" @ended="playing = false"
           @timeupdate="current = $event.target.currentTime" @loadedmetadata="duration = $event.target.duration"
           x-on:error="error = true"></audio>{{-- x-on:error, because Blade treats @error as a directive --}}

    <div class="mx-auto flex max-w-[720px] items-center gap-3 rounded-[22px] bg-ink p-2.5 pr-3 text-white shadow-[var(--shadow-float)]">
        <button type="button" @click="toggle()" :disabled="error"
                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-brand-deep disabled:opacity-50"
                :aria-label="playing ? '{{ __('Pause') }}' : '{{ __('Play') }}'">
            <x-icon name="play" size="18" class="ml-0.5 fill-current" x-show="!playing" />
            <x-icon name="pause" size="18" class="fill-current" x-show="playing" x-cloak />
        </button>
        <div class="min-w-0 flex-1">
            <p class="truncate text-[13.5px] font-bold" x-text="error ? '{{ __('Audio is not available for this story yet.') }}' : title"></p>
            <div class="mt-1.5 flex items-center gap-2 text-[11.5px] text-white/70">
                <span x-text="fmt(current)" class="w-8 tabular-nums"></span>
                <div class="relative h-1.5 flex-1 cursor-pointer rounded-full bg-white/20" @click="seek($event)"
                     role="progressbar" aria-label="{{ __('Progress') }}" :aria-valuenow="Math.round(current)" aria-valuemin="0" :aria-valuemax="Math.round(duration)">
                    <div class="absolute inset-y-0 left-0 rounded-full bg-brand" :style="`width: ${duration ? current / duration * 100 : 0}%`"></div>
                </div>
                <span x-text="fmt(duration)" class="w-8 text-right tabular-nums"></span>
            </div>
        </div>
        <button type="button" @click="cycleRate()" class="h-11 min-w-[52px] rounded-full bg-white/10 px-2.5 text-[13px] font-extrabold hover:bg-white/20"
                :aria-label="'{{ __('Playback speed') }} ' + rate + 'x'" x-text="rate + 'x'"></button>
        <button type="button" @click="close()" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-white/80 hover:text-white" aria-label="{{ __('Close player') }}">
            <x-icon name="x" size="18" />
        </button>
    </div>
</div>
