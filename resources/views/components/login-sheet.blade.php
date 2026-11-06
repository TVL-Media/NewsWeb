{{--
    Guest login. Mobile: bottom sheet (.sheet) over a dimmed page. Desktop: centred modal, same content.
    Opened by $store.auth.show() / $store.auth.require(action). Step 1: phone. Step 2: 6-digit OTP.
--}}
@guest
<div x-data="loginSheet"
     x-show="$store.auth.open" x-cloak
     @keydown.escape.window="$store.auth.close()"
     data-phone-error="{{ __('Enter a valid 10-digit mobile number.') }}"
     data-generic-error="{{ __('Something went wrong. Please try again.') }}"
     class="fixed inset-0 z-[60]">
    <div class="absolute inset-0 bg-black/55" @click="$store.auth.close()"
         x-show="$store.auth.open" x-transition.opacity></div>

    <div role="dialog" aria-modal="true" aria-labelledby="login-title"
         x-show="$store.auth.open"
         x-trap.noscroll="$store.auth.open"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="translate-y-full md:translate-y-[-46%] md:opacity-0"
         x-transition:enter-end="translate-y-0 md:translate-y-[-50%] md:opacity-100"
         class="sheet md:bottom-auto md:left-1/2 md:right-auto md:top-1/2 md:w-[440px] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-[28px] md:pb-7">
        <div class="sheet-handle" aria-hidden="true"></div>

        <div class="flex items-start justify-between gap-4">
            <h2 id="login-title" class="text-[26px] font-extrabold leading-tight tracking-[-0.5px]">
                <span x-show="step === 'phone'">{{ __('Log in to continue') }}</span>
                <span x-show="step === 'otp'" x-cloak>{{ __('Enter the OTP') }}</span>
            </h2>
            <button type="button" @click="$store.auth.close()"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-chip text-ink hover:bg-line"
                    aria-label="{{ __('Close') }}">
                <x-icon name="x" size="20" />
            </button>
        </div>

        {{-- Step 1: phone --}}
        <form x-show="step === 'phone'" @submit.prevent="sendOtp()" class="mt-2" novalidate>
            <p class="text-[15px] leading-relaxed text-muted">
                {{ __('Log in to like, comment, share, save, follow pages or vote in polls. Reading stays free without an account.') }}
            </p>

            <label for="login-phone" class="sr-only">{{ __('Mobile number') }}</label>
            <div class="mt-5 flex h-[54px] overflow-hidden rounded-[14px] border-[1.5px] border-line-2 focus-within:border-ink">
                <span class="flex items-center gap-1.5 border-r-[1.5px] border-line-2 bg-chip px-3.5 text-base font-bold text-ink">
                    <x-icon name="phone" size="17" />
                    +91
                    <x-icon name="chevron-down" size="15" class="text-muted-2" />
                </span>
                <input id="login-phone" x-model="phone" type="tel" inputmode="numeric" autocomplete="tel-national"
                       maxlength="10" placeholder="{{ __('Enter mobile number') }}"
                       @input="phone = phone.replace(/\D/g, '')"
                       class="min-w-0 flex-1 px-4 text-base text-ink outline-none placeholder:text-muted-2">
            </div>

            <p x-show="error" x-text="error" class="mt-2 text-sm font-semibold text-brand-dark" role="alert"></p>

            <button type="submit" class="btn-primary-lg mt-5" :disabled="busy" :class="busy && 'opacity-70'">
                {{ __('Send OTP') }} <x-icon name="arrow-right" size="19" />
            </button>

            <div class="my-5 flex items-center gap-3 text-[13px] font-semibold text-muted-2" aria-hidden="true">
                <span class="h-px flex-1 bg-line"></span>{{ __('or') }}<span class="h-px flex-1 bg-line"></span>
            </div>

            <a href="{{ config('app.android_url') }}" rel="noopener"
               class="btn h-[52px] w-full rounded-[16px] border-[1.5px] border-line-2 bg-white text-[15px] text-ink no-underline hover:bg-chip hover:text-ink">
                <x-icon name="android" size="20" class="text-success" /> {{ __('Open in the Android app') }}
            </a>

            <p class="mt-4 text-center text-xs text-muted-2">
                {!! __('By continuing you agree to the :terms and :privacy.', [
                    'terms' => '<a href="'.route('terms').'" class="font-semibold">'.e(__('Terms')).'</a>',
                    'privacy' => '<a href="'.route('privacy').'" class="font-semibold">'.e(__('Privacy Policy')).'</a>',
                ]) !!}
            </p>
        </form>

        {{-- Step 2: OTP --}}
        <form x-show="step === 'otp'" x-cloak @submit.prevent="verify()" class="mt-2" novalidate>
            <p class="text-[15px] leading-relaxed text-muted">
                {{ __('We sent a 6-digit code to') }} <strong class="text-ink">+91 <span x-text="phone"></span></strong>.
                <button type="button" class="font-bold text-brand underline-offset-2 hover:underline" @click="reset()">{{ __('Change') }}</button>
            </p>

            <fieldset class="mt-5">
                <legend class="sr-only">{{ __('One-time password') }}</legend>
                <div class="flex justify-between gap-2">
                    <template x-for="(d, i) in digits" :key="i">
                        <input type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                               data-otp
                               :value="digits[i]"
                               :aria-label="'{{ __('Digit') }} ' + (i + 1)"
                               @input="onDigit(i, $event)"
                               @keydown.backspace="onBackspace(i)"
                               class="h-14 w-full max-w-[52px] rounded-[14px] border-[1.5px] bg-white text-center text-[22px] font-extrabold text-ink outline-none"
                               :class="digits[i] !== '' ? 'border-ink' : (i === current ? 'border-brand' : 'border-line-2')">
                    </template>
                </div>
            </fieldset>

            @if (\App\Services\OtpService::devCode())
                <p class="mt-3 rounded-[12px] bg-chip px-3 py-2 text-[12.5px] text-muted">Development mode: use OTP <strong class="text-ink">{{ \App\Services\OtpService::devCode() }}</strong></p>
            @elseif (app()->isLocal() && config('services.sms.driver') === 'log')
                <p class="mt-3 rounded-[12px] bg-chip px-3 py-2 text-[12.5px] text-muted">Local dev: the OTP is written to <code>storage/logs/laravel.log</code>.</p>
            @endif

            <p class="mt-3 text-sm text-muted-2">
                <span x-show="resendIn > 0">{{ __('Resend in') }} <span x-text="resendLabel" class="font-bold text-ink"></span></span>
                <button type="button" x-show="resendIn <= 0" x-cloak @click="sendOtp()" class="font-bold text-brand">{{ __('Resend OTP') }}</button>
            </p>

            <p x-show="error" x-text="error" class="mt-2 text-sm font-semibold text-brand-dark" role="alert"></p>

            <button type="submit" class="btn-primary-lg mt-5" :disabled="busy || code.length !== 6" :class="(busy || code.length !== 6) && 'opacity-60'">
                {{ __('Verify') }}
            </button>
        </form>
    </div>
</div>
@endguest
