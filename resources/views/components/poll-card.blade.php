{{--
    Poll card. Open polls show option buttons (.poll-option). After voting, or once closed,
    it shows .poll-result bars with percentages. Guests get the login sheet.
    Voting posts to /polls/{poll}/vote (endpoint arrives in Milestone 4; the UI updates optimistically).
    Expects $poll with `options` loaded. $votedOptionId = the signed-in user's choice, if any.
--}}
@props(['poll', 'votedOptionId' => null, 'showCreate' => true, 'title' => null, 'results' => false])
@php
    $state = [
        'id' => $poll->id,
        'forced' => (bool) $results,
        'open' => $poll->isOpen(),
        'voted' => $votedOptionId,
        'options' => $poll->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'votes' => (int) $o->votes_count])->values(),
    ];
@endphp
<section {{ $attributes->merge(['class' => 'card p-5']) }}
         x-data="{
            ...@js($state),
            busy: false,
            get showResults() { return this.forced || this.voted !== null || !this.open; },
            get total() { return this.options.reduce((s, o) => s + o.votes, 0); },
            get top() { return Math.max(...this.options.map(o => o.votes)); },
            pct(o) { return this.total ? Math.round(o.votes * 100 / this.total) : 0; },
            async vote(option) {
                if (!option || !$store.auth.require('vote', { poll: this.id, option: option.id })) return;
                if (this.voted !== null || !this.open || this.busy) return;
                this.busy = true;
                this.voted = option.id; option.votes++;           // optimistic
                const res = await window.ngnFetch('/polls/' + this.id + '/vote', { body: { option_id: option.id } });
                this.busy = false;
                if (res.data?.options) { this.options = res.data.options; }
                if (res.ok) return;
                if (res.status === 409) { this.voted = res.data.voted; return; }          // already voted
                if (res.status === 422) { this.voted = null; this.open = false; $store.toast.show(res.data?.message); return; } // closed
                this.voted = null; option.votes--;
            },
         }"
         @ngn-replay.window="const p = $event.detail; if (!p.done && p.action === 'vote' && p.payload?.poll === id) { p.done = true; const o = options.find(o => o.id === p.payload.option); if (o) vote(o); }"
         aria-labelledby="poll-{{ $poll->id }}-q">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-[17px] font-black">{{ $title ?? __('Poll') }}</h3>
        @if ($showCreate)
            <button type="button" class="btn-outline"
                    @click="if ($store.auth.require('create_poll')) $dispatch('ngn-create-poll')">
                <x-icon name="plus" size="15" /> {{ __('Create poll') }}
            </button>
        @endif
    </div>

    <p id="poll-{{ $poll->id }}-q" class="mt-3 text-[15px] font-bold leading-snug">{{ $poll->question }}</p>

    {{-- Options --}}
    <div class="mt-3 space-y-2" x-show="!showResults">
        @foreach ($poll->options as $option)
            <button type="button" class="poll-option hover:border-brand hover:bg-brand-tint"
                    @click="vote(options.find(o => o.id === {{ $option->id }}))">
                {{ $option->label }}
            </button>
        @endforeach
    </div>

    {{-- Results --}}
    <div class="mt-3 space-y-2" x-show="showResults" x-cloak>
        <template x-for="o in options" :key="o.id">
            <div class="poll-result">
                <div class="bar" :class="o.votes !== top && '!bg-line'" :style="`width: ${pct(o)}%`"></div>
                <div class="row">
                    <span class="flex items-center gap-1.5">
                        <span x-text="o.label"></span>
                        <template x-if="o.id === voted"><x-icon name="check" size="15" class="text-brand" /></template>
                    </span>
                    <span x-text="pct(o) + '%'"></span>
                </div>
            </div>
        </template>
    </div>

    <p class="meta mt-3">
        <span x-text="total.toLocaleString('en-IN')"></span> {{ __('votes') }}
        @if ($poll->ends_at)
            · {{ $poll->isOpen() ? __('Ends :time', ['time' => $poll->ends_at->diffForHumans()]) : __('Closed') }}
        @endif
    </p>
</section>
