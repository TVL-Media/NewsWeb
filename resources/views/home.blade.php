<x-layouts.app active="home">
    <x-category-chips :active="$activeChip" class="hidden md:block" />

    <div class="mx-auto max-w-[1280px] px-4 pt-4 md:px-6 md:pt-5">
        {{-- 1. Greeting + story circles (one compact row on desktop) --}}
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between md:gap-6">
            <div class="min-w-0 shrink-0">
                <h1 class="text-[22px] font-black leading-tight tracking-[-0.5px] md:text-[26px]">{{ $greeting }}</h1>
                <p class="mt-0.5 text-[13px] text-muted">
                    {{ __('Here\'s what\'s happening today') }} · {{ $today->translatedFormat('l, j F') }}
                </p>
            </div>

            <ul class="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4 md:mx-0 md:px-0" aria-label="{{ __('Stories') }}">
                {{-- Every circle opens the story viewer at its group; stories then play on into the next circles. --}}
                @foreach ($circles as $i => $group)
                    @php $keys = empty($group['empty']) ? collect($group['slides'])->pluck('url')->filter()->values()->all() : []; @endphp
                    <li class="shrink-0"
                        x-data="{ keys: @js($keys), seen: false, check() { this.seen = this.keys.length > 0 && this.keys.every(k => window.ngnSeen?.has(k)); } }"
                        x-init="check()" @story-seen.window="check()">
                        {{-- No focus outline on the button itself (the scrolling row cut it into two stray lines); keyboard focus rings the circle instead. --}}
                        <button type="button" @click="$dispatch('open-stories', { group: {{ $i }} })" class="group flex w-[58px] flex-col items-center gap-1 text-ink focus-visible:outline-none"
                                aria-label="{{ __('Stories') }}: {{ $group['label'] }}">
                            @if ($group['badge'])
                                {{-- Same ring as the other circles (drawn inside the box, so the scrolling row can't clip it). --}}
                                <span class="story-ring !h-[54px] !w-[54px] !p-[2px] group-focus-visible:ring-2 group-focus-visible:ring-ink" :class="seen && '!border-line-2'"><span class="flex h-full w-full items-center justify-center rounded-full text-[20px] font-black text-white" :class="seen ? 'bg-muted' : 'bg-brand'">{{ $group['badge'] }}</span></span>
                            @else
                                {{-- Grey ring: nothing new in this category right now (its card says so), or every story in it has been watched. --}}
                                <span class="story-ring !h-[54px] !w-[54px] !p-[2px] group-focus-visible:ring-2 group-focus-visible:ring-ink {{ !empty($group['empty']) ? '!border-line-2' : '' }}" :class="seen && '!border-line-2'"><img src="{{ $group['avatar'] }}" alt="" @if ($i > 3) loading="lazy" @endif class="{{ !empty($group['empty']) ? 'opacity-70' : '' }}"></span>
                            @endif
                            <span class="w-full truncate text-center text-[11px] font-semibold">{{ $group['label'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Mobile: chips row sits under the circles --}}
    <x-category-chips :active="$activeChip" class="mt-3 border-t md:hidden" />

    <div class="mx-auto max-w-[1280px] px-4 md:px-6">
        <x-reel-row :reels="$reels" class="mt-4 md:mt-5" :query="$activeChip !== 'all' ? ['category' => $activeChip] : []" />
        {{-- 2. Hero row --}}
        <div class="mt-3 flex flex-wrap gap-4 md:mt-4">
            @if ($hero)
                <div class="min-w-0" style="flex: 999 1 640px">
                    <x-hero-card :story="$hero" class="h-full" />
                </div>
            @elseif ($category)
                {{-- A category with no posts yet: say so instead of an empty space. --}}
                <div class="min-w-0" style="flex: 999 1 640px">
                    <section class="hero-card flex h-full min-h-[260px] items-end bg-ink md:min-h-[420px]" aria-labelledby="empty-cat-title">
                        @if ($category->imageUrl())<img src="{{ $category->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60">@endif
                        <span class="scrim"></span>
                        <div class="relative p-6 text-white md:p-8">
                            <span class="chip-red h-7 text-[12px]">{{ $category->name() }}</span>
                            <h2 id="empty-cat-title" class="mt-3 text-[24px] font-black leading-tight md:text-[32px]">{{ __('No posts in this category right now') }}</h2>
                            <p class="mt-1.5 text-[14.5px] text-white/85">{{ __('New stories in :category will show up here.', ['category' => $category->name()]) }}</p>
                            <a href="{{ route('home') }}" class="btn mt-4 rounded-full bg-white px-5 text-ink no-underline hover:bg-white/90 hover:text-ink">{{ __('See all news') }}</a>
                        </div>
                    </section>
                </div>
            @endif
            <div class="hidden min-w-0 grow basis-[300px] flex-col gap-4 md:flex md:max-w-[340px]">
                <x-top5-card :stories="$trending" class="flex-1" />
                <x-quiz-card :streak="$streak" />
            </div>
        </div>

        {{-- Mobile: Top 5 under the hero --}}
        <x-top5-card :stories="$trending" class="mt-3 md:hidden" />

        {{-- 3. Latest News + right rail --}}
        <div class="mt-6 flex flex-wrap gap-5">
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
                <div class="flex items-end justify-between border-b border-line pb-2">
                    <h2 id="latest-title" class="text-[19px] font-black tracking-[-0.3px] md:text-[20px]">{{ __('Latest News') }}</h2>
                    <a href="{{ route('explore') }}" class="text-[13px] font-bold no-underline">{{ __('See more') }} ›</a>
                </div>

                @if ($latest->isEmpty())
                    <div class="card mt-3 p-6 text-center text-muted">{{ $category ? __('No posts in this category right now') : __('No stories here yet.') }}</div>
                @else
                    <div x-ref="list" class="mt-3 grid gap-3 md:grid-cols-[repeat(auto-fill,minmax(240px,1fr))] md:gap-4" aria-live="polite">
                        @include('partials.latest-cards', ['stories' => $latest])
                    </div>
                    <div class="mt-4 text-center" x-show="next">
                        <button type="button" @click="more()" class="btn-ghost px-6" :disabled="loading">
                            <span x-show="!loading">{{ __('Load more') }}</span>
                            <span x-show="loading" x-cloak>{{ __('Loading…') }}</span>
                        </button>
                    </div>
                @endif
            </section>

            <aside class="min-w-0 grow basis-[300px] space-y-4 md:max-w-[340px]" aria-label="{{ __('More') }}">
                <x-pages-to-follow :pages="$pagesToFollow" :followed="$followed" class="hidden md:block" />
                @if ($poll)
                    <x-poll-card :poll="$poll" class="hidden md:block" />
                @endif
            </aside>
        </div>
    </div>

    @if ($circles)
        <x-story-viewer :groups="$circles" />
    @endif
</x-layouts.app>
