@php
    /** @var array $reels  ReelResource arrays; @var ?App\Models\Reel $start */
    $first = $reels[0] ?? null;
    $messages = [
        'saved' => __('Saved'),
        'unsaved' => __('Removed from saved'),
        'failed' => __('Something went wrong. Please try again.'),
    ];
    $iconBtn = 'inline-flex h-[46px] w-[46px] items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-sm transition hover:bg-black/55 md:bg-white/10 md:hover:bg-white/20';
    $count = 'mt-1 text-[12px] font-bold text-white [text-shadow:0_1px_2px_rgba(0,0,0,.5)]';
@endphp
<x-layouts.app
    :title="$start ? $start->title : __('Reels')"
    :description="$description ?? __('Short video news from NextGenNews, in Hindi and English.')"
    active="reels" immersive
    :canonical="$start ? route('reels.show', $start) : route('reels')"
    :og-image="$start ? $start->thumbnailUrl() : null"
    :og-type="$start ? 'video.other' : 'website'">

    @if ($start && $first)
        <x-slot:head>
            <meta property="og:video" content="{{ $first['video_720_url'] }}">
            <meta property="og:video:secure_url" content="{{ $first['video_720_url'] }}">
            <meta property="og:video:type" content="video/mp4">
            <meta property="og:video:width" content="720">
            <meta property="og:video:height" content="1280">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $start->title,
                'description' => $description,
                'thumbnailUrl' => [$first['thumbnail_url']],
                'uploadDate' => $start->published_at?->toIso8601String(),
                'duration' => 'PT'.(int) $start->duration_seconds.'S',
                'contentUrl' => $first['video_720_url'],
                'embedUrl' => route('reels.show', $start),
                'inLanguage' => $start->language,
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'NextGenNews',
                    'logo' => ['@type' => 'ImageObject', 'url' => asset('images/logo.svg')],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        </x-slot:head>
    @endif

    <h1 class="sr-only">{{ $start ? $start->title : __('Reels') }}</h1>

    <div x-data="reelFeed(@js($reels), @js($next), @js($messages))"
         @keydown.window="key($event)"
         @ngn-replay.window="replay($event.detail)"
         class="relative text-white">

        <div x-ref="scroller"
             class="no-scrollbar h-[calc(100dvh-66px-env(safe-area-inset-bottom))] snap-y snap-mandatory overflow-y-scroll overscroll-contain md:h-[calc(100dvh-65px)]">

            <template x-for="(r, i) in reels" :key="r.id">
                <section :data-index="i" data-reel-index :lang="r.language" :aria-label="r.title"
                         class="relative flex h-full snap-start snap-always items-center justify-center md:gap-5 md:py-5">

                    {{-- Player --}}
                    <div class="relative h-full w-full overflow-hidden bg-black md:aspect-[9/16] md:h-auto md:w-[min(420px,calc((100dvh-105px)*9/16))] md:rounded-[24px] md:shadow-[0_20px_60px_rgba(0,0,0,.5)]">
                        <video :data-i="i" :poster="r.thumbnail_url" :src="r.near ? src(r) : null"
                               :preload="Math.abs(i - current) <= 1 ? 'auto' : 'metadata'"
                               @timeupdate="tick(i, $event.target)"
                               playsinline webkit-playsinline muted loop disablepictureinpicture
                               class="h-full w-full object-cover" :aria-label="r.title"></video>

                        {{-- Tap to pause / play --}}
                        <button type="button" class="absolute inset-0 z-[1] cursor-pointer" @click="togglePlay(i)"
                                :aria-label="current === i && paused ? @js(__('Play')) : @js(__('Pause'))"></button>

                        <span x-show="current === i && (paused || flash)" x-cloak x-transition.opacity
                              class="pointer-events-none absolute left-1/2 top-1/2 z-[2] inline-flex h-[72px] w-[72px] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/45">
                            <span x-show="paused"><x-icon name="play" size="34" class="fill-current" /></span>
                            <span x-show="!paused"><x-icon name="pause" size="34" class="fill-current" /></span>
                        </span>

                        {{-- Top: sound --}}
                        <button type="button" @click="toggleMute()" class="absolute right-3 top-[calc(12px+env(safe-area-inset-top))] z-[3] inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-sm hover:bg-black/60 md:top-3"
                                :aria-label="muted ? @js(__('Turn sound on')) : @js(__('Turn sound off'))" :aria-pressed="(!muted).toString()">
                            <span x-show="muted"><x-icon name="volume-x" size="19" /></span>
                            <span x-show="!muted" x-cloak><x-icon name="volume-2" size="19" /></span>
                        </button>

                        {{-- Bottom-left: page, title, story link --}}
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-[2] bg-gradient-to-t from-black/85 via-black/45 to-transparent px-4 pb-5 pr-[78px] pt-24 md:pr-4">
                            <div class="pointer-events-auto flex items-center gap-2.5" x-show="r.page">
                                <a :href="r.page && ('/page/' + r.page.slug)" class="shrink-0" tabindex="-1" aria-hidden="true">
                                    <img :src="r.page?.avatar" alt="" width="36" height="36" class="h-9 w-9 rounded-full border border-white/40 bg-white/20 object-cover">
                                </a>
                                <a :href="r.page && ('/page/' + r.page.slug)" class="truncate text-[14.5px] font-extrabold text-white no-underline hover:text-white hover:underline" x-text="r.page?.name"></a>
                                <button type="button" @click="follow(r)" :aria-pressed="(!!r.page?.is_following).toString()"
                                        class="h-7 shrink-0 rounded-full border px-3 text-[12.5px] font-bold transition"
                                        :class="r.page?.is_following ? 'border-white/40 bg-white/15 text-white' : 'border-white bg-white text-ink hover:bg-white/90'">
                                    <span x-text="r.page?.is_following ? @js(__('Following')) : @js(__('Follow').' +')"></span>
                                </button>
                            </div>
                            <p class="mt-2.5 line-clamp-2 text-[15.5px] font-bold leading-snug [text-shadow:0_1px_3px_rgba(0,0,0,.5)]" x-text="r.title"></p>
                            <a x-show="r.story_url" :href="r.story_url" class="pointer-events-auto mt-1.5 inline-flex items-center gap-0.5 text-[13.5px] font-semibold text-white/90 no-underline hover:text-white">
                                {{ __('Read full story') }} <x-icon name="chevron-right" size="15" />
                            </a>
                        </div>

                        {{-- Progress --}}
                        <div class="absolute inset-x-0 bottom-0 z-[3] h-[3px] bg-white/20" aria-hidden="true">
                            <div class="h-full bg-brand" :style="'width:' + (current === i ? progress * 100 : 0) + '%'"></div>
                        </div>
                    </div>

                    {{-- Actions: over the video on mobile, beside it on desktop --}}
                    <div class="absolute bottom-[104px] right-2.5 z-[4] flex flex-col items-center gap-3.5 md:static md:mb-6 md:self-end">
                        <div class="flex flex-col items-center">
                            <button type="button" @click="like(r)" class="{{ $iconBtn }}" :aria-pressed="r.is_liked.toString()" aria-label="{{ __('Like') }}">
                                <x-icon name="heart" size="23" x-bind:class="r.is_liked ? 'fill-current text-brand' : ''" />
                            </button>
                            <span class="{{ $count }}" x-text="fmt(r.counts.likes)"></span>
                        </div>
                        <div class="flex flex-col items-center">
                            <button type="button" @click="openComments(r)" class="{{ $iconBtn }}" aria-label="{{ __('Comments') }}">
                                <x-icon name="message-circle" size="23" />
                            </button>
                            <span class="{{ $count }}" x-text="fmt(r.counts.comments)"></span>
                        </div>
                        <div class="flex flex-col items-center">
                            <button type="button" @click="openShare(r)" class="{{ $iconBtn }}" aria-label="{{ __('Share') }}">
                                <x-icon name="send" size="21" />
                            </button>
                            <span class="{{ $count }}" x-text="fmt(r.counts.shares)"></span>
                        </div>
                        <div class="flex flex-col items-center">
                            <button type="button" @click="save(r)" class="{{ $iconBtn }}" :aria-pressed="r.is_saved.toString()" aria-label="{{ __('Save') }}">
                                <x-icon name="bookmark" size="22" x-bind:class="r.is_saved ? 'fill-current' : ''" />
                            </button>
                            <span class="{{ $count }}">{{ __('Save') }}</span>
                        </div>
                        <div class="flex flex-col items-center" x-show="r.other_language_reel_id">
                            <button type="button" @click="switchLang(i)" class="{{ $iconBtn }} text-[14px] font-black"
                                    :aria-label="r.language === 'en' ? 'हिंदी में देखें' : 'Watch in English'">
                                <span x-show="r.language === 'en'" lang="hi">हिं</span>
                                <span x-show="r.language === 'hi'">EN</span>
                            </button>
                            <span class="{{ $count }}" x-text="r.language === 'en' ? 'हिंदी' : 'English'"></span>
                        </div>
                    </div>
                </section>
            </template>

            {{-- Loading skeleton --}}
            <section x-show="loading" x-cloak class="flex h-full snap-start items-center justify-center md:py-5" aria-busy="true" aria-label="{{ __('Loading') }}">
                <div class="relative h-full w-full animate-pulse bg-white/[.06] md:aspect-[9/16] md:h-auto md:w-[min(420px,calc((100dvh-105px)*9/16))] md:rounded-[24px]">
                    <div class="absolute bottom-6 left-4 right-20 space-y-2.5">
                        <div class="flex items-center gap-2.5"><span class="h-9 w-9 rounded-full bg-white/10"></span><span class="h-3.5 w-28 rounded bg-white/10"></span></div>
                        <div class="h-4 w-full rounded bg-white/10"></div>
                        <div class="h-4 w-2/3 rounded bg-white/10"></div>
                    </div>
                </div>
            </section>

            {{-- Error --}}
            <section x-show="error" x-cloak class="flex h-full snap-start flex-col items-center justify-center gap-4 px-8 text-center" role="alert">
                <p class="text-[17px] font-bold">{{ __('Couldn\'t load more reels.') }}</p>
                <button type="button" @click="retry()" class="btn rounded-full bg-white px-5 text-ink hover:bg-white/90">
                    <x-icon name="rotate-cw" size="17" /> {{ __('Retry') }}
                </button>
            </section>

            {{-- End --}}
            <section x-ref="end" x-show="!next && !loading && !error" class="flex h-full snap-start flex-col items-center justify-center gap-3 px-8 text-center">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand"><x-icon name="check" size="28" :stroke="3" /></span>
                <p class="text-[19px] font-black" x-show="reels.length">{{ __('You\'re all caught up') }} ✓</p>
                <p class="text-[19px] font-black" x-show="!reels.length" x-cloak>{{ __('No reels yet.') }}</p>
                <p class="text-[14px] text-white/70">{{ __('Catch up on the day\'s stories in 60 words.') }}</p>
                <a href="{{ route('home') }}" class="btn mt-2 rounded-full bg-white px-5 text-ink no-underline hover:bg-white/90 hover:text-ink">{{ __('Back to news') }}</a>
            </section>
        </div>

        {{-- Desktop: up / down --}}
        <div class="fixed right-8 top-1/2 z-20 hidden -translate-y-1/2 flex-col gap-3 md:flex">
            <button type="button" @click="go(-1)" :disabled="current <= 0" class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 disabled:opacity-30" aria-label="{{ __('Previous reel') }}">
                <x-icon name="chevron-up" size="24" />
            </button>
            <button type="button" @click="go(1)" class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="{{ __('Next reel') }}">
                <x-icon name="chevron-down" size="24" />
            </button>
        </div>

        {{-- Comments: bottom sheet (mobile) / side panel (desktop) --}}
        <div x-show="commentsOpen" x-cloak class="fixed inset-0 z-50 text-ink" @keydown.escape.window="commentsOpen = false">
            <div class="absolute inset-0 bg-black/45" @click="commentsOpen = false" x-show="commentsOpen" x-transition.opacity></div>
            <aside role="dialog" aria-modal="true" aria-labelledby="reel-comments-title"
                   x-show="commentsOpen" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full md:translate-y-0 md:translate-x-full"
                   class="absolute inset-x-0 bottom-0 flex h-[72dvh] flex-col rounded-t-[24px] bg-white md:inset-y-0 md:left-auto md:right-0 md:h-auto md:w-[400px] md:rounded-none md:rounded-l-[24px]">
                <div class="mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-line md:hidden" aria-hidden="true"></div>
                <header class="flex items-center gap-3 border-b border-line px-5 py-3.5">
                    <h2 id="reel-comments-title" class="flex-1 text-[17px] font-black">
                        {{ __('Comments') }} · <span x-text="cReel ? cReel.counts.comments.toLocaleString('en-IN') : 0"></span>
                    </h2>
                    <button type="button" @click="commentsOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-full hover:bg-chip" aria-label="{{ __('Close') }}">
                        <x-icon name="x" size="20" />
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-5">
                    <p x-show="cLoading && !comments.length" class="py-6 text-[14px] text-muted">{{ __('Loading…') }}</p>
                    <p x-show="!cLoading && !comments.length" class="py-6 text-[14px] text-muted">{{ __('No comments yet. Start the conversation.') }}</p>
                    <ul>
                        <template x-for="c in comments" :key="c.id">
                            <li class="border-b border-line py-4">
                                <div class="flex gap-3">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-brand text-sm font-extrabold text-white" aria-hidden="true" x-text="c.initial"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[14px]"><strong class="font-extrabold" x-text="c.name"></strong> <span class="meta" x-text="'· ' + c.ago"></span></p>
                                        <p class="mt-1 whitespace-pre-line break-words text-[15px] leading-relaxed text-ink-2" x-text="c.body"></p>
                                        <div class="mt-1.5 flex gap-4 text-[12.5px] font-bold text-muted-2">
                                            <button type="button" class="hover:text-brand" :class="c.liked && 'text-brand'" @click="likeComment(c)" :aria-pressed="c.liked.toString()">{{ __('Like') }}<span x-show="c.likes" x-text="' · ' + c.likes"></span></button>
                                            <button type="button" class="hover:text-brand" @click="reply(c)">{{ __('Reply') }}</button>
                                            <button type="button" class="hover:text-brand" @click="report('comment', c.id)">{{ __('Report') }}</button>
                                        </div>
                                        <ul class="mt-2 space-y-3 border-l-2 border-line pl-4" x-show="c.replies.length">
                                            <template x-for="rp in c.replies" :key="rp.id">
                                                <li>
                                                    <p class="text-[13.5px]"><strong class="font-extrabold" x-text="rp.name"></strong> <span class="meta" x-text="'· ' + rp.ago"></span></p>
                                                    <p class="mt-0.5 whitespace-pre-line break-words text-[14.5px] text-ink-2" x-text="rp.body"></p>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <button type="button" x-show="cNext" @click="loadComments(cNext)" class="my-4 text-[14px] font-bold text-brand">{{ __('Load more') }}</button>
                </div>

                <form class="border-t border-line px-4 pb-[calc(12px+env(safe-area-inset-bottom))] pt-3" @submit.prevent="postComment()">
                    <p x-show="replyTo" x-cloak class="mb-2 flex items-center gap-2 text-[13px] font-semibold text-muted">
                        {{ __('Replying to') }} <span class="text-ink" x-text="replyTo?.name"></span>
                        <button type="button" class="text-brand" @click="replyTo = null">{{ __('Cancel') }}</button>
                    </p>
                    @auth
                        <div class="flex gap-2">
                            <label for="reel-comment" class="sr-only">{{ __('Add a comment') }}</label>
                            <input id="reel-comment" x-ref="commentInput" x-model="cBody" class="input h-12 flex-1" placeholder="{{ __('Add a comment…') }}" maxlength="1000" autocomplete="off">
                            <button type="submit" class="btn-primary" :disabled="cBusy || cBody.trim().length < 2" :class="(cBusy || cBody.trim().length < 2) && 'opacity-60'">{{ __('Post') }}</button>
                        </div>
                    @else
                        <button type="button" @click="$store.auth.require('reel_comment', { id: cReel?.id })" class="input flex h-12 w-full items-center text-left text-muted-2 hover:border-ink">
                            {{ __('Log in to comment…') }}
                        </button>
                    @endauth
                    <p x-show="cError" x-text="cError" x-cloak class="mt-2 text-[13px] font-semibold text-brand-dark" role="alert"></p>
                </form>
            </aside>
        </div>

        {{-- Share --}}
        <div x-show="shareOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center text-ink md:items-center" @keydown.escape.window="shareOpen = false">
            <div class="absolute inset-0 bg-black/45" @click="shareOpen = false"></div>
            <div role="dialog" aria-modal="true" aria-labelledby="reel-share-title"
                 class="relative w-full rounded-t-[24px] bg-white px-5 pb-[calc(20px+env(safe-area-inset-bottom))] pt-4 md:max-w-[400px] md:rounded-[24px] md:pb-5">
                <div class="flex items-center">
                    <h2 id="reel-share-title" class="flex-1 text-[17px] font-black">{{ __('Share reel') }}</h2>
                    <button type="button" @click="shareOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-full hover:bg-chip" aria-label="{{ __('Close') }}"><x-icon name="x" size="20" /></button>
                </div>
                <p class="mt-1 line-clamp-2 text-[14px] text-muted" x-text="sReel?.title"></p>
                <div class="mt-4 grid gap-2.5">
                    <a :href="whatsapp" target="_blank" rel="noopener" @click="countShare('whatsapp')" class="btn rounded-[14px] bg-[#25D366] text-white no-underline hover:bg-[#1fb457] hover:text-white">
                        <x-icon name="message-circle" size="19" /> WhatsApp
                    </a>
                    <button type="button" @click="copyLink()" class="btn rounded-[14px] border-[1.5px] border-line-2 bg-white text-ink hover:bg-chip">
                        <x-icon name="link" size="18" /> <span x-text="copied ? @js(__('Link copied')) : @js(__('Copy link'))"></span>
                    </button>
                    <button type="button" x-show="canNativeShare" @click="nativeShare()" class="btn rounded-[14px] border-[1.5px] border-line-2 bg-white text-ink hover:bg-chip">
                        <x-icon name="share" size="18" /> {{ __('More options') }}
                    </button>
                </div>
                <button type="button" @click="shareOpen = false; report('reel', sReel.id)" class="mt-4 text-[13px] font-semibold text-muted hover:text-brand">
                    <x-icon name="flag" size="14" class="inline" /> {{ __('Report this reel') }}
                </button>
            </div>
        </div>
    </div>
</x-layouts.app>
