{{-- Topic / city / show page. Deliberately not an Instagram-style profile: banner + news layout, no avatar-and-stats header, no square photo grid. --}}
@php
    $chips = ['latest' => __('Latest'), 'explainers' => __('Explainers')];
    if ($page->pinned_text) { $chips['live'] = __('Live scores'); }
    $chips['polls'] = __('Polls');
    $updated = $updatedAt ? \App\Support\Lang::ago(\Illuminate\Support\Carbon::parse($updatedAt)) : null;
@endphp
<x-layouts.app :title="$page->name()" :description="$page->description" :og-image="$page->coverUrl() ? url($page->coverUrl()) : null">
    <div class="mx-auto max-w-[1280px] px-4 pt-4 md:px-6 md:pt-8">
        {{-- Banner --}}
        <section class="relative h-[250px] overflow-hidden rounded-[26px] bg-ink md:h-[320px] md:rounded-[30px]" aria-labelledby="page-title">
            @if ($page->coverUrl())
                <img src="{{ $page->coverUrl() }}" alt="" fetchpriority="high" width="1600" height="640" class="absolute inset-0 h-full w-full object-cover">
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-black/5"></div>
            <div class="relative flex h-full max-w-[640px] flex-col justify-end p-5 md:justify-center md:p-10">
                @if ($page->desk_label)
                    <p class="text-[12px] font-extrabold tracking-[0.12em] text-brand-pink-text">{{ $page->desk_label }}</p>
                @endif
                <h1 id="page-title" class="mt-1 flex items-center gap-2 text-[34px] font-black leading-none tracking-[-1px] text-white md:text-[52px] md:tracking-[-1.5px]">
                    {{ $page->name() }}
                    @if ($page->verified)<x-icon name="badge-check" size="26" class="text-white" label="{{ __('Verified') }}" />@endif
                </h1>
                @if ($page->description)
                    <p class="mt-2.5 line-clamp-2 text-[14px] leading-snug text-white/85 md:text-[16px]">{{ $page->description }}</p>
                @endif
                <p class="mt-2 text-[13px] text-white/75">
                    {{ trans_choice(':count follower|:count followers', $page->followers_count, ['count' => number_format($page->followers_count)]) }}@if ($updated) · {{ __('Updated :time', ['time' => $updated]) }}@endif
                </p>
                <div class="mt-4 flex gap-2">
                    <x-follow-button :page="$page" :following="$following" variant="light" :label="__('Follow').' +'" />
                    <button type="button" class="btn h-10 min-h-0 rounded-full border-[1.5px] border-white/70 bg-transparent px-5 text-[13.5px] text-white hover:bg-white/10"
                            x-data @click="navigator.share ? navigator.share({ title: @js($page->name()), url: location.href }).catch(() => {}) : navigator.clipboard?.writeText(location.href)">
                        <x-icon name="share" size="16" /> {{ __('Share') }}
                    </button>
                </div>
            </div>
        </section>

        {{-- Chips --}}
        <nav class="no-scrollbar -mx-4 mt-5 flex gap-2 overflow-x-auto px-4 md:mx-0 md:px-0" aria-label="{{ __('Sections') }}">
            @foreach ($chips as $key => $label)
                @php $active = $key === $tab; @endphp
                <a href="{{ $key === 'live' ? '#result' : route('page.show', [$page, 'tab' => $key === 'latest' ? null : $key]) }}"
                   class="{{ $active ? 'chip-red' : 'chip hover:bg-line' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="mt-6 flex flex-wrap gap-6">
            {{-- Main column --}}
            <div class="min-w-0 space-y-5" style="flex: 999 1 640px">
                @if ($page->pinned_text && $tab === 'latest')
                    <section id="result" class="card-dark flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4" aria-label="{{ $page->pinned_label }}">
                        <span class="rounded-[7px] bg-brand px-2 py-1 text-[11px] font-extrabold tracking-wide text-white">{{ $page->pinned_label ?? 'LIVE' }}</span>
                        <p class="min-w-0 flex-1 text-[14.5px] font-bold text-white">{{ $page->pinned_text }}</p>
                        @if ($page->pinnedStory)
                            <a href="{{ route('story.show', $page->pinnedStory) }}" class="text-[13.5px] font-bold text-brand-pink-text no-underline hover:text-white">{{ __('Match report') }} ›</a>
                        @endif
                    </section>
                @endif

                @if ($tab === 'polls')
                    @forelse ($polls as $poll)
                        <x-poll-card :poll="$poll" :show-create="false" :title="__('Poll')" />
                    @empty
                        <div class="card p-8 text-center text-muted">{{ __('No polls on this page yet.') }}</div>
                    @endforelse
                @elseif (! $top)
                    <div class="card p-8 text-center text-muted">{{ __('No stories here yet.') }}</div>
                @else
                    {{-- TOP STORY: horizontal card --}}
                    @php $tv = $top->version(); @endphp
                    <article class="card group relative flex flex-col md:flex-row">
                        <div class="h-[200px] shrink-0 overflow-hidden bg-chip md:h-auto md:min-h-[260px] md:w-[46%]">
                            @if ($top->photoUrl())
                                <img src="{{ $top->photoUrl() }}" alt="" width="640" height="420" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col justify-center p-5 md:p-7">
                            <p class="tag">{{ __('Top story') }}</p>
                            <h2 class="mt-2 text-[21px] font-black leading-[1.2] tracking-[-0.4px] md:text-[26px]">
                                <a href="{{ route('story.show', $top) }}" class="text-ink no-underline after:absolute after:inset-0 hover:text-brand">{{ $tv?->headline }}</a>
                            </h2>
                            <p class="mt-2.5 line-clamp-3 text-[15px] leading-relaxed text-ink-2">{{ $tv?->card_text }}</p>
                            <p class="meta mt-3">{{ $top->ago() }} · {{ trans_choice(':count min read', $top->read_minutes) }}@if ($top->source) · {{ $top->source->name }}@endif</p>
                        </div>
                    </article>

                    @if ($more->isNotEmpty())
                        <h2 class="pt-2 text-[20px] font-black tracking-[-0.4px]">{{ __('More stories') }}</h2>
                        <div class="grid gap-3 md:grid-cols-2 md:gap-4">
                            @foreach ($more as $story)
                                <x-story-card :story="$story" variant="row" thumb="w-[104px] h-[84px]" />
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            {{-- Right rail --}}
            <aside class="min-w-0 grow basis-[300px] space-y-5 md:max-w-[340px]" aria-label="{{ __('More') }}">
                @if ($upcoming->isNotEmpty())
                    <section class="card p-5" aria-labelledby="upcoming-title">
                        <h2 id="upcoming-title" class="text-[18px] font-black">{{ __('Upcoming') }}</h2>
                        <ul class="mt-3 space-y-3">
                            @foreach ($upcoming as $event)
                                @php $d = \Illuminate\Support\Carbon::parse($event['date']); @endphp
                                <li class="flex items-center gap-3">
                                    <span class="flex h-[52px] w-[52px] shrink-0 flex-col items-center justify-center rounded-[14px] bg-brand-tint leading-none">
                                        <span class="text-[20px] font-black text-brand">{{ $d->format('j') }}</span>
                                        <span class="mt-0.5 text-[10.5px] font-bold uppercase text-brand-dark">{{ $d->translatedFormat('M') }}</span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[14px] font-extrabold leading-snug">{{ $event['title'] }}</p>
                                        <p class="meta">{{ $event['subtitle'] ?? '' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($related->isNotEmpty())
                    <section class="card p-5" aria-labelledby="related-topics-title">
                        <h2 id="related-topics-title" class="text-[18px] font-black">{{ __('Related topics') }}</h2>
                        <ul class="mt-2">
                            @foreach ($related as $rp)
                                <li class="relative flex items-center gap-3 py-2.5 {{ $loop->first ? '' : 'border-t border-line' }}">
                                    <img src="{{ $rp->avatarUrl() }}" alt="" loading="lazy" width="40" height="40" class="h-10 w-10 shrink-0 rounded-[12px] object-cover">
                                    <a href="{{ route('page.show', $rp) }}" class="min-w-0 flex-1 truncate text-[14.5px] font-extrabold text-ink no-underline after:absolute after:inset-0 hover:text-brand">{{ $rp->name() }}</a>
                                    <x-follow-button :page="$rp" :following="in_array($rp->id, $followed, true)" />
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.app>
