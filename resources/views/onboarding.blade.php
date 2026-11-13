@php
    // Every state/UT capital plus major cities (~120); coords come straight from the cities
    // table now, not a hardcoded map, so "Use my current location" works for all of them.
    $cityData = $cities->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name(), 'name_en' => $c->name_en, 'name_hi' => $c->name_hi,
        'state' => $c->state, 'coords' => $c->latitude !== null ? [$c->latitude, $c->longitude] : null,
        'popular' => (bool) $c->is_popular,
    ])->values();
@endphp
<x-layouts.app :title="__('Welcome')" bare no-replay>
    <div class="flex min-h-screen items-stretch justify-center bg-white md:items-center md:bg-page md:px-6 md:py-10">
        <form method="POST" action="{{ route('onboarding.store') }}"
              class="flex w-full flex-col bg-white px-5 pb-6 pt-5 md:max-w-[640px] md:rounded-[28px] md:p-8 md:shadow-[var(--shadow-hero)]"
              x-data="{
                step: 1,
                language: @js(auth()->user()->language ?? app()->getLocale()),
                selected: [],
                cityId: null,
                query: '',
                locating: false, locError: '', detected: null,
                cities: @js($cityData),
                // Biggest/most-recognised first for the default shortlist (cities.php groups by
                // state alphabetically, which would otherwise put Andhra Pradesh ahead of Delhi/Mumbai).
                cityPriority: ['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Surat', 'Jaipur', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Bhopal', 'Patna', 'Chandigarh', 'Kochi', 'Coimbatore', 'Visakhapatnam', 'Guwahati', 'Bhubaneswar', 'Thiruvananthapuram'],
                get chosen() { return this.cities.find(c => c.id === this.cityId) || null; },
                get filtered() {
                    const q = this.query.trim().toLowerCase();
                    if (!q) {
                        const popular = this.cities.filter(c => c.popular)
                            .sort((a, b) => this.cityPriority.indexOf(a.name_en) - this.cityPriority.indexOf(b.name_en));
                        // The picked city (found by location or search) always shows, at the top, even if it isn't popular.
                        return this.chosen && !popular.includes(this.chosen) ? [this.chosen, ...popular] : popular;
                    }
                    return this.cities.filter(c => c.name_en.toLowerCase().includes(q) || c.name_hi.includes(q) || c.state.toLowerCase().includes(q));
                },
                toggle(id) { this.selected.includes(id) ? this.selected = this.selected.filter(i => i !== id) : this.selected.push(id); },
                pickCity(id) { this.cityId = id; this.detected = null; },
                locate() {
                    this.locError = ''; this.detected = null;
                    if (!window.isSecureContext) { this.locError = @js(__('Location only works on a secure (https) connection. Please pick your city.')); return; }
                    if (!navigator.geolocation) { this.locError = @js(__('Location is not available on this device.')); return; }
                    this.locating = true;
                    navigator.geolocation.getCurrentPosition(pos => {
                        const { latitude: la, longitude: lo } = pos.coords;
                        let best = null, bestD = Infinity;
                        this.cities.forEach(c => { if (!c.coords) return; const d = (c.coords[0] - la) ** 2 + (c.coords[1] - lo) ** 2; if (d < bestD) { bestD = d; best = c; } });
                        this.locating = false;
                        if (!best) { this.locError = @js(__('We couldn\'t get your location. Please pick a city.')); return; }
                        this.cityId = best.id; this.detected = best; this.query = '';
                        this.$nextTick(() => document.getElementById('city-' + best.id)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
                    }, err => {
                        this.locating = false;
                        this.locError = err.code === 1
                            ? @js(__('Location is blocked. Allow location for this site in your browser settings, or pick your city.'))
                            : @js(__('We couldn\'t get your location. Please pick a city.'));
                    }, { timeout: 15000, maximumAge: 600000 });
                },
              }">
            @csrf
            <input type="hidden" name="language" :value="language">
            <template x-for="id in selected" :key="id"><input type="hidden" name="categories[]" :value="id"></template>
            <input type="hidden" name="city_id" :value="cityId ?? ''">

            {{-- Progress --}}
            <div class="flex items-center gap-3">
                <button type="button" x-show="step > 1" x-cloak @click="step--" class="-ml-2 inline-flex h-11 w-11 items-center justify-center rounded-full hover:bg-chip" aria-label="{{ __('Back') }}">
                    <x-icon name="chevron-left" size="22" />
                </button>
                <x-logo x-show="step === 1" :size="24" text="text-[18px]" />
                <span class="ml-auto text-[13px] font-bold text-muted-2" x-text="step + ' {{ __('of') }} 3'">1 {{ __('of') }} 3</span>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-1.5" role="progressbar" aria-valuemin="1" aria-valuemax="3" :aria-valuenow="step" aria-label="{{ __('Setup progress') }}">
                @foreach ([1, 2, 3] as $s)
                    <span class="h-1.5 rounded-full" :class="step >= {{ $s }} ? 'bg-brand' : 'bg-line'"></span>
                @endforeach
            </div>

            @if ($errors->any())
                <p class="mt-4 rounded-[14px] bg-brand-tint px-4 py-3 text-[14px] font-semibold text-brand-dark" role="alert">{{ $errors->first() }}</p>
            @endif

            {{-- Step 1: Language --}}
            <section x-show="step === 1" class="mt-7 flex flex-1 flex-col" aria-labelledby="ob-lang">
                <h1 id="ob-lang" class="text-[26px] font-black leading-tight tracking-[-0.6px] md:text-[30px]">{{ __('Choose your language') }}</h1>
                <p class="mt-1.5 text-[15px] text-muted">{{ __('You can change this any time.') }}</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('Language') }}">
                    @foreach (['hi' => ['हिंदी', 'अ', 'Hindi'], 'en' => ['English', 'A', 'अंग्रेज़ी']] as $code => [$label, $glyph, $sub])
                        <button type="button" role="radio" :aria-checked="(language === '{{ $code }}').toString()" @click="language = '{{ $code }}'"
                                class="relative flex items-center gap-4 rounded-[22px] border-2 p-5 text-left transition"
                                :class="language === '{{ $code }}' ? 'border-brand bg-brand-tint' : 'border-line-2 bg-white hover:border-muted-2'">
                            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-[16px] text-[28px] font-black {{ $code === 'hi' ? 'bg-brand text-white' : 'bg-chip text-ink' }}" @if ($code === 'hi') lang="hi" @endif>{{ $glyph }}</span>
                            <span>
                                <span class="block text-[20px] font-black" @if ($code === 'hi') lang="hi" @endif>{{ $label }}</span>
                                <span class="block text-[13px] text-muted-2" @if ($code === 'en') lang="hi" @endif>{{ $sub }}</span>
                            </span>
                            <span x-show="language === '{{ $code }}'" class="check-dot absolute right-4 top-4 !mt-0 !h-6 !w-6"><x-icon name="check" size="14" :stroke="3" /></span>
                        </button>
                    @endforeach
                </div>
                <button type="button" @click="step = 2" class="btn-primary-lg mt-auto pt-0 md:mt-8">{{ __('Continue') }}</button>
            </section>

            {{-- Step 2: Categories --}}
            <section x-show="step === 2" x-cloak class="mt-7 flex flex-1 flex-col" aria-labelledby="ob-cats">
                <h1 id="ob-cats" class="text-[26px] font-black leading-tight tracking-[-0.6px] md:text-[30px]">{{ __('What do you want to read?') }}</h1>
                <p class="mt-1.5 text-[15px] text-muted">{{ __('Pick at least 1 topic.') }}</p>
                <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach ($categories as $category)
                        <button type="button" @click="toggle({{ $category->id }})" :aria-pressed="selected.includes({{ $category->id }}).toString()"
                                class="relative h-[118px] overflow-hidden rounded-[20px] bg-ink text-left ring-inset transition"
                                :class="selected.includes({{ $category->id }}) ? 'ring-[3px] ring-brand' : ''">
                            <img src="{{ $category->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                            <span class="scrim"></span>
                            <span class="absolute inset-x-3 bottom-2.5 text-[15px] font-black text-white">{{ $category->name() }}</span>
                            <span x-show="selected.includes({{ $category->id }})" class="absolute right-2.5 top-2.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-brand text-white shadow">
                                <x-icon name="check" size="15" :stroke="3" />
                            </span>
                        </button>
                    @endforeach
                </div>
                <button type="button" @click="step = 3" :disabled="selected.length === 0" class="btn-primary-lg mt-6 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none">
                    {{ __('Continue') }} <span x-show="selected.length" x-text="'· ' + selected.length + ' {{ __('selected') }}'"></span>
                </button>
            </section>

            {{-- Step 3: City --}}
            <section x-show="step === 3" x-cloak class="mt-7 flex flex-1 flex-col" aria-labelledby="ob-city">
                <h1 id="ob-city" class="text-[26px] font-black leading-tight tracking-[-0.6px] md:text-[30px]">{{ __('Your city') }}</h1>
                <p class="mt-1.5 text-[15px] text-muted">{{ __('Get local news from your city.') }}</p>

                <label for="ob-city-q" class="sr-only">{{ __('Search city') }}</label>
                <div class="relative mt-5">
                    <x-icon name="search" size="19" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-2" />
                    <input id="ob-city-q" x-model="query" type="search" class="input pl-11" placeholder="{{ __('Search city') }}">
                </div>

                <button type="button" @click="locate()" :disabled="locating" class="mt-3 flex h-[52px] w-full items-center gap-3 rounded-[14px] px-4 text-left text-[15px] font-bold"
                        :class="detected ? 'bg-[#E8F7EE] text-[#146C3A]' : 'bg-brand-tint text-brand-dark'">
                    <x-icon name="navigation" size="18" />
                    <span class="flex-1" x-show="!detected" x-text="locating ? @js(__('Finding you…')) : @js(__('Use my current location'))"></span>
                    <span class="flex-1" x-show="detected" x-cloak>
                        <span x-text="detected?.name + ', ' + detected?.state"></span>
                        <span class="block text-[12.5px] font-semibold opacity-80">{{ __('Detected from your location. Tap to try again.') }}</span>
                    </span>
                    <span x-show="detected" x-cloak><x-icon name="check" size="18" :stroke="3" /></span>
                </button>
                <p x-show="locError" x-text="locError" x-cloak class="mt-2 text-[13px] font-semibold text-brand-dark" role="alert"></p>

                <p x-show="!query.trim()" class="mt-6 text-[12px] font-extrabold tracking-[0.1em] text-muted-2">{{ __('POPULAR CITIES') }}</p>
                <ul class="mt-1" role="radiogroup" aria-label="{{ __('City') }}">
                    <template x-for="c in filtered" :key="c.id">
                        <li :id="'city-' + c.id">
                            <button type="button" role="radio" :aria-checked="(cityId === c.id).toString()" @click="pickCity(c.id)"
                                    class="flex w-full items-center gap-3 border-b border-line py-3 text-left"
                                    :class="cityId === c.id && 'bg-brand-tint/50'">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-[12px] bg-chip text-ink-2"><x-icon name="building" size="19" /></span>
                                <span class="flex-1">
                                    <span class="block text-[15.5px] font-extrabold" x-text="c.name"></span>
                                    <span class="block text-[12.5px] text-muted-2" x-text="c.state"></span>
                                </span>
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border-2"
                                      :class="cityId === c.id ? 'border-brand' : 'border-line-2'">
                                    <span class="h-3 w-3 rounded-full bg-brand" x-show="cityId === c.id"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                    <li x-show="!filtered.length" class="py-4 text-[14px] text-muted">{{ __('No city found. You can skip this for now.') }}</li>
                </ul>

                <button type="submit" class="btn-primary-lg mt-6">{{ __('Save and start reading') }}</button>
            </section>
        </form>
    </div>
</x-layouts.app>
