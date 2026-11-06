{{-- Global sheets for signed-in readers (report, share as image) and the toast for everyone. --}}
@auth
    {{-- Report --}}
    <div x-data="reportSheet" @ngn-report.window="show($event.detail)" x-show="open" x-cloak
         @keydown.escape.window="open = false"
         data-done="{{ __('Thanks. Our moderators will review it.') }}" data-failed="{{ __('Something went wrong. Please try again.') }}"
         class="fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/55" @click="open = false"></div>
        <form role="dialog" aria-modal="true" aria-labelledby="report-title" @submit.prevent="submit()" x-trap.noscroll="open"
              class="sheet md:bottom-auto md:left-1/2 md:right-auto md:top-1/2 md:w-[420px] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-[28px] md:pb-7">
            <div class="sheet-handle" aria-hidden="true"></div>
            <div class="flex items-start justify-between gap-4">
                <h2 id="report-title" class="text-[22px] font-extrabold">{{ __('Report') }}</h2>
                <button type="button" @click="open = false" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-chip" aria-label="{{ __('Close') }}"><x-icon name="x" size="20" /></button>
            </div>
            <p class="mt-1 text-[14px] text-muted">{{ __('Why are you reporting this?') }}</p>
            <fieldset class="mt-4 space-y-2">
                <legend class="sr-only">{{ __('Reason') }}</legend>
                @foreach (['spam' => __('Spam or promotion'), 'abusive' => __('Abusive or hateful'), 'misinformation' => __('False or misleading'), 'other' => __('Something else')] as $value => $label)
                    <label class="flex h-12 cursor-pointer items-center gap-3 rounded-[14px] border-[1.5px] px-4 text-[15px] font-semibold"
                           :class="reason === '{{ $value }}' ? 'border-brand bg-brand-tint' : 'border-line-2'">
                        <input type="radio" name="reason" value="{{ $value }}" x-model="reason" class="accent-[#D7141E]"> {{ $label }}
                    </label>
                @endforeach
            </fieldset>
            <button type="submit" class="btn-primary-lg mt-5" :disabled="!reason || busy" :class="(!reason || busy) && 'opacity-60'">{{ __('Send report') }}</button>
        </form>
    </div>

    {{-- Create poll (goes to editors as "pending") --}}
    <div x-data="{
            open: false, busy: false, error: '',
            question: '', options: ['', ''], days: 1,
            show() { this.open = true; this.error = ''; },
            get valid() { return this.question.trim().length >= 10 && this.options.filter(o => o.trim()).length >= 2; },
            async submit() {
                if (!this.valid || this.busy) return;
                this.busy = true; this.error = '';
                const r = await window.ngnFetch('/polls', { body: { question: this.question, options: this.options.filter(o => o.trim()), days: this.days } });
                this.busy = false;
                if (!r.ok) { this.error = r.data?.message || @js(__('Something went wrong. Please try again.')); return; }
                this.open = false; this.question = ''; this.options = ['', '']; this.days = 1;
                $store.toast.show(r.data.message);
            },
         }"
         @ngn-create-poll.window="show()"
         @ngn-replay.window="const p = $event.detail; if (!p.done && p.action === 'create_poll') { p.done = true; show(); }"
         x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/55" @click="open = false"></div>
        <form role="dialog" aria-modal="true" aria-labelledby="poll-create-title" @submit.prevent="submit()" x-trap.noscroll="open"
              class="sheet max-h-[92vh] overflow-y-auto md:bottom-auto md:left-1/2 md:right-auto md:top-1/2 md:w-[460px] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-[28px] md:pb-7">
            <div class="sheet-handle" aria-hidden="true"></div>
            <div class="flex items-start justify-between gap-4">
                <h2 id="poll-create-title" class="text-[22px] font-extrabold">{{ __('Create poll') }}</h2>
                <button type="button" @click="open = false" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-chip" aria-label="{{ __('Close') }}"><x-icon name="x" size="20" /></button>
            </div>
            <p class="mt-1 text-[13.5px] text-muted">{{ __('Editors review polls before they go live.') }} <a href="{{ route('community') }}">{{ __('Community rules') }}</a></p>

            <label for="poll-q" class="mt-4 block text-[13px] font-bold text-muted">{{ __('Question') }}</label>
            <textarea id="poll-q" x-model="question" maxlength="150" rows="2" class="input mt-1.5 h-auto py-3" placeholder="{{ __('Ask something about today\'s news…') }}"></textarea>

            <fieldset class="mt-4">
                <legend class="text-[13px] font-bold text-muted">{{ __('Options') }}</legend>
                <template x-for="(o, i) in options" :key="i">
                    <div class="mt-2 flex gap-2">
                        <input x-model="options[i]" maxlength="60" class="input h-12 flex-1" :aria-label="'{{ __('Option') }} ' + (i + 1)" :placeholder="'{{ __('Option') }} ' + (i + 1)">
                        <button type="button" x-show="options.length > 2" @click="options.splice(i, 1)" class="inline-flex h-12 w-12 items-center justify-center rounded-[14px] bg-chip" aria-label="{{ __('Remove option') }}"><x-icon name="x" size="18" /></button>
                    </div>
                </template>
                <button type="button" x-show="options.length < 4" @click="options.push('')" class="btn-outline mt-3"><x-icon name="plus" size="15" /> {{ __('Add option') }}</button>
            </fieldset>

            <p class="mt-4 text-[13px] font-bold text-muted">{{ __('Runs for') }}</p>
            <div class="seg mt-1.5" role="radiogroup" aria-label="{{ __('Runs for') }}">
                @foreach ([1 => __('1 day'), 3 => __('3 days'), 7 => __('7 days')] as $d => $label)
                    <button type="button" role="radio" :aria-checked="(days === {{ $d }}).toString()" @click="days = {{ $d }}" :class="days === {{ $d }} ? 'seg-item-active' : 'seg-item'" class="!h-9">{{ $label }}</button>
                @endforeach
            </div>

            <p x-show="error" x-text="error" x-cloak class="mt-3 text-[13px] font-semibold text-brand-dark" role="alert"></p>
            <button type="submit" class="btn-primary-lg mt-5" :disabled="!valid || busy" :class="(!valid || busy) && 'opacity-60'">{{ __('Send for review') }}</button>
        </form>
    </div>

    {{-- Share as image --}}
    <div x-data="shareSheet" @ngn-share.window="show($event.detail)" x-show="open" x-cloak
         @keydown.escape.window="open = false" class="fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/55" @click="open = false"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="share-title" x-trap.noscroll="open"
             class="sheet max-h-[92vh] overflow-y-auto md:bottom-auto md:left-1/2 md:right-auto md:top-1/2 md:w-[440px] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-[28px] md:pb-7">
            <div class="sheet-handle" aria-hidden="true"></div>
            <div class="flex items-start justify-between gap-4">
                <h2 id="share-title" class="text-[22px] font-extrabold">{{ __('Share') }}</h2>
                <button type="button" @click="open = false" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-chip" aria-label="{{ __('Close') }}"><x-icon name="x" size="20" /></button>
            </div>
            <div class="mx-auto mt-3 aspect-[4/5] w-full max-w-[260px] overflow-hidden rounded-[18px] bg-chip">
                <img x-show="image" :src="image" alt="{{ __('Share image preview') }}" class="h-full w-full object-cover">
                <div x-show="busy" class="flex h-full items-center justify-center text-[13px] font-semibold text-muted-2">{{ __('Making your image…') }}</div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <a :href="whatsapp" target="_blank" rel="noopener" class="btn-primary text-[14px]"><x-icon name="send" size="17" /> WhatsApp</a>
                <a :href="image" download="nextgennews.jpg" class="btn-ghost text-[14px]" :class="!image && 'pointer-events-none opacity-50'"><x-icon name="image" size="17" /> {{ __('Download') }}</a>
                <button type="button" @click="copy()" class="btn-ghost text-[14px]">
                    <x-icon name="share" size="17" /> <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy link') }}'"></span>
                </button>
                <button type="button" x-show="canNativeShare" @click="nativeShare()" class="btn-ghost text-[14px]"><x-icon name="smartphone" size="17" /> {{ __('More') }}</button>
            </div>
        </div>
    </div>
@endauth

<div x-data x-show="$store.toast.message" x-cloak x-transition.opacity
     class="pointer-events-none fixed inset-x-0 bottom-[calc(80px+env(safe-area-inset-bottom))] z-[70] flex justify-center px-4 md:bottom-8"
     role="status" aria-live="polite">
    <p class="rounded-full bg-ink px-5 py-3 text-[14px] font-semibold text-white shadow-[var(--shadow-float)]" x-text="$store.toast.message"></p>
</div>
