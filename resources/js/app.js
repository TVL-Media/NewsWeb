// Livewire 3 ships Alpine (with the focus, collapse, intersect and persist plugins),
// so Alpine is taken from Livewire's ESM build instead of being installed separately.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

const PENDING_KEY = 'ngn.pendingAction';

const storage = {
    get(key) {
        try { return JSON.parse(sessionStorage.getItem(key)); } catch { return null; }
    },
    set(key, value) {
        try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* private mode */ }
    },
    remove(key) {
        try { sessionStorage.removeItem(key); } catch { /* private mode */ }
    },
};

/** fetch() with CSRF + JSON. Resolves to { ok, status, data }. */
window.ngnFetch = async (url, { method = 'POST', body = null } = {}) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : null,
    });
    let data = null;
    try { data = await res.json(); } catch { /* empty body */ }
    // Restricted or suspended in the admin panel: explain it, whatever the reader tried.
    if (res.status === 403 && data?.account_status) {
        Alpine.store('toast')?.show(data.message);
        if (data.account_status === 'suspended') setTimeout(() => window.location.assign('/'), 1800);
    }
    return { ok: res.ok, status: res.status, data };
};

document.addEventListener('alpine:init', () => {
    /**
     * Guests can read everything. Like, comment, share, save, follow, vote, start quiz,
     * create poll and For You call `$store.auth.require(action, payload)`: it returns
     * true for signed-in users; for guests it remembers the action and opens the login
     * sheet, and the action is replayed right after login (see `takePending`).
     */
    Alpine.store('auth', {
        loggedIn: Boolean(window.NGN?.loggedIn),
        open: false,

        require(action, payload = {}) {
            if (this.loggedIn) return true;
            storage.set(PENDING_KEY, { action, payload, at: Date.now() });
            this.open = true;
            return false;
        },
        show() { this.open = true; },
        close() { this.open = false; },

        /** The action a guest tried before logging in (valid for 30 minutes). */
        takePending() {
            const pending = storage.get(PENDING_KEY);
            storage.remove(PENDING_KEY);
            return pending && Date.now() - pending.at < 30 * 60 * 1000 ? pending : null;
        },
    });

    /** Small toast at the bottom: $store.toast.show('Saved'). */
    Alpine.store('toast', {
        message: '',
        timer: null,
        show(message) {
            this.message = message;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.message = ''; }, 2600);
        },
    });

    /** Report sheet: $dispatch('ngn-report', { type: 'story'|'comment'|'poll', id }). */
    Alpine.data('reportSheet', () => ({
        open: false, target: null, reason: '', busy: false,
        show(detail) { this.target = detail; this.reason = ''; this.open = true; },
        async submit() {
            if (!this.reason || this.busy) return;
            this.busy = true;
            const { ok } = await window.ngnFetch('/reports', { body: { target_type: this.target.type, target_id: this.target.id, reason: this.reason } });
            this.busy = false;
            this.open = false;
            Alpine.store('toast').show(ok ? this.$el.dataset.done : this.$el.dataset.failed);
        },
    }));

    /** Share sheet: $dispatch('ngn-share', { id, url, title, lang }). Shows the 1080×1350 image + link. */
    Alpine.data('shareSheet', () => ({
        open: false, busy: false, image: null, url: '', title: '', copied: false,
        async show(detail) {
            this.open = true; this.busy = true; this.image = null; this.copied = false;
            this.url = detail.url; this.title = detail.title;
            const { ok, data } = await window.ngnFetch(`/stories/${detail.id}/share`, { body: { channel: 'image', lang: detail.lang } });
            this.busy = false;
            if (ok) { this.image = data.image_url; this.url = data.url; }
        },
        get whatsapp() { return 'https://wa.me/?text=' + encodeURIComponent(this.title + ' ' + this.url); },
        async copy() {
            try { await navigator.clipboard.writeText(this.url); this.copied = true; } catch { /* no clipboard */ }
        },
        async nativeShare() {
            try {
                const blob = await (await fetch(this.image)).blob();
                const file = new File([blob], 'nextgennews.jpg', { type: blob.type });
                if (navigator.canShare?.({ files: [file] })) { await navigator.share({ files: [file], title: this.title, url: this.url }); return; }
                await navigator.share({ title: this.title, url: this.url });
            } catch { /* cancelled */ }
        },
        get canNativeShare() { return typeof navigator.share === 'function'; },
    }));

    /**
     * Searchable city picker (onboarding step 3, Account → Preferences → City, the account
     * edit sheet). Shows only the popular-city shortlist until the reader types; "Use my
     * current location" finds the nearest of ALL cities by straight-line distance.
     * `messages` carries the few translatable strings, since this factory is compiled once
     * for both languages — see <x-city-picker>, which passes them in via @js(__(...)).
     */
    // Biggest/most-recognised first, for the default (no search) shortlist — the city data file
    // groups cities by state alphabetically, which puts Andhra Pradesh ahead of Delhi/Mumbai.
    const CITY_PRIORITY = [
        'Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad',
        'Surat', 'Jaipur', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Bhopal', 'Patna', 'Chandigarh',
        'Kochi', 'Coimbatore', 'Visakhapatnam', 'Guwahati', 'Bhubaneswar', 'Thiruvananthapuram',
    ];

    Alpine.data('cityPicker', (cities, selectedId, messages) => ({
        cities,
        selectedId,
        open: false,
        query: '',
        locating: false,
        locError: '',
        get selected() { return this.cities.find((c) => c.id === this.selectedId) || null; },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) {
                return this.cities.filter((c) => c.popular)
                    .sort((a, b) => CITY_PRIORITY.indexOf(a.name_en) - CITY_PRIORITY.indexOf(b.name_en));
            }
            return this.cities.filter((c) => c.name_en.toLowerCase().includes(q) || c.name_hi.includes(q) || c.state.toLowerCase().includes(q));
        },
        show() {
            this.open = true;
            this.query = '';
            this.locError = '';
            this.$nextTick(() => this.$refs.search?.focus());
        },
        close() { this.open = false; },
        pick(id) {
            this.selectedId = id;
            this.open = false;
            this.$dispatch('city-picked', { id });
        },
        locate() {
            this.locError = '';
            if (!window.isSecureContext) { this.locError = messages.insecure; return; }
            if (!navigator.geolocation) { this.locError = messages.noGeo; return; }
            this.locating = true;
            navigator.geolocation.getCurrentPosition((pos) => {
                const { latitude: la, longitude: lo } = pos.coords;
                let best = null;
                let bestD = Infinity;
                this.cities.forEach((c) => {
                    if (c.latitude == null || c.longitude == null) return;
                    const d = (c.latitude - la) ** 2 + (c.longitude - lo) ** 2;
                    if (d < bestD) { bestD = d; best = c; }
                });
                this.locating = false;
                if (best) this.pick(best.id);
            }, (err) => {
                this.locating = false;
                this.locError = err.code === 1 ? messages.denied : messages.noMatch;
            }, { timeout: 15000, maximumAge: 600000 });
        },
    }));

    /**
     * Reels player (/reels, /reels/{slug}): one reel per screen with CSS scroll-snap.
     * IntersectionObserver plays the reel on screen and pauses the rest; starts muted (choice kept in
     * localStorage); picks the 480p or 720p file from screen size + navigator.connection; loads the
     * next page near the end; sends POST /reels/{id}/view with the watched seconds on leaving a reel.
     */
    const MUTE_KEY = 'ngn.reels.muted';
    const pickQuality = () => {
        const c = navigator.connection;
        if (c && (c.saveData || /2g|3g/.test(c.effectiveType || '') || (c.downlink && c.downlink < 1.5))) return '480';
        return Math.min(window.screen?.width || 9999, window.innerWidth) * (window.devicePixelRatio || 1) >= 700 ? '720' : '480';
    };
    const compact = (n) => {
        n = Number(n) || 0;
        if (n < 1000) return String(n);
        const [v, unit] = n < 100000 ? [n / 1000, 'K'] : [n / 100000, 'L'];
        return (v < 10 ? v.toFixed(1) : Math.round(v).toString()).replace(/\.0$/, '') + unit;
    };

    Alpine.data('reelFeed', (initial, next, msg) => ({
        reels: initial.map((r, i) => ({ ...r, near: i < 2 })),
        next,
        current: -1,
        muted: (() => { try { return localStorage.getItem(MUTE_KEY) !== '0'; } catch { return true; } })(),
        paused: false,
        flash: '',
        progress: 0,
        loading: false,
        error: false,
        quality: '720',
        watch: null,
        query: window.location.search,
        // comments
        commentsOpen: false, cReel: null, comments: [], cNext: null, cLoading: false, cBody: '', cBusy: false, cError: '', replyTo: null,
        // share
        shareOpen: false, sReel: null, copied: false,

        init() {
            this.quality = pickQuality();
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach((e) => { if (e.isIntersecting && e.intersectionRatio >= 0.6) this.activate(Number(e.target.dataset.index)); });
            }, { root: this.$refs.scroller, threshold: [0.6] });
            this.$nextTick(() => this.observeAll());

            const frame = () => {
                const v = this.video(this.current);
                if (v && v.duration) this.progress = v.currentTime / v.duration;
                this.raf = requestAnimationFrame(frame);
            };
            this.raf = requestAnimationFrame(frame);

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { this.flushView(); this.video(this.current)?.pause(); }
                else if (!this.paused && !this.commentsOpen) this.play(this.current, false);
            });
            window.addEventListener('pagehide', () => this.flushView());
        },

        observeAll() { this.$root.querySelectorAll('[data-reel-index]').forEach((el) => this.observer.observe(el)); },
        video(i) { return i < 0 ? null : this.$root.querySelector(`video[data-i="${i}"]`); },
        section(i) { return this.$root.querySelector(`[data-reel-index][data-index="${i}"]`); },
        src(r) { return this.quality === '480' ? (r.video_480_url || r.video_720_url) : (r.video_720_url || r.video_480_url); },
        fmt: compact,

        activate(i) {
            if (i === this.current || !this.reels[i]) return;
            this.flushView();
            this.current = i;
            this.progress = 0;
            for (let j = Math.max(0, i - 1); j <= i + 2 && j < this.reels.length; j++) this.reels[j].near = true;
            try { history.replaceState(null, '', this.reels[i].url + this.query); } catch { /* sandboxed */ }
            this.$nextTick(() => this.play(i));
            if (i >= this.reels.length - 3) this.loadMore();
        },

        play(i, fresh = true) {
            this.$root.querySelectorAll('video[data-i]').forEach((v) => { if (Number(v.dataset.i) !== i) v.pause(); });
            const v = this.video(i);
            if (!v) return;
            v.muted = this.muted;
            if (fresh) {
                try { v.currentTime = 0; } catch { /* not loaded yet */ }
                this.watch = { id: this.reels[i].id, seconds: 0, last: 0, completed: false };
            }
            this.paused = false;
            v.play().catch(() => {
                // Unmuted autoplay can be refused before the reader has interacted: fall back to muted.
                if (!this.muted) { this.muted = true; v.muted = true; v.play().catch(() => { this.paused = true; }); }
                else this.paused = true;
            });
        },

        tick(i, v) {
            if (i !== this.current || !this.watch) return;
            const t = v.currentTime;
            const d = t - this.watch.last;
            if (d > 0 && d < 1.5) this.watch.seconds += d;
            if (d < -1 && this.watch.last > (v.duration || 0) - 1.5) this.watch.completed = true; // looped
            this.watch.last = t;
        },

        flushView() {
            const w = this.watch;
            this.watch = null;
            if (!w || w.seconds < 1) return;
            const body = new FormData();
            body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            body.append('watched_seconds', String(Math.round(w.seconds)));
            body.append('completed', w.completed ? '1' : '0');
            if (!navigator.sendBeacon?.(`/reels/${w.id}/view`, body)) {
                window.ngnFetch(`/reels/${w.id}/view`, { body: { watched_seconds: Math.round(w.seconds), completed: w.completed } });
            }
        },

        togglePlay(i) {
            const v = this.video(i);
            if (!v) return;
            if (v.paused) { v.muted = this.muted; v.play().catch(() => {}); this.paused = false; this.flash = 'play'; }
            else { v.pause(); this.paused = true; this.flash = 'pause'; }
            clearTimeout(this.flashTimer);
            this.flashTimer = setTimeout(() => { this.flash = ''; }, 600);
        },

        toggleMute() {
            this.muted = !this.muted;
            try { localStorage.setItem(MUTE_KEY, this.muted ? '1' : '0'); } catch { /* private mode */ }
            const v = this.video(this.current);
            if (v) { v.muted = this.muted; if (!this.muted && v.paused && !this.paused) v.play().catch(() => {}); }
        },

        go(delta) {
            const target = this.current + delta;
            if (target < 0) return;
            const el = this.section(target) || (target >= this.reels.length ? this.$refs.end : null);
            if (el && el.offsetParent !== null) this.$refs.scroller.scrollTo({ top: el.offsetTop, behavior: 'smooth' });
        },

        key(e) {
            if (this.commentsOpen || this.shareOpen || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
            if (e.key === 'ArrowDown' || e.key === 'PageDown') { e.preventDefault(); this.go(1); }
            else if (e.key === 'ArrowUp' || e.key === 'PageUp') { e.preventDefault(); this.go(-1); }
            else if (e.key === ' ' || e.key === 'k') { e.preventDefault(); this.togglePlay(this.current); }
            else if (e.key === 'm') this.toggleMute();
        },

        async loadMore() {
            if (this.loading || this.error || !this.next) return;
            this.loading = true;
            try {
                const res = await fetch(this.next, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();
                const seen = new Set(this.reels.map((r) => r.id));
                this.reels.push(...data.reels.filter((r) => !seen.has(r.id)).map((r) => ({ ...r, near: false })));
                this.next = data.next;
                this.$nextTick(() => this.observeAll());
            } catch {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },

        retry() { this.error = false; this.loadMore(); },

        async switchLang(i) {
            const r = this.reels[i];
            if (!r?.other_language_reel_id) return;
            const res = await fetch(`/reels/${r.other_language_reel_id}/data`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) { this.$store.toast.show(msg.failed); return; }
            const { reel } = await res.json();
            this.flushView();
            this.reels.splice(i, 1, { ...reel, near: true });
            this.$nextTick(() => {
                this.observeAll();
                try { history.replaceState(null, '', reel.url + this.query); } catch { /* sandboxed */ }
                this.play(i);
            });
        },

        // ── Like / save / follow (guests: login sheet, then the action is replayed) ──
        async like(r) {
            if (!this.$store.auth.require('reel_like', { id: r.id })) return;
            r.is_liked = !r.is_liked;
            r.counts.likes += r.is_liked ? 1 : -1;
            const res = await window.ngnFetch(`/reels/${r.id}/like`, { method: r.is_liked ? 'POST' : 'DELETE' });
            if (!res.ok) { r.is_liked = !r.is_liked; r.counts.likes += r.is_liked ? 1 : -1; }
        },

        async save(r) {
            if (!this.$store.auth.require('reel_save', { id: r.id })) return;
            r.is_saved = !r.is_saved;
            const res = await window.ngnFetch(`/reels/${r.id}/save`, { method: r.is_saved ? 'POST' : 'DELETE' });
            if (!res.ok) { r.is_saved = !r.is_saved; return; }
            this.$store.toast.show(r.is_saved ? msg.saved : msg.unsaved);
        },

        async follow(r) {
            if (!r.page || !this.$store.auth.require('reel_follow', { page: r.page.slug, id: r.id })) return;
            const following = !r.page.is_following;
            const setAll = (value) => this.reels.forEach((x) => { if (x.page?.slug === r.page.slug) x.page.is_following = value; });
            setAll(following);
            const res = await window.ngnFetch(`/pages/${r.page.slug}/follow`, { method: following ? 'POST' : 'DELETE' });
            if (!res.ok) setAll(!following);
        },

        replay(p) {
            if (!p || p.done) return;
            const r = this.reels.find((x) => x.id === p.payload?.id);
            if (!r) return;
            p.done = true;
            if (p.action === 'reel_like' && !r.is_liked) this.like(r);
            else if (p.action === 'reel_save' && !r.is_saved) this.save(r);
            else if (p.action === 'reel_follow' && !r.page?.is_following) this.follow(r);
            else if (p.action === 'reel_comment') this.openComments(r);
        },

        // ── Comments (bottom sheet on mobile, side panel on desktop) ──
        async openComments(r) {
            this.cReel = r; this.commentsOpen = true; this.comments = []; this.cNext = null;
            this.cBody = ''; this.cError = ''; this.replyTo = null;
            await this.loadComments(`/reels/${r.id}/comments`);
        },
        async loadComments(url) {
            this.cLoading = true;
            try {
                const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await res.json();
                this.comments.push(...data.comments);
                this.cNext = data.next;
            } catch { this.cError = msg.failed; }
            this.cLoading = false;
        },
        async postComment() {
            const r = this.cReel;
            if (!this.$store.auth.require('reel_comment', { id: r.id })) return;
            if (this.cBody.trim().length < 2 || this.cBusy) return;
            this.cBusy = true; this.cError = '';
            const res = await window.ngnFetch(`/reels/${r.id}/comments`, { body: { body: this.cBody, parent_id: this.replyTo?.id ?? null } });
            this.cBusy = false;
            if (!res.ok) { this.cError = res.data?.message || msg.failed; return; }
            if (this.replyTo) this.replyTo.replies.push(res.data.comment); else this.comments.unshift(res.data.comment);
            r.counts.comments++;
            this.cBody = ''; this.replyTo = null;
        },
        reply(c) {
            if (!this.$store.auth.require('reel_comment', { id: this.cReel.id })) return;
            this.replyTo = c;
            this.$nextTick(() => this.$refs.commentInput?.focus());
        },
        async likeComment(c) {
            if (!this.$store.auth.require('reel_comment', { id: this.cReel.id })) return;
            c.liked = !c.liked; c.likes += c.liked ? 1 : -1;
            const res = await window.ngnFetch(`/comments/${c.id}/like`, { method: c.liked ? 'POST' : 'DELETE' });
            if (!res.ok) { c.liked = !c.liked; c.likes += c.liked ? 1 : -1; }
        },
        report(type, id) {
            if (this.$store.auth.require('report', { type, id })) this.$dispatch('ngn-report', { type, id });
        },

        // ── Share: copy link, WhatsApp, native share ──
        openShare(r) { this.sReel = r; this.copied = false; this.shareOpen = true; },
        get shareUrl() { return this.sReel ? new URL(this.sReel.url, window.location.origin).href : ''; },
        get whatsapp() { return 'https://wa.me/?text=' + encodeURIComponent((this.sReel?.title || '') + ' ' + this.shareUrl); },
        get canNativeShare() { return typeof navigator.share === 'function'; },
        async countShare(channel) {
            const r = this.sReel;
            const res = await window.ngnFetch(`/reels/${r.id}/share`, { body: { channel } });
            if (res.ok) r.counts.shares = res.data.shares;
        },
        async copyLink() {
            try { await navigator.clipboard.writeText(this.shareUrl); this.copied = true; this.countShare('link'); } catch { /* no clipboard */ }
        },
        async nativeShare() {
            try { await navigator.share({ title: this.sReel.title, url: this.shareUrl }); this.countShare('native'); } catch { /* cancelled */ }
        },
    }));

    /** Login sheet: phone → OTP. Mobile: bottom sheet. Desktop: centred modal. */
    Alpine.data('loginSheet', () => ({
        step: 'phone',
        phone: '',
        digits: ['', '', '', '', '', ''],
        resendIn: 0,
        timer: null,
        busy: false,
        error: '',

        get phoneValid() { return /^[6-9]\d{9}$/.test(this.phone); },
        get code() { return this.digits.join(''); },
        get current() { const i = this.digits.findIndex((d) => d === ''); return i === -1 ? 5 : i; },
        get resendLabel() { return `0:${String(this.resendIn).padStart(2, '0')}`; },

        async sendOtp() {
            this.error = '';
            if (!this.phoneValid) { this.error = this.$el.dataset.phoneError; return; }
            this.busy = true;
            const { ok, data } = await window.ngnFetch('/auth/otp/send', { body: { phone: this.phone } });
            this.busy = false;
            if (!ok) { this.error = data?.message || this.$el.dataset.genericError; return; }
            this.step = 'otp';
            this.digits = ['', '', '', '', '', ''];
            this.startTimer();
            this.$nextTick(() => this.otpInput(0)?.focus());
        },

        otpInput(i) { return this.$root.querySelectorAll('[data-otp]')[i]; },

        startTimer() {
            clearInterval(this.timer);
            this.resendIn = 30;
            this.timer = setInterval(() => { if (--this.resendIn <= 0) clearInterval(this.timer); }, 1000);
        },

        onDigit(i, event) {
            const value = event.target.value.replace(/\D/g, '');
            if (value.length > 1) { // pasted the whole code
                value.slice(0, 6).split('').forEach((d, j) => { this.digits[j] = d; });
                this.otpInput(Math.min(value.length, 5))?.focus();
                return;
            }
            this.digits[i] = value;
            event.target.value = value;
            if (value && i < 5) this.otpInput(i + 1)?.focus();
        },

        onBackspace(i) {
            if (this.digits[i] === '' && i > 0) {
                this.digits[i - 1] = '';
                this.otpInput(i - 1)?.focus();
            }
        },

        async verify() {
            this.error = '';
            if (this.code.length !== 6) return;
            this.busy = true;
            const { ok, data } = await window.ngnFetch('/auth/otp/verify', { body: { phone: this.phone, code: this.code } });
            this.busy = false;
            if (!ok) { this.error = data?.message || this.$el.dataset.genericError; return; }
            window.location.href = data?.redirect || window.location.href;
        },

        reset() {
            this.step = 'phone';
            this.error = '';
            clearInterval(this.timer);
        },
    }));
});

/**
 * After login, finish the action the guest tried: "visit" navigates; anything else is
 * re-dispatched as `ngn-replay` so the matching component (like button, follow, poll…) repeats it.
 * Skipped on onboarding, so the action waits until the reader lands on a normal page.
 */
document.addEventListener('alpine:initialized', () => {
    if (!window.NGN?.loggedIn || window.NGN?.noReplay) return;
    const pending = Alpine.store('auth').takePending();
    if (!pending) return;
    if (pending.action === 'visit' && pending.payload?.url) {
        if (pending.payload.url !== window.location.href) window.location.href = pending.payload.url;
        return;
    }
    setTimeout(() => window.dispatchEvent(new CustomEvent('ngn-replay', { detail: pending })), 50);
});

Livewire.start();
