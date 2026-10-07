/*
 * Pop-up notifications in the browser (spec 022). One Alpine component, `pushControl`, used three ways:
 * the "Turn on notifications" card, the per-device switch in Account, and a silent copy in every signed-in
 * page that keeps this device registered and refreshes the bell when a push arrives.
 *
 * Rules it follows: the browser's permission dialog opens only from a tap; nothing is sent to the server
 * until the person has allowed notifications; signing out removes this device first.
 */
const DISMISS_KEY = 'push-card-dismissed-until';
const DISMISS_DAYS = 14;

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
const isApple = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const installed = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

const keyToBytes = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(padded);

    return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
};

const remember = (until) => {
    try {
        localStorage.setItem(DISMISS_KEY, String(until));
    } catch (error) {
        // storage can be blocked; the card then simply shows again
    }
};

const dismissedNow = () => {
    try {
        return Number(localStorage.getItem(DISMISS_KEY) || 0) > Date.now();
    } catch (error) {
        return false;
    }
};

const call = (method, body) => fetch('/push/subscriptions', {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': meta('csrf-token') },
    body: JSON.stringify(body),
});

const existing = async () => {
    const registration = await navigator.serviceWorker.getRegistration('/');

    return registration ? registration.pushManager.getSubscription() : null;
};

let registering = null;

/** Subscribes this device (permission must already be granted) and tells the server. One run at a time. */
const register = () => {
    registering ??= (async () => {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;

        const subscribe = () => registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(meta('vapid-public-key')) });
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            try {
                subscription = await subscribe();
            } catch (error) {
                // A leftover subscription made with other keys blocks a new one: drop it and try once more.
                if (error.name !== 'InvalidStateError') {
                    throw error;
                }

                const stale = await registration.pushManager.getSubscription();

                if (stale) {
                    await stale.unsubscribe();
                }

                subscription = await subscribe();
            }
        }

        const { endpoint, keys } = subscription.toJSON();
        const response = await call('POST', { endpoint, keys, contentEncoding: 'aes128gcm' });

        if (!response.ok) {
            throw Object.assign(new Error('The server did not accept this device.'), { name: 'ServerError', status: response.status });
        }
    })().finally(() => { registering = null; });

    return registering;
};

/** What went wrong, in words a person can act on (the technical name stays in the browser console). */
const explain = (error) => {
    switch (error && error.name) {
        case 'AbortError':
            return 'Your browser could not reach its push service. In Brave, turn on Settings, Privacy and security, "Use Google Services for Push Messaging", then try again. A VPN or strict privacy setting can also block it.';
        case 'NotAllowedError':
            return 'The browser did not allow notifications for this site. Check the site permission in your browser settings.';
        case 'SecurityError':
            return 'This page cannot use notifications. It has to be opened over https.';
        case 'ServerError':
            return `The server did not accept this device (error ${error.status}). Please try again.`;
        default:
            return `Something went wrong (${(error && error.name) || 'unknown'}). Please try again.`;
    }
};

/** Stops pop-ups on this device and tells the server. Safe to call when nothing is subscribed. */
const unregister = async () => {
    if (!supported()) {
        return;
    }

    const subscription = await existing();

    if (!subscription) {
        return;
    }

    const { endpoint } = subscription;
    await subscription.unsubscribe();
    await call('DELETE', { endpoint });
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('pushControl', (config = {}) => ({
        mode: config.mode || 'card',
        state: 'checking',
        busy: false,
        failed: false,
        reason: '',
        justEnabled: false,
        dismissed: dismissedNow(),

        async init() {
            this.state = await this.detect();
        },

        async detect() {
            if (!meta('vapid-public-key')) {
                return 'unavailable';
            }

            // iPhone and iPad only allow pop-ups for a site added to the Home Screen.
            if (isApple() && !installed()) {
                return 'install';
            }

            if (!supported()) {
                return 'unsupported';
            }

            if (Notification.permission === 'denied') {
                return 'blocked';
            }

            if (Notification.permission === 'granted') {
                try {
                    // Granted already: quietly keep this device registered, including after the browser rotates it.
                    await register();

                    return 'on';
                } catch (error) {
                    console.error('Pop-up notifications could not connect this device.', error);
                    this.reason = explain(error);

                    return 'off';
                }
            }

            return 'ask';
        },

        get visible() {
            if (this.mode === 'silent' || this.state === 'checking' || this.state === 'unavailable') {
                return false;
            }

            if (this.mode === 'switch') {
                return true;
            }

            return this.justEnabled || (!this.dismissed && ['ask', 'install', 'blocked', 'off'].includes(this.state));
        },

        async enable() {
            this.busy = true;
            this.failed = false;

            try {
                // Asked here, inside the tap, because browsers ignore or block prompts that appear on their own.
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    await register();
                    this.state = 'on';
                    this.justEnabled = true;
                    setTimeout(() => { this.justEnabled = false; }, 4000);
                } else {
                    this.state = permission === 'denied' ? 'blocked' : 'ask';
                }
            } catch (error) {
                console.error('Pop-up notifications could not connect this device.', error);
                this.reason = explain(error);
                this.failed = true;
                this.state = 'off';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;

            try {
                await unregister();
                this.state = Notification.permission === 'granted' ? 'off' : 'ask';
            } finally {
                this.busy = false;
            }
        },

        dismiss() {
            remember(Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000);
            this.dismissed = true;
        },
    }));
});

// A push that arrives while the site is open refreshes the bell and the inbox straight away.
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data && event.data.type === 'push-received' && window.Livewire) {
            window.Livewire.dispatch('push-received');
        }
    });
}

// Signing out removes this device first, so the next person on it never gets the last person's pop-ups.
document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !/\/logout$/.test(form.action) || form.dataset.pushDone === '1') {
        return;
    }

    event.preventDefault();
    form.dataset.pushDone = '1';

    try {
        await Promise.race([unregister(), new Promise((resolve) => setTimeout(resolve, 2000))]);
    } catch (error) {
        // never block signing out
    }

    form.submit();
}, true);
