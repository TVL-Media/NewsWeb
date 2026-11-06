<footer class="mt-12 bg-ink text-white">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 py-9 md:flex-row md:items-center md:px-6">
        <a href="{{ route('home') }}" class="no-underline" aria-label="NextGenNews home">
            <x-logo light />
        </a>
        <nav aria-label="{{ __('Footer') }}" class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold md:ml-6">
            <a href="{{ route('about') }}" class="text-white/80 no-underline hover:text-white">{{ __('About') }}</a>
            <a href="{{ route('grievance') }}" class="text-white/80 no-underline hover:text-white">{{ __('Grievance officer') }}</a>
            <a href="{{ route('privacy') }}" class="text-white/80 no-underline hover:text-white">{{ __('Privacy') }}</a>
            <a href="{{ route('terms') }}" class="text-white/80 no-underline hover:text-white">{{ __('Terms') }}</a>
        </nav>
        <a href="{{ config('app.android_url') }}" class="btn-primary self-start text-sm md:ml-auto md:self-auto" rel="noopener">
            <x-icon name="android" size="18" /> {{ __('Get the Android app') }}
        </a>
    </div>
    <div class="border-t border-white/10">
        <p class="mx-auto max-w-[1280px] px-4 py-4 text-xs text-white/60 md:px-6">© {{ now()->year }} NextGenNews. {{ __('Summaries by our editors, AI-assisted.') }}</p>
    </div>
</footer>
