{{-- Account page (not a social profile): account card, stats, library, preferences. --}}
@php
    $userCats = $user->categories->pluck('id')->all();
    $libraryRows = [
        'saved' => ['icon' => 'bookmark', 'label' => __('Saved stories')],
        'following' => ['icon' => 'users', 'label' => __('Pages you follow')],
        'liked' => ['icon' => 'heart', 'label' => __('Liked')],
        'history' => ['icon' => 'history', 'label' => __('Reading history')],
    ];
@endphp
<x-layouts.app :title="__('Account')">
    <div class="mx-auto max-w-[720px] px-4 py-6 md:px-6 md:py-10"
         x-data="{
            prefs: @js([
                'city_id' => $user->city_id,
                'language' => $user->language,
                'notifications_per_day' => $user->notifications_per_day,
                'text_size' => $user->text_size,
                'theme' => $user->theme,
                'categories' => $userCats,
            ]),
            editing: false,
            topicsOpen: false,
            async save(fields, reload = false) {
                Object.assign(this.prefs, fields);
                const r = await window.ngnFetch('{{ route('account.update') }}', { method: 'PATCH', body: fields });
                if (!r.ok) { $store.toast.show(r.data?.message || @js(__('Something went wrong. Please try again.'))); return; }
                if (reload) { window.location.reload(); return; }
                $store.toast.show(@js(__('Saved')));
            },
            setTextSize(size) { document.documentElement.dataset.textSize = size; this.save({ text_size: size }); },
            toggleDark() { const t = this.prefs.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = t; this.save({ theme: t }); },
            toggleTopic(id) {
                const c = this.prefs.categories;
                this.prefs.categories = c.includes(id) ? c.filter(i => i !== id) : [...c, id];
            },
         }">
        <h1 class="sr-only">{{ __('Account') }}</h1>

        @if (session('status'))
            <p class="mb-4 rounded-[14px] bg-success-tint px-4 py-3 text-[14px] font-semibold text-success-text" role="status">{{ session('status') }}</p>
        @endif

        {{-- Account card --}}
        <section class="card flex items-center gap-4 p-5" aria-label="{{ __('Your account') }}">
            <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-[18px] bg-brand text-[28px] font-black text-white" aria-hidden="true">{{ $user->initial() }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[20px] font-black">{{ $user->name ?: __('Add your name') }}</p>
                <p class="meta">+91 {{ substr($user->phone, 0, 5) }} {{ substr($user->phone, 5) }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if ($user->city)
                        <span class="chip h-7 text-[12px]"><x-icon name="map-pin" size="13" class="mr-1" />{{ $user->city->name() }}</span>
                    @endif
                    <span class="chip h-7 text-[12px]"><x-icon name="languages" size="13" class="mr-1" />{{ $user->language === 'hi' ? 'हिंदी' : 'English' }}</span>
                </div>
            </div>
            <button type="button" class="btn-ghost h-10 min-h-0" @click="editing = true">{{ __('Edit') }}</button>
        </section>

        {{-- Stats --}}
        <div class="mt-4 grid grid-cols-3 gap-3">
            <div class="rounded-[22px] bg-brand p-4 text-white shadow-[var(--shadow-cta)]">
                <x-icon name="flame" size="22" />
                <p class="mt-2 text-[26px] font-black leading-none">{{ $streak }}</p>
                <p class="mt-1 text-[12.5px] font-semibold text-white/85">{{ __('day streak') }}</p>
            </div>
            <div class="card p-4">
                <x-icon name="help-circle" size="22" class="text-brand" />
                <p class="mt-2 text-[26px] font-black leading-none">{{ $quizScore }}</p>
                <p class="mt-1 text-[12.5px] font-semibold text-muted">{{ __("today's quiz") }}</p>
            </div>
            <div class="card p-4">
                <x-icon name="layers" size="22" class="text-brand" />
                <p class="mt-2 text-[26px] font-black leading-none">{{ $readThisWeek }}</p>
                <p class="mt-1 text-[12.5px] font-semibold text-muted">{{ __('read this week') }}</p>
            </div>
        </div>

        {{-- Library --}}
        <h2 class="mb-2 mt-8 px-1 text-[12px] font-extrabold tracking-[0.1em] text-muted-2">{{ __('YOUR LIBRARY') }}</h2>
        <ul class="card">
            @foreach ($libraryRows as $key => $row)
                <li class="{{ $loop->first ? '' : 'border-t border-line' }}">
                    <a href="{{ route('account.library', $key) }}" class="flex h-[60px] items-center gap-3 px-4 text-ink no-underline hover:bg-page hover:text-ink">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon :name="$row['icon']" size="18" /></span>
                        <span class="flex-1 text-[15px] font-bold">{{ $row['label'] }}</span>
                        <span class="text-[14px] font-semibold text-muted-2">{{ $counts[$key] }}</span>
                        <x-icon name="chevron-right" size="18" class="text-muted-2" />
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Preferences --}}
        <h2 class="mb-2 mt-8 px-1 text-[12px] font-extrabold tracking-[0.1em] text-muted-2">{{ __('PREFERENCES') }}</h2>
        <ul class="card">
            {{-- Topics --}}
            <li>
                <button type="button" @click="topicsOpen = !topicsOpen" :aria-expanded="topicsOpen.toString()" class="flex h-[60px] w-full items-center gap-3 px-4 text-left">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon name="layout-grid" size="18" /></span>
                    <span class="flex-1 text-[15px] font-bold">{{ __('Topics') }}</span>
                    <span class="text-[14px] font-semibold text-muted-2" x-text="prefs.categories.length + ' {{ __('selected') }}'"></span>
                    <x-icon name="chevron-down" size="18" class="text-muted-2 transition" x-bind:class="topicsOpen && 'rotate-180'" />
                </button>
                <div x-show="topicsOpen" x-collapse x-cloak class="px-4 pb-4">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categories as $category)
                            <button type="button" @click="toggleTopic({{ $category->id }})" :aria-pressed="prefs.categories.includes({{ $category->id }}).toString()"
                                    :class="prefs.categories.includes({{ $category->id }}) ? 'chip-red' : 'chip hover:bg-line'" class="chip">{{ $category->name() }}</button>
                        @endforeach
                    </div>
                    <button type="button" class="btn-primary mt-3 text-sm" :disabled="!prefs.categories.length" :class="!prefs.categories.length && 'opacity-50'"
                            @click="save({ categories: prefs.categories }); topicsOpen = false">{{ __('Save topics') }}</button>
                </div>
            </li>
            {{-- City --}}
            <li class="border-t border-line">
                <label class="flex h-[60px] items-center gap-3 px-4">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon name="map-pin" size="18" /></span>
                    <span class="flex-1 text-[15px] font-bold">{{ __('City') }}</span>
                    <select class="h-10 rounded-[12px] border-[1.5px] border-line-2 bg-white px-3 text-[14px] font-semibold" x-model.number="prefs.city_id" @change="save({ city_id: prefs.city_id })">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name() }}</option>
                        @endforeach
                    </select>
                </label>
            </li>
            {{-- Language --}}
            <li class="border-t border-line">
                <div class="flex h-[60px] items-center gap-3 px-4">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon name="languages" size="18" /></span>
                    <span class="flex-1 text-[15px] font-bold">{{ __('Language') }}</span>
                    <div class="seg !p-0.5" role="radiogroup" aria-label="{{ __('Language') }}">
                        <button type="button" role="radio" :aria-checked="(prefs.language === 'hi').toString()" @click="save({ language: 'hi' }, true)" :class="prefs.language === 'hi' ? 'seg-item-active' : 'seg-item'" class="!h-9 !px-3.5" lang="hi">हिंदी</button>
                        <button type="button" role="radio" :aria-checked="(prefs.language === 'en').toString()" @click="save({ language: 'en' }, true)" :class="prefs.language === 'en' ? 'seg-item-active' : 'seg-item'" class="!h-9 !px-3.5">English</button>
                    </div>
                </div>
            </li>
            {{-- Notifications --}}
            <li class="border-t border-line">
                <label class="flex h-[60px] items-center gap-3 px-4">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon name="bell" size="18" /></span>
                    <span class="flex-1">
                        <span class="block text-[15px] font-bold">{{ __('Notifications') }}</span>
                        <span class="block text-[12px] text-muted-2">{{ __('Max per day (Android app)') }}</span>
                    </span>
                    <select class="h-10 rounded-[12px] border-[1.5px] border-line-2 bg-white px-3 text-[14px] font-semibold" x-model.number="prefs.notifications_per_day" @change="save({ notifications_per_day: prefs.notifications_per_day })">
                        @foreach ([0, 1, 3, 5, 10] as $n)
                            <option value="{{ $n }}">{{ $n === 0 ? __('Off') : $n }}</option>
                        @endforeach
                    </select>
                </label>
            </li>
            {{-- Text size --}}
            <li class="border-t border-line">
                <div class="flex h-[60px] items-center gap-3 px-4">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon name="type" size="18" /></span>
                    <span class="flex-1 text-[15px] font-bold">{{ __('Text size') }}</span>
                    <div class="seg !p-0.5" role="radiogroup" aria-label="{{ __('Text size') }}">
                        @foreach (['small' => 'A', 'medium' => 'A', 'large' => 'A'] as $size => $glyph)
                            <button type="button" role="radio" :aria-checked="(prefs.text_size === '{{ $size }}').toString()" @click="setTextSize('{{ $size }}')"
                                    :class="prefs.text_size === '{{ $size }}' ? 'seg-item-active' : 'seg-item'" class="!h-9 !w-11 !px-0 {{ ['small' => '!text-[12px]', 'medium' => '!text-[15px]', 'large' => '!text-[18px]'][$size] }}"
                                    aria-label="{{ __(ucfirst($size)) }}">{{ $glyph }}</button>
                        @endforeach
                    </div>
                </div>
            </li>
            {{-- Dark mode --}}
            <li class="border-t border-line">
                <div class="flex h-[60px] items-center gap-3 px-4">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[11px] bg-brand-tint text-brand"><x-icon name="moon" size="18" /></span>
                    <span class="flex-1 text-[15px] font-bold" id="dark-label">{{ __('Dark mode') }}</span>
                    <button type="button" role="switch" :aria-checked="(prefs.theme === 'dark').toString()" aria-labelledby="dark-label" @click="toggleDark()"
                            class="relative h-8 w-[52px] rounded-full transition" :class="prefs.theme === 'dark' ? 'bg-brand' : 'bg-line-2'">
                        <span class="absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-all" :class="prefs.theme === 'dark' ? 'left-[24px]' : 'left-1'"></span>
                    </button>
                </div>
            </li>
        </ul>

        {{-- Bottom links --}}
        <div class="mt-8 flex flex-col gap-3">
            <a href="{{ route('grievance') }}" class="card flex h-[56px] items-center gap-3 px-4 text-ink no-underline hover:text-ink">
                <x-icon name="help-circle" size="19" class="text-muted-2" /><span class="flex-1 text-[15px] font-bold">{{ __('Help and grievance') }}</span><x-icon name="chevron-right" size="18" class="text-muted-2" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="card flex h-[56px] w-full items-center gap-3 px-4 text-left text-[15px] font-bold text-brand">
                    <x-icon name="log-out" size="19" /> {{ __('Log out') }}
                </button>
            </form>
            <form method="POST" action="{{ route('account.destroy') }}" class="text-center"
                  @submit="if (!confirm(@js(__('Delete your account? Your likes, saves, comments and streak will be removed.')))) $event.preventDefault()">
                @csrf @method('DELETE')
                <button type="submit" class="h-11 text-[13px] font-semibold text-muted-2 underline-offset-2 hover:underline">{{ __('Delete account') }}</button>
            </form>
        </div>

        {{-- Edit sheet: name, city, language --}}
        <div x-show="editing" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="editing = false">
            <div class="absolute inset-0 bg-black/55" @click="editing = false"></div>
            <form method="POST" action="{{ route('account.update') }}" role="dialog" aria-modal="true" aria-labelledby="edit-title" x-trap.noscroll="editing"
                  class="sheet md:bottom-auto md:left-1/2 md:right-auto md:top-1/2 md:w-[440px] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-[28px] md:pb-7">
                @csrf @method('PATCH')
                <div class="sheet-handle" aria-hidden="true"></div>
                <div class="flex items-start justify-between">
                    <h2 id="edit-title" class="text-[22px] font-extrabold">{{ __('Edit account') }}</h2>
                    <button type="button" @click="editing = false" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-chip" aria-label="{{ __('Close') }}"><x-icon name="x" size="20" /></button>
                </div>
                <label class="mt-4 block text-[13px] font-bold text-muted" for="edit-name">{{ __('Name') }}</label>
                <input id="edit-name" name="name" value="{{ old('name', $user->name) }}" maxlength="60" class="input mt-1.5" autocomplete="name">
                <label class="mt-4 block text-[13px] font-bold text-muted" for="edit-city">{{ __('City') }}</label>
                <select id="edit-city" name="city_id" class="input mt-1.5">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city->id }}" @selected($user->city_id === $city->id)>{{ $city->name() }} · {{ $city->state }}</option>
                    @endforeach
                </select>
                <label class="mt-4 block text-[13px] font-bold text-muted" for="edit-lang">{{ __('Language') }}</label>
                <select id="edit-lang" name="language" class="input mt-1.5">
                    <option value="en" @selected($user->language === 'en')>English</option>
                    <option value="hi" @selected($user->language === 'hi')>हिंदी</option>
                </select>
                <button type="submit" class="btn-primary-lg mt-6">{{ __('Save') }}</button>
            </form>
        </div>
    </div>
</x-layouts.app>
