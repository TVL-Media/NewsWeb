@php
    $titles = [
        'saved' => __('Saved stories'),
        'following' => __('Pages you follow'),
        'liked' => __('Liked'),
        'history' => __('Reading history'),
    ];
@endphp
<x-layouts.app :title="$titles[$type]">
    <div class="mx-auto max-w-[720px] px-4 py-6 md:px-6 md:py-10">
        <a href="{{ route('account') }}" class="meta inline-flex items-center gap-1 no-underline"><x-icon name="chevron-left" size="16" /> {{ __('Account') }}</a>
        <h1 class="mt-2 text-[26px] font-black tracking-[-0.6px]">{{ $titles[$type] }}</h1>

        @if ($type === 'following')
            @forelse ($pages as $page)
                <div class="card relative mt-3 flex items-center gap-3 p-3">
                    <img src="{{ $page->avatarUrl() }}" alt="" loading="lazy" class="h-14 w-14 rounded-[14px] object-cover">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('page.show', $page) }}" class="text-[15px] font-extrabold text-ink no-underline after:absolute after:inset-0 hover:text-brand">{{ $page->name() }}</a>
                        <p class="meta">{{ trans_choice(':count follower|:count followers', $page->followers_count, ['count' => number_format($page->followers_count)]) }}</p>
                    </div>
                    <x-follow-button :page="$page" :following="true" />
                </div>
            @empty
                <div class="card mt-4 p-8 text-center text-muted">{{ __('You are not following any pages yet.') }} <a href="{{ route('explore') }}">{{ __('Explore topics') }}</a></div>
            @endforelse
        @else
            <div class="mt-4 space-y-3">
                @forelse ($stories as $story)
                    <x-story-card :story="$story" variant="row" />
                @empty
                    <div class="card p-8 text-center text-muted">{{ __('Nothing here yet.') }}</div>
                @endforelse
            </div>
        @endif
    </div>
</x-layouts.app>
