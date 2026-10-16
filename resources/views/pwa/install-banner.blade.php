{{--
    Ajakan memasang aplikasi (PWA) di Chrome Android dan Safari iPhone.

    - Chrome/Android (dan Chrome desktop): browser mengirim `beforeinstallprompt` bila aplikasi layak dipasang
      (HTTPS, manifest dengan ikon 192/512, service worker ber-handler fetch). Peristiwa itu DITAHAN lalu dipakai
      tombol "Pasang"; tanpa ini browser hanya menaruh "Instal aplikasi" di dalam menu titik tiga.
    - Safari/iPhone: tidak ada peristiwa itu dan tidak ada prompt dari kode; satu-satunya jalan adalah
      Bagikan -> Tambahkan ke Layar Utama, jadi yang bisa dilakukan hanya menunjukkan caranya.
    - Tidak tampil bila sudah terpasang (mode standalone), dan bila ditutup tidak muncul lagi selama 7 hari
      (disimpan di localStorage; bila localStorage tidak tersedia, banner tetap berfungsi tanpa mengingat).
    Gaya inline, tidak bergantung pada kelas Tailwind yang mungkin tidak ikut terkompilasi.
--}}
<div
    x-data="pwaInstall()"
    x-init="init()"
    x-show="visible"
    x-cloak
    style="position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 60; display: none;"
    x-bind:style="visible ? 'position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 60; display: block;' : 'display: none;'"
    role="dialog"
    aria-live="polite"
>
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; color: #111827; border: 1px solid #d1d5db; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,.25); padding: 12px 14px; display: flex; gap: 12px; align-items: center; font-size: 14px; line-height: 1.35;">
        <img src="/img/pwalogo-192.png" alt="" width="40" height="40" style="border-radius: 8px; flex: none;">

        <div style="flex: 1; min-width: 0;">
            <div style="font-weight: 600;">{{ __('Install SWM ERP') }}</div>

            <template x-if="mode === 'prompt'">
                <div style="color: #4b5563;">{{ __('Add the app to your home screen for quick access.') }}</div>
            </template>

            <template x-if="mode === 'ios'">
                <div style="color: #4b5563;">
                    {{ __('Tap the Share button, then choose "Add to Home Screen".') }}
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -3px;" aria-hidden="true"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
                </div>
            </template>
        </div>

        <button
            type="button"
            x-show="mode === 'prompt'"
            x-on:click="install()"
            style="flex: none; background: #000080; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 600; cursor: pointer;"
        >{{ __('Install') }}</button>

        <button
            type="button"
            x-on:click="dismiss()"
            aria-label="{{ __('Close') }}"
            style="flex: none; background: transparent; border: 0; color: #6b7280; font-size: 20px; line-height: 1; padding: 4px; cursor: pointer;"
        >&times;</button>
    </div>
</div>

<script>
    function pwaInstall() {
        return {
            visible: false,
            mode: null,          // 'prompt' (Chrome/Android) | 'ios' (Safari iPhone/iPad)
            deferred: null,
            storageKey: 'swm.pwa.install-dismissed',

            standalone() {
                return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            },

            dismissedRecently() {
                try {
                    const at = parseInt(window.localStorage.getItem(this.storageKey) || '0', 10);

                    return at > 0 && (Date.now() - at) < 7 * 24 * 60 * 60 * 1000;
                } catch (error) {
                    return false;
                }
            },

            isIosSafari() {
                const ua = window.navigator.userAgent;
                const ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                // Chrome/Firefox/Edge di iOS memakai mesin Safari tetapi tidak punya "Tambahkan ke Layar Utama" yang sama.
                const safari = /Safari/.test(ua) && ! /CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

                return ios && safari;
            },

            init() {
                if (this.standalone() || this.dismissedRecently()) {
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
                    // Diabaikan: banner hanya akan muncul lagi di kunjungan berikutnya.
                }
            },
        };
    }
</script>
