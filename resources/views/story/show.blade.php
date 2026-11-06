@php
    $lang = app()->getLocale();
    $v = $versions[$lang];
    $other = $lang === 'hi' ? 'en' : 'hi';
    $hasOther = $versions[$other] && $versions[$other]->language === $other;
    $page = $story->page;
    $url = route('story.show', $story);
    $whatsapp = 'https://wa.me/?text='.rawurlencode($v->headline.' '.$url);
    $reactions = [
        'surprising' => __('Surprising'),
        'unfair' => __('Unfair'),
        'need_info' => __('Need more info'),
        'good_news' => __('Good news'),
    ];
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => \Illuminate\Support\Str::limit($v->headline, 110, ''),
        'description' => $v->card_text,
        'image' => array_filter([$story->photoUrl() ? url($story->photoUrl()) : null]),
        'datePublished' => $story->published_at->toIso8601String(),
        'dateModified' => $story->updated_at->toIso8601String(),
        'inLanguage' => $v->language === 'hi' ? 'hi-IN' : 'en-IN',
        'articleSection' => $story->category?->name_en,
        'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Organization', 'name' => 'NextGenNews'],
        'publisher' => ['@type' => 'Organization', 'name' => 'NextGenNews', 'logo' => ['@type' => 'ImageObject', 'url' => asset('images/logo.svg')]],
    ];
