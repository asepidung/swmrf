{{--
    Peringatan "halaman ini tidak cocok dibuka di HP", dipakai bersama oleh
    setiap halaman kerja layar-lebar (scan barcode, cetak label) yang
    SENGAJA tidak dibuat responsif -- Keputusan Owner, 7 September 2026:
    halaman-halaman ini memang harus dioperasikan dari laptop/komputer.

    Wajib kirim variabel `$backUrl` (URL tombol "BACK") saat @include.

    Dibuat sebagai partial bersama, bukan disalin per halaman, supaya revisi
    tampilan (dan revisi 7 September ini sendiri -- versi pertama dianggap
    "kurang nendang") cukup satu tempat.
--}}
<style>
    [x-cloak] {
        display: none !important;
    }
</style>

<div
    x-data="{ show: window.innerWidth < 768 }"
    x-show="show"
    x-cloak
    style="position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.75); display: flex; align-items: center; justify-content: center; padding: 1.5rem;"
>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="max-width: 24rem; width: 100%; text-align: center; border-top: 6px solid rgb(var(--danger-600));">
        <div style="padding: 1.5rem;">
            <x-heroicon-o-exclamation-triangle class="text-danger-600 dark:text-danger-400" style="width: 3rem; height: 3rem; margin: 0 auto 0.75rem;" />
            <div class="text-danger-600 dark:text-danger-400" style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.02em;">
                {{ __('This page is not suitable for phones') }}
            </div>
            <p style="margin-bottom: 1.5rem; color: #6b7280;">
                {{ __('It needs a wide screen to work properly. Please continue from a laptop or computer.') }}
            </p>
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <x-filament::button
                    href="{{ $backUrl }}"
                    tag="a"
                    color="gray">
                    {{ __('BACK') }}
                </x-filament::button>
                <x-filament::button x-on:click="show = false" color="danger">
                    {{ __('Continue Anyway') }}
                </x-filament::button>
            </div>
        </div>
    </div>
</div>
