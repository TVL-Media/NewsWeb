{{-- /notifications: the header bell. Items come from App\Support\ReaderNotifications; new = after the last visit. --}}
@php
    $tz = config('app.timezone');
    $today = now($tz)->toDateString();
    $groups = $items->groupBy(fn ($i) => $i['at']->copy()->setTimezone($tz)->toDateString() === $today ? 'today' : 'earlier');
    $tone = [
        'breaking' => 'bg-brand text-white',
        'page' => 'bg-brand-tint text-brand',
        'reply' => 'bg-[#EAF2FF] text-[#2563EB]',
        'like' => 'bg-[#FFE8EC] text-[#E11D48]',
        'poll' => 'bg-[#F1ECFF] text-[#6D28D9]',
        'quiz' => 'bg-[#FFF6DA] text-[#B7791F]',
        'streak' => 'bg-[#FFEDE5] text-[#EA580C]',
    ];
@endphp
<x-layouts.app :title="__('Notifications')">
    <div class="mx-auto max-w-[720px] px-4 pb-12 pt-5 md:px-6 md:pt-8">
        <h1 class="text-[26px] font-black leading-tight tracking-[-0.6px] md:text-[30px]">{{ __('Notifications') }}</h1>
        <p class="mt-0.5 text-[13.5px] text-muted">{{ __('Breaking news, your pages, replies and reminders.') }}</p>

        @guest
            <div class="card mt-5 flex items-center gap-3 p-4">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand"><x-icon name="bell" size="19" /></span>
                <p class="min-w-0 flex-1 text-[14px] text-ink-2">{{ __('Log in to see replies to your comments, likes, poll results and new stories from pages you follow.') }}</p>
                <button type="button" class="btn-primary h-10 min-h-0 px-4 text-[13.5px]" @click="$store.auth.show()">{{ __('Log in') }}</button>
            </div>
        @endguest

        @foreach (['today' => __('Today'), 'earlier' => __('Earlier')] as $key => $heading)
            @continue(! $groups->has($key))
            <h2 class="mb-2 mt-6 text-[12px] font-extrabold uppercase tracking-[0.1em] text-muted-2">{{ $heading }}</h2>
            <ul class="card divide-y divide-line overflow-hidden">
                @foreach ($groups[$key] as $item)
                    @php $new = ! $seenAt || $item['at']->getTimestamp() > $seenAt; @endphp
                    <li>
                        <a href="{{ $item['url'] }}" class="flex items-start gap-3 px-4 py-3.5 text-ink no-underline transition hover:bg-chip hover:text-ink {{ $new ? 'bg-brand-tint/40' : '' }}">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $tone[$item['type']] ?? 'bg-chip text-ink' }}">
                                <x-icon :name="$item['icon']" size="19" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[14.5px] font-extrabold leading-snug">{{ $item['title'] }}</span>
                                @if ($item['body'])<span class="mt-0.5 line-clamp-2 block text-[13.5px] leading-snug text-ink-2">{{ $item['body'] }}</span>@endif
                                <span class="mt-1 block text-[12px] text-muted-2">{{ \App\Support\Lang::ago($item['at']) }}</span>
                            </span>
                            @if ($item['image'])
                                <img src="{{ $item['image'] }}" alt="" loading="lazy" width="56" height="56" class="h-14 w-14 shrink-0 rounded-[12px] object-cover">
                            @endif
                            @if ($new)<span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-brand" aria-label="{{ __('New') }}"></span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach

        @if ($items->isEmpty())
            <div class="card mt-6 flex flex-col items-center px-6 py-10 text-center">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-chip text-muted"><x-icon name="bell" size="26" /></span>
                <h2 class="mt-3 text-[19px] font-black">{{ __('You\'re all caught up') }}</h2>
                <p class="mt-1 text-[14px] text-muted">{{ __('Follow pages and join the conversation to get updates here.') }}</p>
                <a href="{{ route('explore') }}" class="btn-primary mt-4 h-10 min-h-0 text-[14px]">{{ __('Explore pages') }}</a>
            </div>
        @endif
    </div>
</x-layouts.app>
