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
