{{--
    Follow / Following toggle for a page. Guests get the login sheet. Optimistic; posts to
    /pages/{page}/follow (POST follow, DELETE unfollow), wired to the backend in Milestone 3.
    variant: outline (red outline pill) | solid (red filled) | light (white outline, on dark banners)
--}}
@props(['page', 'following' => false, 'variant' => 'outline', 'label' => null])
@php
    $base = match ($variant) {
        'solid' => 'btn-primary h-10 min-h-0 px-5 text-[13.5px]',
        'light' => 'btn rounded-full h-10 min-h-0 px-5 text-[13.5px] bg-brand text-white hover:bg-brand-dark',
        default => 'btn-outline',
    };
@endphp
<button type="button"
        x-data="{
            following: @js((bool) $following),
            async toggle() {
                if (!$store.auth.require('follow', { page: @js($page->slug) })) return;
                this.following = !this.following;
                const res = await window.ngnFetch('/pages/{{ $page->slug }}/follow', { method: this.following ? 'POST' : 'DELETE' });
                if (!res.ok) this.following = !this.following;
            },
        }"
        @click.stop.prevent="toggle()"
        @ngn-replay.window="const p = $event.detail; if (!p.done && p.action === 'follow' && p.payload?.page === @js($page->slug)) { p.done = true; if (!following) toggle(); }"
        :aria-pressed="following.toString()"
        :class="following && '!bg-white !text-ink !border-line-2'"
        {{ $attributes->merge(['class' => $base.' relative z-10']) }}
        aria-label="{{ __('Follow') }} {{ $page->name() }}">
    <span x-show="!following">{{ $label ?? __('Follow') }}</span>
    <span x-show="following" x-cloak>{{ __('Following') }}</span>
</button>