@endphp
<x-layouts.app :title="$v->headline" :description="$v->card_text" :canonical="$url"
               :og-image="$story->photoUrl() ? url($story->photoUrl()) : null" og-type="article">
    <x-slot:head>
        <meta property="article:published_time" content="{{ $story->published_at->toIso8601String() }}">
        @if ($hasOther)
            <link rel="alternate" hreflang="{{ $v->language === 'hi' ? 'hi-IN' : 'en-IN' }}" href="{{ $url }}">
        @endif
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <div class="mx-auto max-w-[1200px] px-4 pb-24 pt-5 md:px-6 md:pb-0 md:pt-8"
         x-data="{
            lang: @js($lang),
            tab: 'key',
            liked: @js($liked), likes: {{ (int) $story->likes_count }},
            saved: @js($saved),
            reaction: @js($reaction),
            audio: @js(['en' => $versions['en']?->audioUrl(), 'hi' => $versions['hi']?->audioUrl()]),
            headlines: @js(['en' => $versions['en']?->headline, 'hi' => $versions['hi']?->headline]),
            listen() { $dispatch('ngn-listen', { src: this.audio[this.lang], title: this.headlines[this.lang] }); },
            async like() {
                if (!$store.auth.require('like', { story: {{ $story->id }} })) return;
                this.liked = !this.liked; this.likes += this.liked ? 1 : -1;
                const r = await window.ngnFetch('/stories/{{ $story->id }}/like', { method: this.liked ? 'POST' : 'DELETE' });
                if (!r.ok) { this.liked = !this.liked; this.likes += this.liked ? 1 : -1; }
            },
            async save() {
                if (!$store.auth.require('save', { story: {{ $story->id }} })) return;
                this.saved = !this.saved;
                const r = await window.ngnFetch('/stories/{{ $story->id }}/save', { method: this.saved ? 'POST' : 'DELETE' });
                if (!r.ok) this.saved = !this.saved;
            },
            async react(type) {
                if (!$store.auth.require('react', { story: {{ $story->id }}, type })) return;
                const before = this.reaction;
                this.reaction = this.reaction === type ? null : type;
                const r = await window.ngnFetch('/stories/{{ $story->id }}/reaction', { method: 'PUT', body: { type: this.reaction } });
                if (!r.ok) this.reaction = before;
            },
            shareImage() {
                if (!$store.auth.require('share', { story: {{ $story->id }} })) return;
                $dispatch('ngn-share', { id: {{ $story->id }}, url: @js($url), title: this.headlines[this.lang], lang: this.lang });
            },
            report() {
                if ($store.auth.require('report', { story: {{ $story->id }}, type: 'story', id: {{ $story->id }} })) $dispatch('ngn-report', { type: 'story', id: {{ $story->id }} });
            },
            /* Finish what a guest tried before logging in. */
            replay(p) {
                if (p.payload?.story !== {{ $story->id }}) return;
                if (p.action === 'like' && !this.liked) this.like();
                if (p.action === 'save' && !this.saved) this.save();
                if (p.action === 'react' && this.reaction !== p.payload.type) this.react(p.payload.type);
                if (p.action === 'share') this.shareImage();
                if (p.action === 'report') this.report();
                if (p.action === 'comment') this.$nextTick(() => document.getElementById('comment-body')?.focus());
            },
            init() {
                /* Count a read (streaks) after 8 seconds on the page. */
                if ($store.auth.loggedIn) setTimeout(() => window.ngnFetch('/stories/{{ $story->id }}/read'), 8000);
            },
         }"
         @ngn-replay.window="replay($event.detail)">
        <div class="flex flex-wrap gap-8">
            {{-- ===== Main column ===== --}}
            <article class="min-w-0" style="flex: 999 1 640px">
                <nav aria-label="{{ __('Breadcrumb') }}" class="meta flex flex-wrap items-center gap-1">
                    <a href="{{ route('home') }}" class="no-underline">{{ __('Home') }}</a> <span aria-hidden="true">›</span>
                    @if ($desk)<span>{{ $desk }}</span> <span aria-hidden="true">›</span>@endif
                    @if ($page)<a href="{{ route('page.show', $page) }}" class="no-underline">{{ $page->name() }}</a>@endif
                </nav>

                @if ($story->correction_label)
                    <p class="mt-3 inline-flex rounded-full bg-brand-tint px-3 py-1 text-[12.5px] font-bold text-brand-dark">{{ $story->correction_label }}</p>
                @endif

                <h1 class="mt-3 text-[25px] font-black leading-[1.15] tracking-[-0.5px] md:text-[40px] md:tracking-[-1px]">
                    @foreach (['en', 'hi'] as $l)
                        @if ($versions[$l])
                            <span x-show="lang === '{{ $l }}'" @if ($l !== $lang) x-cloak @endif lang="{{ $versions[$l]->language }}">{{ $versions[$l]->headline }}</span>
                        @endif
                    @endforeach
                </h1>

                {{-- Meta row --}}
                <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-3">
                    @if ($page)
                        <a href="{{ route('page.show', $page) }}" class="shrink-0" aria-hidden="true" tabindex="-1">
                            <img src="{{ $page->avatarUrl() }}" alt="" width="42" height="42" class="h-[42px] w-[42px] rounded-[12px] object-cover">
                        </a>
                        <div class="min-w-0 flex-1 sm:flex-none">
                            <a href="{{ route('page.show', $page) }}" class="flex items-center gap-1 text-[15px] font-extrabold text-ink no-underline hover:text-brand">
                                {{ $page->name() }}
                                @if ($page->verified)<x-icon name="badge-check" size="15" class="text-brand" label="{{ __('Verified') }}" />@endif
                            </a>
                            <p class="meta">
                                @if ($story->source){{ __('Source') }}: {{ $story->source->name }} · @endif{{ $story->ago() }} · {{ trans_choice(':count min read', $story->read_minutes) }}
                            </p>
                        </div>
                        <x-follow-button :page="$page" :following="$following" :label="__('Follow').' +'" />
                    @endif

                    <div class="flex w-full items-center gap-2 sm:ml-auto sm:w-auto">
                        <button type="button" class="btn-tint" @click="listen()">
                            <x-icon name="headphones" size="17" />
                            {{ __('Listen') }} · {{ max(1, (int) ceil(($v->audio_seconds ?: 60) / 60)) }} {{ __('min') }}
                        </button>
                        @if ($hasOther)
                            <button type="button" class="btn-ghost" @click="lang = lang === 'en' ? 'hi' : 'en'" :aria-pressed="(lang !== @js($lang)).toString()">
                                <span x-show="lang === 'en'" @if ($lang !== 'en') x-cloak @endif>Read in <span lang="hi">हिंदी</span></span>
                                <span x-show="lang === 'hi'" @if ($lang !== 'hi') x-cloak @endif>Read in English</span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Photo --}}
                <figure class="mt-5">
                    <div class="h-[240px] overflow-hidden rounded-[22px] bg-chip md:h-[440px] md:rounded-[26px]">
                        @if ($story->photoUrl())
                            <img src="{{ $story->photoUrl() }}" alt="" fetchpriority="high" width="1200" height="800" class="h-full w-full object-cover">
                        @endif
                    </div>
                    @if ($story->photo_credit)
                        <figcaption class="meta mt-1.5 text-right text-[11.5px]">{{ __('Photo') }}: {{ $story->photo_credit }}</figcaption>
                    @endif
                </figure>

                {{-- Tabs --}}
                <div class="mt-6 overflow-x-auto no-scrollbar">
                    <div class="seg" role="tablist" aria-label="{{ __('How to read this story') }}">
                        @foreach (['key' => __('Key Points'), 'simple' => __('Explain Simply'), 'full' => __('Full Story')] as $key => $label)
                            <button type="button" role="tab" id="tab-{{ $key }}" :aria-controls="'panel-{{ $key }}-' + lang"
                                    :aria-selected="(tab === '{{ $key }}').toString()"
                                    :class="tab === '{{ $key }}' ? 'seg-item-active' : 'seg-item hover:text-ink'"
                                    class="{{ $key === 'key' ? 'seg-item-active' : 'seg-item' }} whitespace-nowrap"
                                    @click="tab = '{{ $key }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @foreach (['en', 'hi'] as $l)
                    @php $ver = $versions[$l]; @endphp
                    @continue(! $ver)
                    <div x-show="lang === '{{ $l }}'" @if ($l !== $lang) x-cloak @endif lang="{{ $ver->language }}">
                        <section id="panel-key-{{ $l }}" role="tabpanel" aria-labelledby="tab-key" x-show="tab === 'key'" class="card mt-4 p-5 md:p-6">
                            <ul class="space-y-4">
                                @foreach ($ver->key_points as $point)
                                    <li class="reading-text flex gap-3 text-[16px] leading-[1.55] text-ink md:text-[17px]">
                                        <span class="check-dot"><x-icon name="check" size="13" :stroke="3" /></span>
                                        <span>{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                        <section id="panel-simple-{{ $l }}" role="tabpanel" aria-labelledby="tab-simple" x-show="tab === 'simple'" x-cloak class="card mt-4 p-5 md:p-6">
                            <p class="reading-text text-[16px] leading-[1.65] text-ink md:text-[17px]">{{ $ver->simple_text }}</p>
                        </section>
                        <section id="panel-full-{{ $l }}" role="tabpanel" aria-labelledby="tab-full" x-show="tab === 'full'" x-cloak class="card mt-4 space-y-4 p-5 md:p-6">
                            @foreach ($ver->paragraphs() as $para)
                                <p class="reading-text text-[16px] leading-[1.7] text-ink-2 md:text-[17px]">{{ $para }}</p>
                            @endforeach
                        </section>

                        @if ($ver->why_it_matters)
                            <aside class="why-box mt-4" aria-label="{{ __('Why it matters') }}">
                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-[15px] font-black text-white" aria-hidden="true">i</span>
                                <div>
                                    <h4>{{ $l === 'hi' ? 'यह क्यों मायने रखता है' : 'Why it matters' }}</h4>
                                    <p class="mt-1 text-[15px] leading-relaxed text-ink">{{ $ver->why_it_matters }}</p>
                                </div>
                            </aside>
                        @endif
                    </div>
                @endforeach

                {{-- Actions --}}
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <button type="button" @click="like()" :aria-pressed="liked.toString()"
                            class="btn rounded-full border-[1.5px] px-4 text-[13.5px]"
                            :class="liked ? 'border-brand bg-brand-tint text-brand' : 'border-line-2 bg-white text-ink hover:bg-chip'">
                        <x-icon name="heart" size="17" x-bind:class="liked && 'fill-current'" />
                        <span x-text="likes.toLocaleString('en-IN')">{{ number_format($story->likes_count) }}</span>
                        <span class="sr-only">{{ __('likes') }}</span>
                    </button>
                    @foreach ($reactions as $type => $label)
                        <button type="button" @click="react('{{ $type }}')" :aria-pressed="(reaction === '{{ $type }}').toString()"
                                class="h-11 rounded-full border-[1.5px] px-3.5 text-[13px] font-bold"
                                :class="reaction === '{{ $type }}' ? 'border-brand bg-brand-tint text-brand' : 'border-line-2 bg-white text-ink-2 hover:bg-chip'">
                            {{ $label }}
                        </button>
                    @endforeach
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" @click="shareImage()" class="btn rounded-full bg-ink px-4 text-[13.5px] text-white hover:bg-black">
                            <x-icon name="image" size="17" /> {{ __('Share as image') }}
                        </button>
                        <button type="button" @click="save()" :aria-pressed="saved.toString()"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-full border-[1.5px] border-line-2 bg-white hover:bg-chip"
                                :class="saved ? 'text-brand' : 'text-ink'" aria-label="{{ __('Save') }}">
                            <x-icon name="bookmark" size="19" x-bind:class="saved && 'fill-current'" />
                        </button>
                    </div>
                </div>

                {{-- Comments (live: post, reply, like, report) --}}
                <section class="card mt-6 p-5 md:p-6" aria-labelledby="comments-title"
                         x-data="{
                            comments: @js($commentsData),
                            count: {{ (int) $story->comments_count }},
                            body: '', replyTo: null, busy: false, error: '',
                            async post() {
                                if (!$store.auth.require('comment', { story: {{ $story->id }} })) return;
                                if (this.body.trim().length < 2 || this.busy) return;
                                this.busy = true; this.error = '';
                                const r = await window.ngnFetch('/stories/{{ $story->id }}/comments', { body: { body: this.body, parent_id: this.replyTo?.id ?? null } });
                                this.busy = false;
                                if (!r.ok) { this.error = r.data?.message || '{{ __('Something went wrong. Please try again.') }}'; return; }
                                if (this.replyTo) { this.replyTo.replies.push(r.data.comment); } else { this.comments.unshift(r.data.comment); }
                                this.count++; this.body = ''; this.replyTo = null;
                            },
                            reply(c) {
                                if (!$store.auth.require('comment', { story: {{ $story->id }} })) return;
                                this.replyTo = c; this.$nextTick(() => document.getElementById('comment-body')?.focus());
                            },
                            async like(c) {
                                if (!$store.auth.require('like_comment', { story: {{ $story->id }} })) return;
                                c.liked = !c.liked; c.likes += c.liked ? 1 : -1;
                                const r = await window.ngnFetch('/comments/' + c.id + '/like', { method: c.liked ? 'POST' : 'DELETE' });
                                if (!r.ok) { c.liked = !c.liked; c.likes += c.liked ? 1 : -1; }
                            },
                            report(c) {
                                if ($store.auth.require('report', { type: 'comment', id: c.id })) $dispatch('ngn-report', { type: 'comment', id: c.id });
                            },
                         }">
                    <h2 id="comments-title" class="text-[18px] font-black">{{ __('Comments') }} · <span x-text="count.toLocaleString('en-IN')">{{ number_format($story->comments_count) }}</span></h2>
                    @auth
                        <form class="mt-4" @submit.prevent="post()">
                            <p x-show="replyTo" x-cloak class="mb-2 flex items-center gap-2 text-[13px] font-semibold text-muted">
                                {{ __('Replying to') }} <span class="text-ink" x-text="replyTo?.name"></span>
                                <button type="button" class="text-brand" @click="replyTo = null">{{ __('Cancel') }}</button>
                            </p>
                            <div class="flex gap-2">
                                <label for="comment-body" class="sr-only">{{ __('Add a comment') }}</label>
                                <input id="comment-body" x-model="body" class="input h-12 flex-1" placeholder="{{ __('Add a comment…') }}" maxlength="1000" autocomplete="off">
                                <button type="submit" class="btn-primary" :disabled="busy || body.trim().length < 2" :class="(busy || body.trim().length < 2) && 'opacity-60'">{{ __('Post') }}</button>
                            </div>
                            <p x-show="error" x-text="error" x-cloak class="mt-2 text-[13px] font-semibold text-brand-dark" role="alert"></p>
                        </form>
                    @else
                        <button type="button" @click="$store.auth.require('comment', { story: {{ $story->id }} })"
                                class="input mt-4 flex h-12 items-center text-left text-muted-2 hover:border-ink">
                            {{ __('Log in to comment…') }}
                        </button>
                    @endauth

                    <p x-show="!comments.length" class="mt-5 text-[14px] text-muted">{{ __('No comments yet. Start the conversation.') }}</p>
                    <ul class="mt-4" x-show="comments.length">
                        <template x-for="c in comments" :key="c.id">
                            <li class="border-t border-line py-4">
                                <div class="flex gap-3">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-brand text-sm font-extrabold text-white" aria-hidden="true" x-text="c.initial"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[14px]"><strong class="font-extrabold" x-text="c.name"></strong> <span class="meta" x-text="'· ' + c.ago"></span></p>
                                        <p class="mt-1 whitespace-pre-line break-words text-[15px] leading-relaxed text-ink-2" x-text="c.body"></p>
                                        <div class="mt-1.5 flex gap-4 text-[12.5px] font-bold text-muted-2">
                                            <button type="button" class="hover:text-brand" :class="c.liked && 'text-brand'" @click="like(c)" :aria-pressed="c.liked.toString()">{{ __('Like') }}<span x-show="c.likes" x-text="' · ' + c.likes"></span></button>
                                            <button type="button" class="hover:text-brand" @click="reply(c)">{{ __('Reply') }}</button>
                                            <button type="button" class="hover:text-brand" @click="report(c)">{{ __('Report') }}</button>
                                        </div>
                                        <ul class="mt-2 space-y-3 border-l-2 border-line pl-4" x-show="c.replies.length">
                                            <template x-for="r in c.replies" :key="r.id">
                                                <li>
                                                    <p class="text-[13.5px]"><strong class="font-extrabold" x-text="r.name"></strong> <span class="meta" x-text="'· ' + r.ago"></span></p>
                                                    <p class="mt-0.5 whitespace-pre-line break-words text-[14.5px] text-ink-2" x-text="r.body"></p>
                                                    <div class="mt-1 flex gap-4 text-[12px] font-bold text-muted-2">
                                                        <button type="button" class="hover:text-brand" :class="r.liked && 'text-brand'" @click="like(r)">{{ __('Like') }}<span x-show="r.likes" x-text="' · ' + r.likes"></span></button>
                                                        <button type="button" class="hover:text-brand" @click="report(r)">{{ __('Report') }}</button>
                                                    </div>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
                <p class="meta mt-5 leading-relaxed">
                    {{ __('Summary by our editors, AI-assisted.') }}
                    @if ($story->source) {{ __('Source') }}: {{ $story->source->name }} · @endif
                    @if ($story->source_url)<a href="{{ $story->source_url }}" rel="nofollow noopener" target="_blank">{{ __('Read original') }}</a> · @endif
                    <button type="button" class="font-semibold text-brand hover:text-brand-dark" @click="report()">{{ __('Report this story') }}</button>
                </p>
            </article>

            {{-- ===== Right rail ===== --}}
            <aside class="min-w-0 grow basis-[300px] space-y-5 md:max-w-[340px]" aria-label="{{ __('More') }}">
                <x-morning5-card variant="mini" :briefing="$morning5['briefing']" />

                @if ($related->isNotEmpty())
                    <section class="card p-5" aria-labelledby="related-title">
                        <h2 id="related-title" class="text-[18px] font-black">{{ __('Related stories') }}</h2>
                        <div class="mt-3 space-y-3.5">
                            @foreach ($related as $item)
                                <x-story-card :story="$item" variant="row" thumb="w-[86px] h-[66px]" :card="false" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($poll)
                    <x-poll-card :poll="$poll" :voted-option-id="$votedOptionId" :show-create="false" :results="true" />
                @endif

                <div class="flex h-[250px] items-center justify-center rounded-[22px] border-2 border-dashed border-line-2 text-[13px] font-bold tracking-wide text-muted-2" aria-hidden="true">
                    AD · 300×250
                </div>
            </aside>
        </div>

        {{-- Mobile: fixed bottom action bar (above the bottom nav) --}}
        <div class="fixed inset-x-0 bottom-[calc(66px+env(safe-area-inset-bottom))] z-30 flex items-center gap-2 border-t border-line bg-white px-4 py-2 md:hidden">
            <button type="button" @click="like()" class="inline-flex h-11 items-center gap-1.5 px-2 font-bold" :class="liked ? 'text-brand' : 'text-ink'" :aria-pressed="liked.toString()">
                <x-icon name="heart" size="20" x-bind:class="liked && 'fill-current'" />
                <span x-text="likes.toLocaleString('en-IN')"></span><span class="sr-only">{{ __('likes') }}</span>
            </button>
            <button type="button" @click="save()" class="inline-flex h-11 w-11 items-center justify-center" :class="saved ? 'text-brand' : 'text-ink'" aria-label="{{ __('Save') }}" :aria-pressed="saved.toString()">
                <x-icon name="bookmark" size="20" x-bind:class="saved && 'fill-current'" />
            </button>
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn-primary ml-auto text-[14px]"
               @click="window.ngnFetch('/stories/{{ $story->id }}/share', { body: { channel: 'whatsapp' } })">
                <x-icon name="send" size="17" /> {{ __('Share to WhatsApp') }}
            </a>
        </div>
    </div>
</x-layouts.app>
