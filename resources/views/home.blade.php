<x-layouts.app active="home">
    <x-category-chips :active="$activeChip" class="hidden md:block" />

    <div class="mx-auto max-w-[1280px] px-4 pt-5 md:px-6 md:pt-8">
        {{-- 1. Greeting + story circles --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
                <h1 class="text-[24px] font-black tracking-[-0.6px] md:text-[32px] md:tracking-[-0.8px]">{{ $greeting }}</h1>
                <p class="mt-1 text-[13.5px] text-muted md:text-[14px]">
                    {{ __('Here\'s what\'s happening today') }} · {{ $today->translatedFormat('l, j F') }}
                </p>
            </div>

            <ul class="no-scrollbar -mx-4 flex gap-3.5 overflow-x-auto px-4 md:mx-0 md:px-0" aria-label="{{ __('Stories') }}">
                <li class="shrink-0">
                    <a href="{{ route('briefing') }}" class="flex w-[64px] flex-col items-center gap-1 text-ink no-underline">
                        <span class="flex h-[60px] w-[60px] items-center justify-center rounded-full bg-brand text-[24px] font-black text-white shadow-[var(--shadow-cta)]">5</span>
                        <span class="w-full truncate text-center text-[11.5px] font-semibold">{{ __('Morning 5') }}</span>
                    </a>
                </li>
                @foreach ($circles['viewer'] as $i => $group)
                    <li class="shrink-0">
                        <button type="button" @click="$dispatch('open-stories', { group: {{ $i }} })" class="flex w-[64px] flex-col items-center gap-1 text-ink">
                            <span class="story-ring !h-[60px] !w-[60px]"><img src="{{ $group['avatar'] }}" alt=""></span>
                            <span class="w-full truncate text-center text-[11.5px] font-semibold">{{ $group['page'] }}</span>
                        </button>
                    </li>
                @endforeach
                @foreach ($circles['categories'] as $category)
                    <li class="shrink-0">
                        <a href="{{ route('home', ['category' => $category->slug]) }}" class="flex w-[64px] flex-col items-center gap-1 text-ink no-underline">
                            <span class="story-ring !h-[60px] !w-[60px]"><img src="{{ $category->imageUrl() }}" alt="" loading="lazy"></span>
                            <span class="w-full truncate text-center text-[11.5px] font-semibold">{{ $category->name() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Mobile: chips row sits under the circles --}}
    <x-category-chips :active="$activeChip" class="mt-3 border-t md:hidden" />

    <div class="mx-auto max-w-[1280px] px-4 md:px-6">
        {{-- 2. Hero row --}}
        <div class="mt-4 flex flex-wrap gap-5 md:mt-6">
            @if ($hero)
                <div class="min-w-0" style="flex: 999 1 640px">
                    <x-hero-card :story="$hero" class="h-full" />
                </div>
            @endif
            <div class="hidden min-w-0 grow basis-[300px] flex-col gap-5 md:flex md:max-w-[340px]">
                <x-morning5-card :briefing="$morning5['briefing']" :read="$morning5['read']" class="flex-1" />
                <x-quiz-card :streak="$streak" />
            </div>
        </div>

        {{-- Mobile: slim red Morning 5 strip --}}
        <x-morning5-card variant="strip" :briefing="$morning5['briefing']" class="mt-4 md:hidden" />

        {{-- 3. Latest News + right rail --}}
        <div class="mt-7 flex flex-wrap gap-6 md:mt-9">
            <section class="min-w-0" style="flex: 999 1 640px" aria-labelledby="latest-title"
                     x-data="{
                        next: @js($latest->nextPageUrl() ? $latest->nextPageUrl().'&fragment=1' : null),
                        loading: false,
                        async more() {
                            if (!this.next || this.loading) return;
                            this.loading = true;
                            const res = await fetch(this.next, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                            this.$refs.list.insertAdjacentHTML('beforeend', await res.text());
                            this.next = res.headers.get('X-Next-Page') || null;
                            this.loading = false;
                        },
                     }">
                <div class="flex items-center justify-between">
                    <h2 id="latest-title" class="text-[20px] font-black tracking-[-0.4px] md:text-[22px]">{{ __('Latest News') }}</h2>
                    <a href="{{ route('explore') }}" class="text-sm font-bold no-underline">{{ __('See more') }} ›</a>
                </div>

                @if ($latest->isEmpty())
                    <div class="card mt-4 p-8 text-center text-muted">{{ __('No stories here yet.') }}</div>
                @else
                    <div x-ref="list" class="mt-4 grid gap-3 md:grid-cols-[repeat(auto-fill,minmax(240px,1fr))] md:gap-5" aria-live="polite">
                        @include('partials.latest-cards', ['stories' => $latest])
                    </div>
                    <div class="mt-6 text-center" x-show="next">
                        <button type="button" @click="more()" class="btn-ghost px-6" :disabled="loading">
                            <span x-show="!loading">{{ __('Load more') }}</span>
                            <span x-show="loading" x-cloak>{{ __('Loading…') }}</span>
                        </button>
                    </div>
                @endif
            </section>

            <aside class="min-w-0 grow basis-[300px] space-y-5 md:max-w-[340px]" aria-label="{{ __('More') }}">
                <x-trending-card :stories="$trending" />
                <x-pages-to-follow :pages="$pagesToFollow" :followed="$followed" class="hidden md:block" />
                @if ($poll)
                    <x-poll-card :poll="$poll" class="hidden md:block" />
                @endif
            </aside>
        </div>
    </div>

    @if ($circles['viewer']->isNotEmpty())
        <x-story-viewer :groups="$circles['viewer']" />
    @endif
</x-layouts.app>
