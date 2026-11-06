@php
    $filterUrl = fn (array $change) => route('search', array_filter(array_merge(
        ['q' => $q, 'type' => $type === 'all' ? null : $type, 'category' => $category?->slug], $change)));
    $total = $stories->count() + $pages->count();
@endphp
<x-layouts.app :title="$q !== '' ? __('Search: :q', ['q' => $q]) : __('Search')">
    <div class="mx-auto max-w-[860px] px-4 pt-6 md:px-6 md:pt-8"
         x-data="{
            recent: [],
            init() {
                try { this.recent = JSON.parse(localStorage.getItem('ngn.recentSearches')) || []; } catch (e) { this.recent = []; }
                const q = @js($q);
                if (q.length >= 2) this.remember(q);
            },
            remember(q) {
                this.recent = [q, ...this.recent.filter(r => r.toLowerCase() !== q.toLowerCase())].slice(0, 8);
                try { localStorage.setItem('ngn.recentSearches', JSON.stringify(this.recent)); } catch (e) {}
            },
            clear() { this.recent = []; try { localStorage.removeItem('ngn.recentSearches'); } catch (e) {} },
         }">
        <h1 class="sr-only">{{ __('Search') }}</h1>
        <form action="{{ route('search') }}" method="GET" role="search">
            <label for="search-q" class="sr-only">{{ __('Search news, topics, pages') }}</label>
            <div class="relative">
                <x-icon name="search" size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-2" />
                <input id="search-q" type="search" name="q" value="{{ $q }}" autofocus
                       placeholder="{{ __('Search news, topics, pages') }}"
                       class="input rounded-full pl-12 pr-28">
                @if ($type !== 'all')<input type="hidden" name="type" value="{{ $type }}">@endif
                @if ($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
                <button type="submit" class="btn-primary absolute right-1.5 top-1/2 h-[42px] min-h-0 -translate-y-1/2 px-5 text-sm">{{ __('Search') }}</button>
            </div>
        </form>

        @if ($q === '')
            {{-- Recent searches (this browser only) --}}
            <section class="mt-6" x-show="recent.length" x-cloak aria-labelledby="recent-title">
                <div class="flex items-center justify-between">
                    <h2 id="recent-title" class="text-[17px] font-black">{{ __('Recent searches') }}</h2>
                    <button type="button" @click="clear()" class="text-[13px] font-bold text-brand">{{ __('Clear') }}</button>
                </div>
                <ul class="mt-3 flex flex-wrap gap-2">
                    <template x-for="r in recent" :key="r">
                        <li><a :href="'{{ route('search') }}?q=' + encodeURIComponent(r)" class="chip hover:bg-line">
                            <x-icon name="history" size="14" class="mr-1.5" /><span x-text="r"></span></a></li>
                    </template>
                </ul>
            </section>
            <p class="mt-8 text-center text-[14px] text-muted" x-show="!recent.length">{{ __('Search stories by headline or summary, or find pages to follow.') }}</p>
        @else
            {{-- Filters --}}
            <div class="mt-5 space-y-2.5">
                <nav class="flex gap-2" aria-label="{{ __('Result type') }}">
                    @foreach (['all' => __('All'), 'stories' => __('Stories'), 'pages' => __('Pages')] as $key => $label)
                        <a href="{{ $filterUrl(['type' => $key === 'all' ? null : $key]) }}" class="{{ $type === $key ? 'chip-active' : 'chip hover:bg-line' }}" @if ($type === $key) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <nav class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:px-0" aria-label="{{ __('Category') }}">
                    <a href="{{ $filterUrl(['category' => null]) }}" class="{{ ! $category ? 'chip-red' : 'chip hover:bg-line' }}">{{ __('Any category') }}</a>
                    @foreach ($categories as $cat)
                        <a href="{{ $filterUrl(['category' => $cat->slug]) }}" class="{{ $category?->is($cat) ? 'chip-red' : 'chip hover:bg-line' }}">{{ $cat->name() }}</a>
                    @endforeach
                </nav>
            </div>

            <p class="meta mt-5" aria-live="polite">{{ trans_choice(':count results for “:q”', $total, ['count' => $total, 'q' => $q]) }}</p>

            @if ($total === 0)
                <div class="card mt-4 p-8 text-center">
                    <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-[16px] bg-brand-tint text-brand"><x-icon name="search" size="26" /></span>
                    <h2 class="mt-4 text-[20px] font-black">{{ __('No results for “:q”', ['q' => $q]) }}</h2>
                    <p class="mt-1.5 text-[14px] text-muted">{{ __('Try fewer or different words, or remove the filters.') }}</p>
                    <a href="{{ route('explore') }}" class="btn-primary mt-5">{{ __('Explore topics') }}</a>
                </div>
            @endif

            @if ($pages->isNotEmpty())
                <section class="mt-4" aria-labelledby="res-pages">
                    <h2 id="res-pages" class="text-[17px] font-black">{{ __('Pages') }}</h2>
                    <ul class="mt-3 space-y-3">
                        @foreach ($pages as $page)
                            <li class="card relative flex items-center gap-3 p-3">
                                <img src="{{ $page->avatarUrl() }}" alt="" loading="lazy" width="64" height="64" class="h-16 w-16 shrink-0 rounded-[14px] object-cover">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('page.show', $page) }}" class="text-[15px] font-extrabold text-ink no-underline after:absolute after:inset-0 hover:text-brand">{{ $page->name() }}</a>
                                    <p class="line-clamp-1 text-[13px] text-muted-2">{{ $page->description }}</p>
                                </div>
                                <x-follow-button :page="$page" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($stories->isNotEmpty())
                <section class="mt-6" aria-labelledby="res-stories">
                    <h2 id="res-stories" class="text-[17px] font-black">{{ __('Stories') }}</h2>
                    <div class="mt-3 space-y-3">
                        @foreach ($stories as $story)
                            <x-story-card :story="$story" variant="row" thumb="w-[96px] h-[80px] md:w-[132px] md:h-[96px]" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-layouts.app>
