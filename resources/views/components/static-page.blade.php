{{--
    Wrapper for About, Grievance officer, Privacy, Terms and Community rules.
    Content goes in the slot as plain HTML (h2, h3, p, ul/ol, li, a, strong, table); this wrapper styles it.
--}}
@props(['title', 'description' => null, 'updated' => null])
<x-layouts.app :title="$title" :description="$description">
    <div class="mx-auto max-w-[820px] px-4 py-8 md:px-6 md:py-12">
        <nav aria-label="{{ __('Breadcrumb') }}" class="meta">
            <a href="{{ route('home') }}" class="no-underline">{{ __('Home') }}</a> › <span>{{ $title }}</span>
        </nav>
        <h1 class="mt-3 text-[30px] font-black leading-tight tracking-[-0.8px] md:text-[40px]">{{ $title }}</h1>
        @if ($updated)
            <p class="meta mt-2">{{ __('Last updated') }}: {{ $updated }}</p>
        @endif

        <div class="card mt-6 p-6 md:p-9
                    text-[16px] leading-[1.65] text-ink-2
                    [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-[21px] [&_h2]:font-black [&_h2]:text-ink [&_h2:first-child]:mt-0
                    [&_h3]:mt-6 [&_h3]:mb-1.5 [&_h3]:text-[17px] [&_h3]:font-extrabold [&_h3]:text-ink
                    [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:mt-1.5
                    [&_strong]:text-ink [&_a]:font-semibold
                    [&_table]:mt-4 [&_table]:w-full [&_table]:text-[15px] [&_td]:border-t [&_td]:border-line [&_td]:py-2.5 [&_td]:pr-4 [&_td]:align-top [&_th]:py-2 [&_th]:pr-4 [&_th]:text-left [&_th]:font-extrabold [&_th]:text-ink">
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
