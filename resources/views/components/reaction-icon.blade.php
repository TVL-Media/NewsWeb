{{-- Coloured reaction icons (flat, emoji-style). type: surprising | unfair | need_info | good_news --}}
@props(['type', 'size' => 26])
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @switch($type)
        @case('surprising')
            <circle cx="12" cy="12" r="11" fill="#FFC53D"/>
            <circle cx="12" cy="12" r="11" fill="none" stroke="#E9A400" stroke-width=".8"/>
            <path d="M6.9 7.7 9.4 7M14.6 7l2.5.7" stroke="#6B4100" stroke-width="1.5" stroke-linecap="round"/>
            <circle cx="9" cy="10.4" r="1.35" fill="#5C3700"/>
            <circle cx="15" cy="10.4" r="1.35" fill="#5C3700"/>
            <ellipse cx="12" cy="16.1" rx="2.3" ry="2.9" fill="#5C3700"/>
            @break
        @case('unfair')
            <circle cx="12" cy="12" r="11" fill="#FF6B4A"/>
            <circle cx="12" cy="12" r="11" fill="none" stroke="#E5482A" stroke-width=".8"/>
            <path d="M6.6 8.1 10 9.5M17.4 8.1 14 9.5" stroke="#5E1100" stroke-width="1.7" stroke-linecap="round"/>
            <circle cx="9.2" cy="11.4" r="1.3" fill="#5E1100"/>
            <circle cx="14.8" cy="11.4" r="1.3" fill="#5E1100"/>
            <path d="M8.4 17.3c1-1.4 2.2-2.1 3.6-2.1s2.6.7 3.6 2.1" fill="none" stroke="#5E1100" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('need_info')
            <circle cx="12" cy="12" r="11" fill="#3B82F6"/>
            <circle cx="12" cy="12" r="11" fill="none" stroke="#2563EB" stroke-width=".8"/>
            <path d="M9.4 9.3a2.7 2.7 0 1 1 3.9 2.4c-.9.45-1.3 1-1.3 1.95v.55" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="17.4" r="1.3" fill="#fff"/>
            @break
        @default
            <circle cx="12" cy="12" r="11" fill="#22C55E"/>
            <circle cx="12" cy="12" r="11" fill="none" stroke="#16A34A" stroke-width=".8"/>
            <path d="M7.6 10.3c.7-1.1 2-1.1 2.7 0M13.7 10.3c.7-1.1 2-1.1 2.7 0" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
            <path d="M7.3 13.4c.9 2.5 2.6 3.8 4.7 3.8s3.8-1.3 4.7-3.8Z" fill="#fff"/>
    @endswitch
</svg>
