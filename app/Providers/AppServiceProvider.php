<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Tugas QC dibukakan sendiri untuk setiap dokumen pendampingnya.
         *
         * Pengamatnya dipasang lewat perulangan atas `QcReport::DOKUMEN`,
         * bukan disebutkan satu per satu. Daftar itu sudah menjadi
         * satu-satunya tempat titik QC ditulis; kalau pendaftarannya ditulis
         * terpisah, menambah titik berarti mengubah DUA tempat -- dan yang
         * kedua akan terlupa persis pada titik yang paling jarang disentuh.
         */
        foreach (\App\Models\QcReport::DOKUMEN as $kelas) {
            $kelas::observe(\App\Observers\QcCompanionObserver::class);
        }

        // ------------------------------------------------------------------
        // Semua isian tanggal: pemilih milik Filament, format hari/bulan/tahun
        // ------------------------------------------------------------------
        //
        // Dua hal yang diperbaiki sekaligus, dan keduanya berlaku untuk 124
        // isian tanggal di seluruh aplikasi.
        //
        // PERTAMA, isian tanggal bawaan browser hanya bisa dibuka lewat ikon
        // kalender di kanan; mengklik teksnya cuma memindahkan kursor antar
        // bagian tanggal. Pemilih milik Filament terbuka begitu field-nya
        // disentuh di mana saja.
        //
        // KEDUA, dan ini yang lebih penting: isian bawaan menampilkan tanggal
        // mengikuti bahasa BROWSER, sehingga di mesin berbahasa Inggris ia
        // tampil `mm/dd/yyyy`. Artinya "03/09" bisa berarti 3 September ATAU
        // 9 Maret tergantung siapa yang membukanya -- pada tanggal kirim dan
        // jatuh tempo, itu ambiguitas yang tidak pernah memunculkan error.
        // Formatnya kini dipatok hari/bulan/tahun, apa pun mesinnya.
        //
        // Dipasang di SATU tempat, bukan disalin ke 124 pemanggilan. Isian
        // tanggal yang dibuat nanti ikut mendapatkannya tanpa perlu diingat,
        // dan yang benar-benar butuh format lain masih bisa menimpanya sendiri
        // karena pemanggilan berantai di call site berjalan sesudah ini.
        //
        // Yang tersimpan tetap Y-m-d; yang berubah hanya yang dibaca manusia.
        // DateTimePicker didaftarkan LEBIH DULU, dan itu disengaja.
        //
        // Di Filament, DatePicker adalah TURUNAN dari DateTimePicker -- bukan
        // sebaliknya. Jadi aturan DateTimePicker ikut mengenai setiap
        // DatePicker, dan yang terdaftar belakangan berjalan belakangan.
        // Kalau urutannya dibalik, seluruh isian tanggal biasa ikut
        // menampilkan jam. Sudah terjadi sekali dan langsung ketahuan dari
        // pemeriksaan, bukan dari layar.
        \Filament\Forms\Components\DateTimePicker::configureUsing(
            fn (\Filament\Forms\Components\DateTimePicker $picker) => $picker
                ->native(false)
                ->displayFormat('d/m/Y H:i'),
        );

        \Filament\Forms\Components\DatePicker::configureUsing(
            fn (\Filament\Forms\Components\DatePicker $picker) => $picker
                ->native(false)
                ->displayFormat('d/m/Y')
                // State-nya dikembalikan menjadi TANGGAL SAJA.
                //
                // Filament sengaja menyimpan state pemilih non-native sebagai
                // datetime penuh (DateTimePicker.php, `(string) $state`),
                // sementara pemilih bawaan browser menyimpannya sebagai
                // tanggal saja. Untuk isian FORM perbedaan itu tidak terasa:
                // dehidrasi mengembalikannya ke Y-m-d sebelum disimpan.
                //
                // Untuk SARINGAN TABEL terasa sekali. Saringan membaca state
                // mentah tanpa melewati dehidrasi, lalu memakainya di
                // whereDate() -- dan '2026-09-01' >= '2026-09-01 00:00:00'
                // bernilai SALAH karena dibandingkan sebagai teks. Seluruh
                // baris hari itu lenyap dari daftar tanpa satu pun error.
                //
                // Ada 42 berkas yang membaca state saringan tanggal seperti
                // itu. Menormalkannya di sini menyelesaikan semuanya sekaligus,
                // dan yang dibuang cuma bagian jam yang memang tidak pernah
                // dimiliki DatePicker.
                ->afterStateHydrated(function (\Filament\Forms\Components\DatePicker $component, $state): void {
                    if (blank($state)) {
                        return;
                    }

                    try {
                        $component->state(\Carbon\Carbon::parse($state)->toDateString());
                    } catch (\Throwable) {
                        // Nilai yang tidak bisa dibaca dibiarkan apa adanya;
                        // Filament sendiri yang akan menolaknya.
                    }
                }),
            // isImportant: dijalankan SESUDAH setUp() milik Filament.
            // Tanpa penanda ini, callback di atas terdaftar lebih dulu dan
            // langsung ditimpa oleh milik Filament sendiri -- perbaikannya
            // tampak terpasang padahal tidak berpengaruh sama sekali.
            isImportant: true,
        );

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'id'])
                ->visible(outsidePanels: true);
        });

        \Illuminate\Support\Facades\Gate::define('viewLogViewer', function (?\App\Models\User $user) {
            return $user ? $user->hasPermission('view_activity_logs') : false;
        });

        if (config('app.env') !== 'local' || (request()->getHost() !== 'localhost' && request()->getHost() !== '127.0.0.1')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // \Filament\Support\Facades\FilamentAsset::register([
        //     \Filament\Support\Assets\Js::make('auto-logout', asset('js/auto-logout.js')),
        // ]);

        \App\Models\GoodsReceiptMaterialItem::observe(\App\Observers\GoodsReceiptMaterialItemObserver::class);

        \Illuminate\Support\Facades\Gate::policy(\App\Models\BeefStock::class, \App\Policies\BeefStockPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\BeefStockMovement::class, \App\Policies\BeefStockMovementPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\DeliveryOrderReceipt::class, \App\Policies\DeliveryOrderReceiptPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);

        // Inject PWA Manifest and Service Worker
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::HEAD_END,
            fn (): string => '<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#000080">
<link rel="apple-touch-icon" href="/img/pwalogo-maskable-192.png">'
        );

        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn (): string => "<script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function() {
                        navigator.serviceWorker.register('/sw.js').then(function(registration) {
                            console.log('ServiceWorker registration successful with scope: ', registration.scope);
                        }, function(err) {
                            console.log('ServiceWorker registration failed: ', err);
                        });
                    });
                }
            </script>"
        );
    }
}
