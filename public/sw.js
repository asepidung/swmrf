const CACHE_NAME = 'swm-erp-cache-v4';
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
            // icon WAJIB diisi. SUDAH DICOBA DUA KALI melepasnya, dan dua
            // kali menghasilkan kegagalan yang sama -- jangan dicoba ketiga.
            //
            // Tanpa icon, Android Chrome membuat AVATAR HURUF dari nama
            // domain: huruf "C" dari coba.wijayameat.co.id, yang disangka
            // pengguna sebagai inisial nama pengirim.
            //
            // Percobaan kedua (31 Agustus 2026) berangkat dari dugaan bahwa
            // aplikasi yang sudah TERPASANG sebagai PWA akan memakai ikon
            // aplikasinya sendiri. Diuji langsung di perangkat Project Owner:
            // huruf "C" tetap muncul. Dugaan itu salah, dan status terpasang
            // tidak mengubah apa pun.
            //
            // Kesimpulan yang berlaku: logo tampil dua kali adalah perilaku
            // bawaan Android untuk notifikasi web, dan itu lebih baik
            // daripada huruf yang menyesatkan.
            icon: payload.icon || '/img/pwalogo-maskable-192.png',
            // badge WAJIB aset siluet putih tersendiri, BUKAN logo berwarna.
            // Android hanya membaca kanal alpha-nya lalu mewarnai sendiri;
            // logo berwarna penuh tampil sebagai blok padat yang terlihat
            // "terpotong", bukan siluet yang bisa dikenali.
            badge: payload.badge || '/img/pwalogo-badge-192.png',
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
