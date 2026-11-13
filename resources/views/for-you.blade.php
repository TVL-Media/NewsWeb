{{--
    For You: stories from the reader's topics and followed pages only.
    Desktop: sidebar (every topic as a chip → ?topic=slug, followed pages filter in place) + horizontal story cards.
    J / K (or ↓ / ↑) move between cards; the current one has a red outline. Mobile: chips on top, stacked cards.
--}}
@php $lang = app()->getLocale(); @endphp
<x-layouts.app :title="__('For You')" active="for-you">
    <div class="mx-auto max-w-[1100px] px-4 pb-10 pt-4 md:px-6 md:pt-7"
         x-data="{
            filter: null,          // { type: 'cat' | 'page', id }
            activeId: @js($stories->first()?->id),   // the card J / K is on (red outline)
            matches(el) { return !this.filter || Number(el.dataset[this.filter.type]) === this.filter.id; },
            cards() { return [...this.$root.querySelectorAll('[data-card]')].filter(el => this.matches(el)); },
            setFilter(f) { this.filter = f; this.activeId = Number(this.cards()[0]?.dataset.id) || null; },
            move(dir) {
                const cards = this.cards();
                if (!cards.length) return;
                const at = cards.findIndex(c => Number(c.dataset.id) === this.activeId);
                const card = cards[Math.min(Math.max(at + dir, 0), cards.length - 1)];
                this.activeId = Number(card.dataset.id);
                card.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                card.querySelector('h2 a')?.focus({ preventScroll: true });
            },
            key(e) {
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.metaKey || e.ctrlKey || e.altKey) return;
                if (e.key === 'j' || e.key === 'ArrowDown') { e.preventDefault(); this.move(1); }
                else if (e.key === 'k' || e.key === 'ArrowUp') { e.preventDefault(); this.move(-1); }
            },
         }"
         @keydown.window="key($event)">

        <div class="flex gap-6">
            {{-- ===== Sidebar (desktop) ===== --}}
            <aside class="hidden w-[230px] shrink-0 md:block" aria-label="{{ __('Filter your feed') }}">
                <div class="sticky top-[84px] space-y-4">
                    <section class="card p-4" aria-labelledby="fy-topics">
                        <h2 id="fy-topics" class="text-[14px] font-black">{{ __('Your topics') }}</h2>
                        {{-- Every topic: one of the reader's own, or any other, loads that topic's latest stories. --}}
                        <nav class="mt-3 flex flex-wrap gap-1.5" aria-label="{{ __('Your topics') }}">
                            <a href="{{ route('for-you') }}" class="{{ $topic ? 'chip hover:bg-line' : 'chip-active' }}" @unless ($topic) aria-current="page" @endunless>{{ __('All') }}</a>
                            @foreach ($topics as $cat)
                                <a href="{{ route('for-you', ['topic' => $cat->slug]) }}" class="{{ $topic?->is($cat) ? 'chip-active' : 'chip hover:bg-line' }}" @if ($topic?->is($cat)) aria-current="page" @endif>{{ $cat->name() }}</a>
                            @endforeach
                        </nav>
                        <a href="{{ route('account') }}" class="mt-3 inline-flex items-center gap-0.5 text-[13px] font-bold text-brand no-underline hover:text-brand-dark">{{ __('Edit topics') }} <x-icon name="chevron-right" size="14" /></a>
                    </section>

                    @if ($pages->isNotEmpty())
                        <section class="card p-4" aria-labelledby="fy-pages">
                            <h2 id="fy-pages" class="text-[14px] font-black">{{ __('Pages you follow') }}</h2>
                            <ul class="mt-2 -mx-1.5">
                                @foreach ($pages as $page)
                                    @php $fresh = in_array($page->id, $freshPageIds, true); @endphp
                                    <li>
                                        <button type="button" @click="setFilter(filter?.type === 'page' && filter.id === {{ $page->id }} ? null : { type: 'page', id: {{ $page->id }} })"
                                                class="flex w-full items-center gap-2.5 rounded-[10px] px-1.5 py-1.5 text-left hover:bg-chip"
                                                :class="filter?.type === 'page' && filter.id === {{ $page->id }} && 'bg-brand-tint'"
                                                :aria-pressed="(filter?.type === 'page' && filter.id === {{ $page->id }}).toString()">
                                            <img src="{{ $page->avatarUrl() }}" alt="" loading="lazy" width="28" height="28" class="h-7 w-7 shrink-0 rounded-[8px] object-cover">
                                            <span class="min-w-0 flex-1 truncate text-[13.5px] font-bold">{{ $page->name() }}</span>
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $fresh ? 'bg-brand' : 'bg-line-2' }}" @if ($fresh) title="{{ __('New stories') }}" @endif></span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            </aside>

            {{-- ===== Feed ===== --}}
            <div class="min-w-0 flex-1">
                <div>
                    <h1 class="text-[26px] font-black leading-tight tracking-[-0.6px] md:text-[30px]">{{ $topic ? $topic->name() : __('For You') }}</h1>
                    <p class="mt-0.5 text-[13.5px] text-muted">{{ $topic ? __('The latest stories in this topic') : __('From your topics and the pages you follow') }}</p>
                </div>

                {{-- Mobile: topic chips --}}
                <nav class="no-scrollbar -mx-4 mt-3 flex gap-1.5 overflow-x-auto px-4 md:hidden" aria-label="{{ __('Your topics') }}">
                    <a href="{{ route('for-you') }}" class="{{ $topic ? 'chip' : 'chip-active' }}">{{ __('All') }}</a>
                    @foreach ($topics as $cat)
                        <a href="{{ route('for-you', ['topic' => $cat->slug]) }}" class="{{ $topic?->is($cat) ? 'chip-active' : 'chip' }}">{{ $cat->name() }}</a>
                    @endforeach
                </nav>

                @if ($topic && $stories->isEmpty())
                    <div class="card mt-5 p-8 text-center text-muted">{{ __('No new stories in :topic this week.', ['topic' => $topic->name()]) }}</div>
                @endif

                @if (! $hasTopics)
                    <div class="card mt-5 p-8 text-center">
                        <h2 class="text-[22px] font-black">{{ __('Pick your topics') }}</h2>
                        <p class="mt-2 text-muted">{{ __('Choose topics in your account or follow a few pages to fill For You.') }}</p>
                        <a href="{{ route('explore') }}" class="btn-primary mt-5">{{ __('Explore topics') }}</a>
                    </div>
                @endif

                <div class="mt-4 space-y-4">
                    @foreach ($stories as $i => $story)
                        @if ($hasTopics && $i === $newCount)
                            @include('partials.for-you-caught-up', ['hasEarlier' => true])
                        @endif
                        @php
                            $isEarlier = $i >= $newCount;
                            $en = $story->version('en'); $hi = $story->version('hi');
                            $v = $story->version($lang);
                            $data = [
                                'id' => $story->id,
                                'lang' => $v->language,
                                'liked' => in_array($story->id, $liked, true),
                                'likes' => (int) $story->likes_count,
                                'saved' => in_array($story->id, $saved, true),
                                'text' => [
                                    'en' => ['headline' => $en?->headline, 'card' => $en?->card_text, 'audio' => $en?->audioUrl(), 'len' => $en?->audioLength()],
                                    'hi' => ['headline' => $hi?->headline, 'card' => $hi?->card_text, 'audio' => $hi?->audioUrl(), 'len' => $hi?->audioLength()],
                                ],
                            ];
                        @endphp
                        <article data-card data-id="{{ $story->id }}" data-cat="{{ $story->category_id }}" data-page="{{ $story->page_id }}"
                                 x-show="matches($el)"
                                 class="card flex flex-col gap-4 p-3 transition md:flex-row md:p-4"
                                 :class="activeId === id ? 'outline outline-2 outline-brand' : ''"
                                 @click="activeId = id"
                                 x-data="{
                                    ...@js($data),
                                    get t() { return this.text[this.lang].headline ? this.text[this.lang] : this.text[this.lang === 'en' ? 'hi' : 'en']; },
                                    readSent: @js($isEarlier),
                                    seen() { if (this.readSent) return; setTimeout(() => { if (!this.readSent) { this.readSent = true; window.ngnFetch('/stories/' + this.id + '/read'); } }, 3000); },
                                    async like() {
                                        this.liked = !this.liked; this.likes += this.liked ? 1 : -1;
                                        const r = await window.ngnFetch('/stories/' + this.id + '/like', { method: this.liked ? 'POST' : 'DELETE' });
                                        if (!r.ok) { this.liked = !this.liked; this.likes += this.liked ? 1 : -1; }
                                    },
                                    async save() {
                                        this.saved = !this.saved;
                                        const r = await window.ngnFetch('/stories/' + this.id + '/save', { method: this.saved ? 'POST' : 'DELETE' });
                                        if (r.ok) $store.toast.show(this.saved ? @js(__('Saved')) : @js(__('Removed from saved')));
                                        else this.saved = !this.saved;
                                    },
                                 }"
                                 x-intersect.half="seen()">
                            {{-- Photo --}}
                            <a href="{{ route('story.show', $story) }}" class="relative block h-[190px] shrink-0 overflow-hidden rounded-[16px] bg-ink md:h-auto md:min-h-[168px] md:w-[250px]" tabindex="-1" aria-hidden="true">
                                @if ($story->photoUrl())
                                    <img src="{{ $story->photoUrl() }}" alt="" @if ($i > 2) loading="lazy" @endif width="500" height="340" class="absolute inset-0 h-full w-full object-cover">
                                @endif
                                <span class="absolute left-2.5 top-2.5 flex gap-1.5">
                                    @if ($story->category)<span class="chip-red h-6 px-2.5 text-[11.5px]">{{ $story->category->name() }}</span>@endif
                                    @if ($isEarlier)<span class="photo-pill h-6">{{ __('Earlier') }}</span>@endif
                                </span>
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col">
                                {{-- Page row --}}
                                <div class="flex items-center gap-2">
                                    @if ($story->page)
                                        <img src="{{ $story->page->avatarUrl() }}" alt="" loading="lazy" width="24" height="24" class="h-6 w-6 shrink-0 rounded-[7px] object-cover">
                                        <p class="min-w-0 flex-1 truncate text-[12.5px] text-muted">
                                            <a href="{{ route('page.show', $story->page) }}" class="font-extrabold text-ink no-underline hover:text-brand">{{ $story->page->name() }}</a>
                                            @if ($story->source) · {{ $story->source->name }}@endif · {{ $story->ago() }}
                                        </p>
                                        <x-follow-button :page="$story->page" :following="in_array($story->page_id, $followed, true)" class="!h-7 !min-h-0 !px-3 !text-[12px]" />
                                    @else
                                        <p class="meta flex-1">@if ($story->source){{ $story->source->name }} · @endif{{ $story->ago() }}</p>
                                    @endif
                                </div>

                                {{-- Text --}}
                                <div class="mt-2" :lang="t === text.hi ? 'hi' : 'en'">
                                    <h2 class="line-clamp-2 text-[18px] font-extrabold leading-[1.3] tracking-[-0.2px] md:text-[19px]">
                                        <a href="{{ route('story.show', $story) }}" class="text-ink no-underline hover:text-brand focus:outline-none" x-text="t.headline">{{ $v->headline }}</a>
                                    </h2>
                                    {{-- "Read more" sits outside the clamp so it is never cut off with the "…". --}}
                                    <p class="reading-text mt-1.5 line-clamp-3 text-[14px] leading-[1.55] text-ink-2" x-text="t.card">{{ $v->card_text }}</p>
                                    <a href="{{ route('story.show', $story) }}" class="mt-1 inline-block text-[13.5px] font-bold text-brand no-underline hover:text-brand-dark">{{ __('Read more') }} ›</a>
                                </div>

                                {{-- Actions --}}
                                <div class="mt-auto flex items-center gap-0.5 pt-2.5">
                                    <button type="button" @click.stop="like()" :aria-pressed="liked.toString()"
                                            class="inline-flex h-9 items-center gap-1.5 rounded-full px-2 text-[13px] font-bold" :class="liked ? 'text-brand' : 'text-ink'">
                                        <x-icon name="heart" size="19" x-bind:class="liked && 'fill-current'" />
                                        <span x-text="likes.toLocaleString('en-IN')"></span><span class="sr-only">{{ __('likes') }}</span>
                                    </button>
                                    <a href="{{ route('story.show', $story) }}#comments-title" class="inline-flex h-9 items-center gap-1.5 rounded-full px-2 text-[13px] font-bold text-ink no-underline">
                                        <x-icon name="message-circle" size="19" /> {{ number_format($story->comments_count) }}<span class="sr-only">{{ __('Comments') }}</span>
                                    </a>
                                    <button type="button" @click.stop="$dispatch('ngn-share', { id, url: @js(route('story.show', $story)), title: t.headline, lang })"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-ink hover:bg-chip" aria-label="{{ __('Share') }}">
                                        <x-icon name="share" size="18" />
                                    </button>
                                    <div class="ml-auto flex items-center gap-1">
                                        @if (config('services.audio.enabled'))
                                            <button type="button" @click.stop="$dispatch('ngn-listen', { src: t.audio, title: t.headline })"
                                                    class="inline-flex h-8 items-center gap-1 rounded-full border-[1.5px] border-brand/30 px-2.5 text-[12px] font-bold text-brand"
                                                    :aria-label="'{{ __('Listen') }} ' + t.len">
                                                <x-icon name="volume-2" size="14" /> {{ __('Listen') }} <span x-text="t.len">{{ $v->audioLength() }}</span>
                                            </button>
                                        @endif
                                        @if ($en && $hi)
                                            <button type="button" @click.stop="lang = lang === 'en' ? 'hi' : 'en'"
                                                    class="inline-flex h-8 items-center gap-0.5 rounded-full border-[1.5px] border-line-2 px-2.5 text-[12px] font-bold hover:bg-chip"
                                                    aria-label="{{ __('Switch language') }}">
                                                <span lang="hi" :class="lang === 'hi' ? 'text-brand' : 'text-muted'">हिं</span><span class="text-line-2">/</span><span :class="lang === 'en' ? 'text-brand' : 'text-muted'">EN</span>
                                            </button>
                                        @endif
                                        <button type="button" @click.stop="save()" :aria-pressed="saved.toString()" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-chip"
                                                :class="saved ? 'text-brand' : 'text-ink'" aria-label="{{ __('Save') }}">
                                            <x-icon name="bookmark" size="18" x-bind:class="saved && 'fill-current'" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach

                    <div x-show="filter && !cards().length" x-cloak class="card p-8 text-center text-muted">{{ __('No stories for this filter yet.') }}</div>

                    {{-- Nothing read yet (or no earlier stories): the marker goes after the last card. --}}
                    @if ($hasTopics && $newCount === $stories->count())
                        @include('partials.for-you-caught-up', ['hasEarlier' => false])
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
