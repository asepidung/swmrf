@auth
    @if (config('webpush.vapid.public_key'))
        {{--
            Tombol berlangganan notifikasi.

            Izin SENGAJA tidak diminta saat halaman dibuka. Browser hanya
            memberi satu kesempatan: begitu pengguna menekan "Blokir", tidak ada
            cara memintanya lagi dari kode — dia harus membukanya sendiri lewat
            pengaturan browser. Jadi izin baru diminta setelah dia sadar sedang
            menyalakan sesuatu, lewat tombol ini.
        --}}
        <div
            x-data="pushSubscription(@js(config('webpush.vapid.public_key')))"
            x-init="init()"
            class="fi-topbar-item"
        >
            <button
                type="button"
                x-show="supported && (state !== 'granted' || failed)"
                x-on:click="subscribe()"
                x-bind:disabled="busy"
                class="fi-icon-btn relative flex items-center justify-center rounded-lg p-2 outline-none transition duration-75 hover:bg-gray-100 focus-visible:bg-gray-100 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
                x-bind:title="state === 'denied'
                    ? @js(__('Notifications are blocked. Enable them from your browser settings.'))
                    : @js(__('Turn on notifications'))"
            >
                <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                <span
                    x-show="state === 'denied'"
                    class="absolute right-1 top-1 h-2 w-2 rounded-full bg-danger-500"
                ></span>
            </button>
        </div>

        <script>
            function pushSubscription(vapidPublicKey) {
                return {
                    supported: false,
                    state: 'default',
                    busy: false,
                    failed: false,
                    storageKey: 'swm.push.vapid-key',

                    init() {
                        this.supported = 'serviceWorker' in navigator
                            && 'PushManager' in window
                            && 'Notification' in window;

                        if (this.supported) {
                            this.state = Notification.permission;
                            this.ensureSubscription();
                        }
                    },

                    // Kunci VAPID yang dipakai saat langganan ini dibuat.
                    // Dibaca lewat try/catch: localStorage melempar di mode
                    // penyamaran sebagian browser, dan itu tidak boleh
                    // menjatuhkan seluruh proses berlangganan.
                    rememberedKey() {
                        try {
                            return window.localStorage.getItem(this.storageKey);
                        } catch (error) {
                            return null;
                        }
                    },

                    rememberKey() {
                        try {
                            window.localStorage.setItem(this.storageKey, vapidPublicKey);
                        } catch (error) {
                            // Diabaikan. Konsekuensinya cuma satu kali berlangganan
                            // ulang yang tidak perlu di kunjungan berikutnya.
                        }
                    },

                    /**
                     * Pulihkan langganan tanpa melibatkan pengguna.
                     *
                     * Sebuah langganan terikat pada applicationServerKey yang
                     * dipakai saat dibuat. Begitu kunci VAPID dirotasi, langganan
                     * lama ditolak layanan push -- dan pengguna yang sudah pernah
                     * menekan "Izinkan" TIDAK punya cara memperbaikinya sendiri,
                     * karena tombol loncengnya sudah menghilang. Satu-satunya
                     * jalan keluar dulu adalah menghapus data situs.
                     *
                     * Karena izinnya sudah ada, membuat ulang di sini tidak
                     * memunculkan prompt apa pun.
                     */
                    async ensureSubscription() {
                        if (Notification.permission !== 'granted') {
                            return;
                        }

                        try {
                            const registration = await navigator.serviceWorker.ready;
                            let subscription = await registration.pushManager.getSubscription();

                            if (subscription && this.rememberedKey() !== vapidPublicKey) {
                                await subscription.unsubscribe();
                                subscription = null;
                            }

                            if (! subscription) {
                                subscription = await registration.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
                                });
                            }

                            // Dikirim ulang setiap kali, bukan hanya saat baru
                            // dibuat: barisnya bisa saja hilang di sisi server
                            // sementara browser masih merasa berlangganan.
                            // Endpoint penyimpanannya idempoten.
                            await this.store(subscription);
                            this.failed = false;
                        } catch (error) {
                            console.error('Push subscription repair failed', error);
                            this.failed = true;
                        }
                    },

                    async store(subscription) {
                        const payload = subscription.toJSON();

                        const response = await fetch(@js(route('push-subscriptions.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                endpoint: payload.endpoint,
                                keys: payload.keys,
                                content_encoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                            }),
                        });

                        if (! response.ok) {
                            throw new Error('Gagal menyimpan langganan');
                        }

                        this.rememberKey();
                    },

                    // applicationServerKey WAJIB Uint8Array, bukan string base64.
                    // Ini jebakan paling sering pada Web Push.
                    urlBase64ToUint8Array(base64String) {
                        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                        const raw = window.atob(base64);
                        const output = new Uint8Array(raw.length);

                        for (let i = 0; i < raw.length; ++i) {
                            output[i] = raw.charCodeAt(i);
                        }

                        return output;
                    },

                    async subscribe() {
                        if (this.state === 'denied') {
                            window.alert(@js(__('Notifications are blocked. Enable them from your browser settings.')));
                            return;
                        }

                        this.busy = true;

                        try {
                            const permission = await Notification.requestPermission();
                            this.state = permission;

                            if (permission !== 'granted') {
                                return;
                            }

                            const registration = await navigator.serviceWorker.ready;

                            const subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
                            });

                            await this.store(subscription);
                            this.failed = false;
                        } catch (error) {
                            console.error('Push subscription failed', error);
                            this.failed = true;
                        } finally {
                            this.busy = false;
                        }
                    },
                };
            }
        </script>
    @endif
@endauth
