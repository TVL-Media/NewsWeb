{{--
    Searchable city picker. Shows only the popular-city shortlist by default; typing searches
    the full list (name, state, Hindi name). "Use my current location" picks the nearest city
    by geolocation. Used in: Account → Preferences → City (AJAX, via @city-picked) and the
    Account edit sheet / onboarding step 3 (plain form field, via :input-name).

    Selection is reported two ways, so either integration style works:
    - a `city-picked` event (bubbles, not global — safe with more than one picker on a page)
    - a hidden `<input>` for forms that just submit normally, when $inputName is given
--}}
@props(['cities', 'selected' => null, 'inputName' => null, 'buttonClass' => null])
@php
    $nameField = app()->getLocale() === 'hi' ? 'name_hi' : 'name_en';
    $citiesJson = $cities->map(fn ($c) => [
        'id' => $c->id, 'name_en' => $c->name_en, 'name_hi' => $c->name_hi, 'state' => $c->state,
        'latitude' => $c->latitude, 'longitude' => $c->longitude, 'popular' => (bool) $c->is_popular,
    ])->values();
@endphp
<div class="relative inline-block"
     x-data="cityPicker(@js($citiesJson), {{ $selected ?? 'null' }}, {
        noGeo: @js(__('Location is not available on this device.')),
        noMatch: @js(__('We couldn\'t get your location. Please pick a city.')),
     })"
     @keydown.escape.window="open && close()">
    @if ($inputName)
        <input type="hidden" name="{{ $inputName }}" :value="selectedId ?? ''">
    @endif

    <button type="button" @click="open ? close() : show()" :aria-expanded="open.toString()"
            {{ $attributes->merge(['class' => $buttonClass ?? 'flex h-9 items-center gap-1.5 rounded-[10px] border-[1.5px] border-line-2 bg-white px-2.5 text-[13px] font-semibold text-ink hover:border-ink']) }}>
        <span x-text="selected ? selected.{{ $nameField }} : @js(__('None'))">{{ $selected ? $cities->firstWhere('id', $selected)?->name() : __('None') }}</span>
        <x-icon name="chevron-down" size="14" class="shrink-0 text-muted-2 transition" x-bind:class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-cloak @click.outside="close()"
         x-transition:enter="transition duration-100 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="absolute right-0 z-30 mt-2 w-[290px] origin-top-right rounded-[18px] border border-line bg-white p-3 shadow-[var(--shadow-hero)]"
         role="dialog" aria-label="{{ __('City') }}">
        <label for="city-picker-q-{{ $selected ?? 'new' }}" class="sr-only">{{ __('Search city') }}</label>
        <div class="relative">
            <x-icon name="search" size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-2" />
            <input id="city-picker-q-{{ $selected ?? 'new' }}" x-ref="search" x-model="query" type="search"
                   class="h-10 w-full rounded-[12px] border-[1.5px] border-line-2 bg-white pl-9 pr-3 text-[13px] text-ink outline-none focus:border-ink"
                   placeholder="{{ __('Search city') }}">
        </div>

        <button type="button" @click="locate()" class="mt-2 flex h-10 w-full items-center gap-2 rounded-[12px] bg-brand-tint px-3 text-[13px] font-bold text-brand-dark">
            <x-icon name="navigation" size="15" />
            <span x-text="locating ? @js(__('Finding you…')) : @js(__('Use my current location'))">{{ __('Use my current location') }}</span>
        </button>
        <p x-show="locError" x-text="locError" x-cloak class="mt-1.5 text-[12px] font-semibold text-brand-dark"></p>

        <p x-show="!query.trim()" class="mb-1 mt-3 px-1 text-[11px] font-extrabold tracking-wide text-muted-2">{{ __('POPULAR CITIES') }}</p>
        <ul class="mt-1 max-h-[240px] space-y-0.5 overflow-y-auto" role="listbox" aria-label="{{ __('City') }}">
            <template x-for="c in filtered" :key="c.id">
                <li>
                    <button type="button" @click="pick(c.id)" role="option" :aria-selected="(c.id === selectedId).toString()"
                            class="flex w-full items-center justify-between gap-2 rounded-[10px] px-2.5 py-2 text-left text-[13px] hover:bg-page"
                            :class="c.id === selectedId && '!bg-brand-tint font-bold text-brand'">
                        <span class="min-w-0 truncate"><span x-text="c.name_en"></span> <span class="text-muted-2" x-text="'· ' + c.state"></span></span>
                        <x-icon name="check" size="14" class="shrink-0 text-brand" x-show="c.id === selectedId" x-cloak />
                    </button>
                </li>
            </template>
            <li x-show="!filtered.length" x-cloak class="px-2.5 py-3 text-[13px] text-muted">{{ __('No city found.') }}</li>
        </ul>
    </div>
</div>
