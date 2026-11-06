{{-- Mobile-only "Get the Android app" banner. Dismissal is remembered in localStorage. --}}
<div x-data="{
        show: false,
        init() { try { this.show = localStorage.getItem('ngn.appBannerDismissed') !== '1'; } catch (e) { this.show = true; } },
        dismiss() { this.show = false; try { localStorage.setItem('ngn.appBannerDismissed', '1'); } catch (e) {} },
     }"
     x-show="show" x-cloak
     class="flex items-center gap-3 bg-ink py-2 pl-4 pr-1 text-white md:hidden"
     role="region" aria-label="{{ __('Android app') }}">
    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[11px] bg-white">
        <x-logo :wordmark="false" :size="22" />
    </span>
    <p class="min-w-0 flex-1 text-[12.5px] leading-snug">
        <strong class="font-extrabold">{{ __('Get the Android app') }}</strong>
        <span class="text-white/75">· {{ __('Audio, streaks and daily quiz') }}</span>
    </p>
    <a href="{{ config('app.android_url') }}" class="btn-primary h-9 px-4 text-[13px]" rel="noopener">{{ __('Open') }}</a>
    <button type="button" @click="dismiss()" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-white/80 hover:text-white" aria-label="{{ __('Close') }}">
        <x-icon name="x" size="18" />
    </button>
</div>
