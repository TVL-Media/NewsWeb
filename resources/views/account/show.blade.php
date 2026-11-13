{{-- Account page (not a social profile). Desktop: account, stats and library on the left; preferences on the right. --}}
@php
    $userCats = $user->categories->pluck('id')->all();
    $libraryRows = [
        'saved' => ['icon' => 'bookmark', 'label' => __('Saved stories')],
        'following' => ['icon' => 'users', 'label' => __('Pages you follow')],
        'liked' => ['icon' => 'heart', 'label' => __('Liked')],
        'history' => ['icon' => 'history', 'label' => __('Reading history')],
    ];
    $rowIcon = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px] bg-brand-tint text-brand';
    $sectionLabel = 'mb-2 px-1 text-[11px] font-extrabold tracking-[0.1em] text-muted-2';
@endphp
<x-layouts.app :title="__('Account')">
    <div class="mx-auto max-w-[1120px] px-4 py-4 md:px-6 md:py-6"
         x-data="{
            prefs: @js([
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
        <nav class="meta mb-2" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('home') }}" class="no-underline">{{ __('Home') }}</a> › <span>{{ __('Account') }}</span>
        </nav>
        <h1 class="sr-only">{{ __('Account') }}</h1>

        @if (session('status'))
            <p class="mb-3 rounded-[12px] bg-success-tint px-4 py-2.5 text-[14px] font-semibold text-success-text" role="status">{{ session('status') }}</p>
        @endif

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-5">
            {{-- ===== Left: account, stats, library ===== --}}
            <div class="space-y-4">
                <section class="card flex items-center gap-3.5 p-4" aria-label="{{ __('Your account') }}">
                    <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-[16px] bg-brand text-[24px] font-black text-white" aria-hidden="true">{{ $user->initial() }}</span>
                    <div class="min-w-0 flex-1">
                        @if ($user->name)
                            <p class="truncate text-[18px] font-black leading-tight">{{ $user->name }}</p>
                        @else
                            <button type="button" @click="editing = true" class="text-[17px] font-black leading-tight text-ink hover:text-brand">{{ __('Add your name') }} <span class="text-brand">+</span></button>
                        @endif
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12.5px] text-muted-2">
                            <span>+91 {{ substr($user->phone, 0, 5) }} {{ substr($user->phone, 5) }}</span>
                            @if ($user->city)
                                <span class="inline-flex items-center gap-1"><x-icon name="map-pin" size="13" />{{ $user->city->name() }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1"><x-icon name="languages" size="13" />{{ $user->language === 'hi' ? 'हिंदी' : 'English' }}</span>
                            <span>{{ __('Member since :date', ['date' => $user->created_at->translatedFormat('M Y')]) }}</span>
                        </div>
                    </div>
                    <button type="button" class="btn-ghost h-9 min-h-0 px-4 text-[13px]" @click="editing = true">{{ __('Edit') }}</button>
                </section>

                {{-- Stats --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="flex items-center gap-3 rounded-[18px] bg-brand px-3.5 py-3 text-white">
                        <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-white/15 sm:inline-flex"><x-icon name="flame" size="19" /></span>
                        <div>
                            <p class="text-[22px] font-black leading-none">{{ $streak }}</p>
                            <p class="mt-1 text-[12px] font-semibold text-white/85">{{ __('day streak') }}</p>
                        </div>
                    </div>
                    <div class="card flex items-center gap-3 !rounded-[18px] px-3.5 py-3">
                        <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-brand-tint text-brand sm:inline-flex"><x-icon name="help-circle" size="19" /></span>
                        <div>
                            <p class="text-[22px] font-black leading-none">{{ $quizScore }}</p>
                            <p class="mt-1 text-[12px] font-semibold text-muted">{{ __("today's quiz") }}</p>
                        </div>
                    </div>
                    <div class="card flex items-center gap-3 !rounded-[18px] px-3.5 py-3">
                        <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-brand-tint text-brand sm:inline-flex"><x-icon name="layers" size="19" /></span>
                        <div>
                            <p class="text-[22px] font-black leading-none">{{ $readThisWeek }}</p>
                            <p class="mt-1 text-[12px] font-semibold text-muted">{{ __('read this week') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Library --}}
                <section aria-labelledby="library-title">
                    <h2 id="library-title" class="{{ $sectionLabel }}">{{ __('YOUR LIBRARY') }}</h2>
                    <ul class="card">
                        @foreach ($libraryRows as $key => $row)
                            <li class="{{ $loop->first ? '' : 'border-t border-line' }}">
                                <a href="{{ route('account.library', $key) }}" class="flex h-[52px] items-center gap-3 px-4 text-ink no-underline hover:bg-page hover:text-ink">
                                    <span class="{{ $rowIcon }}"><x-icon :name="$row['icon']" size="16" /></span>
                                    <span class="flex-1 text-[14.5px] font-bold">{{ $row['label'] }}</span>
                                    <span class="rounded-full bg-chip px-2 py-0.5 text-[12px] font-bold text-ink-2">{{ $counts[$key] }}</span>
                                    <x-icon name="chevron-right" size="17" class="text-muted-2" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            {{-- ===== Right: preferences + links ===== --}}
            <div class="space-y-4">
                <section aria-labelledby="prefs-title">
                    <h2 id="prefs-title" class="{{ $sectionLabel }}">{{ __('PREFERENCES') }}</h2>
                    <ul class="card">
                        {{-- Topics --}}
                        <li>
                            <button type="button" @click="topicsOpen = !topicsOpen" :aria-expanded="topicsOpen.toString()" class="flex h-[52px] w-full items-center gap-3 px-4 text-left">
                                <span class="{{ $rowIcon }}"><x-icon name="layout-grid" size="16" /></span>
                                <span class="flex-1 text-[14.5px] font-bold">{{ __('Topics') }}</span>
                                <span class="text-[13px] font-semibold text-muted-2" x-text="prefs.categories.length + ' {{ __('selected') }}'"></span>
                                <x-icon name="chevron-down" size="17" class="text-muted-2 transition" x-bind:class="topicsOpen && 'rotate-180'" />
                            </button>
                            <div x-show="topicsOpen" x-collapse x-cloak class="px-4 pb-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($categories as $category)
                                        <button type="button" @click="toggleTopic({{ $category->id }})" :aria-pressed="prefs.categories.includes({{ $category->id }}).toString()"
                                                :class="prefs.categories.includes({{ $category->id }}) ? 'chip-red' : 'chip hover:bg-line'" class="chip">{{ $category->name() }}</button>
                                    @endforeach
                                </div>
                                <button type="button" class="btn-primary mt-3 h-9 min-h-0 text-[13px]" :disabled="!prefs.categories.length" :class="!prefs.categories.length && 'opacity-50'"
                                        @click="save({ categories: prefs.categories }); topicsOpen = false">{{ __('Save topics') }}</button>
                            </div>
                        </li>
                        {{-- City: a searchable popover (the full list is too long for a plain dropdown) --}}
                        <li class="border-t border-line" @city-picked="save({ city_id: $event.detail.id })">
                            <div class="flex h-[52px] items-center gap-3 px-4">
                                <span class="{{ $rowIcon }}"><x-icon name="map-pin" size="16" /></span>
                                <span class="flex-1 text-[14.5px] font-bold">{{ __('City') }}</span>
                                <x-city-picker :cities="$cities" :selected="$user->city_id" />
                            </div>
                        </li>
                        {{-- Language --}}
                        <li class="border-t border-line">
                            <div class="flex h-[52px] items-center gap-3 px-4">
                                <span class="{{ $rowIcon }}"><x-icon name="languages" size="16" /></span>
                                <span class="flex-1 text-[14.5px] font-bold">{{ __('Language') }}</span>
                                <div class="seg !gap-0.5 !p-0.5" role="radiogroup" aria-label="{{ __('Language') }}">
                                    <button type="button" role="radio" :aria-checked="(prefs.language === 'hi').toString()" @click="save({ language: 'hi' }, true)" :class="prefs.language === 'hi' ? 'seg-item-active' : 'seg-item'" class="!h-8 !px-3 !text-[13px]" lang="hi">हिंदी</button>
                                    <button type="button" role="radio" :aria-checked="(prefs.language === 'en').toString()" @click="save({ language: 'en' }, true)" :class="prefs.language === 'en' ? 'seg-item-active' : 'seg-item'" class="!h-8 !px-3 !text-[13px]">English</button>
                                </div>
                            </div>
                        </li>
                        {{-- Notifications --}}
                        <li class="border-t border-line">
                            <label class="flex h-[52px] items-center gap-3 px-4">
                                <span class="{{ $rowIcon }}"><x-icon name="bell" size="16" /></span>
                                <span class="flex-1 leading-tight">
                                    <span class="block text-[14.5px] font-bold">{{ __('Notifications') }}</span>
                                    <span class="block text-[11.5px] text-muted-2">{{ __('Max per day (Android app)') }}</span>
                                </span>
                                <select class="h-9 rounded-[10px] border-[1.5px] border-line-2 bg-white px-2.5 text-[13px] font-semibold" x-model.number="prefs.notifications_per_day" @change="save({ notifications_per_day: prefs.notifications_per_day })">
                                    @foreach ([0, 1, 3, 5, 10] as $n)
                                        <option value="{{ $n }}">{{ $n === 0 ? __('Off') : $n }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </li>
                        {{-- Text size --}}
                        <li class="border-t border-line">
                            <div class="flex h-[52px] items-center gap-3 px-4">
                                <span class="{{ $rowIcon }}"><x-icon name="type" size="16" /></span>
                                <span class="flex-1 text-[14.5px] font-bold">{{ __('Text size') }}</span>
                                <div class="seg !gap-0.5 !p-0.5" role="radiogroup" aria-label="{{ __('Text size') }}">
                                    @foreach (['small' => '!text-[11px]', 'medium' => '!text-[14px]', 'large' => '!text-[17px]'] as $size => $cls)
                                        <button type="button" role="radio" :aria-checked="(prefs.text_size === '{{ $size }}').toString()" @click="setTextSize('{{ $size }}')"
                                                :class="prefs.text_size === '{{ $size }}' ? 'seg-item-active' : 'seg-item'" class="!h-8 !w-9 !px-0 {{ $cls }}"
                                                aria-label="{{ __(ucfirst($size)) }}">A</button>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                        {{-- Dark mode --}}
                        <li class="border-t border-line">
                            <div class="flex h-[52px] items-center gap-3 px-4">
                                <span class="{{ $rowIcon }}"><x-icon name="moon" size="16" /></span>
                                <span class="flex-1 text-[14.5px] font-bold" id="dark-label">{{ __('Dark mode') }}</span>
                                <button type="button" role="switch" :aria-checked="(prefs.theme === 'dark').toString()" aria-labelledby="dark-label" @click="toggleDark()"
                                        class="relative h-7 w-12 rounded-full transition" :class="prefs.theme === 'dark' ? 'bg-brand' : 'bg-line-2'">
                                    <span class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all" :class="prefs.theme === 'dark' ? 'left-6' : 'left-1'"></span>
                                </button>
                            </div>
                        </li>
                    </ul>
                </section>

                {{-- Help + log out --}}
                <ul class="card">
                    <li>
                        <a href="{{ route('grievance') }}" class="flex h-[52px] items-center gap-3 px-4 text-ink no-underline hover:bg-page hover:text-ink">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-[10px] bg-chip text-ink-2"><x-icon name="help-circle" size="16" /></span>
                            <span class="flex-1 text-[14.5px] font-bold">{{ __('Help and grievance') }}</span>
                            <x-icon name="chevron-right" size="17" class="text-muted-2" />
                        </a>
                    </li>
                    <li class="border-t border-line">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex h-[52px] w-full items-center gap-3 px-4 text-left text-[14.5px] font-bold text-brand hover:bg-page">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-[10px] bg-brand-tint"><x-icon name="log-out" size="16" /></span>
                                {{ __('Log out') }}
                            </button>
                        </form>
                    </li>
                </ul>
                <form method="POST" action="{{ route('account.destroy') }}" class="px-1"
                      @submit="if (!confirm(@js(__('Delete your account? Your likes, saves, comments and streak will be removed.')))) $event.preventDefault()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[12.5px] font-semibold text-muted-2 underline-offset-2 hover:text-brand hover:underline">{{ __('Delete account') }}</button>
                </form>
            </div>
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
                <label class="mt-4 block text-[13px] font-bold text-muted">{{ __('City') }}</label>
                <x-city-picker :cities="$cities" :selected="$user->city_id" input-name="city_id"
                               button-class="mt-1.5 flex h-[54px] w-full items-center justify-between gap-2 rounded-[14px] border-[1.5px] border-line-2 bg-white px-4 text-base text-ink hover:border-ink" />
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
