{{--
    Footer. Per the brief: black #111111, logo in light red, About · Grievance officer ·
    Privacy · Terms, and a red "Get the Android app" pill — all kept below.
    Restyled with a richer multi-column layout (categories, company, policies, social) from a
    reference the user supplied; adapted to this site's real pages/categories and kept on the
    required black background (see the two UI decisions this was built against).
    The newsletter box is visual only — @submit.prevent, no backend — like the Reels/Videos/
    Podcast "coming soon" pages: it doesn't collect or store anything yet.
--}}
<footer class="mt-10 bg-ink text-white">
    {{-- Newsletter banner (decorative; nothing is sent or stored) --}}
    <div class="mx-auto max-w-[1280px] px-4 pt-8 md:px-6">
        <div class="flex flex-col items-start gap-4 rounded-[22px] bg-dark-card/60 p-5 md:flex-row md:items-center md:justify-between md:gap-6 md:p-6">
            <div class="min-w-0">
                <p class="text-[11.5px] font-extrabold uppercase tracking-wide text-brand-pink-text">{{ __('Newsletter') }}</p>
                <h2 class="mt-1 text-[19px] font-black leading-tight text-white md:text-[21px]">{{ __('Get our top stories by email') }}</h2>
                <p class="mt-1 text-[13.5px] text-white/60">{{ __('Join our newsletter for a quick morning read — no spam, unsubscribe anytime.') }}</p>
            </div>
            <form class="flex w-full shrink-0 gap-2 md:w-auto" @submit.prevent="$store.toast.show(@js(__('Email digests aren\'t live yet — check back soon!'))); $event.target.reset()">
                <label for="footer-newsletter-email" class="sr-only">{{ __('Enter your email') }}</label>
                <div class="relative min-w-0 flex-1 md:w-[260px] md:flex-none">
                    <x-icon name="mail" size="17" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-2" />
                    <input id="footer-newsletter-email" type="email" required placeholder="{{ __('Enter your email') }}"
                           class="h-11 w-full rounded-full bg-white pl-11 pr-4 text-[14px] text-ink outline-none placeholder:text-muted-2">
                </div>
                <button type="submit" class="btn-primary h-11 shrink-0 text-[14px]">{{ __('Subscribe') }} <x-icon name="arrow-right" size="16" /></button>
            </form>
        </div>
    </div>

    {{-- Columns --}}
    <div class="mx-auto grid max-w-[1280px] grid-cols-2 gap-x-6 gap-y-8 px-4 py-9 md:grid-cols-[1.3fr_1fr_1fr_0.7fr_0.8fr] md:gap-8 md:px-6">
        {{-- Brand --}}
        <div class="col-span-2 md:col-span-1">
            <a href="{{ route('home') }}" class="no-underline" aria-label="NextGenNews home">
                <x-logo light />
            </a>
            <p class="mt-3 max-w-[220px] text-[13.5px] leading-relaxed text-white/60">{{ __('Short news with depth, in Hindi and English') }}.</p>
            <ul class="mt-4 flex items-center gap-2" aria-label="{{ __('Follow us') }}">
                @foreach (['instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X', 'linkedin' => 'LinkedIn'] as $icon => $label)
                    <li>
                        {{-- Inert for now, same as the header's notification bell: shown, not yet linked. --}}
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white/70" role="img" aria-label="{{ $label }}">
                            <x-icon :name="$icon" size="16" />
                        </span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ config('app.android_url') }}" class="btn-primary mt-5 text-sm" rel="noopener">
                <x-icon name="android" size="18" /> {{ __('Get the Android app') }}
            </a>
        </div>

        {{-- Categories (split into two columns, like the reference) --}}
        @php $half = (int) ceil($navCategories->count() / 2); @endphp
        @foreach ($navCategories->chunk($half) as $chunk)
            <nav aria-label="{{ __('Categories') }}">
                @if ($loop->first)
                    <h3 class="text-[12px] font-extrabold uppercase tracking-wide text-white/50">{{ __('Categories') }}</h3>
                @else
                    {{-- Hidden on mobile (would read as a duplicate heading); kept as an invisible
                         spacer on desktop so this column's list lines up with the first. --}}
                    <h3 class="hidden text-[12px] font-extrabold uppercase tracking-wide text-white/50 md:block md:invisible" aria-hidden="true">{{ __('Categories') }}</h3>
                @endif
                <ul class="mt-3 space-y-2.5">
                    @foreach ($chunk as $category)
                        <li><a href="{{ route('home', ['category' => $category->slug]) }}" class="text-[13.5px] text-white/75 no-underline hover:text-white">{{ $category->name() }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endforeach

        {{-- Company --}}
        <nav aria-label="{{ __('Company') }}">
            <h3 class="text-[12px] font-extrabold uppercase tracking-wide text-white/50">{{ __('Company') }}</h3>
            <ul class="mt-3 space-y-2.5">
                <li><a href="{{ route('about') }}" class="text-[13.5px] text-white/75 no-underline hover:text-white">{{ __('About') }}</a></li>
            </ul>
        </nav>

        {{-- Policies --}}
        <nav aria-label="{{ __('Policies') }}">
            <h3 class="text-[12px] font-extrabold uppercase tracking-wide text-white/50">{{ __('Policies') }}</h3>
            <ul class="mt-3 space-y-2.5">
                <li><a href="{{ route('grievance') }}" class="text-[13.5px] text-white/75 no-underline hover:text-white">{{ __('Grievance officer') }}</a></li>
                <li><a href="{{ route('privacy') }}" class="text-[13.5px] text-white/75 no-underline hover:text-white">{{ __('Privacy') }}</a></li>
                <li><a href="{{ route('terms') }}" class="text-[13.5px] text-white/75 no-underline hover:text-white">{{ __('Terms') }}</a></li>
                <li><a href="{{ route('community') }}" class="text-[13.5px] text-white/75 no-underline hover:text-white">{{ __('Community rules') }}</a></li>
            </ul>
        </nav>
    </div>

    <div class="border-t border-white/10">
        <p class="mx-auto max-w-[1280px] px-4 py-4 text-xs text-white/60 md:px-6">© {{ now()->year }} NextGenNews. {{ __('Summaries by our editors, AI-assisted.') }}</p>
    </div>
</footer>
