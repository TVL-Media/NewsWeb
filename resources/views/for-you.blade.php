{{--
    For You: stories from the reader's topics and followed pages only (no story circles).
    One card per screen with scroll snapping; ↑/↓ keys move between cards. Desktop: centred 560px column.
--}}
<x-layouts.app :title="__('For You')" active="for-you">
    <div x-data="{
            next(dir) {
                const box = this.$refs.feed;
                box.scrollBy({ top: dir * box.clientHeight, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            },
         }"
         @keydown.window.arrow-down="if (!['INPUT','TEXTAREA'].includes($event.target.tagName)) { $event.preventDefault(); next(1); }"
         @keydown.window.arrow-up="if (!['INPUT','TEXTAREA'].includes($event.target.tagName)) { $event.preventDefault(); next(-1); }">
        <h1 class="sr-only">{{ __('For You') }}</h1>

        <div x-ref="feed" class="no-scrollbar h-[calc(100dvh-58px-66px)] snap-y snap-mandatory overflow-y-auto md:h-[calc(100dvh-72px)]"
             tabindex="-1" aria-label="{{ __('Your stories') }}">

            @if (! $hasTopics)
                <section class="flex h-full snap-start items-center justify-center p-4">
                    <div class="card max-w-[420px] p-8 text-center">
                        <h2 class="text-[22px] font-black">{{ __('Pick your topics') }}</h2>
                        <p class="mt-2 text-muted">{{ __('Choose topics in your account or follow a few pages to fill For You.') }}</p>
                        <a href="{{ route('explore') }}" class="btn-primary mt-5">{{ __('Explore topics') }}</a>
                    </div>
                </section>
            @endif

            @foreach ($stories as $i => $story)
                @php
                    $en = $story->version('en'); $hi = $story->version('hi');
                    $lang = app()->getLocale();
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
                <section class="flex h-full snap-start snap-always items-stretch justify-center md:px-4 md:py-5"
                         aria-label="{{ __('Story :n of :total', ['n' => $i + 1, 'total' => $stories->count()]) }}">
                    <article class="flex h-full w-full max-w-[560px] flex-col overflow-hidden bg-white md:h-auto md:max-h-full md:self-center md:rounded-[26px] md:shadow-[var(--shadow-hero)]"
                             x-data="{
                                ...@js($data),
                                get t() { return this.text[this.lang].headline ? this.text[this.lang] : this.text[this.lang === 'en' ? 'hi' : 'en']; },
                                readSent: false,
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
                        <div class="relative h-[38%] min-h-[190px] shrink-0 bg-ink md:h-[300px]">
                            @if ($story->photoUrl())
                                <img src="{{ $story->photoUrl() }}" alt="" @if ($i > 1) loading="lazy" @endif width="1120" height="600" class="h-full w-full object-cover">
                            @endif
                            <span class="chip-red absolute left-4 top-4 h-7 text-[12px]">{{ $story->category?->name() }}</span>
                            <button type="button" @click="$dispatch('ngn-listen', { src: t.audio, title: t.headline })"
                                    class="absolute bottom-4 right-4 inline-flex h-9 items-center gap-1.5 rounded-full bg-white px-3.5 text-[13px] font-bold text-ink shadow-[var(--shadow-float)]"
                                    :aria-label="'{{ __('Listen') }} ' + t.len">
                                <x-icon name="volume-2" size="16" /> {{ __('Listen') }} <span x-text="t.len">{{ $v->audioLength() }}</span>
                            </button>
                        </div>

                        <div class="flex min-h-0 flex-1 flex-col px-5 pb-4 pt-4">
                            {{-- Page row --}}
                            @if ($story->page)
                                <div class="flex items-center gap-2.5">
                                    <img src="{{ $story->page->avatarUrl() }}" alt="" loading="lazy" width="38" height="38" class="h-[38px] w-[38px] rounded-[11px] object-cover">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('page.show', $story->page) }}" class="block truncate text-[14px] font-extrabold text-ink no-underline hover:text-brand">{{ $story->page->name() }}</a>
                                        <p class="meta truncate">@if ($story->source){{ $story->source->name }} · @endif{{ $story->ago() }}</p>
                                    </div>
                                    <x-follow-button :page="$story->page" :following="in_array($story->page_id, $followed, true)" />
                                </div>
                            @endif

                            {{-- Text --}}
                            <div class="mt-3 min-h-0 flex-1 overflow-hidden md:flex-none" :lang="t === text.hi ? 'hi' : 'en'">
                                <h2 class="text-[21px] font-extrabold leading-[1.25] tracking-[-0.3px]" x-text="t.headline">{{ $v->headline }}</h2>
                                <p class="reading-text mt-2 text-[15.5px] leading-[1.55] text-ink-2" x-text="t.card">{{ $v->card_text }}</p>
                                <a href="{{ route('story.show', $story) }}" class="mt-1.5 inline-block text-[14px] font-bold no-underline">{{ __('Read more') }} ›</a>
                            </div>

                            {{-- Actions --}}
                            <div class="mt-3 flex items-center gap-1 border-t border-line pt-3">
                                <button type="button" @click="like()" :aria-pressed="liked.toString()"
                                        class="inline-flex h-11 items-center gap-1.5 rounded-full px-2.5 text-[14px] font-bold" :class="liked ? 'text-brand' : 'text-ink'">
                                    <x-icon name="heart" size="21" x-bind:class="liked && 'fill-current'" />
                                    <span x-text="likes.toLocaleString('en-IN')"></span><span class="sr-only">{{ __('likes') }}</span>
                                </button>
                                <a href="{{ route('story.show', $story) }}#comments-title" class="inline-flex h-11 items-center gap-1.5 rounded-full px-2.5 text-[14px] font-bold text-ink no-underline">
                                    <x-icon name="message-circle" size="21" /> {{ number_format($story->comments_count) }}<span class="sr-only">{{ __('Comments') }}</span>
                                </a>
                                <button type="button" @click="$dispatch('ngn-share', { id, url: @js(route('story.show', $story)), title: t.headline, lang })"
                                        class="inline-flex h-11 w-11 items-center justify-center rounded-full text-ink" aria-label="{{ __('Share') }}">
                                    <x-icon name="share" size="20" />
                                </button>
                                <div class="ml-auto flex items-center gap-1">
                                    @if ($en && $hi)
                                        <button type="button" @click="lang = lang === 'en' ? 'hi' : 'en'"
                                                class="inline-flex h-9 items-center gap-1 rounded-full border-[1.5px] border-line-2 px-3 text-[12.5px] font-bold"
                                                aria-label="{{ __('Switch language') }}">
                                            <span lang="hi" :class="lang === 'hi' ? 'text-brand' : 'text-muted'">हिं</span><span class="text-line-2">/</span><span :class="lang === 'en' ? 'text-brand' : 'text-muted'">EN</span>
                                        </button>
                                    @endif
                                    <button type="button" @click="save()" :aria-pressed="saved.toString()" class="inline-flex h-11 w-11 items-center justify-center rounded-full"
                                            :class="saved ? 'text-brand' : 'text-ink'" aria-label="{{ __('Save') }}">
                                        <x-icon name="bookmark" size="20" x-bind:class="saved && 'fill-current'" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>
            @endforeach

            @if ($hasTopics)
                <section class="flex h-full snap-start items-center justify-center p-6">
                    <div class="text-center">
                        <span class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-success-tint text-success"><x-icon name="check" size="30" :stroke="3" /></span>
                        <h2 class="mt-4 text-[24px] font-black tracking-[-0.5px]">{{ __("You're all caught up") }} ✓</h2>
                        <p class="mt-1.5 text-[15px] text-muted">{{ __('New stories from your topics and pages will appear here.') }}</p>
                        <div class="mt-5 flex justify-center gap-2">
                            <a href="{{ route('home') }}" class="btn-primary">{{ __('Latest News') }}</a>
                            <a href="{{ route('explore') }}" class="btn-ghost">{{ __('Explore') }}</a>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
