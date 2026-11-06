{{--
    Full-screen 24-hour page story viewer. Open with $dispatch('open-stories', { group: i }).
    Progress bars, auto-advance (5s), tap left/right, ←/→ keys, swipe up (or "Read story") opens the story.
    $groups: [{ page, avatar, slides: [{ image, headline, url, ago }] }]
--}}
@props(['groups'])
<div x-data="{
        groups: @js($groups),
        open: false, g: 0, s: 0, progress: 0, timer: null, startY: null,
        get group() { return this.groups[this.g] || { slides: [] }; },
        get slide() { return this.group.slides[this.s] || {}; },
        show(i) { this.g = i; this.s = 0; this.open = true; this.play(); },
        play() {
            clearInterval(this.timer); this.progress = 0;
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce) return; // no auto-advance; tap to move
            this.timer = setInterval(() => { this.progress += 2; if (this.progress >= 100) this.next(); }, 100);
        },
        next() {
            if (this.s < this.group.slides.length - 1) { this.s++; }
            else if (this.g < this.groups.length - 1) { this.g++; this.s = 0; }
            else { return this.close(); }
            this.play();
        },
        prev() {
            if (this.s > 0) { this.s--; }
            else if (this.g > 0) { this.g--; this.s = this.groups[this.g].slides.length - 1; }
            this.play();
        },
        close() { clearInterval(this.timer); this.open = false; },
        touchStart(e) { this.startY = e.touches[0].clientY; },
        touchEnd(e) { if (this.startY !== null && this.startY - e.changedTouches[0].clientY > 70) window.location = this.slide.url; this.startY = null; },
     }"
     @open-stories.window="show($event.detail.group)"
     @keydown.window.escape="open && close()"
     @keydown.window.arrow-right="open && next()"
     @keydown.window.arrow-left="open && prev()"
     x-show="open" x-cloak
     class="fixed inset-0 z-[65] flex items-center justify-center bg-black"
     role="dialog" aria-modal="true" aria-label="{{ __('Page stories') }}">
    <div class="relative h-full w-full max-w-[460px] overflow-hidden bg-ink md:h-[92vh] md:rounded-[26px]"
         x-trap.noscroll="open" @touchstart="touchStart($event)" @touchend="touchEnd($event)">
        <img :src="slide.image" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="scrim"></div>
        <div class="absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-black/60 to-transparent"></div>

        {{-- Progress bars --}}
        <div class="absolute inset-x-3 top-3 flex gap-1">
            <template x-for="(sl, i) in group.slides" :key="i">
                <div class="h-[3px] flex-1 overflow-hidden rounded-full bg-white/35">
                    <div class="h-full bg-white" :style="`width: ${i < s ? 100 : (i === s ? progress : 0)}%`"></div>
                </div>
            </template>
        </div>

        {{-- Page + close --}}
        <div class="absolute inset-x-3 top-7 flex items-center gap-2.5">
            <img :src="group.avatar" alt="" class="h-9 w-9 rounded-[10px] object-cover ring-2 ring-white">
            <div class="min-w-0 flex-1 text-white">
                <p class="truncate text-[14px] font-extrabold" x-text="group.page"></p>
                <p class="text-[11.5px] text-white/75" x-text="slide.ago"></p>
            </div>
            <button type="button" @click="close()" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-white" aria-label="{{ __('Close') }}">
                <x-icon name="x" size="22" />
            </button>
        </div>

        {{-- Tap zones --}}
        <button type="button" class="absolute bottom-32 left-0 top-20 w-1/3" @click="prev()" aria-label="{{ __('Previous') }}"></button>
        <button type="button" class="absolute bottom-32 right-0 top-20 w-2/3" @click="next()" aria-label="{{ __('Next') }}"></button>

        {{-- Headline + open --}}
        <div class="absolute inset-x-0 bottom-0 p-5 text-white">
            <p class="text-[22px] font-black leading-tight" x-text="slide.headline"></p>
            <a :href="slide.url" class="mt-4 flex flex-col items-center gap-0.5 text-[13px] font-bold text-white no-underline hover:text-white">
                <x-icon name="chevron-down" size="20" class="rotate-180" />
                {{ __('Swipe up to read') }}
            </a>
        </div>
    </div>
</div>
