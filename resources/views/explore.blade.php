<x-layouts.app :title="__('Explore')" active="explore">
    <div class="mx-auto max-w-[1280px] px-4 pt-6 md:px-6 md:pt-8">
        <h1 class="text-[28px] font-black tracking-[-0.8px] md:text-[34px]">{{ __('Explore') }}</h1>
        <p class="mt-1 text-[14px] text-muted">{{ __('Discover news by topics') }}</p>

        {{-- Category photo tiles: 2×2 on mobile, 4 across on desktop --}}
        <ul class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-5" aria-label="{{ __('Categories') }}">
            @foreach ($categories as $category)
                @php $target = $category->pages->first() ? route('page.show', $category->pages->first()) : route('home', ['category' => $category->slug]); @endphp
                <li>
                    <a href="{{ $target }}" class="hero-card group h-[150px] bg-ink no-underline md:h-[190px]">
                        @if ($category->imageUrl())
                            <img src="{{ $category->imageUrl() }}" alt="" loading="lazy" width="600" height="400" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]">
                        @endif
                        <span class="scrim"></span>
                        <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-4">
                            <span class="text-[18px] font-black leading-tight text-white md:text-[21px]">{{ $category->name() }}</span>
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-ink" aria-hidden="true">
                                <x-icon name="arrow-right" size="17" />
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach

            @if (config('reels.enabled'))
                <li>
                    <a href="{{ route('reels') }}" class="hero-card group flex h-[150px] items-center justify-center bg-brand no-underline md:h-[190px]">
                        <span class="absolute left-4 top-4 rounded-full bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-brand">{{ __('New') }}</span>
                        <x-icon name="square-play" size="38" class="text-white/80 transition group-hover:text-white" />
                        <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-4">
                            <span class="text-[18px] font-black leading-tight text-white md:text-[21px]">{{ __('Reels') }}</span>
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20 text-white" aria-hidden="true">
                                <x-icon name="arrow-right" size="17" />
                            </span>
                        </span>
                    </a>
                </li>
            @endif

            {{-- Later-phase teasers: no photo (nothing is published yet), so an icon stands in for one. --}}
            @foreach ([
                ['route' => 'videos', 'icon' => 'video', 'label' => __('Videos')],
                ['route' => 'podcast', 'icon' => 'mic', 'label' => __('Podcast')],
            ] as $teaser)
                <li>
                    <a href="{{ route($teaser['route']) }}" class="hero-card group flex h-[150px] items-center justify-center bg-ink no-underline md:h-[190px]">
                        <span class="absolute left-4 top-4 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">{{ __('Soon') }}</span>
                        <x-icon :name="$teaser['icon']" size="34" class="text-white/50 transition group-hover:text-white/70" />
                        <span class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-4">
                            <span class="text-[18px] font-black leading-tight text-white md:text-[21px]">{{ $teaser['label'] }}</span>
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20 text-white" aria-hidden="true">
                                <x-icon name="arrow-right" size="17" />
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex flex-wrap gap-6">
            <section class="min-w-0" style="flex: 999 1 640px" aria-labelledby="explore-pages-title">
                <h2 id="explore-pages-title" class="text-[20px] font-black tracking-[-0.4px] md:text-[22px]">{{ __('Pages to follow') }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 md:gap-4">
                    @foreach ($pages as $page)
                        <li class="card relative flex items-center gap-3 p-4">
                            <img src="{{ $page->avatarUrl() }}" alt="" loading="lazy" width="52" height="52" class="h-[52px] w-[52px] shrink-0 rounded-[14px] object-cover">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('page.show', $page) }}" class="flex items-center gap-1 text-[15px] font-extrabold text-ink no-underline after:absolute after:inset-0 hover:text-brand">
                                    <span class="truncate">{{ $page->name() }}</span>
                                    @if ($page->verified)<x-icon name="badge-check" size="15" class="text-brand" label="{{ __('Verified') }}" />@endif
                                </a>
                                <p class="meta">{{ trans_choice(':count follower|:count followers', $page->followers_count, ['count' => number_format($page->followers_count)]) }}</p>
                            </div>
                            <x-follow-button :page="$page" :following="in_array($page->id, $followed, true)" variant="solid" />
                        </li>
                    @endforeach
                </ul>
            </section>

            <aside class="min-w-0 grow basis-[300px] md:max-w-[340px]">
                <x-trending-card :stories="$trending" />
            </aside>
        </div>
    </div>
</x-layouts.app>
