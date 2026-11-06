{{-- Pages to follow (rail card): 44px rounded-square avatar, name, one-line description, outline Follow pill. --}}
@props(['pages', 'followed' => []])
<section {{ $attributes->merge(['class' => 'card p-5']) }} aria-labelledby="ptf-title">
    <h2 id="ptf-title" class="text-[18px] font-black">{{ __('Pages to follow') }}</h2>
    <ul class="mt-2">
        @foreach ($pages as $page)
            <li class="relative flex items-center gap-3 py-2.5 {{ $loop->first ? '' : 'border-t border-line' }}">
                <img src="{{ $page->avatarUrl() }}" alt="" loading="lazy" width="44" height="44" class="h-11 w-11 shrink-0 rounded-[12px] object-cover">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('page.show', $page) }}" class="flex items-center gap-1 text-[14.5px] font-extrabold text-ink no-underline after:absolute after:inset-0 hover:text-brand">
                        {{ $page->name() }}
                        @if ($page->verified)<x-icon name="badge-check" size="15" class="text-brand" label="{{ __('Verified') }}" />@endif
                    </a>
                    <p class="truncate text-[12.5px] text-muted-2">{{ $page->description }}</p>
                </div>
                <x-follow-button :page="$page" :following="in_array($page->id, $followed)" />
            </li>
        @endforeach
    </ul>
</section>
