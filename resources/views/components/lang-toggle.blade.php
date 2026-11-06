{{-- हिं / EN outline pill. Posts to /preferences/language (user preference, or a cookie for guests). --}}
@props(['compact' => false])
@php $lang = app()->getLocale(); @endphp
<form method="POST" action="{{ route('preferences.language') }}" class="contents">
    @csrf
    <input type="hidden" name="language" value="{{ $lang === 'hi' ? 'en' : 'hi' }}">
    <button type="submit"
            class="inline-flex items-center gap-1 rounded-full border-[1.5px] border-line-2 bg-white font-bold text-ink hover:bg-chip {{ $compact ? 'h-9 px-3 text-[12.5px]' : 'h-[38px] px-3.5 text-[13px]' }}"
            aria-label="{{ $lang === 'hi' ? 'Switch to English' : 'हिंदी में पढ़ें' }}">
        <span lang="hi" class="{{ $lang === 'hi' ? 'text-brand' : 'text-muted' }}">हिं</span>
        <span class="text-line-2" aria-hidden="true">/</span>
        <span class="{{ $lang === 'en' ? 'text-brand' : 'text-muted' }}">EN</span>
    </button>
</form>
