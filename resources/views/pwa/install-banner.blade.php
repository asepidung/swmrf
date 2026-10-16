{{--
    Ajakan memasang aplikasi (PWA) -- DICEGAT di layar penuh pada perangkat seluler (Android dan iPhone/iPad) yang
    belum memasangnya: di halaman login dan di dashboard setelah masuk.

    Tidak bergantung pada `beforeinstallprompt`, yang tidak selalu dikirim Chrome (sudah pernah ditutup, sudah
    dipasang sebagai pintasan, atau browser lain). Tiga mode:
    - 'prompt'  : Chrome mengirim `beforeinstallprompt` -> tombol "Pasang" memanggil dialog install bawaan.
    - 'android' : tidak ada peristiwa itu (atau browser lain) -> langkah manual: menu titik tiga -> Instal aplikasi
                  / Tambahkan ke layar utama.
    - 'ios'     : Safari iPhone/iPad tidak punya prompt dari kode -> Bagikan -> Tambahkan ke Layar Utama
                  (hanya Safari asli; Chrome/Firefox di iOS tidak punya fitur itu).
    Tidak tampil bila sudah terpasang (mode standalone) dan tidak di desktop. "Nanti saja" menyembunyikannya 3 hari
    (localStorage; bila tidak tersedia tetap berfungsi tanpa mengingat). Gaya inline.
--}}
<div
    x-data="pwaInstall()"
    x-init="init()"
    x-show="visible"
    x-cloak
    style="display: none;"
    x-bind:style="visible ? 'position: fixed; inset: 0; z-index: 100; display: flex; align-items: flex-end; justify-content: center; background: rgba(0,0,0,.6); padding: 16px;' : 'display: none;'"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pwa-install-title"
>
    <div style="width: 100%; max-width: 440px; background: #ffffff; color: #111827; border-radius: 16px; padding: 20px; box-shadow: 0 20px 40px rgba(0,0,0,.35); font-size: 15px; line-height: 1.45;">
        <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 12px;">
            <img src="/img/pwalogo-192.png" alt="" width="52" height="52" style="border-radius: 12px; flex: none;">
            <div>
                <div id="pwa-install-title" style="font-weight: 700; font-size: 18px;">{{ __('Install SWM ERP') }}</div>
                <div style="color: #4b5563; font-size: 14px;">{{ __('Open it straight from your home screen, like any other app.') }}</div>
            </div>
        </div>

        <template x-if="mode === 'prompt'">
            <button
                type="button"
                x-on:click="install()"
                style="width: 100%; background: #000080; color: #fff; border: 0; border-radius: 10px; padding: 13px; font-weight: 700; font-size: 16px; cursor: pointer;"
            >{{ __('Install') }}</button>
        </template>

        <template x-if="mode === 'android'">
            <div style="background: #f3f4f6; border-radius: 10px; padding: 12px 14px;">
                <div style="font-weight: 600; margin-bottom: 4px;">{{ __('How to install') }}</div>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>
                        {{ __('Tap the browser menu') }}
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#374151" style="display: inline-block; vertical-align: -3px;" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        {{ __('(three dots, top right).') }}
                    </li>
                    <li>{{ __('Choose "Install app" or "Add to Home screen".') }}</li>
                    <li>{{ __('Confirm with "Install".') }}</li>
                </ol>
            </div>
        </template>

        <template x-if="mode === 'ios'">
            <div style="background: #f3f4f6; border-radius: 10px; padding: 12px 14px;">
                <div style="font-weight: 600; margin-bottom: 4px;">{{ __('How to install') }}</div>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>
                        {{ __('Tap the Share button') }}
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -3px;" aria-hidden="true"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
                        {{ __('at the bottom of Safari.') }}
                    </li>
                    <li>{{ __('Choose "Add to Home Screen".') }}</li>
                    <li>{{ __('Tap "Add".') }}</li>
                </ol>
            </div>
        </template>

        <button
            type="button"
            x-on:click="dismiss()"
            style="width: 100%; margin-top: 10px; background: transparent; color: #6b7280; border: 0; padding: 10px; font-size: 14px; cursor: pointer;"
        >{{ __('Not now') }}</button>
    </div>
</div>

<script>
    function pwaInstall() {
        return {
            visible: false,
            mode: null,          // 'prompt' | 'android' | 'ios'
            deferred: null,
            storageKey: 'swm.pwa.install-dismissed',

            standalone() {
                return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            },

            dismissedRecently() {
                try {
                    const at = parseInt(window.localStorage.getItem(this.storageKey) || '0', 10);

                    return at > 0 && (Date.now() - at) < 3 * 24 * 60 * 60 * 1000;
                } catch (error) {
                    return false;
                }
            },

            isIos() {
                const ua = window.navigator.userAgent;

                return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            },

            isIosSafari() {
                const ua = window.navigator.userAgent;

                // Chrome/Firefox/Edge di iOS memakai mesin Safari tetapi tidak punya "Tambahkan ke Layar Utama" yang sama.
                return this.isIos() && /Safari/.test(ua) && ! /CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
            },

            isAndroid() {
                return /Android/.test(window.navigator.userAgent);
            },

            init() {
                if (this.standalone() || this.dismissedRecently()) {
                    return;
                }

                // Hanya perangkat seluler dicegat; di desktop tidak mengganggu.
                if (! this.isAndroid() && ! this.isIos()) {
                    return;
                }

                window.addEventListener('beforeinstallprompt', (event) => {
                    event.preventDefault();
                    this.deferred = event;
                    this.mode = 'prompt';
                    this.visible = true;
                });

                window.addEventListener('appinstalled', () => {
                    this.visible = false;
                    this.deferred = null;
                });

                if (this.isIosSafari()) {
                    this.mode = 'ios';
                    this.visible = true;

                    return;
                }

                // Android tanpa peristiwa install (atau browser lain): tunjukkan langkah manual setelah sejenak.
                if (this.isAndroid()) {
                    setTimeout(() => {
                        if (! this.visible) {
                            this.mode = 'android';
                            this.visible = true;
                        }
                    }, 1500);
                }
            },

            async install() {
                if (! this.deferred) {
                    return;
                }

                this.deferred.prompt();
                await this.deferred.userChoice;
                this.deferred = null;
                this.visible = false;
            },

            dismiss() {
                this.visible = false;

                try {
                    window.localStorage.setItem(this.storageKey, String(Date.now()));
                } catch (error) {
                    // Diabaikan: ajakan hanya akan muncul lagi di kunjungan berikutnya.
                }
            },
        };
    }
</script>
