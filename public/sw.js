const CACHE_NAME = 'swm-erp-cache-v3';
const urlsToCache = [
    '/offline.html',
    '/manifest.json'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                if (response) {
                    return response;
                }
                return fetch(event.request).catch(function() {
                    // Fallback to offline page if available
                    return caches.match('/offline.html');
                });
            }
        )
    );
});

self.addEventListener('activate', function(event) {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

/*
 * Web Push.
 *
 * Tanpa dua pendengar di bawah, notifikasi tidak akan pernah muncul meski
 * server sudah mengirimnya dengan benar dan pengguna sudah mengizinkan.
 */

self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    var payload;
    try {
        payload = event.data.json();
    } catch (e) {
        // Bila muatannya bukan JSON, tampilkan apa adanya daripada diam saja.
        payload = { title: 'WijayaApps', body: event.data.text() };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'WijayaApps', {
            body: payload.body || '',
            // icon WAJIB diisi.
            //
            // Sempat dikosongkan dengan alasan logo tampil ganda (kiri dari
            // manifest, kanan dari icon). Ternyata bila icon kosong, Android
            // Chrome membuat AVATAR HURUF dari nama domain -- huruf "C" dari
            // coba.wijayameat.co.id -- yang disangka pengguna sebagai inisial
            // nama pengirim. Logo ganda lebih baik daripada huruf menyesatkan.
            icon: payload.icon || '/img/pwalogo-maskable-192.png',
            badge: payload.badge || '/img/pwalogo-maskable-192.png',
            tag: payload.tag || undefined,
            data: payload.data || {},
            requireInteraction: false
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var target = (event.notification.data && event.notification.data.url) || '/admin';

    // Kalau tab aplikasi sudah terbuka, fokuskan tab itu alih-alih membuka
    // jendela baru setiap kali notifikasi diklik.
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if (client.url.indexOf(target) !== -1 && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(target);
            }
        })
    );
});
