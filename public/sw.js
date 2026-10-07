/*
 * Get Sorted service worker (spec 022). It does one job: receive a push and show the pop-up, and open the
 * right page when it is tapped. No caching, no offline mode. It lives at the site root so it can control
 * every page.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        // Open tabs refresh their bell and inbox straight away.
        windows.forEach((client) => client.postMessage({ type: 'push-received' }));

        // Always show the pop-up, even when the site is open: Safari requires it, and it means a tester or a
        // busy pro cannot miss one. A newer notice with the same tag replaces the older and alerts again.
        await self.registration.showNotification(payload.title || 'Get Sorted', {
            body: payload.body || '',
            icon: payload.icon || '/icons/icon-192.png',
            badge: payload.badge || '/icons/badge-96.png',
            tag: payload.tag || undefined,
            renotify: Boolean(payload.tag),
            data: payload.data || {},
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Only ever open a page on this site, whatever the payload says.
    let target = new URL('/app', self.location.origin);

    try {
        const requested = new URL((event.notification.data || {}).url || '', self.location.origin);

        if (requested.origin === self.location.origin) {
            target = requested;
        }
    } catch (error) {
        // keep the default
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if ('focus' in client) {
                await client.focus();

                if ('navigate' in client) {
                    try {
                        await client.navigate(target.href);

                        return;
                    } catch (error) {
                        // fall through to opening a new window
                    }
                }
            }
        }

        await self.clients.openWindow(target.href);
    })());
});
