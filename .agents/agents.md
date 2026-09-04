# Catatan Agen — Wijaya Meat (SWM)

> **Untuk agen/implementor yang baru masuk.** Dokumen ini menjelaskan **apa yang sudah diputuskan dan kenapa**.
>
> - Aturan main yang wajib dipatuhi ada di [`project.md`](../project.md).
>
> **Repositori ini sengaja tidak punya `CLAUDE.md`.** Konsekuensinya dokumen ini tidak termuat otomatis di sesi baru, jadi Project Owner yang akan mengarahkan agen membacanya. Kalau suatu saat itu terasa merepotkan, cukup buat `CLAUDE.md` berisi beberapa baris penunjuk ke sini.
> - Penjelasan rinci tiap modul ada di [`docs/modules/`](../docs/modules/).
> - Dokumen ini adalah **konteks**: alasan, riwayat, dan jebakan yang sudah pernah kami tabrak.
>
> **Perbarui dokumen ini setiap kali sebuah keputusan diambil.** Tujuannya supaya sesi berikutnya tidak perlu menggali ulang dari nol atau mengulang perdebatan yang sudah selesai.

---

## 1. Ini proyek apa

ERP untuk **Wijaya Meat**, produsen daging sapi. Alurnya mengikuti perjalanan barang secara fisik:

```
Sapi hidup → Terima & Timbang → Karkas → Boning → Repack/Relabel → Stok → Tally → Delivery Order → Invoice → Piutang
```

Sedang dimigrasikan dari sistem PHP prosedural ke Laravel 12 + Filament v3 dengan **Strangler Pattern**, jadi selama masa transisi kedua sistem berbagi database yang sama. Itulah alasan banyak nama tabel dan konvensi terasa "warisan" — memang sengaja, jangan dirapikan sepihak.

### Lingkungan

| Lingkungan | Keterangan |
|---|---|
| Lokal | MySQL `swmv2`, dipakai untuk pengembangan |
| Uji coba | `coba.wijayameat.co.id` (shared hosting Hostinger), **auto-deploy Hostinger dari `main` -- kode saja, tanpa migrate dan tanpa clear cache**, isinya **data dummy** |
| Produksi | **VPS terpisah**, di luar jangkauan pekerjaan sehari-hari |

**Sejak 24 Agustus 2026, deploy otomatis DIMATIKAN.** Koneksi SSH dari GitHub Actions ke Hostinger berulang kali gagal dengan `dial tcp: i/o timeout` (setidaknya dua kali), sehingga merge ke `main` kadang tidak benar-benar sampai ke server tanpa ada yang menyadarinya. Auto-deploy bawaan Hostinger juga dimatikan Owner.

**Konsekuensinya: merge saja tidak membuat perubahan sampai ke server.** Setelah merge, deploy manual lewat SSH:

```
cd /home/u525862761/domains/coba.wijayameat.co.id/public_html
git pull origin main
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Migrasi tetap ikut jalan di langkah itu, jadi tulis migrasi yang aman. Workflow GitHub Actions dipertahankan tapi hanya bisa dipicu manual dari tab Actions.

**DEPLOY BELUM SELESAI SEBELUM LOKAL IKUT DIMIGRASI, DAN ITU PEKERJAAN KITA --
BUKAN PEKERJAAN OWNER.** Keputusan Owner, 4 September 2026: "pokoknya saya
terima beres". Kedua lingkungan diserahkan sepenuhnya; keduanya percobaan.

Jadi setiap kali sebuah PR di-merge, KEDUA sisi diselesaikan sendiri:

```
# lokal (direktori kerja ini sendiri)
git checkout main && git pull origin main
php artisan migrate            # bila PR-nya membawa migrasi

# hosting
tunggu klon otomatis sampai, lalu migrate --force + hangatkan cache
```

Lalu laporkan keduanya sudah sama -- commit yang sama, migrasi yang sama.
Jangan menyuruh Owner menjalankan apa pun.

Ini bukan formalitas. **3 September 2026** lokal Owner tertinggal empat migrasi
sekaligus karena tidak pernah sekali pun disebut, dan baru ketahuan saat ia
membukanya: dashboard langsung Internal Server Error karena
`scheduled_run_marks` belum ada.

Akibatnya jauh lebih besar daripada satu halaman error. **Produksi berjalan
dengan `APP_DEBUG=false`, jadi satu-satunya tempat Owner bisa melihat pesan
kesalahan yang utuh adalah lokalnya.** Membiarkan lokal itu basi berarti
mencabut satu-satunya alat diagnosis yang ia punya, tepat pada saat ia paling
membutuhkannya.

**404 TIDAK PERNAH MASUK LOG.** `NotFoundHttpException` dan
`ModelNotFoundException` ada di daftar `dontReport` bawaan Laravel, jadi
halaman yang menjawab 404 di produksi tidak meninggalkan jejak apa pun di Log
Viewer. Jangan menyimpulkan "tidak ada error di log, berarti tidak ada
masalah" -- untuk 404, log memang selalu kosong.

`migrate:fresh` **tidak pernah** dijalankan otomatis, dan memang dilarang.

---

## 2. Keputusan yang sudah diambil

### Master data WAJIB punya index unique; barcode transaksional TIDAK

Dua aturan ini berlawanan dan gampang tertukar, jadi perhatikan bedanya.

**Master data** — nama, kode, prefix, username, nomor rekening — **wajib** punya index unique di database. Validasi `->unique()` di form Filament saja tidak mengikat: dua permintaan yang tiba bersamaan bisa sama-sama lolos, dan penyisipan lewat seeder, import, atau tinker melewatinya sama sekali.

Sebelum 24 Agustus 2026, `suppliers.name`, `customers.name`, dan `materials.name` hanya dijaga di form tanpa pengaman database, sementara `warehouses.name` tidak dijaga sama sekali. Sudah ditutup.

**Pengecualian yang disengaja: `users.name`.** Dua orang boleh bernama sama; yang menjadi identitas adalah `username`, dan kolom itu sudah unique. Ada test yang menegaskan ini keputusan, bukan kelalaian.

`MasterDataUniquenessTest` memeriksa 19 kolom identitas sekaligus. Saat menambah master data baru, daftarkan kolom identitasnya di test itu.

### Barcode sengaja tanpa index unique

Kolom `barcode` pada `sales_return_items`, `mutation_items`, `repack_results`, dan `repack_materials` **sengaja tidak diberi index unique**.

**Kenapa:** tabel-tabel itu transaksional. Satu barang fisik bisa keluar-masuk berkali-kali — diretur, dimutasi, di-repack ulang — sehingga barcode yang sama sah muncul berulang lintas dokumen. Unique global justru memblokir alur bisnis yang benar.

Bandingkan supaya tidak keliru menyamakan:

| Tabel | Bentuk | Alasan |
|---|---|---|
| `beef_stocks` | `unique(barcode)` | Tabel stok berjalan: satu baris per barang yang sedang ada di gudang, dihapus saat keluar |
| `tally_items` | `unique(tally_id, barcode)` | Unique berlingkup dokumen |
| `stock_take_items` | `unique(stock_take_id, barcode)` | Unique berlingkup dokumen |
| `sales_return_items`, `mutation_items`, `repack_results`, `repack_materials` | tanpa unique | Transaksional, barang berulang lintas dokumen |

**Konsekuensinya:** pencegahan duplikat sepenuhnya ada di level aplikasi dan **wajib berlingkup per dokumen**. Jangan pernah mengusulkan unique index global di kolom-kolom itu; usulan itu sudah pernah diajukan dan ditolak.

### Pola konkurensi: fast-path plus penjagaan berkunci

Untuk scan barcode dan mutasi stok, polanya:

1. Cek duplikat **di luar** transaksi sebagai *fast-path* — hanya untuk memberi pesan ramah dan spesifik ke operator.
2. Cek duplikat **di dalam** `DB::transaction()` dengan `->lockForUpdate()` — inilah penjagaan yang mengikat.

**Kenapa tidak sekadar memindahkan cek ke dalam transaksi** (seperti saran audit lama): memindahkannya menurunkan kualitas pesan, dari notifikasi *warning* spesifik ("Barang Sudah Terinput") menjadi *error* generik. Operator gudang bekerja cepat; pesan yang jelas itu penting.

### Nomor invoice dan kosakata status

- **Nomor invoice:** `SWM-INV#260001` (format sistem baru). Legacy memakai `INV-SWM/23/VIII/00323`; Project Owner memutuskan **tetap memakai format baru**.
- **Status invoice yang hidup di kode:** `Belum Dibayar`, `Belum TF`, `Sudah TF`, `Lunas`.

**Alur bisnisnya:**

- Customer **tanpa** tukar faktur: `Belum Dibayar` sampai dibayar atau dicicil.
- Customer **dengan** tukar faktur: mulai dari `Belum TF`, karena diksi "Belum Dibayar" belum relevan sebelum fakturnya ditukar. **Setelah tukar faktur, jatuh tempo baru dihitung mulai dari tanggal tukar faktur** (bukan tanggal invoice). Setelah dibayar, keduanya sama saja.

**Yang masih terbuka:** cicilan belum punya status sendiri meski alur bisnisnya ada — lihat komentar ragu di `ReceivePayment.php`. Usulan yang belum diajukan resmi: pisahkan sumbu pembayaran (`Belum Dibayar` → `Cicilan` → `Lunas`) dari sumbu tukar faktur, karena status TF sebenarnya sudah bisa diturunkan dari kolom `invoice_exchange_date` (null berarti belum ditukar). Project Owner ingin menjajal ulang aplikasinya dulu sebelum memutuskan diksi.

**Sumber kebenaran `due_date`:** hook `saving()` di `app/Models/Invoice.php` adalah **satu-satunya** pemilik logika perhitungan `due_date`. Ia bercabang berdasarkan `status`. Jangan menghitung `due_date` di tempat lain — pernah ada dua perhitungan paralel dan keduanya saling menimpa.

### Sub-menu cluster wajib di atas, bukan di samping

Keputusan Owner: sub-menu sebuah Cluster harus tampil sebagai **tab horizontal di bagian atas** halaman, bukan daftar vertikal di sisi kiri.

```php
protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
```

**Pasang di setiap RESOURCE di dalam cluster, bukan di kelas Cluster.** Filament membacanya lewat `Resource::getSubNavigationPosition()`; menyetelnya di kelas Cluster saja **tidak mengubah tampilan sama sekali**. Ini sempat menipu: percobaan pertama hanya menyetel di kelas Cluster, test-nya hijau karena ikut memeriksa lapis yang salah, tapi menunya tetap di samping. Pelajarannya — kalau menguji perilaku UI, pastikan test memeriksa lapis yang benar-benar dibaca framework.

**Status per 24 Agustus 2026: seluruh Resource di semua cluster sudah menyetelnya.** Karena yang menentukan tampilan adalah Resource, tidak ada lagi cluster yang sub-menunya di samping.

Saat menambah Resource baru ke dalam cluster mana pun, jangan lupa menyetel properti ini — ada test yang menjaganya.

### Jangan number_format() nilai yang masuk ke field ->numeric()

Field `->numeric()` dirender sebagai `<input type="number">`, yang **tidak bisa menampung string ber-pemisah ribuan** seperti `"1.234,50"`. Browser menolaknya dan fieldnya tampil **kosong** — bukan error, jadi gejalanya menyesatkan.

Terjadi di halaman View Request Beef dan Request Material: `mutateFormDataBeforeFill()` memformat `qty`, `price`, dan `item_total` dengan `number_format()`, sehingga ketiganya kosong saat halaman dibuka. Kirim angka mentah ke form; pemformatan adalah urusan tampilan, bukan isian.

Ada test yang menjaganya (`RequisitionViewFormTest`).

### Laravel 12, dan kenapa BUKAN Laravel 13 atau Filament 5

Diputuskan 24 Agustus 2026 setelah `composer audit` melaporkan 21 advisory.

**Yang dikerjakan:** naik ke `laravel/framework` ^12.0 plus dompdf 3.1.6, guzzle 7.15.5, commonmark 2.10.0. Hasilnya `composer audit` bersih, nol advisory.

**Kenapa Laravel 11 harus ditinggalkan:** ketiga advisory Laravel menyebut versi terdampak `<12.60.0`, salah satunya eksplisit `>=11.0.0,<12.0.0`. **Tidak ada rilis 11.x yang memperbaikinya** — v11.56.0 pun masih terdampak. Laravel 11 sudah lewat masa dukungan keamanan, jadi kerentanan berikutnya pun tidak akan dapat perbaikan.

**Kenapa belum Laravel 13:** terhalang `laravel/tinker`. Versi stabil terakhirnya (v2.11.1) baru mendukung sampai Laravel 12; yang mendukung 13 masih `2.x-dev` yang di bawah minimum-stability. **Periksa lagi nanti** — begitu tinker rilis stabil untuk 13, jalannya terbuka. Filament v3.3.54 sendiri sudah mendukung `^13.0`.

**Kenapa belum Filament 5 — ini yang paling perlu dipahami sebelum tergoda:**

Filament 5 memang sudah ada (v5.7.6), dan Owner berminat. Tapi dari 3 ke 5 itu **dua versi mayor**, dan permukaannya di proyek ini besar sekali:

| | Jumlah |
|---|---|
| Resource | 48 |
| Halaman kustom | 188 |
| Total berkas Filament | 275 |
| Baris kode Filament | **28.310** |

Filament 4 memindahkan hampir seluruh namespace form dan menyatukan API schema, jadi hampir semua baris itu perlu disentuh — lalu diulang untuk versi 5. Test kita 166 tapi cakupannya baru sebagian; sebagian besar kerusakan hanya ketahuan dengan mengklik 48 modul satu per satu.

**Yang menentukan: Filament 5 BUKAN kebutuhan keamanan.** Filament v3.3.54 sudah mendukung Laravel 12 dan 13, jadi kita bisa sepenuhnya bebas advisory tanpa menyentuhnya. Layak dikejar sebagai proyek terjadwal tersendiri, jangan disisipkan di tengah penyisiran modul — kalau dipaksakan, penyisiran berhenti berminggu-minggu dan aplikasi tidak bisa dipakai di tengah jalan.

**Cara memverifikasi upgrade tanpa data:** 27 template di `resources/views/exports/` dirender penuh lewat dompdf dan semuanya menghasilkan `%PDF`; 64 view cetak dan Filament dikompilasi Blade tanpa error; 23 halaman modul diakses lewat browser dan semuanya 200. Pola ini berguna diulang untuk upgrade berikutnya.

### Notifikasi: Web Push (PWA), bukan toast lintas-pengguna

Keputusan Owner, 24 Agustus 2026, untuk poin 6 pada #77.

**Yang diganti:** toast yang muncul di layar ORANG LAIN — pemberitahuan antar-peran seperti "ada request beef baru" untuk purchasing. Dulu dipancarkan `GlobalTaskPoller` yang mem-polling setiap 5 detik.

**Yang TIDAK diganti:** toast bawaan Filament untuk pelaku aksinya sendiri — "berhasil disimpan", "gagal". Itu umpan balik untuk dirinya sendiri, bukan pemberitahuan untuk orang lain. Peringatan statis di Dashboard juga dipertahankan.

**Dikerjakan bertahap.** Baru modul Request Beef yang dipindahkan; 15 toast milik modul lain masih di poller dan menyusul sambil disisir.

Paket: `laravel-notification-channels/webpush`, terpasang di `^10.0` (v10.5.0). **Alasan aslinya sudah tidak berlaku:** dulu ^11.0 dihindari karena mensyaratkan Laravel 12/13 sementara proyek masih Laravel 11 — sejak naik ke Laravel 12, penghalang itu hilang. Pin `^10.0` dipertahankan karena v10.5.0 sendiri sudah mendukung `illuminate/notifications ^11.0|^12.0|^13.0`, jadi naik ke 11.x atau 12.x sifatnya opsional, bukan kebutuhan keamanan.

**Keputusan ShouldQueue: TIDAK dipakai.** `QUEUE_CONNECTION` masih `sync` dan tidak ada queue worker di shared hosting. Notifikasi yang diantre tanpa worker justru menumpuk diam-diam tanpa error — persis jebakan yang diperingatkan Owner dari proyek sebelumnya. Penerimanya pun cuma segelintir orang. Bila kelak jumlah penerima membengkak atau approve terasa lambat, pindah ke queue **berbarengan** dengan menyiapkan workernya, jangan salah satu saja.

Pengiriman dibungkus try/catch di `TaskNotifier`: **notifikasi tidak boleh menggagalkan aksi bisnis yang memicunya.** Kalau layanan push mati, dokumen tetap harus tersimpan.

**Penghitung langganan di Dashboard adalah bagian penting, bukan hiasan.** Pelajaran Owner dari proyek presensi: fiturnya sempurna secara teknis, tetapi hanya **3 dari 193 orang** yang menekan "Izinkan" — berbulan-bulan tidak ada notifikasi yang sampai ke siapa pun dan tidak ada yang menyadarinya karena tidak ada yang menghitung. `PushSubscriptionCoverageWidget` menampilkan angkanya sejak hari pertama.

**Izin sengaja TIDAK diminta saat halaman dibuka.** Browser hanya memberi satu kesempatan: begitu pengguna menekan "Blokir", tidak ada cara memintanya lagi dari kode. Karena itu izin diminta lewat tombol lonceng di topbar, setelah pengguna sadar sedang menyalakan sesuatu.

**Syarat yang tidak bisa ditawar:** HTTPS wajib (kecuali localhost), iOS baru bisa sejak 16.4 dan **hanya bila aplikasinya dipasang ke layar utama** — dibuka lewat Safari biasa notifikasi tidak akan pernah muncul meski kodenya benar.

**Kunci VAPID bersifat rahasia** dan hanya ada di `.env` masing-masing lingkungan. `.env.example` sengaja diisi placeholder kosong. Membuatnya: `php artisan webpush:vapid`. Di Windows/Laragon perintah itu gagal dengan "Unable to create the key" bila `OPENSSL_CONF` belum diset ke `openssl.cnf` bawaan PHP.

#### Kunci VAPID rusak: gagal senyap, dan pengguna terkunci tanpa jalan keluar

Ditemukan 26 Agustus 2026 saat Owner menguji notifikasi di HP untuk pertama kalinya. Layak dibaca utuh, karena tiga lapis penyamarannya menumpuk.

**Gejalanya menyesatkan.** Penerima sudah menekan lonceng dan mengizinkan, langganannya tersimpan benar, tetapi tidak ada notifikasi yang muncul. Di layar tidak ada error apa pun — dokumennya tersimpan normal.

**Sebabnya `VAPID_PRIVATE_KEY` di `.env` server rusak**: 81 karakter dan mengandung karakter di luar alfabet base64url, sehingga `Base64Url::decode()` menolaknya dengan `Invalid data provided`. **Kunci VAPID private yang sah panjangnya 43 karakter, public 87.** Angka itu layak dihafal — memeriksanya butuh dua detik dan langsung menjawab.

Kegagalannya senyap karena pengiriman memang dibungkus `try/catch` (dan itu benar — notifikasi tidak boleh menggagalkan aksi bisnis). Satu-satunya jejaknya satu baris di `laravel.log`. **Saat notifikasi tidak sampai, baca log server lebih dulu, jangan menebak dari kode.**

**Jebakan lanjutan yang lebih penting daripada kunci rusaknya:** mengganti kunci VAPID membuat **seluruh langganan lama tidak berlaku**, karena langganan terikat pada `applicationServerKey` yang dipakai saat dibuat. Padahal tombol lonceng menghilang begitu izin diberikan, dan dulu `init()` cuma membaca `Notification.permission` tanpa pernah memeriksa apakah langganannya masih ada dan masih cocok. Pengguna yang sudah pernah mengizinkan jadi **tidak punya cara berlangganan ulang lewat aplikasi sama sekali** — satu-satunya jalan keluar menghapus data situs.

Sekarang tombol loncengnya menyembuhkan diri: saat halaman dibuka dan izin sudah `granted`, langganan yang sebenarnya ada di browser diperiksa, lalu dibuat ulang bila hilang atau terikat kunci yang berbeda. Tanpa prompt, karena izinnya memang sudah ada. Kunci yang dipakai diingat di `localStorage`. Bila pemulihannya gagal, loncengnya muncul kembali supaya pengguna tidak terkunci diam-diam.

**Penghitung langganan di Dashboard tidak menangkap masalah ini** — ia menghitung baris, dan barisnya ada. Angka sehat di Dashboard bukan bukti notifikasi bisa sampai.

#### Ikon PWA: satu file tidak boleh merangkap `any` dan `maskable`

`manifest.json` sempat mendaftarkan satu file yang sama sebagai `"purpose": "any maskable"` untuk ukuran 192 maupun 512, padahal filenya cuma satu berukuran 512x512.

`maskable` menyuruh sistem memotong ikon ke bentuk mask dan hanya menjamin **lingkaran di tengah, sekitar 80% kanvas**. Logo Wijaya Meat membentang penuh dari tepi ke tepi, jadi sisi kiri-kanannya pasti terpotong di layar utama dan splash screen.

Bentuk yang benar: ikon `any` memakai logo apa adanya, ikon `maskable` dibuat **tersendiri** dengan logo diperkecil ke 70% lebar kanvas di atas latar putih penuh. Ukuran yang didaftarkan juga harus benar-benar ukuran filenya. Ada test yang menjaga ketiganya, termasuk memeriksa piksel tepi ikon maskable benar-benar kosong.

`apple-touch-icon` ikut diarahkan ke versi berlatar penuh: iOS menaruh **hitam** di belakang bagian transparan, sehingga logo bertepi transparan tampil bersudut hitam di layar utama.

#### Notifikasi: pasang `icon` eksplisit agar tidak diganti huruf oleh browser

Dulu diputuskan tidak mengisi `icon` karena logo akan tampil ganda (di kiri dan kanan). Namun ternyata jika `icon` kosong, Android Chrome akan otomatis membuat avatar huruf berdasarkan nama domain (misal huruf **C** dari *coba.wijayameat.co.id*). 
Huruf ini disangka sebagai inisial nama pengirim oleh pengguna, sehingga membingungkan. Keputusannya: **tetap isi `icon` dengan logo aplikasi**, lebih baik tampil ganda daripada memunculkan huruf yang menyesatkan. Teks notifikasi tetap ditulis sependek mungkin.

**Bahasa notifikasi mengikuti locale PENGIRIM, bukan penerima**, karena `__()` dievaluasi saat aksi dijalankan. Penerima berbahasa Indonesia bisa menerima notifikasi berbahasa Inggris bila yang memicunya sedang memakai EN. Belum diputuskan apakah ini perlu diubah.

**Saat sebuah keputusan dibatalkan, balik juga test-nya.** Keputusan "jangan isi `icon`" sempat dikunci sebuah test yang memastikan `icon:` TIDAK ada di `sw.js`. Setelah keputusannya dibatalkan, test itu tetap hijau — dan yang dijaganya justru kebalikan dari aturan yang berlaku. Lebih buruk lagi, `TaskAlert` sudah dikembalikan mengirim `icon` sementara service worker masih membuangnya, jadi perbaikannya tidak berefek apa pun sampai kedua sisi disamakan. **Test yang menjaga keputusan lama tidak akan pernah memberitahu bahwa keputusannya sudah berubah.**

Sekarang test-nya memeriksa **dua sisi sekaligus**: `TaskAlert` mengirim `icon`, DAN `sw.js` membacanya. Mengirim tanpa membaca sama saja dengan tidak mengirim.

**Ikon notifikasi wajib memakai versi BERALAS.** Android memotong ikon besar notifikasi menjadi **lingkaran**. Versi `any` (`pwalogo-192.png`) isinya membentang penuh sampai tepi kanvas, jadi sisi kiri-kanannya pasti terpangkas. Yang dipakai `pwalogo-maskable-192.png` — versi dengan area aman yang dibuat untuk keperluan ini. Aturan ringkasnya: **di mana pun sebuah ikon bisa dipotong bentuk (lingkaran, squircle, mask peluncur), pakai versi beralas.**

**Ikon di layar utama disimpan Android saat PWA dipasang.** Perubahan `manifest.json` tidak langsung terlihat pada perangkat yang sudah memasang aplikasinya — harus dihapus dari layar utama (atau di-uninstall lewat App Info) lalu dipasang ulang, idealnya sesudah membersihkan data situs di Chrome supaya `manifest.json` yang lama tidak ikut tersimpan di cache HTTP browser. **Diverifikasi 26 Agustus 2026:** md5sum file dan isi `manifest.json` di server sama persis dengan lokal — jadi kalau ikon masih terlihat terpotong padahal filenya sudah benar, itu instalasi lama yang belum di-refresh, bukan kode atau deploy yang salah. Desktop yang baru memasang PWA-nya langsung menampilkan ikon yang benar, mengonfirmasi ini murni soal cache instalasi, bukan berkasnya.

#### Ikon notifikasi ganda: sudah dicoba dilepas DUA KALI, dua kali gagal

Owner menanyakannya lagi 31 Agustus 2026: kenapa notifikasi menampilkan logo
dua kali, di kiri dan di kanan. Pertanyaan yang sama yang melahirkan
keputusan pertama.

**Percobaan kedua dilakukan dengan dasar yang masuk akal:** aplikasinya kini
sudah TERPASANG sebagai PWA, sehingga diduga Android akan memakai ikon
aplikasinya sendiri dan huruf tidak akan muncul. Diuji langsung di perangkat
Owner setelah deploy.

**Hasilnya: huruf "C" tetap muncul.** Status terpasang tidak mengubah apa
pun. Dugaan itu salah, dan `icon` dikembalikan pada hari yang sama.

**Kesimpulan yang berlaku dan jangan diulang:** logo tampil dua kali adalah
perilaku bawaan Android untuk notifikasi web. Itu lebih baik daripada avatar
huruf yang disangka inisial nama pengirim. Jangan mencoba melepasnya untuk
ketiga kalinya tanpa alasan baru yang benar-benar kuat -- dan kalau dicoba,
uji di perangkat sungguhan sebelum menganggapnya berhasil.

**Yang justru berhasil dari sesi itu:** ikon status bar. Owner menanyakan
kenapa ia cuma bentuk putih tanpa logo -- itu memang tidak bisa diperbaiki
dengan memasang logo, karena Android HANYA membaca kanal alpha lalu
mewarnainya sendiri. Yang bisa diperbaiki cuma bentuk siluetnya: dari
perisai luar logo (yang pada ~24px kehilangan seluruh detail dan tampil
sebagai bidang penuh) menjadi **siluet kepala sapi** -- tanduk melengkung,
telinga mendatar, moncong membulat -- yang masih terbaca pada ukuran itu.

Asetnya dibangkitkan dari kode (Pillow), jadi proporsinya bisa disetel ulang
tanpa berkas desain terpisah.

#### Badge notifikasi butuh aset TERSENDIRI, bukan logo berwarna

Ditemukan 26 Agustus 2026, setelah ikon besar (`icon`) dibereskan tapi ikon kecil di status bar (`badge`) masih tampil sebagai blok padat yang terlihat "terpotong".

**Android hanya membaca KANAL ALPHA gambar `badge`, lalu mewarnainya sendiri** (biasanya putih) dan memotongnya ke lingkaran kecil di status bar. `icon` dan `badge` bukan skala berbeda dari gambar yang sama — keduanya perlu **desain berbeda**. Memberinya `pwalogo-maskable-192.png` (logo berwarna penuh, berlatar **putih solid**) membuat seluruh kanvas dianggap "isi" karena alpha-nya seragam, sehingga badge-nya tampil sebagai blok padat tanpa detail, bukan siluet yang bisa dikenali.

**Aset baru `pwalogo-badge-192.png`**: siluet putih di atas latar **transparan**, dibuat dari kanal alpha `pwalogo.png` asli (bukan versi maskable) lalu warnanya dipaksa putih penyeluruh. `icon` tetap memakai `pwalogo-maskable-192.png` (perlu warna dan area aman, karena itu memang ikon besar berwarna). `badge` memakai aset baru ini.

Ada test yang menjaga keduanya sekaligus: `icon` wajib mengandung "maskable", `badge` wajib **TIDAK** mengandung "maskable" dan wajib benar-benar punya piksel transparan (bukan sekadar nama file yang benar — isinya diperiksa piksel per piksel).

#### Alur notifikasi Request Beef, dan siapa penerimanya

Dikunci `RequisitionNotificationFlowTest`, seluruhnya lewat aksi UI sungguhan.

| Tahap | Penerima |
|---|---|
| Request dibuat | pemegang `review_product_requisitions` (purchasing) |
| Ditolak purchasing | **pemohon**, lewat `TaskNotifier::notifyUser()` |
| Disetujui purchasing | pemegang `approve_product_requisitions` (finance) |
| Dikembalikan finance | purchasing |
| Disetujui finance, PO terbit | purchasing |

Yang dijaga bukan sekadar "ada notifikasi terkirim", melainkan **siapa** yang menerima di tiap tahap. Salah sasaran tidak menimbulkan error apa pun: dokumennya tetap tersimpan, dan orang yang seharusnya bertindak cuma tidak pernah tahu ada yang menunggunya. Ada pula test yang memastikan PO tetap terbit meski layanan push mati.

**Request Material memakai alur yang sama persis**, disamakan 26 Agustus 2026 atas permintaan Owner. Statusnya kembar (`Requested`, `Pending Finance`, `Returned to Purchasing`, `PO Created`, `Rejected`), permission-nya sepola (`review_material_requisitions`, `approve_material_requisitions`), jadi tabel di atas berlaku sama dengan mengganti kata Beef menjadi Material. Dikunci `MaterialRequisitionNotificationFlowTest`. Sebelumnya modul itu **tidak punya notifikasi sama sekali**, bahkan toast untuk pelakunya sendiri.

`RequisitionTranslationCoverageTest` kini memindai **kedua** modul. Alurnya kembar sehingga teksnya tumbuh berbarengan, dan lubang bahasa yang sama gampang terulang di sebelahnya.

**Dan lubang itu memang terulang, 28 Agustus 2026.** Alur notifikasi diperluas (pemohon ikut diberi tahu saat purchasing approve, plus notifikasi resubmit) dengan **enam kunci baru yang tidak didaftarkan**, ditambah grup navigasi `ACCOUNTING`. Test-nya menangkapnya — itu memang gunanya.

**Temuan lanjutan yang lebih luas:** `lang/id.json` punya **173 kunci yang tidak ada di `lang/en.json`**, sementara sebaliknya nol. Dampak nyatanya kecil karena Laravel menampilkan kuncinya sendiri (yang kebetulan sudah bahasa Inggris), tetapi itu melanggar konvensi yang sengaja dipilih proyek ini: kunci Inggris tetap didaftarkan meski nilainya sama, supaya penyeragaman istilah nanti cukup mengubah berkas bahasa tanpa menyentuh kode. **Menyisir 173 kunci itu pekerjaan tersendiri dan BELUM dikerjakan.**

Yang sudah ditutup baru yang ada di cakupan kerja sekarang: sebelas kunci pada halaman **View PO Product, View PO Material, dan View Payable**. Ketiganya kini dijaga `it_registers_every_payment_page_string_in_both_languages`, yang memeriksa **seluruh** `__()` di halaman itu — bukan cuma teks notifikasi seperti pemindai modul Request. Bisa seketat itu karena ketiga halaman itu masih baru dan bersih.

#### Notifikasi kini juga mengabari PEMOHON saat request-nya maju

Keputusan Project Owner 27 Agustus 2026 (`515c2a8`). Sebelumnya pemohon hanya dikabari saat ditolak; kalau disetujui ia tidak mendengar apa-apa sampai PO terbit, jadi tidak tahu pengajuannya sudah bergerak.

Sekarang purchasing approve mengirim **dua** notifikasi: ke finance (giliran bertindak) dan ke pemohon (kabar bahwa request-nya maju). Berlaku di kedua modul, dan ditambah notifikasi saat request yang ditolak diajukan ulang.

Test lama justru menegaskan pemohon TIDAK diberi tahu di tahap itu, sehingga gagal begitu keputusannya berubah — persis fungsinya. Sudah diperbarui.

#### `GlobalTaskPoller` sekarang bisu total

`a4dfd22` mengosongkannya: seluruh toast lintas-pengguna dihapus karena tugas itu sudah sepenuhnya diambil alih Web Push PWA. Membiarkan keduanya hidup membuat pemberitahuan yang sama muncul dua kali lewat jalur berbeda.

Komponennya sengaja **dipertahankan** (masih ada `wire:poll`), hanya isinya dikosongkan. Dua test lama yang menyetel properti checkpoint (`lastSalesOrderCheckAt` dan kawan-kawan) diganti menjadi penjagaan atas keputusannya: poller boleh ada, tapi tidak boleh memancarkan notifikasi apa pun, dan properti `*CheckAt` tidak boleh hidup lagi.

**Toast buatan tangan untuk PELAKU aksinya sendiri dihapus, 26 Agustus 2026.** `c766adf` sempat menambah toast eksplisit ("Approved successfully", "PO Generated successfully", "Returned successfully", "Rejected successfully") di keempat halaman keputusan (Review dan Finance, kedua modul). Keputusan Owner: dihapus — push notification sudah cukup memberi tahu **orang lain** yang harus bertindak berikutnya, dan toast buatan tangan ini dianggap berlebihan bagi pelaku aksinya sendiri.

**Toast BAWAAN Filament tetap ada dan sengaja tidak disentuh** — misalnya toast "Created" saat `CreateRecord` menyimpan. Bedanya: toast bawaan itu melekat pada siklus hidup record (create/save) yang memang jadi tanggung jawab Filament, sedangkan yang dihapus adalah `Notification::make()` yang sengaja ditulis manual di dalam `action()` sebuah `Actions\Action`. Toast **penjagaan** (warning/danger — status tidak valid, request kosong, harga belum lengkap) juga sengaja dipertahankan; itu bukan konfirmasi "berhasil", melainkan alasan kenapa aksinya diblokir.

Dikunci `RequisitionActorToastTest`, delapan kombinasi (2 modul × 2 halaman × approve/reject), memanggil aksinya lewat `callAction()` sungguhan lalu memeriksa `session('filament.notifications')` tidak memuat keempat judul itu. Sudah diverifikasi test-nya gagal saat salah satu toast dikembalikan.

#### Belum ada buku besar; `bank_transactions` baru dipakai separuh

Ditanyakan Owner 26 Agustus 2026. Kondisi sebenarnya:

- **`payables` ADA** — utang lengkap dengan `amount`, `paid_amount`, `balance`, `due_date`, `status`, dan sumber polimorfik.
- **Tabel jurnal TIDAK ADA.** Tidak ada chart of accounts, tidak ada journal entry berpasangan.
- **`bank_transactions` ADA tapi baru dipakai satu sisi**: hanya `ReceivePayment` yang menulisnya (`type => 'in'`). Itulah kenapa terasa tidak ada gunanya.

**Celah yang sudah pasti bug, apa pun keputusan desainnya:** `SupplierPayment` **tidak menulis `bank_transactions` sama sekali**, sehingga uang muka yang dibayar lewat transfer tidak mengurangi saldo bank di mana pun. Sisi piutang sudah benar; sisi supplier belum punya pasangan `type => 'out'`-nya.

**Kenapa DP tidak boleh dicatat sebagai utang:** DP dibayar saat order, barangnya belum diterima. Uang sudah keluar dan supplier justru berutang barang — itu **aset** (Uang Muka Pembelian), bukan kewajiban. Utang usaha baru lahir saat barang diterima. Membuat baris `payables` saat PO terbit akan menggelembungkan neraca di dua sisi sekaligus.

**Arah yang disepakati Owner:** laporan keuangan sesederhana mungkin, uang dan barang punya buku besar dan laporan masing-masing. `bank_transactions` dipromosikan menjadi buku kas tunggal — **dikerjakan 26 Agustus 2026**, meski buku besar penuh (chart of accounts, journal entry berpasangan, laporan laba rugi/neraca) tetap ditunda.

#### DP ke supplier sekarang tercatat sebagai pengeluaran

Dikerjakan 26 Agustus 2026, menutup celah di atas. Owner tegas: DP tetap uang yang sungguh-sungguh keluar dan wajib tercatat, terlepas dari kapan utangnya lahir.

**Ditaruh sebagai model event, bukan dipanggil manual di tiap halaman.** `SupplierPayment::booted()` mendengarkan `created` dan langsung menulis `BankTransaction` (`type => 'out'`) plus mengurangi `bank_accounts.balance`. Pilihan ini disengaja — memanggilnya manual di tiap pemanggil gampang lupa disalin ke jalur baru; menaruhnya di model membuatnya berlaku otomatis dari jalur mana pun DP itu lahir, sekarang maupun nanti.

**Metode transfer memakai rekening yang dipilih finance.** Metode tunai memakai **satu akun KAS tunggal**, dibuat lewat `BankAccount::cashAccount()` — `bank_transactions.bank_account_id` tidak boleh NULL, dan sebuah kas tunai memang sebuah akun, sama seperti rekening bank. Helper itu dikunci `->lockForUpdate()` mengikuti pola generator dokumen lain di proyek ini, supaya dua pembayaran tunai bersamaan tidak menciptakan dua baris KAS. Didaftarkan juga di `DatabaseSeeder` supaya barisnya sudah terlihat sejak awal.

**Ditemukan sekaligus dibereskan:** modal pembayaran finance di Request Material belum punya pemisah ribuan maupun batas atas nilai (padahal Request Beef sudah, sejak #99). Disamakan persis — termasuk batas atasnya ikut menghitung pajak pembelian material lewat `Supplier::is_tax_11`, bukan `has_tax` milik Product. **Jangan tertukar nama kolomnya**, keduanya sama-sama menandai "supplier ini kena pajak" tapi di modul yang berbeda.

**Yang masih di luar cakupan ini, sengaja tidak disentuh:** `BankAccountResource` tetap `canAccess() => false` (tiga modul finance dimatikan atas instruksi Owner). Tidak ada satu pun bank_accounts row selain KAS di database mana pun — jadi metode transfer di modal pembayaran akan menemukan dropdown Bank Account kosong sampai Owner memutuskan mengaktifkan Resource itu atau menambah rekening lewat cara lain.

#### Teks notifikasi wajib terdaftar dua bahasa, dan ada test yang menjaganya

Satu alur notifikasi sempat ditambahkan lengkap dengan sepuluh teks ber-`__()` tanpa satu pun didaftarkan di berkas bahasa. Tidak ada error: Laravel menampilkan kuncinya apa adanya, sehingga pengguna yang memilih Indonesia tetap melihat kalimat bahasa Inggris dan tidak ada yang menyadarinya.

`RequisitionTranslationCoverageTest` kini memindai argumen `TaskNotifier::notify*` dan judul/isi toast Filament di modul Request Beef, lalu memastikan setiap kuncinya terdaftar di `id.json` **dan** `en.json`.

**Sengaja dibatasi pada teks notifikasi.** Label formulir modul ini sudah lama banyak yang belum terdaftar — sekitar 20 kunci pada berkas ID dan lebih banyak lagi pada EN, termasuk `Header Information`, `Due Date`, `Item Details`, `Price`, `Summary`, dan `Rejection Info`. Membereskannya pekerjaan tersendiri dan belum dikerjakan; jangan menganggapnya sudah beres hanya karena test ini hijau.

### Uang muka ke supplier: dokumen tersendiri, bukan kolom di request

Keputusan Owner, 24 Agustus 2026, untuk poin 5 pada #77.

**Fakta yang menentukan bentuknya:** utang (`Payable`) lahir saat barang **DITERIMA**, sementara DP dibayar saat **ORDER**. Jadi saat DP dicatat, utangnya belum ada.

Kalau DP disimpan sebagai kolom di tabel request, saat barang datang sistem akan membuat utang sebesar **nilai penuh** tanpa ada yang tahu DP sudah dibayar. Kesalahan itu **tidak menimbulkan error apa pun** dan baru ketahuan saat supplier menagih — jenis kesalahan paling mahal karena tampak benar.

**Bentuk yang dipakai:**

- Tabel `supplier_payments` berdiri sendiri, sumbernya polimorfik (`source_type`/`source_id`) supaya kelak bisa dipakai dari PO, Goods Receipt, atau modul hutang tanpa mengubah struktur.
- Finance mengisinya saat approve — tetap praktis, sesuai maunya Owner.
- Saat `Payable::generateForGoodsReceipt*()` berjalan, uang muka **ditelusuri lewat rantai dokumen** `Goods Receipt → PO → Request` lalu dipotongkan ke utangnya.
- Selisih `amount` dan `allocated_amount` adalah uang muka yang masih menggantung.

**Bila nilai pembayaran 0, seluruh bagian itu dilewati** — tidak ada dokumen pembayaran bernilai nol. Dokumen murni jadi utang dan TOP mulai berjalan saat barang diterima.

**Uang muka dibatasi nilai tagihannya sendiri.** Kelebihan bayar tidak menimbulkan error apa pun: ia baru terasa jauh kemudian, saat utang dihitung dan `allocated_amount` tidak pernah terpakai habis, lalu menggantung selamanya sebagai uang muka semu. Batasnya dihitung dari isi **form**, bukan record tersimpan, dengan alasan yang sama seperti pemeriksaan harga — finance boleh memperbaiki harga di halaman itu dan batasnya harus ikut angka terbaru. Perhitungan pajaknya mengikuti `updateTotalAmount()` supaya batasnya sama persis dengan nilai yang kelak menjadi utang.

Kolomnya memakai `$money($input, ',', '.', 0)`. **Aman karena berada di form modal, bukan di dalam Repeater** — larangan `$money()` hanya berlaku di dalam Repeater. Formatnya wajib gaya Indonesia; format gaya Inggris (`1,000,000`) justru terbaca `parseNumber()` sebagai 1,0 dan uang mukanya menyusut tanpa error.

#### `save(false)` masih memunculkan toast "Saved"

Tanda tangannya `save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true)`. Menulis `save(false)` hanya mematikan redirect-nya; toast "Saved" tetap terkirim, sehingga pengguna melihat **dua toast sekaligus** — "Saved" dari penyimpanan dan pesan hasil aksinya sendiri. Yang benar `save(false, false)`. Ada test yang menjaganya.

**Catatan penting soal `payments` yang sudah ada:** tabel itu untuk **terima uang dari customer** (`customer_group_id`, beralokasi ke `invoice_id`), bukan bayar ke supplier. Jangan tertukar. Sebelum ini, sisi pembayaran ke supplier belum ada sama sekali — kolom `paid_amount` di `payables` ada tapi tidak pernah ada yang mengisinya.

Ada test yang menjaga uang muka tidak terpakai dua kali bila utang dihitung ulang, dan tidak terpakai melebihi nilai utangnya.

### Satu jalur per tahap keputusan — halaman View wajib baca-saja

Penjagaan tidak ada gunanya bila ada jalur pintas yang melewatinya. Ini pernah terjadi dan memakan waktu untuk ketahuan.

Modul Requisition dulu punya **dua jalur** untuk tiap tahap:

1. Halaman khusus (`ReviewXxx`, `ApproveFinanceXxx`) — form bisa diedit, ada validasi
2. **Modal di halaman View** — menulis status langsung, tanpa validasi apa pun

Yang dipakai operator justru yang kedua, karena tombolnya ada di halaman yang dia buka. Padahal di halaman View harga tidak bisa dilihat apalagi diperbaiki, sehingga dokumen berharga nol lolos ke finance meski penjagaannya sudah dipasang.

Lebih parah: **`ApproveFinanceProductRequisition` dan `ApproveFinanceMaterialRequisition` tidak terdaftar di `getPages()`** sehingga tidak punya rute sama sekali. Seluruh isinya, termasuk penjagaan harga, adalah kode mati. Semua persetujuan finance mengalir lewat modal View.

**Aturannya sekarang:** halaman View murni baca-saja. Tombol keputusan **mengarahkan** ke halaman khususnya, tidak membuka modal. Keputusan yang bergantung pada data harus diambil di tempat data itu bisa dilihat dan diperbaiki.

Ada test yang menjaga dua hal sekaligus: halaman View tidak boleh menulis `Pending Finance` atau `PO Created`, dan halaman Finance Approval wajib punya rute terdaftar.

**Pelajaran umum:** saat memasang validasi, cari dulu **semua** jalur yang bisa mengubah status yang sama. Grep `'status' =>` di seluruh modul, jangan cuma di halaman yang sedang dikerjakan.

### Halaman keputusan wajib menjaga statusnya sendiri, dan penjagaan wajib menutup dokumen kosong

Lanjutan langsung dari keputusan di atas, ditemukan 26 Agustus 2026 saat menyisir Request Beef. Menyembunyikan tombol saja ternyata belum menutup jalurnya.

**Menyembunyikan tombol bukan penjagaan.** Halaman View sudah menyembunyikan tombol Review dan Finance Approval menurut status, tetapi kedua halamannya sendiri tidak memeriksa status sama sekali dan tetap bisa dicapai dengan mengetik URL. Akibatnya dokumen ber-status `PO Created` masih bisa dibuka di halaman finance lalu di-Approve lagi: **PO kedua terbit berikut dokumen uang muka kedua, tanpa error apa pun.** Sekarang setiap halaman keputusan memeriksa statusnya di `mount()` dan memantulkan pengguna ke halaman View. `generatePurchaseOrder()` menolak bila PO-nya sudah ada, sebagai lapis kedua supaya penjagaannya tidak bergantung pada satu halaman. PO yang sudah di-soft-delete sengaja tidak dihitung agar dokumen yang dibatalkan masih bisa diterbitkan ulang.

**Penjagaan yang hanya memeriksa baris berisi akan meloloskan dokumen kosong.** `itemsMissingPrice()` hanya menelusuri baris yang punya `product_id`. Bila seluruh baris dikosongkan, daftar "harga kosong" ikut kosong dan dokumen dianggap lulus — lalu naik ke Finance dengan **0 item dan total 0**, persis PO bernilai nol yang penjagaan harga dibangun untuk mencegahnya. Saat menulis penjagaan berbasis daftar, periksa juga kasus daftarnya kosong; "tidak ada yang salah" dan "tidak ada apa-apa" bukan hal yang sama.

**Baris yang tidak bisa diklik bisa mematikan sebuah alur.** `recordUrl()` mengembalikan `null` untuk status `Rejected`, sementara tombol **Resubmit** hanya ada di halaman View. Jalur "ditolak → perbaiki → ajukan ulang" jadi buntu tanpa ada pesan apa pun. Aturannya sekarang: **pemohon selalu boleh membuka dokumennya sendiri, apa pun statusnya.** Pemegang permission review/approve juga boleh — dokumen yang ditolak adalah arsip keputusan mereka.

**Nama relasi yang salah pada export tidak menghasilkan kolom kosong, melainkan gagal fatal.** Export Excel pada halaman Detail memanggil `$record->requisition`, padahal relasinya bernama `productRequisition`; versi PDF-nya sejak awal benar. Gejalanya `Attempt to read property on null` setiap kali tombolnya ditekan. Karena kedua export dirawat terpisah, perubahan pada salah satunya perlu diperiksa di dua tempat.

**Yang sudah dipastikan TIDAK bermasalah:** redirect kembali ke Index setelah approve purchasing berjalan normal pada jalur wajar — diuji langsung lewat `callAction()`. Bila gejala poin 3 daftar Owner muncul lagi, pemicunya bukan redirect yang hilang melainkan `$this->save(false)` yang gagal validasi lebih dulu.

### Harga kosong di Request: purchasing yang mengisi, bukan pemohon

Keputusan Owner, 24 Agustus 2026. Tiga situasi yang dulu dipaksa masuk dua tombol:

| Situasi | Yang terjadi |
|---|---|
| Harga kosong, barang benar | **Purchasing mengisi sendiri** — dialah yang tahu harga supplier |
| Barang atau qty salah | Reject dengan catatan |
| Memang tidak jadi dibeli | Reject |

Owner memutuskan **tidak** menambah tombol "Kembalikan ke Pemohon"; Reject dianggap cukup.

**Penyempurnaan skema (keputusan lanjutan Owner):** harga adalah tanggung jawab **purchasing**, bukan pemohon. Kolom harga tetap ada di form pemohon sebagai perkiraan opsional, tetapi di halaman review purchasing kolom itu **wajib** terisi dan tidak boleh nol — ditandai `required` sehingga kesalahannya muncul langsung di kolomnya, bukan sekadar toast setelah menekan Approve. Dengan begitu finance tidak pernah menerima dokumen berharga kosong.

Catatan penting soal alur: **tombol "reject" di halaman finance sebenarnya BUKAN reject.** Ia menyetel status ke `Returned to Purchasing`, jadi jalur pulang ke purchasing sudah tersedia. Kalau kunci di finance sampai berbunyi karena data lama, finance tinggal memakai tombol itu.

Tombol Approve dikunci selama masih ada harga 0, **di purchasing maupun di finance**. Finance dikunci sebagai lapis kedua karena data lama atau perubahan langsung lewat database bisa lolos dari lapis pertama, dan PO bernilai nol akan menciptakan utang palsu yang mengacaukan perhitungan TOP.

Pemeriksaan membaca isi **form**, bukan record di database, supaya harga yang baru diketik purchasing langsung terhitung tanpa perlu menyimpan lebih dulu. Pesannya menyebut nama barang yang kosong, bukan sekadar "ada harga yang belum diisi".

### Desimal di papan ketik ponsel

Pemformat dikunci ke satu format (titik ribuan, koma desimal) dan **tidak** ikut setelan perangkat, jadi tampilannya konsisten di mana pun. Justru `<input type="number">` yang perilakunya ikut regional — satu alasan lagi untuk tidak memakainya.

Sisa risikonya cuma satu: papan ketik angka sebagian perangkat mengeluarkan `.` sebagai tombol desimal. Karena itu titik yang diketik pengguna pada field qty diubah menjadi koma lewat `x-on:keydown`, supaya pemformat cukup mengenal satu bentuk desimal.

### RENCANA BERIKUTNYA: modul Request Beef

Daftar dari Owner, 24 Agustus 2026. Nomor 2 sudah selesai; sisanya menunggu.

| No | Kebutuhan | Ukuran |
|---|---|---|
| 1 | ~~Pemisah ribuan otomatis pada input price~~ | **selesai** |
| 2 | ~~Qty tidak ter-load di halaman View~~ | **selesai** |
| 3 | ~~Setelah approve purchasing dan approve finance, balik ke Index~~ | **selesai** — redirect tertahan bila `save()` gagal validasi (itu wajar), dan sekarang ditambahkan toast sukses di akhir blok sehingga jelas bila proses berhasil. |
| 4 | ~~Cara menolak bila ada barang tanpa harga~~ | **selesai** — purchasing mengisi sendiri, Approve dikunci di dua tahap |
| 5 | ~~Input pembayaran saat approve finance~~ | **selesai** — tersimpan sebagai dokumen `supplier_payments` dan otomatis memotong utang |
| 6 | ~~Notifikasi PWA~~ | **selesai untuk modul ini** — alur penuh: pemohon diberitahu jika ditolak purchasing; finance diberitahu jika disetujui purchasing; purchasing diberitahu jika finance menolak atau menyetujui (PO terbit). |

Owner menyebut masih ada satu hal umum lagi yang belum teringat.

### UTANG TEKNIS: urutan Tab belum wajar di banyak form

**Belum dibereskan. Perlu satu sapuan tersendiri, jangan ditambal per modul.**

Gejalanya terlihat di form Create Beef, dan hampir pasti terulang di form lain: kursor mendarat di satu field, lalu menekan Tab melompat ke tempat yang tidak diduga — bahkan langsung ke tombol Create sebelum semua field terlewati.

Penyebabnya kombinasi tiga hal:

1. **`->columns(2)`** membuat susunan visual dua kolom, sementara Tab tetap mengikuti urutan DOM. Yang terlihat berdampingan justru tidak berurutan saat di-Tab.
2. **`autofocus()` bisa mendarat di tengah form**, sehingga field di atasnya tidak pernah terjangkau lewat keyboard.
3. **Field `disabled()`** (misalnya `code` yang terisi otomatis) dilewati browser, jadi lompatannya terasa makin jauh.

Owner menilai membetulkannya satu per satu per modul akan makan waktu terlalu lama, jadi sengaja ditunda. Saat dikerjakan nanti, perlakukan sebagai **satu pekerjaan lintas modul**: tentukan dulu urutan baku entri data, baru sesuaikan susunan field dan kolomnya.

Penambal yang sudah dipasang di **seluruh master data**: toggle **Set Active dihilangkan dari halaman Create** (hanya muncul di Edit). Data master yang baru dibuat sudah pasti aktif, seluruh kolom `is_active` memang `DEFAULT 1` di database, dan togglenya cuma menambah satu perhentian Tab yang tidak berguna.

Berlaku di: BankAccount, Grade, Material, Supplier, User, Warehouse, Customer, dan Product. Ada test yang menjaganya.

### autofocus wajib di field pertama, bukan sekadar ada

Di form Create Beef, `->autofocus()` sempat terpasang di field `name` yang berada di urutan keempat. Akibatnya kursor mendarat di tengah form, dan menekan Tab dari sana **melewatkan tiga field di atasnya** lalu langsung menuju tombol — pengguna tidak pernah sampai ke `structure_type`, `parent_id`, dan `category_id` lewat keyboard.

Aturan di `project.md` menyebut "field pertama", dan inilah alasannya: bukan soal kenyamanan, tapi karena autofocus di tengah form secara efektif **menyembunyikan field-field di atasnya** dari alur keyboard.

**Pengecualian yang berlaku sekarang:** di form Create Beef, Owner ingin kursor mendarat di **Beef Name**, bukan di field pertama. Itu keputusan Owner dan dipertahankan; konsekuensinya field di atas Beef Name tidak terjangkau lewat Tab, dan itu bagian dari utang teknis urutan Tab di atas.

### Bilingual dijaga otomatis, bukan diaudit manual

**Sejak 28 Agustus 2026 dijaga menyeluruh, bukan lagi per modul.** `BilingualParityTest` memastikan `id.json` dan `en.json` memuat kunci yang **sama persis** — dua arah. Sebelumnya ada 162 kunci yang hanya hidup di `id.json`; sekarang nol, dan keduanya berisi 689 kunci.

Gejalanya dulu tidak pernah terlihat sebagai error: Laravel menampilkan **kuncinya sendiri** saat terjemahan tidak ditemukan, jadi teksnya tetap muncul — hanya dalam bahasa yang salah, dan tidak ada yang menyadarinya.

#### Kunci WAJIB ditulis dalam Bahasa Inggris

Ini yang paling sering keliru dipahami. Mendaftarkan kunci berbahasa Indonesia ke `en.json` **tidak memperbaiki apa pun** — pengguna yang memilih bahasa Inggris tetap melihat teks Indonesia, karena nilainya sama dengan kuncinya. Yang benar: **ganti kuncinya di KODE** menjadi Bahasa Inggris, lalu daftarkan terjemahan Indonesianya di `id.json`.

Dua puluh empat kunci sudah dipindahkan begitu (40 penggantian di 15 berkas), misalnya `Gagal Scan` → `Scan Failed`, `Selisih` → `Variance`, `Armada` → `Fleet`. Tampilan bahasa Indonesia **tidak berubah sama sekali** — nilai `id.json`-nya dipertahankan persis seperti teks yang selama ini dilihat operator.

Ditemukan sekalian: `Nomor Segel` ternyata **duplikat** — `Seal Number` sudah ada dengan terjemahan yang tepat, hanya saja kodenya memakai kunci Indonesia. Enam entri lain sudah mati (sisa `GlobalTaskPoller` yang dikosongkan, konfirmasi Stock Opname yang berganti awalan, dan teks polos di blade cetak yang memang tidak lewat `__()`).

#### Utang yang tersisa: 43 kunci, dijaga ratchet

Masih ada **73 kunci berbahasa Indonesia** yang sudah terdaftar di kedua berkas, tersebar sampai ke modul yang belum disisir (Repack, Sales Return, Cattle Weighing, Boning). Membereskannya pekerjaan tersendiri dan **belum dikerjakan**.

Daftarnya dicatat di `tests/Fixtures/indonesian-translation-keys.json` sebagai register utang yang terlihat, dan `no_new_indonesian_translation_keys_are_introduced` menjaganya sebagai **ratchet**: kunci Indonesia BARU langsung gagal, sementara yang lama dibiarkan sampai gilirannya. Saat ada yang dibereskan, test itu justru gagal juga bila barisnya lupa dihapus dari baseline — supaya utang yang sudah lunas tidak bisa diam-diam kembali.

**Blade cetak sengaja tidak disentuh.** Teks di `resources/views/print/` dan `exports/` sebagian besar hardcode dan tidak lewat `__()` sama sekali. Membuatnya bilingual adalah pekerjaan tersendiri yang belum diputuskan perlu atau tidak — dokumen cetak untuk supplier/customer lokal mungkin memang tidak perlu.


`NavigationTerminologyTest` memindai seluruh `__()` pada modul Produk Sapi, Grade, dan Warehouse, lalu memastikan setiap kuncinya terdaftar di `lang/id.json` **dan** `lang/en.json`.

Kunci bahasa Inggris sengaja didaftarkan meski nilainya sama dengan kuncinya sendiri. Gunanya bukan menerjemahkan, melainkan supaya penyeragaman istilah Inggris nanti cukup mengubah berkas bahasa tanpa menyentuh kode.

Pola ini layak ditiru saat menyisir modul lain: lebih murah daripada mengaudit manual, dan tidak bisa lupa.

### Istilah "Produk Sapi", bukan "Beef" atau "Products"

Produk di sistem ini adalah hasil pemotongan sapi: **daging, tulang, offal, dan kulit**. Dua istilah lama sama-sama meleset:

- **"Beef"** terlalu sempit — tulang, offal, dan kulit bukan daging. Tapi kata ini memang familiar di lingkungan kerja.
- **"Products"** terlalu luas — tidak membedakannya dari `Material` (bahan penolong).

Istilah yang dipakai: **"Cattle Products"** (EN) / **"Produk Sapi"** (ID). Kata "Sapi" mengunci maknanya, "Produk" cukup lapang untuk menampung non-daging.

**Jebakan yang wajib dihindari: jangan pernah memakai "Stok Sapi".** Sistem ini juga menangani sapi hidup (PO Cattle, Cattle Receiving), sehingga "Stok Sapi" ambigu antara sapi hidup dan barang hasil potong. Bentuk yang benar **"Stok Produk Sapi"**. Ada test yang menjaga ini (`NavigationTerminologyTest`).

Nama kelas PHP tetap `Product*` dan tidak ikut diubah — itu lapisan kode, dan justru berguna sebagai lawan dari `Material`. Yang diseragamkan hanya yang dilihat pengguna.

**Masih berjalan:** label bahasa Inggris di resource bagian dalam sebagian masih "Beef". Penyeragaman penuhnya menunggu Project Owner menelusuri aplikasi dan mencatat di layar mana kata itu terasa janggal.

### nonPKP: invoice tanpa PPN

Wijaya Meat berstatus **nonPKP**, jadi invoice dan penjualan **tidak dikenai PPN**. Pajak hanya relevan pada pembelian material.

Absennya perhitungan pajak di `InvoiceResource::updateTotals()` **bukan bug dan bukan fitur yang tertinggal** — pernah keliru dicatat begitu, lalu dibatalkan. Kolom `invoices.tax` dan flag `customers.is_taxable` adalah sisa desain lama yang tidak terpakai di sisi penjualan.

### Password bawaan wajib `1234`

Repositori ini **publik**. Akun superuser `saepullrock` diseed dengan password `1234` justru karena sepele: middleware `CheckPasswordChange` mendeteksinya dan memaksa penggantian pada login pertama, sebelum pengguna sempat membuka menu apa pun.

Sebelumnya seeder memakai password sungguhan yang juga tertulis di `project.md`. Password itu **sudah permanen di riwayat git** dan harus dianggap bocor.

### `composer.lock` dilacak

Sempat masuk `.gitignore`. Itu keliru: pipeline deploy menjalankan `composer install`, yang tanpa lock file berperilaku seperti `composer update` dan me-resolve versi paket dari nol setiap deploy. Terbukti lokal dan server sempat punya versi dependensi berbeda tanpa ada yang menyadarinya.

### Perubahan Modul PO & Pembayaran (Agustus 2026)

#### Uang muka pindah ke halaman PO, dan apa yang ikut terbawa

**Pemindahan itu MEMUTUS rantai DP ke utang, dan baru ketahuan 30 Agustus 2026.**

`Payable::requisitionBehind()` menelusuri GR → PO → **Request**, lalu mencari uang muka yang menempel pada Request saja. Setelah DP pindah ke halaman PO, uang mukanya tersimpan dengan `source_type = PurchaseProduct` — **tidak pernah ketemu, tidak pernah dipotong**. Utang lahir sebesar nilai penuh seolah belum ada yang dibayar.

Terbukti: DP Rp 4.000.000 di PO, barang diterima Rp 10.000.000 → utang tetap Rp 10.000.000, `paid_amount` nol, status `unpaid`.

Ini persis peringatan yang sudah tertulis di bagian "Uang muka ke supplier" di bawah: *"sistem akan membuat utang sebesar nilai penuh tanpa ada yang tahu DP sudah dibayar… baru ketahuan saat supplier menagih."* Peringatannya benar; yang tidak terduga adalah pemicunya bukan desain awal, melainkan **pemindahan lokasi form** yang tampak tidak berhubungan.

**`SupplierAdvancePaymentTest` tetap hijau sepanjang itu**, karena membuat pembayaran dengan `source_type => ProductRequisition::class` — source yang sudah tidak dipakai kode mana pun. Contoh lain dari "test hijau bukan jaminan fitur jalan": test yang tidak ikut berpindah saat fiturnya berpindah.

**Perbaikannya:** `advanceSourcesBehind()` mengembalikan **seluruh** dokumen di belakang GR (PO dan Request), dan `applyAdvancesBehind()` memotongkan dari semuanya. DP lama yang menempel di Request tetap terpotong, DP baru di PO ikut terpotong, dan gabungan keduanya tidak pernah melebihi nilai utang — sisanya menggantung menunggu utang berikutnya, bukan hangus.

**Kalau kelak DP bisa dibayar dari dokumen lain lagi, tambahkan sumbernya di `advanceSourcesBehind()`** — jangan menambah pemanggilan `applyAdvancesFrom()` terpisah, supaya tidak ada jalur yang lupa disambungkan.

#### Membuka kunci GR: uang muka WAJIB dilepas, dan utangnya dipulihkan bukan dibuat ulang

Dua bug pada alur buka-kunci, ditemukan 30 Agustus 2026 saat menyisir sisa jahitan Payable. Keduanya menyentuh uang.

**Uang muka hilang permanen.** `SupplierPayment::allocateTo()` hanya MENAMBAH `allocated_amount`; tidak ada cara melepasnya. Sementara kode unlock menghapus utangnya begitu saja. Akibatnya DP tercatat "sudah terpakai" untuk utang yang sudah tidak ada — dan karena `unallocatedFor()` menyaring `allocated_amount < amount`, DP itu tidak akan pernah muncul lagi. Utang berikutnya lahir sebesar nilai penuh seolah DP-nya tidak pernah dibayar.

Sekarang `Payable::releaseAdvances()` mengembalikannya ke kolam, dan **wajib dipanggil sebelum utangnya dihapus**. Sudah dipasang di kedua Resource GR.

**GR yang sudah dibuka tidak bisa dikunci ulang.** `Payable` memakai soft delete, sehingga `$gr->payable` tidak menemukannya lagi dan `generateForGoodsReceipt*()` membuat baris BARU dengan `document_number` yang sama (nomor GR) — langsung kena unique constraint. Alur buka-kunci jadi jalan buntu. Sekarang utang yang ter-soft-delete **dipulihkan** lewat `withTrashed()`, bukan dibuatkan baris baru.

**Batas yang disengaja pada pelepasan:** bila satu utang menyerap beberapa uang muka sekaligus, pembagian per-dokumen saat dilepas bisa berbeda dari saat dialokasikan. **Total yang kembali ke kolam selalu tepat**, dan hanya itu yang menentukan perhitungan utang berikutnya — jadi tidak ada dampak pada angka utang mana pun. Yang bisa berbeda hanya laporan alokasi per pembayaran. Kalau kelak laporan itu dibutuhkan, barulah perlu tabel alokasi tersendiri (mirip `payment_allocations` di sisi piutang); jangan menambalnya dengan tebakan.



Dicatat 28 Agustus 2026 saat menyelaraskan test dengan perubahan ini.

**Lokasi barunya:** aksi `pay_down_payment` di **View PO Product / View PO Material**, bukan lagi form di dalam modal Approve & Generate PO pada halaman Finance Approval. Ini lurus dengan akuntansi: DP dibayar saat order, dan PO adalah dokumen order itu sendiri. `PayableResource` juga menerima pembayaran dengan bentuk aksi yang sama untuk pelunasan hutang.

**Yang selamat tanpa disentuh sama sekali: pencatatan DP ke buku kas.** `recordCashOutflow()` dipasang sebagai **model event** pada `SupplierPayment::created`, bukan dipanggil manual di halaman pembuatnya. Ketika form DP dipindah ke tiga halaman yang sama sekali berbeda, pencatatan kas ikut otomatis tanpa satu baris pun ditambahkan. **Ini alasan konkret kenapa aturan lintas-dokumen sebaiknya hidup di model, bukan di halaman** — halaman berpindah, model tidak.

**Bedanya dengan versi lama yang perlu diketahui:**

| | Versi lama (Finance Approval) | Versi sekarang (View PO) |
|---|---|---|
| Nama field | `payment_amount` | `amount_input` |
| Pemformat | `RawJs::make('$money(...)')` | listener Alpine `Intl.NumberFormat("id-ID")` |
| Parser | `parseNumber()` | `(float) str_replace('.', '', $value)` |
| Batas atas | tagihan dari isi FORM | `$this->record->total_amount` milik PO |
| Batas bawah | tidak ada | wajib `> 0` |

Parser barunya lebih sederhana dan aman untuk nilai rupiah bulat, tetapi **tidak menangani desimal koma** seperti `parseNumber()`. Selama kolom itu hanya menerima digit (listener-nya membuang non-digit), keduanya setara. Kalau kelak kolom itu perlu menerima desimal, pindahkan ke `parseNumber()` — jangan tambal `str_replace` lagi.


Disepakati dan dikerjakan beberapa perbaikan terkait PO dan Uang Muka:
- **Tombol Hapus PO:** Sengaja ditambahkan di halaman *View Purchase Product* (berlaku juga untuk Material). **Aturan bisnis:** PO hanya boleh dihapus JIKA barang belum pernah diterima sama sekali (`goodsReceipts()->count() === 0`). Bila sudah ada barang masuk, PO terkunci demi integritas data. Jika PO dihapus, status Request terkaitnya akan dikembalikan ke `Pending Finance`.
- **Cetak PO & Tampilan DP:** Cetakan PO (`resources/views/print/po-*.blade.php`) dimodifikasi menggunakan CSS `white-space: nowrap` agar tata letaknya tidak berantakan/terpotong saat menampilkan teks DP. Juga ditambahkan baris **Ringkasan DP dan Sisa Tagihan** agar supplier tahu persis sisa pembayaran. Nama penandatangan (Reviewer) juga sudah dinamis.
- **Pemisah Ribuan pada Form Pembayaran:** Semua input nominal pembayaran (baik DP di PO maupun pelunasan di *PayableResource*) menggunakan pemisah ribuan gaya Indonesia (`number_format` & Alpine `x-mask`). Nilai akhirnya dikonversi menggunakan logika `str_replace` / `parseNumber()` sebelum divalidasi dan disimpan untuk mencegah nilai terpotong.
- **Bilingual & Lokalisasi (UI Finance):** Semua string statis (awalnya di-*hardcode* bahasa Indonesia) di tombol, tabel Relation Manager, dan form pembayaran sudah dikembalikan ke praktik standar yaitu dibungkus dengan `__()` memakai *key* bahasa Inggris (contoh: `__('Pay Down Payment')`), lalu terjemahannya didaftarkan di `lang/id.json`. Praktik *hardcoding* langsung dengan bahasa Indonesia di kode PHP **tidak disarankan** karena melanggar uji *NavigationTerminologyTest* dan menyulitkan lokalisasi di masa depan.

### Perbaikan Alur Goods Receipt & UI Sidebar (Agustus 2026)

- **Modal Pilihan Tindakan Lanjutan di GR:** Sebelumnya, menyimpan header *Goods Receipt Product* hanya menahan *user* di halaman "parkir" (Input). Sekarang, ketika tombol *Save* diklik dan berhasil, sistem akan memunculkan sebuah **Modal Pilihan** yang memberi 3 jalur: Mulai *Scan* Barcode, Mulai *Labeling* Manual, atau Nanti Saja (Kembali ke Daftar).
- **Tujuan Tombol Back (Scan & Labeling):** Tombol *Back* di halaman *Scan* dan *Labeling* GR diubah arahnya ke halaman **Daftar Index GR**, BUKAN kembali ke halaman Input (agar tidak membingungkan/terjebak *loop*).
- **Bug Sidebar yang Persisten (Global Minimize):** Halaman *Scan* (GR, Stock Take, Tally) sebelumnya menggunakan baris Javascript `window.Alpine.store('sidebar').close()` untuk meminimize sidebar secara paksa. Hal ini menyebabkan Filament **menyimpan preferensi ini ke *localStorage***, sehingga sidebar akan terus mengecil di semua halaman aplikasi. **Solusi:** JS tersebut dihapus total. Sebagai gantinya, sidebar disembunyikan HANYA secara visual menggunakan CSS `display: none !important;` di masing-masing halaman *Scan*, sehingga preferensi global pengguna tidak terganggu.
- **Fitur Buka Kunci GR:** Ikon gembok di index GR dibuat dinamis. *Unlock* GR diperbolehkan HANYA JIKA hutang (*Payable*) yang terbentuk belum pernah dicicil/dibayar. Jika di-*unlock*, draft hutang tersebut akan otomatis dihapus. Jika sudah ada pembayaran, sistem akan melempar *Exception* penolakan.
- **Sistem Kunci GR Material:** Kolom `is_locked` ditambahkan ke `goods_receipt_materials`. *Payable* kini HANYA di-*generate* saat GR dikunci, bukan lagi saat disimpan (*Create*/*Edit*). Tombol *Delete* juga disembunyikan apabila GR Material sudah memiliki item data atau sudah dikunci, sama seperti logika di GR Product.

---

### Log Viewer DIPERTAHANKAN — ia satu-satunya jendela ke error yang ditelan

Owner sempat menganggapnya tidak berguna dan ingin dihapus (28 Agustus 2026). Diperiksa dulu di server, dan datanya justru sebaliknya.

`laravel.log` berisi **11 ERROR**, dan sebagian besar merekam bug yang Owner sendiri perbaiki sehari sebelumnya: `An attempt was made to evaluate a closure for [Filament\Forms\Component...]` (yang menghasilkan `6bc755b`) dan `Unable to find component: [...RelationManager...]` (yang menghasilkan `db233a9`). Alatnya bekerja; yang tidak terjadi adalah ada yang membukanya.

**Alasan yang lebih menentukan: aplikasi ini SENGAJA menelan sebagian error.** `TaskNotifier` dibungkus `try/catch` supaya kegagalan notifikasi tidak pernah menggagalkan penyimpanan dokumen — itu keputusan yang benar dan tetap dipertahankan. Konsekuensinya kegagalan push **tidak muncul di layar sama sekali**. Saat notifikasi tidak sampai ke ruby, satu-satunya bukti adalah satu baris `production.WARNING` di log itu. Menghapus log viewer membuat seluruh kelas kegagalan tersebut permanen tak terlihat, dan memaksa SSH tiap kali — mustahil dari HP.

**Keluhan Owner yang sebenarnya bukan "tidak berguna" melainkan "tidak tahu gunanya apa".** Nama menunya, "Log Viewer", memang tidak memberi tahu apa-apa. Diganti menjadi `System Error Log` / **Log Error Sistem**.

**Tautan pihak ketiga di dalamnya dimatikan** atas permintaan Owner — di aplikasi internal, tombol donasi ke pembuat paket membingungkan karena disangka bagian dari aplikasi:

- Tombol **"Buy me a coffee"** punya flag resmi: `config('log-viewer.show_support_link') => false`. Selesai dengan satu opsi.
- **Ikon GitHub** tidak punya opsi apa pun — hardcode di komponen Vue yang sudah terkompilasi ke `app.js`. Disembunyikan lewat CSS di view timpaan `resources/views/vendor/log-viewer/index.blade.php`, memakai selektor **atribut href** (bukan kelas) supaya tetap bekerja meski paketnya mengubah nama kelas.

**Perhatian saat menaikkan versi paket:** view timpaan itu salinan penuh, jadi perubahan view dari paket tidak ikut. Bandingkan dengan berkas aslinya setelah upgrade. Ada test yang menjaga view timpaannya masih dipakai — kalau terhapus, Laravel diam-diam kembali ke view bawaan dan ikonnya muncul lagi tanpa ada yang menyadarinya.

**Ditemukan saat mengerjakannya:** view timpaan yang sudah ada ternyata salinan versi paket yang **lebih tua** — ia memuat aset dengan path hardcode (`asset('vendor/log-viewer/app.css')`), bukan logika `$assetsPublished` + `mix()` yang dipakai paket sekarang. Disalin ulang dari paket terpasang supaya selaras. Asetnya sudah ter-publish di `public/vendor/log-viewer/` lengkap dengan `mix-manifest.json` di lokal maupun server, jadi jalur `mix()` bekerja. Ada test yang benar-benar me-render halamannya, bukan sekadar memeriksa isi berkas.


#### Permission ganda di seeder menimpa diam-diam

Ditemukan sambil memeriksa Log Viewer. `permissions.name` unique dan seeder memakai `updateOrCreate`, sehingga **entri kedua menimpa yang pertama tanpa error apa pun**.

`view_activity_logs` didaftarkan dua kali dengan `module_name` berbeda — `Activity Logs` dan `System` — dan yang belakangan menang. Akibatnya modul `Activity Logs` tidak pernah benar-benar ada, dan `System` hanya berisi permission duplikat itu. Empat permission `*_stock_takes` juga terdaftar dua kali, meski nilainya identik sehingga tidak berakibat apa-apa.

`no_permission_is_seeded_more_than_once` kini menjaganya. Gejalanya cuma satu modul hilang diam-diam dari form hak akses — jenis kesalahan yang tidak akan pernah ketahuan tanpa ada yang memeriksanya.

### Melihat dokumen ≠ menggerakkan uang

Ditanyakan Project Owner 30 Agustus 2026: modul Accounting hanya punya `view` dan `view_deleted`, padahal seharusnya ada hak khusus untuk membayar atau mencicil. Diperiksa, dan kekhawatirannya benar — bahkan lebih luas.

**Keempat aksi yang memindahkan uang tidak satu pun memeriksa hak akses:**

| Halaman | Aksi | Penjagaan lama |
|---|---|---|
| `ViewPayable` | bayar/cicil utang supplier | hanya `balance > 0` |
| `ViewPurchaseProduct` | DP di PO Beef | tidak ada |
| `ViewPurchaseMaterial` | DP di PO Material | tidak ada |
| `ReceivePayment` | terima pembayaran piutang | tidak ada |

Artinya siapa pun yang diberi hak **melihat** sebuah PO atau utang otomatis bisa **mengeluarkan uang perusahaan**.

**Yang paling menyesatkan:** `ViewPurchaseProduct` memang punya satu `hasPermission`, tetapi itu untuk tombol **Print** — bukan untuk pembayarannya. Sekilas halamannya tampak sudah dijaga. Saat memeriksa penjagaan, jangan berhenti pada "ada `hasPermission`" — periksa ia menjaga aksi yang mana.

Empat permission baru: `pay_payables`, `pay_purchase_products`, `pay_purchase_materials`, `receive_receivables`.

**`ReceivePayment` dijaga di tingkat HALAMAN**, bukan cuma tombolnya — rutenya bisa dicapai dengan mengetik URL. Pelajaran yang sama dengan halaman keputusan Request.

**Penjagaan polanya yang terpenting:** `MoneyActionPermissionTest` memindai **seluruh** halaman Filament, dan halaman mana pun yang membuat `SupplierPayment` atau `Payment` wajib menyebut sebuah permission di berkas yang sama. Menjaga empat halaman yang sudah diketahui saja tidak cukup — yang berikutnya akan lolos dengan cara yang persis sama.

Ditemukan sekalian: tombolnya memakai kunci berbahasa Indonesia (`Terima Pembayaran`) yang lolos dari daftar kata pada ratchet bahasa — bukti bahwa heuristik kata memang tidak menangkap semuanya. Sudah dipindahkan ke kunci Inggris.

### Nama grup navigasi hidup di TIGA tempat — dijaga test, bukan diingat

Ditemukan 30 Agustus 2026 saat Owner melaporkan tab ACCOUNTING tidak muncul di form hak akses, lalu bertanya: *"ini bikin pusing, kalau menu berubah lagi yang lain harus diubah lagi — best practice-nya gimana?"*

Pertanyaan yang tepat, karena nama grup memang hidup di tiga tempat yang saling bebas:

1. `navigationGroups()` di `AdminPanelProvider` — urutan resmi di sidebar
2. `getNavigationGroup()` tiap Resource — penempatan sebenarnya
3. `Permission::moduleGroups()` — tab di form hak akses

**Tidak ada apa pun di framework yang memaksa ketiganya cocok.** Filament menerima begitu saja Resource yang menunjuk grup tak terdaftar: menunya tetap tampil, hanya kehilangan posisi urutannya. Tidak ada error, tidak ada peringatan.

Itulah yang terjadi: empat Resource (`BankAccount`, `Payable`, `Receivable`, `FinancialLoss`) memakai `ACCOUNTING` sementara panel hanya mendeklarasikan `FINANCE`, dan peta permission menaruh keempatnya di `FINANCE`. Sidebar menampilkan "Akuntansi", form hak akses menampilkan "Keuangan".

**Jawaban atas pertanyaannya: jangan mengandalkan pemeriksaan ulang di akhir — buat penyimpangannya GAGAL SEKETIKA.** `NavigationGroupConsistencyTest` memastikan setiap grup yang dipakai Resource terdaftar di panel, setiap grup di peta permission benar-benar ada, dan modul keuangan berada di grup yang sama dengan Resource-nya. Mengganti nama grup kini langsung menggagalkan test dengan pesan yang menyebut apa yang harus ikut diubah.

Pemeriksaan menyeluruh di akhir hanya menemukan yang terpikir untuk dicari; penjaga otomatis menemukannya pada commit yang merusaknya. Dua bug DP pekan ini — rantai putus saat form pindah, dan uang muka hilang saat GR dibuka — dua-duanya lolos berhari-hari justru karena mengandalkan pemeriksaan manual.

**Sisi Inggris label grup juga dibereskan:** `PRODUCTION`, `FINANCE`, `ACCOUNTING` di `en.json` nilainya masih kunci mentah (huruf besar semua) sehingga berteriak di antara tetangganya yang Title Case.

### Form hak akses User dikelompokkan ke tab mengikuti sidebar

Keputusan Project Owner, 28 Agustus 2026.

Sebelumnya form Create/Edit User menumpuk **46 seksi modul** secara vertikal. Masalahnya bukan sekadar tidak nyaman: memilih satu per satu dari 46 seksi itu melelahkan, sehingga lebih gampang mencentang semuanya — dan begitulah akun uji `rafi` dan `coba` berakhir dengan **181 permission**. **Form yang menyulitkan pemberian hak secara selektif akan melahirkan pemberian hak yang serampangan.** Merapikan formnya sekaligus memperbaiki disiplin hak akses.

Sekarang 46 modul dikelompokkan ke **12 tab yang urutannya sama persis dengan grup sidebar**. Kesamaan urutan itu disengaja: admin yang memberi hak sedang membayangkan menu apa yang nanti dilihat pengguna, jadi susunan yang sama membuat hasilnya bisa ditebak tanpa mencoba dulu.

Petanya ada di `Permission::moduleGroups()` — ditaruh di model, bukan di form, karena itu pengetahuan domain tentang permission dan bukan urusan tata letak.

**Modul yang belum dipetakan sengaja TIDAK dibuang.** Ia tetap tampil di tab cadangan `LAINNYA`. Kalau dibuang, haknya tidak bisa diberikan sama sekali dan tidak ada yang menyadarinya — persis jenis kegagalan senyap yang paling mahal. Ada test yang memastikan seluruh modul yang di-seed sudah terpeta, jadi modul baru yang lupa didaftarkan langsung gagal, bukan diam-diam terdampar di tab cadangan.

**Tab bawaan Filament tidak terbaca di HP.** `nav.fi-tabs` memakai satu baris ber-`overflow-x-auto`. Dengan 12 tab, di layar HP hanya 2-3 yang terlihat — dan **tab yang sedang aktif bisa berada di luar layar**, sehingga pengguna tidak tahu sedang membuka bagian yang mana. Ditambahkan CSS di `AdminPanelProvider` agar strip tab **membungkus ke beberapa baris** di bawah 1024px. Sengaja dibatasi pada `.fi-fo-tabs` (tab di dalam form), bukan seluruh tab aplikasi, supaya halaman lain yang belum diperiksa tidak ikut berubah.

Gejalanya **tidak terlihat sama sekali di desktop**, jadi gampang hilang tanpa ada yang menyadarinya — karena itu ada test yang menjaganya.

**Yang wajib diperiksa saat mengubah form ini:** penyimpanan hak akses membaca kunci `permissions_*` dari `$this->form->getRawState()`. `Tabs` dan `Section` adalah komponen **tata letak** — keduanya tidak menyarangkan state, jadi kuncinya tetap datar di tingkat atas. Ada test yang menyimpan hak dari **dua tab berbeda** sekaligus untuk membuktikannya, karena kalau salah, gejalanya menyesatkan: form tampak berhasil disimpan tapi centangnya hilang.

### Policy ditemukan lewat nama MODEL, bukan nama Resource

Ditemukan 28 Agustus 2026 saat Owner melaporkan menu Sales dan Accounting terlihat terbuka untuk semua.

**Sebagian besar laporannya ternyata bukan bug.** Diverifikasi dengan me-render navigasi sungguhan: pengguna tanpa permission melihat menu **kosong**, dan `ruby` (12 permission) hanya melihat grup Permintaan. Yang membuatnya tampak terbuka adalah akun uji `rafi` dan `coba` yang memang punya **181 permission** — praktis seluruhnya.

**Tapi ada dua yang benar-benar bocor.** `PriceListResource` dan `ReceivableResource` sama-sama memakai `$model = CustomerGroup::class`. Laravel menemukan Policy lewat **nama model**, jadi keduanya jatuh ke `CustomerGroupPolicy` — `PriceListPolicy` dan `ReceivablePolicy` **tidak pernah dipanggil sama sekali** dan selama ini kode mati. Akibatnya siapa pun yang punya `view_customer_groups` ikut melihat menu Price List dan seluruh data piutang.

Terbukti pada `ahkmad`, yang diberi hak Master Data tapi ikut melihat kedua menu itu tanpa punya `view_price_lists` maupun `view_receivables`.

**Aturannya sekarang:** sebuah Resource yang **menumpang model milik modul lain** wajib mendeklarasikan `canViewAny()` dan `shouldRegisterNavigation()` sendiri. Selama nama Resource sejalan dengan modelnya (`MaterialResource` → `Material` → `MaterialPolicy`), Policy otomatis memang tepat sasaran dan tidak perlu ditimpa.

Ada tujuh Resource yang namanya tidak cocok dengan modelnya, dan kini **ketujuhnya** punya gerbang sendiri. `ResourceAccessGateTest` menjaga polanya, bukan cuma dua yang kemarin bocor — Resource baru yang menumpang model milik modul lain akan langsung gagal.

**Pelajarannya:** membuat Policy tidak ada gunanya bila Laravel tidak pernah menemukannya. Saat sebuah Resource memakai model milik modul lain, `canViewAny()` di Resource adalah satu-satunya penjagaan yang benar-benar berlaku.

## 3. Jebakan yang sudah pernah menggigit

### Pemisah ribuan di dalam Repeater: BISA, asal listenernya di luar baris

Aturan lama di `project.md` menyiratkan pemformatan hidup mustahil di dalam Repeater. **Itu keliru** — yang terlarang hanya caranya, bukan tujuannya.

`RawJs::make('$money(...)')` memasang Alpine `x-mask` pada **setiap input di dalam baris**. Itulah yang tersangkut saat baris dihapus.

Cara yang benar, dan sudah lama dipakai `SalesOrderResource` tanpa masalah: **satu listener `x-on:input` pada `Section` pembungkus**, di luar baris Repeater, yang memformat berdasarkan CSS class lewat event delegation. Alpine tidak pernah menempel ke elemen baris, jadi tidak ada yang perlu di-teardown dan bug zombie tidak pernah terjadi.

Dua hal yang wajib ikut, kalau tidak justru menimbulkan bug baru:

1. **Lepas `->numeric()`** dari field yang diformat. Itu membuat `<input type="number">`, dan browser **menolak** pemisah ribuan di dalamnya sehingga fieldnya tampil kosong. Ganti dengan `->extraInputAttributes(['inputmode' => 'numeric'])`.
2. **Parse nilai sebelum disimpan.** PHP membaca `"250.000"` sebagai `250.0`, jadi harga 250 ribu menyusut jadi 250 **tanpa error apa pun**. Di Request Beef ada empat halaman yang menyimpan item — Create, Edit, Review, ApproveFinance — dan keempatnya wajib memakai `parseNumber()`.

Sudah diverifikasi langsung di browser: mengetik `250000` menjadi `250.000` per ketikan, dan menghapus baris tidak menyisakan baris zombie.

**Request Material sempat tidak memakai pola ini sama sekali** — ditemukan 26 Agustus 2026 saat Owner mengujinya. `qty` dan `price` masih `->numeric()` polos (bahkan `->numeric()` tertulis dua kali berturut-turut, sisa salin-tempel dari Product), tanpa listener apa pun. Disamakan persis dengan Product.

**Jebakan yang nyaris terulang saat membenahinya:** menambahkan mask TANPA membenahi titik simpannya lebih berbahaya daripada tidak menambahkannya sama sekali. Selama field itu `->numeric()`, keempat halaman penyimpan (Create, Edit, Review, ApproveFinance) aman menulis `$item['qty'] ?? 0` mentah — browser tidak akan pernah mengirim string ber-pemisah ribuan. Begitu mask dipasang, browser mulai mengirim `"15.000"`, dan tanpa `parseNumber()` di titik simpannya, nilai itu tersimpan menyusut jadi `15` tanpa error apa pun. **Kedua sisi — mask di form dan `parseNumber()` di titik simpan — wajib berubah bersamaan, tidak boleh salah satu duluan.** Sudah diverifikasi test-nya gagal saat `parseNumber()` dilepas dari salah satu dari keempat halaman itu.

### Bug baris "zombie" di Repeater

`RawJs::make('$money(...)')` **di dalam `Repeater`** memicu bug Livewire Morphdom: baris terhapus di sisi server, tapi elemen HTML-nya tertahan proses teardown AlpineJS sehingga menyisakan baris kosong yang tidak bisa dihilangkan di browser. Di dalam Repeater cukup `->numeric()`. Di form biasa, mask aman dipakai.

### Migrasi bersintaks MySQL mematikan seluruh test suite

`phpunit.xml` memakai SQLite `:memory:`. Dua migrasi pernah memakai `CREATE OR REPLACE VIEW` yang khusus MySQL, dan SQLite tersedak di kata `OR`. Akibatnya **setiap** test ber-`RefreshDatabase` mati di tahap migrasi: 73 gagal, 2 lolos. Satu masalah menjatuhkan semuanya.

Solusinya `DROP VIEW IF EXISTS` + `CREATE VIEW`, yang didukung kedua driver. **Selalu tulis migrasi dengan sintaks lintas-driver.**

### Test hijau bukan jaminan fitur jalan

Beberapa test lolos padahal fiturnya rusak, karena test-nya **menirukan** logika alih-alih memanggil aksi yang sebenarnya. Contoh paling mahal: `it_processes_tukar_faktur_and_updates_due_date` melakukan `$invoice->update(['status' => 'Sudah TF', ...])` sendiri, sehingga yang teruji hook model — bukan aksi Tukar Faktur yang dipakai pengguna. Aksi aslinya ternyata tidak pernah menetapkan jatuh tempo selama berbulan-bulan.

**Untuk fitur yang dipicu dari UI, panggil aksinya sungguhan** lewat `callTableAction()` atau `callAction()`.

### `assertNotified()` menguras notifikasi

Helper Filament itu memakai `session()->pull()`, sehingga **hanya panggilan pertama yang melihat isinya**. Untuk memeriksa beberapa notifikasi sekaligus, baca `session('filament.notifications')` langsung.

### Error 419 / Page Expired

Jangan mencampur `localhost` dengan `127.0.0.1` dalam satu sesi. Samakan dengan `APP_URL`.

---

## 4. Cara Project Owner ingin bekerja

- **Owner mengarahkan, implementor mengeksekusi.** Kutipan langsung: *"biar tugas saya hanya mengarahkan"*. Jangan menyodorkan langkah manual yang bisa dikerjakan sendiri — kerjakan, lalu laporkan hasilnya.
- **Alur wajib:** Implementation Plan (Bahasa Indonesia) → persetujuan Owner → coding di branch → test → PR → merge → verifikasi di server.
- **Persetujuan atas rencana bukan persetujuan atas hasil.** Tetap test sebelum merge.
- **Jangan jadi yes-man.** Owner secara eksplisit meminta keberatan disampaikan bila ada pendekatan yang lebih baik.
- **`project.md` bukan dogma.** Kutipan: *"jika ada aturan yang lebih baik dari project.md mending pake aturan lebih baik saja"*. Tapi sebutkan penyimpangannya, jangan diam-diam.
- **Diksi diserahkan ke implementor.** Pilih sendiri label/status yang paling relevan, lalu sebutkan pilihannya.
- **Cerita Owner soal alur bisnis itu berharga.** Bug Tukar Faktur ditemukan justru karena Owner menceritakan alur yang seharusnya — bukan dari membaca kode, karena test-nya hijau.

### Akses server uji coba

Owner memberi akses SSH penuh ke server uji coba (bukan produksi):

```
ssh -tt -p 65002 u525862761@153.92.9.218
cd /home/u525862761/domains/coba.wijayameat.co.id/public_html
```

#### WAJIB pakai `-tt`. Tanpa itu SSH tampak "rusak" padahal tidak.

Ditemukan 30 Agustus 2026, setelah sempat salah didiagnosa dan membuat Owner menjalankan deploy manual berkali-kali.

Server **menolak eksekusi perintah tanpa PTY**. Gejalanya sangat menyesatkan karena semua lapisan awal berhasil:

| Lapisan | Hasil |
|---|---|
| TCP + handshake SSH | sehat |
| Autentikasi publickey | sehat — `Authenticated using publickey` |
| Kanal sesi terbuka | sehat |
| `ssh -N` (tanpa shell) | bertahan penuh, transport normal |
| Begitu perintah dieksekusi | **`Connection reset by peer` seketika** |

Karena `Connection reset by peer` muncul berulang di banyak percobaan beruntun, gejalanya **mirip sekali dengan IP yang kena rate-limit** — dan sempat disimpulkan begitu. Itu keliru. Perbaikannya cuma satu flag: `-tt` (memaksa alokasi PTY).

**Cara mendiagnosa ulang bila terulang**, berurutan dari yang paling murah:

1. `ssh -v ...` — kalau muncul `Authenticated using publickey`, autentikasi bukan masalahnya.
2. `timeout 20 ssh -N ...` — kalau bertahan sampai timeout (exit 124), transport sehat dan masalahnya ada di eksekusi shell.
3. `ssh -tt ... 'echo ok'` — kalau ini berhasil, penyebabnya memang PTY.

**Pelajarannya:** jangan menyimpulkan "diblokir" dari gejala berulang. Turunkan satu per satu lapisannya; di sini transport, autentikasi, dan kanal sesi semuanya sehat, dan yang rusak cuma satu lapis paling atas.

**Catatan praktis dengan `-tt`:** keluaran membawa kode warna ANSI dan akhiran baris CRLF. Untuk keluaran yang perlu dibaca mesin, tambahkan `TERM=dumb` di sisi remote dan saring dengan `sed 's/\x1b\[[0-9;?]*[a-zA-Z]//g'`.

Boleh dipakai untuk diagnosa dan perbaikan. Tetap konfirmasi sebelum aksi destruktif yang tidak bisa dibalik, meski itu data dummy — menyiapkan ulang data uji itu merepotkan.

#### Perintah git di server WAJIB `--no-pager`

Ditemukan 30 Agustus 2026, dan gejalanya menghabiskan tujuh belas menit Owner
tanpa satu pun tanda apa yang terjadi.

`git log` di server membuka pager. Dengan `-tt` ada PTY, jadi pager merasa
punya terminal dan menunggu tombol RETURN yang tidak akan pernah datang.
Sesinya menggantung selamanya: tidak ada error, tidak ada keluaran, tidak ada
timeout. Yang terlihat di sisi Owner cuma indikator "running tools" yang terus
berjalan -- dan karena kebetulan sedang membahas test yang lambat, wajar saja
ia disangka test yang belum selesai.

Aturannya: **setiap perintah git di sisi remote memakai `git --no-pager`**.
Berlaku juga untuk perintah lain yang bisa memanggil pager.

**Pelajaran yang lebih umum:** perintah yang menggantung lebih buruk daripada
perintah yang gagal. Yang gagal memberi tahu; yang menggantung menyamar
sebagai pekerjaan yang sedang berjalan. Bila sebuah perintah remote tidak
mengembalikan apa pun dalam waktu yang wajar, curigai pager atau prompt lebih
dulu, jangan menunggu.

#### Auto-deploy Hostinger ternyata MASIH aktif

Dicatat 30 Agustus 2026, mengoreksi keyakinan yang selama ini dipakai.

Sebelum sempat `git pull`, server sudah berada di commit yang baru saja
di-push. `git reflog` di server cuma berisi dua baris -- `clone: from
https://github.com/asepidung/swmrf.git` lalu `checkout` ke commit itu -- dan
reponya `grafted` (shallow). Jadi Hostinger **meng-clone ulang seluruh repo**
tiap kali `main` berubah, bukan `git pull`.

Yang dimatikan Owner adalah auto-deploy dari GitHub Actions; yang bawaan
Hostinger ternyata masih hidup.

**Konsekuensi praktis, dan ini bagian yang penting:** clone ulang itu **tidak
menjalankan `migrate` dan tidak membersihkan cache**. Jadi kode memang sampai
sendiri, tetapi migrasi dan cache tetap tanggung jawab implementor. Merge yang
tidak diikuti langkah itu menghasilkan server yang kodenya baru tetapi
perilakunya lama -- persis jenis kegagalan senyap yang berulang di proyek ini.

**Jangan `git pull` manual.** Percobaan pertama gagal dengan `fatal: not a git
repository` karena menabrak clone ulang yang sedang berjalan: `.git` sedang
diganti saat itu juga. Urutan yang benar: push ke `main`, beri jeda, lalu masuk
untuk `migrate --force` dan cache warming saja.

### Test lambat itu I/O, bukan test-nya

Diukur 30 Agustus 2026 setelah Owner menegur bahwa menghapus satu widget pun
memakan waktu terlalu lama. Teguran itu tepat, dan angkanya membenarkannya.

| Perintah | Waktu |
|---|---|
| Suite penuh -- dilaporkan PHPUnit | 43 detik |
| Suite penuh -- wall clock sebenarnya | **7 menit 26 detik** |
| `--filter` satu test, run dingin | 2 menit 37 detik |
| `--filter` satu test, run kedua | 1,4 detik |
| `php artisan test tests/Feature/NamaTest.php` | 1,7 detik |
| Suite penuh setelah Defender dikecualikan | **1 menit 41 detik** |

Dua hal yang terbaca dari tabel itu:

**Angka PHPUnit menyembunyikan hampir seluruh waktu tunggu.** Ia melaporkan 43
detik untuk sesuatu yang sebenarnya memakan 7 menit 26 detik. Karena itu
laporan durasi PHPUnit **tidak boleh dipakai** untuk menilai apakah test terasa
lambat -- pakai wall clock.

**Baris ketiga dan keempat adalah buktinya:** perintah yang sama persis, 2m37s
saat dingin dan 1,4 detik saat diulang. Kodenya identik; yang berubah cuma
cache filesystem. Itu tanda pemindaian antivirus atas `vendor/`, bukan test
yang berat.

Perbaikannya di luar repo: `Add-MpPreference -ExclusionPath` untuk folder
proyek dan folder PHP, plus `-ExclusionProcess` untuk `php.exe`. Dijalankan
Owner sendiri karena butuh hak admin. Hasilnya 7m26s menjadi 1m41s, 270 test
tetap lolos. **Kalau kelak test terasa lambat lagi di mesin lain, periksa ini
lebih dulu sebelum menyalahkan test-nya.**

`--parallel` (paratest) sengaja **tidak** diambil: sisa 100 detik itu murni
eksekusi test, dan sehari-hari kita menjalankan satu berkas yang cuma 1,7
detik. Menambah dependensi dan risiko test rewel saat paralel tidak sepadan
untuk sekarang.


---

### Uang: saldo diturunkan dari mutasi, TIDAK disimpan di master data

Keputusan Project Owner, 31 Agustus 2026, dengan analogi yang menentukan bentuknya: *"kalo product atau beef kita punya stock, harusnya uang pun ada bisa ditelusuri ada dimana"*, dan *"jangan menyimpan saldo atau stock di master data"*.

Sejajarannya begini:

| | Posisi sekarang | Kartu mutasi | Koreksi |
|---|---|---|---|
| Barang | `beef_stocks` | `beef_stock_movements` | Stock Opname |
| Uang | dihitung dari mutasi | `bank_transactions` (Buku Kas) | Penyesuaian Kas |

**Kolom `bank_accounts.balance` DIHAPUS**, bukan sekadar tidak dipakai lagi. Selama ia ada, seseorang akan menulisinya lagi. Dulu kolom itu di-increment dan di-decrement tiap ada pembayaran, sehingga ada **dua angka yang sama-sama mengaku benar**: kolomnya, dan jumlah baris buku kas. Selama keduanya cocok tidak ada yang terasa; begitu berbeda, tidak ada cara menentukan mana yang salah tanpa memeriksa satu per satu.

Aman dilakukan: `bank_accounts` tabel sistem baru, tidak dipakai sistem lama, dan nilainya memang sudah persis sama dengan jumlah mutasinya (diverifikasi di server sebelum dihapus: selisih nol di ketiga rekening).

**Konsekuensi yang paling berharga:** pembayaran kini menggeser saldo semata-mata karena menambah baris di buku kas. Tidak ada lagi kode terpisah yang mengurangi sebuah kolom, jadi **jalur pembayaran baru mana pun otomatis benar dan tidak bisa lupa**. Saldo dibaca lewat `BankAccount::currentBalance()`; untuk tabel pakai `withSum` supaya tidak satu query per baris.

#### Saldo awal SEKALI; koreksi berikutnya adalah Penyesuaian

Sebelum ini tidak ada cara memasukkan uang ke sistem sama sekali -- uang hanya bisa keluar, sehingga saldo di server minus di dua rekening. Ibarat kartu stok rapi yang tidak pernah punya penerimaan barang pertama.

| | Saldo Awal | Penyesuaian Kas |
|---|---|---|
| Berapa kali | sekali per rekening | berkali-kali |
| Maksudnya | titik mulai pembukuan | koreksi selisih dengan rekening koran |
| Alasan | opsional | **wajib** |
| Arah | masuk | masuk atau keluar |
| Padanan di barang | penerimaan pertama | Stock Opname |
| Permission | `set_opening_balance` | `adjust_cash_balance` |

Keduanya tersimpan sebagai baris `bank_transactions` dengan `reference_type` `opening_balance` / `adjustment`.

**Aturan penguncian, dan kesalahan yang sempat dibuat.** Percobaan pertama mengunci saldo awal begitu rekening punya mutasi. Itu salah: yang berbahaya bukan MEMBUAT saldo awal belakangan, melainkan MENGGESER titik awal yang sudah jadi dasar perhitungan. Padahal membuatnya belakangan justru kondisi normal proyek ini -- sistem ini refactor dari aplikasi lama, hutang dan piutang sudah berjalan, jadi pembukuan memang dimulai dari tengah dengan tanggal mundur ke cut-off. Akibatnya dua rekening di server tidak bisa diberi saldo awal sama sekali.

Aturan yang berlaku: boleh diset kapan pun selama **belum pernah diset**; sesudahnya masih boleh diperbaiki sampai ada mutasi lain menumpuk di atasnya; setelah itu terkunci permanen dan koreksi lewat Penyesuaian.

**Alasan penyesuaian wajib diisi** dan ikut tersimpan di keterangan barisnya. Itu yang membedakan koreksi dari menulis ulang angka diam-diam: selisih tanpa alasan tidak bisa diperiksa siapa pun nanti, dan justru itulah yang paling sering perlu ditelusuri ulang.

#### Buku Kas read-only, dan kenapa tanpa saldo berjalan

`CashBookResource` sengaja read-only: tiap barisnya adalah JEJAK dari dokumen lain. Membolehkan orang mengetik baris kas langsung akan memutus hubungan itu dan membuat buku kas berbeda dari dokumen yang melahirkannya, tanpa ada yang bisa menunjukkan mana yang benar.

Namanya "Buku Kas", bukan "Bank Transactions", karena tabelnya juga menampung akun KAS tunai.

**Tidak ada kolom saldo berjalan.** Tabel ini tidak menyimpan saldo per baris, jadi saldo berjalan harus diakumulasi dari awal -- dan begitu difilter tanggal (yang wajib untuk modul transaksional), angkanya salah karena baris sebelum rentang tidak ikut terhitung. Saldo yang salah lebih berbahaya daripada tidak ada saldo. Kalau kelak dibutuhkan, jawabannya menyimpan saldo per baris saat transaksinya dibuat, bukan menambal kolom.

#### Penjaga aksi uang kini mencakup BankTransaction

Karena saldo diturunkan dari buku kas, siapa pun yang bisa menulis baris di sana bisa **menciptakan uang**, bukan sekadar mencatat perpindahannya. `MoneyActionPermissionTest` menolak halaman mana pun yang membuat `BankTransaction` tanpa memeriksa hak akses.

#### Peta "uang ada di mana"

Ditanyakan Owner. Kondisinya per 31 Agustus 2026:

| Uangnya di mana | Tabel | Status |
|---|---|---|
| Kas dan rekening bank | `bank_transactions` | ada, ada layarnya |
| Di tangan customer (piutang) | `receivables` / invoice | ada, ada layarnya |
| Di tangan supplier (DP belum terpakai) | `supplier_payments` (`amount` - `allocated_amount`) | datanya ada, **belum ada layarnya** |
| Kewajiban ke supplier | `payables` | ada, ada layarnya |
| Berbentuk barang | `beef_stocks`, `material_stocks` | qty ada; nilai rupiahnya belum diperiksa |

DP menggantung sengaja **belum** dibuatkan layar (keputusan Owner, tidak mendesak). Kalau kelak dibuat: DP yang menggantung tidak pernah menimbulkan error -- PO batal atau barang datang kurang membuat sisanya tercatat selamanya tanpa ada yang menagih.

### Permission BARU dikirim lewat MIGRASI, bukan `db:seed`

Ditemukan 30 Agustus 2026 saat hendak mengirim `view_cash_book` ke server.

`DatabaseSeeder` menyetel ulang password akun `saepullrock` menjadi `1234` **tanpa syarat** setiap kali dijalankan. Menjalankannya di server hidup akan melempar pemiliknya ke alur penggantian password.

Karena deploy sudah menjalankan `migrate --force`, permission baru dikirim lewat migrasi kecil ber-`updateOrInsert` (aman diulang, didukung MySQL maupun SQLite). Tetap didaftarkan juga di `DatabaseSeeder` untuk lingkungan yang dibangun dari nol.

### Kelas warna Tailwind tidak menghasilkan CSS

Ditemukan 30 Agustus 2026 dari gejala sepele: tombol "Damaged Label" tampil polos tanpa warna.

Filament hanya menyertakan kelas utilitas yang dipakai kode Filament **sendiri**, dan proyek ini tidak mengompilasi tema Filament kustom. Jadi `bg-warning-500`, `text-success-700`, `divide-y`, dan `bg-gradient-to-r` tidak menghasilkan CSS apa pun.

Kegagalannya tidak pernah terasa sebagai kegagalan: elemennya tetap ada, tetap bisa diklik, hanya tidak berwarna -- dan garis pemisah tabel hilang tanpa ada yang menyadarinya. Setelah dipindai betul-betul: **75 kelas di 14 berkas**.

**Kenapa bukan tema Filament kustom**, meski itu jawaban yang lebih rapi: ia butuh `npm run build`, sementara server **tidak punya node** dan `public/build` tidak masuk repo. Artinya setiap perubahan tampilan jadi bergantung pada langkah build yang bisa terlupakan, dan lupa build berarti perubahan tidak sampai TANPA GEJALA APA PUN.

Yang dipakai: Filament sudah menyuntikkan variabel warnanya (`--warning-500` dan seterusnya) ke setiap halaman panel, jadi kelas yang kurang cukup didefinisikan sekali di `resources/views/filament/admin/missing-color-utilities.blade.php` memakai variabel itu -- ikut palet dan tema gelap, tanpa kompilasi. `MissingColorUtilitiesTest` memindai ulang seluruh blade, jadi kelas baru yang tidak tercakup langsung gagal.

**Kalau kelak ingin memakai kelas warna baru:** tambahkan di berkas itu, atau pakai komponen Filament (`<x-filament::button color="warning">`) yang sudah punya CSS-nya sendiri.

### Jebakan: test hak akses dengan user `programmer` tidak menguji apa pun

`User::hasPermission()` mengembalikan `true` untuk peran `programmer` tanpa memeriksa apa pun. Sebuah test yang memakai user seperti itu untuk memastikan sebuah aksi terjaga akan **selalu lulus** -- yang diujinya cuma "superuser bisa melakukan segalanya".

Terjadi 31 Agustus 2026 saat menulis test permission Penyesuaian Kas. Test hak akses wajib memakai user berperan `employee`.

### Nama relasi salah menyamar jadi teks yang wajar

Tiga bug 30 Agustus 2026 dengan bentuk yang sama, semuanya dilaporkan Owner dari layar aplikasi -- tidak satu pun menimbulkan error:

| Tempat | Salahnya | Tampilnya |
|---|---|---|
| Cetak PO Product | `$item->Beef->name`, relasinya `product()` | kolom nama produk selalu `-` |
| Cetak PO Product | `$record->approvedBy->name`, di model ini namanya `approver()` | penandatangan selalu "FINANCE" |
| View PO Product/Material | `TextInput::make('relasi.kolom')` | field selalu kosong |

Dua yang pertama disamarkan operator `??`, yang mengubah null menjadi tampilan masuk akal. **Fallback berupa kata yang terbaca sebagai data menyembunyikan kerusakan** -- 'FINANCE' terbaca seperti format yang disengaja. Fallback sekarang `-`.

Yang ketiga bentuknya berbeda dan layak diingat sendiri: **halaman View mengisi form dari `attributesToArray()`, yang hanya memuat kolom tabel tanpa relasi**, jadi field bernama jalur relasi TIDAK PERNAH terisi. Ada di sembilan tempat pada lima Resource. Pakai nama datar plus `->formatStateUsing(fn ($record) => data_get($record, 'relasi.kolom'))`. Dijaga `no_form_field_is_named_after_a_relation_path`.

### navigationSort kembar = urutan yang tidak dipilih siapa pun

Beef Stock terdampar di posisi ketiga cluster bukan karena ada yang memilihnya, melainkan karena ia dan BeefStockAging sama-sama bernilai 3 sehingga urutannya diputuskan tie-break. Test-nya menolak nilai kembar, bukan sekadar memeriksa posisi -- kalau cuma posisi yang dijaga, penyebabnya bisa kembali lewat halaman baru yang menyalin nilai tetangganya.

## 5. Status Saat Ini

- **Test suite: 268 lolos, 0 gagal** (1361 assertion, diverifikasi 30 Agustus 2026). Sebelumnya praktis mati total. Jaga tetap hijau.
- **Modul yang benar-benar belum ada:** QC/QA Monitoring Produksi; Killing Lost dan Lost Cost; serta laporan Fast Moving Products, Sales Report, dan Stock Gudang. (UI Warehouse dan Grade **sudah ada** sejak 24 Agustus 2026 — lihat bagian di bawah.) Status lengkap ada di `checklist_modul.md` (file lokal, tidak masuk repo).

### Modul Keuangan (ACCOUNTING) diparkir sementara

Disepakati pada 28 Agustus 2026. Prioritas utama saat ini adalah mematangkan **alur operasional fisik barang** (mulai dari Request, PO, Goods Receipt, Stok, hingga Delivery).

- Masalah pencatatan keuangan yang kompleks (buku besar, jurnal berpasangan lengkap) ditunda pengerjaannya.
- **Resource yang sempat dimatikan kini SUDAH DIAKTIFKAN kembali:** `BankAccountResource`, `PayableResource`, dan `ReceivableResource` sudah dimunculkan (`canAccess` false dihapus) dan disatukan di dalam grup navigasi `ACCOUNTING` beserta `FinancialLossResource`.
- Meskipun menu-menu tersebut sudah bisa diakses, pengerjaan lebih jauh untuk melengkapi alur mutasi dan penjurnalan diparkir sementara. Biarkan modul ini bertindak sebagai penerima data pasif dari alur operasional (misalnya hutang yang lahir otomatis dari penerimaan barang, atau catatan DP yang tercatat lewat halaman PO).

### Financial Loss baru menampung satu jenis kerugian

`FinancialLoss` hanya ditulis dari satu tempat: `CattleWeighing::financialLoss()` dengan `transaction_type = 'Cattle Weighing'` (susut timbang ulang sapi). Filter di `FinancialLossResource` pun cuma punya satu opsi, dengan komentar *"More can be added here later"*.

Karena `canCreate()` mengembalikan `false` dan tidak ada halaman Create, jenis kerugian lain — Killing Lost dan Lost Cost — **tidak bisa masuk sama sekali, bahkan secara manual**. Modul ini praktis baru separuh jalan meski di checklist lama tertulis selesai.

### Warehouse dan Grade: sudah punya UI, dan ID grade dikunci

**Sudah dibereskan.** Keduanya kini punya Resource sendiri di grup MASTER DATA, lengkap dengan permission, Policy, activity log, dan export Excel.

Hal terpenting yang harus dijaga: **id pada tabel `grades` dikunci dan tidak boleh berubah.** Digit grade pada barcode 26 karakter mengacu langsung ke id itu, sehingga menukar urutannya membuat seluruh barcode lama salah arti. Seeder menetapkannya secara eksplisit:

| id | Grade |
|---|---|
| 1 | CHILL |
| 2 | FROZEN |
| 3 | A |
| 4 | B |
| 5 | R |

Sebelumnya blok seeder ini dikomentari dengan alasan "to avoid ID conflicts during data migration", dan karena tidak ada UI-nya, `migrate:fresh` akan menghasilkan **nol grade tanpa cara memulihkannya dari aplikasi** — aplikasi jadi buntu karena setiap `BeefStock`, `TallyItem`, dan `BoningItem` wajib punya `grade_id`. Ada test yang mengunci id ini (`MasterDataGradeWarehouseTest`); kalau test itu gagal, jangan diperbaiki dengan mengubah ekspektasinya.

### Catatan lama: Warehouse dan Grade tidak punya UI

Keduanya master data yang dipakai hampir di seluruh transaksi (setiap baris stok punya `warehouse_id` dan `grade_id`), tapi tidak ada Resource untuk mengelolanya. Warehouse hanya di-*seed* dua baris (`JONGGOL`, `PERUM`), sedangkan blok seeder Grade masih dikomentari. Menambah gudang atau grade baru saat ini harus lewat database langsung.
### Halaman cetak masih bergantung pada CDN (ditunda)

Aset panel Filament sudah lokal, dan **Tailwind tidak lewat CDN** — satu-satunya jejak "tailwindcss.com" cuma komentar di dalam CSS terkompilasi yang di-*inline* ke `welcome.blade.php`, halaman bawaan Laravel yang tidak dipakai.

Yang benar-benar memuat dari CDN semuanya ada di `resources/views/print/`:

| Pustaka | Dipakai di | Jumlah view |
|---|---|---|
| JsBarcode (jsdelivr) | Label barcode: beef stock, boning, GR product, repack, sales return, stock take, tally | 7 |
| Bootstrap 4.6.2 (jsdelivr) | Print invoice dan sales order | 3 |
| Font Awesome 5.15.4 (cdnjs) | Print invoice dan sales order | 2 |

**JsBarcode yang berisiko.** Bila jsdelivr tidak terjangkau, **label barcode tercetak kosong** — dan gejalanya menyesatkan karena halamannya tetap terbuka normal, hanya barcode-nya yang hilang. Di lantai produksi yang sedang menimbang dan melabeli daging, itu menghentikan pekerjaan.

Perbaikannya sepele: unduh pustakanya ke `public/js` dan `public/css`, lalu ganti tautannya jadi lokal. **Ditunda atas keputusan Owner** — dicatat supaya tidak hilang.

- **Utang teknis yang diketahui:** kolom skalar `invoices.charge` sudah mati — digantikan repeater relasi `additionalCharges` — tetapi masih diekspor `InvoiceExporter`, sehingga kolom itu selalu bernilai nol di hasil ekspor.
- **Langkah berikutnya:** menyisir setiap modul satu per satu untuk pengecekan dan perbaikan.

### Alur menyisir modul

Disepakati 23 Agustus 2026. **Project Owner yang menentukan modul mana digarap, satu per satu** — jangan memilih sendiri urutannya.

Pembagian tugas per modul:

| Siapa | Mengerjakan apa |
|---|---|
| Implementor | Menyisir kode dan **menulis test otomatis** untuk alur yang belum tercakup |
| Project Owner | **Memverifikasi langsung di browser** |

Langkah untuk tiap modul:

1. Baca `docs/modules/<modul>.md` lebih dulu.
2. Telusuri Resource, Pages, Model, dan Policy terkait.
3. Periksa kepatuhan terhadap standar wajib di `project.md` — silent date filter, export Excel/PDF, clickable rows, permission, activity log, aturan Repeater, pola konkurensi.
4. **Tulis test otomatis** untuk alur yang belum terjaga. Untuk fitur yang dipicu dari UI, panggil aksinya sungguhan lewat `callTableAction()` atau `callAction()`, jangan menirukan logikanya — lihat jebakan "test hijau bukan jaminan fitur jalan" di atas.
5. Laporkan temuan ke Owner, lalu Owner memverifikasi perilakunya di browser.

Alasan pembagian ini: bug Tukar Faktur membuktikan membaca kode saja tidak cukup, sementara Owner memang perlu membuka aplikasinya sendiri untuk menyegarkan ingatan terhadap alur bisnis. Keduanya saling menutupi titik buta.

---

## 6. Serah Terima — posisi per 30 Agustus 2026

Ditulis di akhir sesi panjang, supaya sesi berikutnya tidak perlu menggali ulang.

### Modul yang SUDAH disisir

Daftar ini WAJIB diperbarui tiap kali sebuah modul selesai disisir. Tanpa
itu, sesi berikutnya akan memeriksa ulang modul yang sudah bersih dan
membuang waktu Project Owner.

| Modul | Kapan | Catatan |
|---|---|---|
| Master Data | 24 Agu 2026 | unique index identitas |
| Request Beef | 26 Agu 2026 | harga, notifikasi, uang muka |
| Request Material | 26 Agu 2026 | disamakan dengan Request Beef |
| PO Beef | 30 Agu 2026 | jahitan ke Payable |
| PO Material | 30 Agu 2026 | jahitan ke Payable |
| GR Beef | 30 Agu 2026 | buka-kunci, pelepasan uang muka |
| GR Material | 30 Agu 2026 | sistem kunci |
| Payable | 31 Agu 2026 | kategori pembelian, pengingat jatuh tempo |
| Bank Account / Buku Kas | 31 Agu 2026 | saldo turunan, saldo awal, penyesuaian |
| PO Cattle | 31 Agu 2026 | penomoran, subtotal semu, ekspor |
| Cattle Receiving | 31 Agu 2026 | eartag unik, hutang otomatis, batas berat |
| Cattle Weighing | 31 Agu 2026 | berat 0 = kerugian penuh, ekspor |
| Carcass | 31 Agu 2026 | total vs bobot sapi, selisih belahan |
| Boning | 31 Agu 2026 | penomoran dari count, pH, ikon gembok |
| Repack | 31 Agu 2026 | neraca bahan vs hasil, warna terbalik |
| Price List + Customer | 31 Agu 2026 | tawaran price list untuk grup baru |
| Sales Order (harga & diskon) | 31 Agu 2026 | diskon persen tanpa penjaga, log debug |
| Diskon pelanggan | 31 Agu 2026 | 2% Lion DC pindah dari cocok-nama ke data |
| Tally (halaman pindai) | 1 Sep 2026 | relabel POD, tata letak pemindai |
| Delivery Order | 1 Sep 2026 | ringkasan total, berat diterima, daftar tolakan |
| Delivery Plan | 1 Sep 2026 | jadwal hilang di akhir hari kirim |
| Dashboard (notifikasi) | 1 Sep 2026 | seragam, bilingual, GR belum dikunci |
| Hutang (kompensasi) | 1 Sep 2026 | potongan total, saldo satu rumus |

**Belum tersentuh:** Sales Return, Invoice, Stock Take, Mutation. Sales Order
baru disisir pada bagian harga dan diskonnya saja, mengikuti alur price
list -- sisanya belum. Tally baru disisir pada halaman pindainya; halaman
Draft, View, dan cetaknya belum. Delivery Order disisir pada ringkasan,
halaman Approve, dan daftar tolakannya.

**Sesi 1 September 2026 berhenti di sini.** Berikutnya **Delivery Order**,
lalu ingatkan Owner mengerjakan **plandev**. Tata letak halaman Scan Tally
ditutup dengan pembagian kolom 7:5; 8:4 sudah dicoba lebih dulu dan Owner
memilih 7:5.

**Catatan untuk sesi berikutnya:** dua hal di Repack sengaja ditinggalkan
dan sudah disepakati Owner -- ambang persen susut menunggu data pemakaian
beberapa minggu, dan penataan ulang tampilan halaman Input Bahan/Hasil
belum dikerjakan. Metode input Material Usage juga masih terbuka; lihat
bagian tersendiri di atas.

Satu temuan dari penyisiran Price List sengaja TIDAK dikerjakan karena
modulnya belum gilirannya:

- **pH masih memakai `->numeric()`** di GR Product labeling, Sales Return,
  Stock Take, dan Found Item Scanner. Owner sudah memutuskan pH tidak boleh
  bertombol panah saat modul Boning disisir; keputusan yang sama belum
  diterapkan di keempat tempat itu, dan pH ikut masuk ke barcode 26 karakter.

### Panel admin TIDAK memuat CSS hasil build aplikasi

`FilamentAsset::register` di `AppServiceProvider` sengaja dinonaktifkan, jadi
yang berlaku di panel hanya CSS bawaan Filament -- dan Filament hanya
menyertakan kelas yang **ia sendiri** pakai. Kelas Tailwind yang ditulis di
blade aplikasi **belum tentu ada wujudnya**, dan kegagalannya sepenuhnya
senyap: tidak ada error, elemennya sekadar tampil tanpa gaya.

**Contoh yang sudah menggigit dua kali di halaman Scan Tally:** `grid-cols-2`
yang polos TIDAK ADA di CSS Filament; yang ada hanya varian ber-breakpoint
seperti `md:grid-cols-2`. Menulis `<div class="grid grid-cols-2">`
menghasilkan grid tanpa definisi kolom, sehingga isinya menumpuk ke bawah.
Karena itu tata letak dua kolom di halaman itu ditulis sebagai **style
langsung**, bukan kelas -- dan penulis aslinya rupanya sudah menabrak hal yang
sama, karena kode lamanya juga memakai style langsung.

**Sebelum memakai kelas Tailwind di blade panel, pastikan ia benar-benar ada:**

```
grep -c "\.nama-kelas[^a-zA-Z0-9_-]" public/css/filament/filament/app.css
```

Perhatikan bahwa sebagian kelas memakai selektor gabungan (`space-y-6` menjadi
`.space-y-6>:not([hidden])~:not([hidden])`), jadi pencocokan yang terlalu ketat
bisa memberi hasil negatif palsu.

**Utang yang sudah diketahui, belum dikerjakan.** Pemindaian halaman Scan Tally
menemukan **22 dari 68 kelas** tidak menghasilkan CSS apa pun. Tujuh di
antaranya diperbaiki saat itu; enam belas sisanya sudah ada sejak sebelumnya --
antara lain `dark:bg-gray-900`, `dark:text-white`, `focus:ring-primary-500`,
`ring-gray-950/5`, bahkan `mb-1`. Halamannya tetap terlihat wajar karena
Filament sudah punya warna bawaan yang masuk akal untuk mode gelap, sehingga
tidak ada yang menyadarinya.

Memperbaikinya menyeluruh berarti menyentuh cara aset dimuat untuk **seluruh**
panel -- entah dengan mendaftarkan build Vite aplikasi, atau dengan memperluas
`missing-color-utilities.blade.php` menjadi berkas utilitas umum. Sudah
disepakati Owner sebagai pekerjaan tersendiri, bukan disisipkan sambil lalu.

### Penagihan berat: invoice memakai berat DO Receipt APA ADANYA

**Jangan memasang pembatas otomatis di invoice.** Pernah dipasang
(`min(diterima, PO)`) dan langsung dicabut lagi atas koreksi Owner.

Aturan pelanggan memang berbunyi: berat kurang ditagih sesuai yang diterima,
berat lebih ditagih sesuai PO. Tetapi **penyesuaiannya dikerjakan MANUSIA di
DO Receipt**, bukan oleh rumus di invoice.

Alurnya: barang datang 52 kg untuk PO 50 kg, petugas menurunkan angkanya
menjadi 50 di DO Receipt, dan selisih 2 kg tercatat sebagai Financial Loss
lewat perbandingan berat kirim lawan berat terima yang sudah ada di halaman
Approve.

Membatasinya otomatis merusak dua hal sekaligus: penyesuaian yang seharusnya
terlihat menjadi tersembunyi, dan kerugian 2 kg itu tidak pernah tercatat sama
sekali. Kodenya yang sekarang terlihat "salah" bila hanya dibaca sepintas --
karena itu alasannya ditulis di dalam `InvoiceResource` dan dikunci oleh
`InvoiceBillableWeightTest`.

### HPP belum ada, karena itu susut kirim tercatat Rp 0

`FinancialLoss` untuk susut kirim sengaja dicatat dengan `amount => 0.00`;
jumlah kilonya hanya ditulis di catatan.

Alasannya, disampaikan Owner: harga di Sales Order adalah **harga jual**,
bukan HPP, sehingga memakainya untuk menilai kerugian akan melebih-lebihkan
angkanya. Sistem ini belum punya cara menghitung HPP sama sekali; tempatnya
semestinya di modul Boning. **Sudah disepakati sebagai sesi tersendiri**,
jangan dikerjakan sambil lalu.

### Awalan nomor DO dan nomor resi punya satu rumah

Nomor resi diturunkan dari nomor DO dengan mengganti awalannya, supaya
keduanya mudah dipasangkan saat dilihat manusia. Kedua awalan ada di
`DeliveryOrder::NUMBER_PREFIX` dan `DeliveryOrder::RECEIPT_NUMBER_PREFIX`.

Dulu awalannya diketik ulang sebagai teks di halaman Approve. Kalau awalan di
model diubah dan salinan itu tertinggal, penggantian teksnya tidak menemukan
apa pun dan **nomor resi menjadi sama persis dengan nomor DO** -- tanpa error,
dan tanpa apa pun yang menahannya, karena index unique pada `receipt_number`
sudah dilepas pada migrasi 1 Juli 2026.

### Jadwal kirim hilang di AKHIR HARI KIRIM, bukan pada peristiwa dokumen

Delivery Plan adalah alat kerja petugas distribusi: jadwal muncul begitu
Sales Order dibuat, dan dibagikan H-1 sekitar pukul sepuluh malam.

Sebelum 1 September 2026 jadwalnya **tidak pernah hilang** -- daftarnya
memuat seluruh jadwal yang pernah dibuat sejak sistem berdiri.

Ketiga peristiwa dokumen ditolak Owner, masing-masing dengan alasannya:

| Penanda | Kenapa keliru |
|---|---|
| Surat jalan dibuat | Kadang dibuat sehari sebelum berangkat, jadi jadwalnya hilang padahal barangnya belum ke mana-mana |
| Resi penerimaan dibuat | Berarti sopir sudah pulang, jauh sesudah jadwalnya tidak relevan |
| Lewat dari hari kirim | Pengiriman sore hilang sejak pagi |

**Perhatikan:** status `on_delivery` pada Sales Order dipasang saat surat
jalan DIBUAT (`DeliveryOrder::created`), bukan saat truk berangkat. Jadi
status itu pun bukan penanda yang dicari, dan sistem ini memang tidak punya
penanda "truk berangkat".

**Aturan yang dipakai:** hari kirim itu sendiri. Sebuah jadwal berhenti
menjadi jadwal ketika harinya habis, sehingga pengiriman sore tetap terlihat
sepanjang hari itu.

**Ditambah satu klausa yang menutup lubangnya:** jadwal yang harinya sudah
lewat tetapi Sales Order-nya belum selesai TETAP ditampilkan, ditandai merah.
Tanpa itu, pengiriman yang tertunda lenyap diam-diam pada tengah malam justru
ketika ia paling perlu dilihat.

Ada di `DeliveryPlan::scopeStillRelevant()`, dipasang sebagai **saringan
bawaan** dan bukan batasan tetap pada `getEloquentQuery()` -- mematikan
saringannya mengembalikan riwayat lengkapnya.

### Kompensasi pemasok: potongan TOTAL, dan TIDAK menyentuh kerugian

Kejadian di lapangan: sapi datang dengan surat jalan 100 kg, harga 50.000, jadi
tagihan 5 juta. Ditimbang ulang hanya 90 kg -- susut 10 kg, tercatat rugi 500
ribu. Lalu purchasing komplen karena mutunya jelek: lemaknya terlalu banyak,
hasil dagingnya sedikit. Pemasok memberi kompensasi, dan hutangnya berkurang.

**Bentuknya potongan TOTAL, bukan potongan per kilo.** Keputusan Owner. Yang
sebenarnya dinegosiasikan memang angka bulat, dan menurunkannya menjadi harga
per kilo adalah ketelitian yang dikarang -- lagi pula itu memaksa menghitung
ulang setiap baris hutang, merambat ke dokumen yang mungkin sudah disetujui.

**Harga di Purchase Order TIDAK diubah.** PO adalah catatan kesepakatan.
Menurunkan harganya di belakang menghapus selisih antara yang disepakati dan
yang akhirnya dibayar. `payables.amount` tetap utuh:

```
balance = amount - compensation - paid_amount
```

**KOMPENSASI TIDAK PERNAH MENYENTUH KERUGIAN.** Ini keputusan yang paling
penting di sini, dan ia menggantikan rancangan sebelumnya.

Rancangan pertama membedakan kompensasi karena BERAT dan karena KUALITAS, dan
yang karena berat ikut mengurangi kerugian susut. Penjelasan Owner membatalkan
dasarnya: **komplainnya selalu soal mutu.** Pemasok tidak pernah mengganti
karena beratnya kurang; susut timbang adalah urusan lain yang sudah tercatat
sendiri, dan berat yang dicatat diterima tetap mengikuti surat jalan pemasok --
itu keputusan bisnis tersendiri.

Artinya pembedaan itu **membedakan sesuatu yang tidak dibedakan di lapangan**,
dan justru pembedaan itulah bagian yang berbahaya: salah memilih alasan
menghapus kerugian susut yang nyata, tanpa satu pun gejala. **Jangan dipasang
lagi.**

Susut 10 kg tetap tercatat 500 ribu karena beratnya memang tidak pernah sampai.
Kompensasi didapat karena hal lain. Gambaran utuhnya terbaca dari kedua angka
yang berdiri sendiri, dan menetralkannya lebih dulu justru menyembunyikan
dua-duanya.

Pertanyaan Owner "bagaimana kalau kompensasinya lebih besar daripada
kerugiannya" ikut hilang dengan sendirinya: tidak ada yang perlu dibatasi.
**Satu-satunya batas yang tersisa adalah sisa tagihan** -- kompensasi yang
melebihi itu berarti pemasok berhutang kepada kita, dan itu dokumen lain.

Alasannya tetap dicatat sebagai **keterangan bebas**, berguna untuk ditelusuri
tetapi tidak mengubah angka apa pun.

Haknya terpisah: `record_payable_compensations`. Mencatat kompensasi mengurangi
yang dibayar perusahaan -- itu keputusan uang, beda dari melihat daftar hutang
dan beda dari membayar.

**Belum ada cara membatalkan kompensasi.** Owner sudah diberi tahu; menunggu
pembahasan tersendiri.

### Pemetaan kolom Filament TIDAK simetris, dan itu merusak layar HP

**3 September 2026.** Owner mengirim tangkapan layar Create Invoice dari HP:
fieldnya mengerut jadi kotak-kotak sempit yang tidak bisa dibaca maupun diisi.

Sebabnya ada di Filament sendiri, dan halus:

```php
->columns(12)      // -> ['lg' => 12]      HANYA dari lg ke atas
->columnSpan(3)    // -> ['default' => 3]  SEMUA ukuran
```

`Grid::columns(int)` dipetakan ke breakpoint **lg**, sementara
`columnSpan(int)` dipetakan ke **default**. Jadi di layar sempit gridnya
menjadi satu kolom sementara isinya tetap meminta rentang tiga.

Dan CSS tidak mengabaikan permintaan itu: kolom eksplisitnya cuma satu, jadi
browser membuat kolom implisit untuk menampung rentangnya, dan kolom implisit
berukuran `auto` -- menyusut ke isinya. Itulah kotak-kotak sempit yang
berjajar di kiri pada tangkapan layar Owner.

**Aturannya: kalau `columns()` disebut per breakpoint, `columnSpan()` juga
harus.**

```php
->columns(['default' => 1, 'lg' => 12])
->columnSpan(['default' => 'full', 'lg' => 3])
```

Untuk menempatkan sesuatu di kanan, pakai `->columnStart(['lg' => 8])` --
BUKAN Placeholder kosong sebagai pengganjal. Di layar sempit pengganjal
seperti itu berubah menjadi baris kosong yang ikut memakan tempat.

Baris judul kolom palsu di atas repeater hanya masuk akal saat kolomnya
berjajar. Kelas `.swm-wide-only` di `missing-color-utilities.blade.php`
menyembunyikannya di bawah 1024px, ambang yang sama dengan lg milik Filament.

**MASIH TERBUKA di dua belas Resource lain**, dan SUDAH PERNAH DICOBA DISAPU
SEKALIGUS LALU DIBATALKAN. Baca bagian di bawah sebelum mencobanya lagi.

### Sapuan tata letak serentak DIBATALKAN, 3 September 2026

Sembilan belas berkas pernah dirapikan sekaligus lewat PR #204: seluruh
`columnSpan(N)` telanjang diubah menjadi `['default' => 'full', 'lg' => N]`,
lengkap dengan penjaga polanya. Semua test hijau, dan tidak ada satu pun
halaman yang pernah dilihat di layar sungguhan sebelum dirilis.

Owner memeriksa satu halaman -- **Create Request Material** -- dan hasilnya
**lebih berantakan daripada sebelumnya**. PR itu di-revert utuh.

**Pelajarannya bukan bahwa perbaikannya salah, melainkan bahwa bentuk
pekerjaannya salah.** Test bisa membuktikan rentangnya sudah per breakpoint;
tidak ada test yang bisa membuktikan halamannya enak dilihat. Tata letak hanya
bisa dinilai dengan mata, di layar sungguhan, satu halaman pada satu waktu.

Satu dugaan penyebabnya, karena ini ikut terbawa dalam sapuan yang sama:
sepuluh berkas menyembunyikan baris judul repeaternya dengan

```php
->extraAttributes(['class' => 'hidden md:grid'])
```

`md:grid` termasuk kelas yang **tidak ada CSS-nya** di panel ini -- lihat
bagian "Panel admin TIDAK memuat CSS hasil build aplikasi". Yang benar-benar
berlaku hanya `hidden`, dan tidak ada apa pun yang membatalkannya di layar
lebar. Artinya baris judul itu **tidak pernah tampil di ukuran layar mana
pun**, termasuk di layar lebar tempat ia dibutuhkan, sejak hari ia ditulis.

Memperbaikinya berarti **memunculkan baris judul yang belum pernah ada** di
sepuluh form sekaligus. Kalau lebar kolomnya tidak pas dengan isinya, hasilnya
justru lebih kacau daripada tidak ada judul sama sekali. Itu bug yang nyata dan
tetap perlu dibereskan -- tapi satu per satu, sambil dilihat.

**Cara mengerjakannya nanti:** ikut giliran modulnya. Saat sebuah modul
disisir, rapikan rentangnya, munculkan baris judulnya, lalu MINTA OWNER
MELIHATNYA sebelum lanjut. Persis cara Invoice dikerjakan, dan itu berhasil.

Kelas `.swm-wide-only` di `missing-color-utilities.blade.php` tetap ada dan
sudah dipakai Invoice; itu pengganti `hidden md:grid` yang benar.

**Halaman pemindai sengaja DILUAR semua ini.** Scan Tally, Scan GR Product,
Scan Mutation, dan Found Item Scanner memang menyembunyikan sidebar dan
dirancang untuk layar lebar dengan alat pemindai di tangan, bukan untuk HP.
Keputusan Owner: pastikan nyaman di layar lebar, layar kecil ditangani nanti
kalau memang perlu.

### Deposit yang tidak bisa dilihat sama saja dengan uang yang hilang

**4 September 2026.** Owner bertanya di mana deposit pelanggan bisa dilihat.
Jawabannya waktu itu: hanya di halaman Terima Pembayaran, dan hanya untuk grup
yang sedang dibuka.

Dua akibatnya, dan yang kedua jauh lebih buruk:

- tidak ada satu pun layar yang bisa menjawab **"pelanggan mana saja yang
  masih punya deposit"**;
- grup yang seluruh invoicenya SUDAH LUNAS **lenyap sama sekali** dari daftar
  piutang, karena daftarnya menyaring "punya invoice belum lunas" -- berikut
  uang perusahaan yang dipegang atas namanya.

Yang kedua itu bentuk kegagalan yang sama persis dengan yang diberantas
sepanjang hari: uang yang tidak punya tempat untuk terlihat.

Sekarang:

- **kolom Deposit di daftar piutang**, dihitung lewat dua subkueri agregat
  pada kueri tabelnya -- menambah grup tidak menambah kueri;
- **daftarnya ikut memuat grup yang tidak berutang tetapi menyimpan deposit**,
  lewat `EXISTS` pada pembayaran yang belum habis teralokasi;
- **kolom Deposit per pembayaran** di daftar pembayaran grup, supaya terlihat
  dari pembayaran MANA depositnya berasal.

Begitu depositnya habis DAN tagihannya lunas, grupnya baru boleh hilang dari
daftar. Ada test untuk ketiga keadaan itu.

### Lebih bayar menjadi DEPOSIT PELANGGAN

**4 September 2026, keputusan Project Owner** -- pertanyaan terakhir dari lima
yang lama menggantung. Kelebihan transfer dulu ditolak di pintu karena tidak
ada tempat menaruhnya, sehingga harus diurus di luar sistem.

Bentuknya mencerminkan uang muka pemasok yang sudah lebih dulu bekerja di sisi
pembelian: uang yang sudah benar-benar diterima, belum menempel ke tagihan
mana pun, menunggu invoice berikutnya.

**Bedanya satu, dan disengaja: depositnya DIHITUNG, bukan disimpan.** Sisi
pemasok menyimpannya di kolom `allocated_amount`; kolom semacam itu selalu
bisa melenceng dari baris alokasinya sendiri tanpa ada yang menyadarinya. Di
sini alokasinya yang menjadi satu-satunya kebenaran, jadi tidak ada kolom baru
sama sekali -- dan tidak ada migrasi.

**Deposit dipakai LEBIH DULU**, sebelum uang yang masuk hari ini. Itu membuat
deposit tidak menumpuk, dan membuat pertanyaan "kenapa pelanggan ini masih
punya deposit padahal tagihannya banyak" tidak pernah muncul.

**Alokasinya melekat pada pembayaran yang MEMBERI uangnya**, bukan pada
pembayaran yang kebetulan sedang dicatat. Itu yang membuat deposit terpakai
tetap bisa ditelusuri ke asalnya -- dan yang membuat membatalkan pembayaran
lama otomatis mengembalikan tagihan yang ditutupnya belakangan.

**Aturan keseimbangannya berubah bentuk**, dari "harus sama persis" menjadi
dua batas:

- alokasi tidak boleh MELEBIHI uang yang ada (termasuk deposit);
- alokasi tidak boleh KURANG dari potongannya. Potongan diberikan justru
  karena ada tagihan yang dianggap lunas tanpa uangnya masuk; potongan yang
  tidak menempel ke tagihan mana pun berarti uang hilang tanpa sebab.

**Pembayaran bernominal NOL kini sah, asal ada depositnya.** Melunasi tagihan
sepenuhnya dari deposit memang berbentuk begitu: tidak ada uang baru hari ini,
yang dipakai uang yang sudah lama diterima. Penjaga lama menolak semua
nominal nol dan sempat menutup jalur ini.

Buku kas tidak berubah perlakuannya: yang masuk tetap seluruh uang yang
diterima, potongannya tetap keluar. Deposit bukan peristiwa kas -- uangnya
sudah masuk sejak hari pembayarannya.

### Pembayaran pelanggan bisa DIBATALKAN -- membalik, bukan menghapus

**4 September 2026, keputusan Project Owner** -- jawaban atas pertanyaan
nomor 4 dari lima yang lama menggantung.

Sampai hari itu tidak ada jalan mundur sama sekali. Salah ketik nominal --
5.500.000 menjadi 55.000.000 -- hanya bisa diperbaiki lewat basis data
langsung.

**MENYUNTING SENGAJA TIDAK DIBUAT, dan Owner menyetujuinya.** Bukti terima
`PR#...` sudah dicetak dan diserahkan kepada pelanggan; kalau angkanya bisa
disunting diam-diam, kertas yang dipegang pelanggan dan catatan di sistem bisa
berbeda TANPA JEJAK bahwa pernah berbeda. Yang benar: batalkan lalu catat
ulang, supaya riwayatnya terbaca.

**Membatalkan berarti membalik LIMA hal**, dan melewatkan satu saja membuat
saldo bank atau piutang salah tanpa gejala:

1. alokasi ke tiap invoice -- `paid_amount` turun kembali;
2. sisa tagihan dan status invoice -- lewat `recalculate()`;
3. baris buku kas yang masuk -- dibalik dengan baris keluar;
4. baris buku kas potongannya -- dibalik dengan baris masuk;
5. pembayarannya sendiri -- DITANDAI batal, bukan dihapus.

**Baris buku kas aslinya TIDAK disentuh.** Menghapusnya membuat buku kas
berbohong tentang masa lalu; yang benar menambahkan lawannya, supaya keduanya
terbaca dan selisihnya nol.

Baris pembalik memakai penanda tersendiri, `Payment::CANCELLATION_REFERENCE` --
mengikuti pola `BankAccount::OPENING_BALANCE_REFERENCE`. Tanpa penanda itu
baris pembalik tidak bisa dibedakan dari baris aslinya, dan pembatalan
berikutnya akan ikut membalik baris pembaliknya sendiri.

**Nomor dokumennya tetap terpakai.** Nomor yang sudah terbit tidak boleh
dipakai ulang, dan penomoran kita memang sudah menghitung yang terhapus.

**Izinnya sendiri: `cancel_receivable_payments`**, tidak menumpang
`receive_receivables`. Mencatat uang masuk dan membatalkannya dua kewenangan
yang berbeda, dan biasanya orangnya juga berbeda.

**Penjaga hapus invoice ikut disesuaikan.** Alokasi milik pembayaran yang sudah
dibatalkan tidak lagi menahan penghapusan -- kalau ikut dihitung, satu kali
salah catat akan mengunci invoicenya selamanya walaupun pembayarannya sudah
dibalik.

**Jebakan yang sempat menghantam:** metode pembantu tidak boleh dinamai seperti
relasi. `bankTransactions()` yang mengembalikan Collection membuat Eloquent
melemparnya sebagai `LogicException` begitu diakses sebagai properti. Namanya
kini `cashBookLines()`.

**Setiap penjumlahan uang wajib lewat `Payment::scopeActive()`.** Yang
dibatalkan tetap ada -- itu inti dari membalik alih-alih menghapus -- jadi
tanpa penyaring itu ia ikut terhitung dan angkanya terlalu besar tanpa satu
pun gejala.

### Potongan akhirnya punya rumah: MASUK seluruh tagihan, KELUAR potongannya

**4 September 2026, keputusan Project Owner** -- jawaban atas pertanyaan
nomor 2 dari lima yang lama menggantung.

```
masuk   6.000.000   seluruh tagihan yang lunas
keluar    500.000   potongannya
─────────────────
saldo   5.500.000   sama dengan rekening sungguhan
```

**Usul pertamanya justru salah, dan sempat hampir dipakai:** mencatat MASUK
5,5 juta lalu KELUAR 500 ribu membuat saldonya 5 juta, padahal di bank ada 5,5
juta. Potongannya terhitung dua kali -- sekali karena tidak pernah masuk,
sekali lagi karena dicatat keluar. Saldo rekening di sistem ini memang
diturunkan dari `total masuk - total keluar`; lihat `BankAccount::currentBalance()`.

Ini menutup dua lubang sekaligus:

- **Potongan tidak lagi menguap.** Sebelumnya kas bertambah 5,5 juta, piutang
  berkurang 6 juta, dan 500 ribunya tidak ke mana-mana -- tidak ada laporan
  yang bisa menjawab "bulan ini biaya bank berapa, promo berapa". Sekarang
  tinggal menjumlahkan baris keluarnya per jenis.
- **Pembayaran yang SELURUHNYA potongan meninggalkan jejak.** Dulu baris buku
  kas hanya dibuat kalau uang riilnya lebih dari nol, jadi tagihan yang
  dilunasi penuh oleh promo lenyap dari piutang tanpa satu baris pun di buku
  kas.

**Harganya disepakati di muka:** rekening koran menampilkan SATU baris 5,5
juta sementara buku kas menampilkan DUA. Karena itu kedua barisnya membawa
NOMOR DOKUMEN YANG SAMA, supaya sekali lihat terbaca sebagai satu peristiwa
dan bukan dua transfer. Ada test yang menjaga itu.

Satu baris keluar PER POTONGAN, bukan satu baris gabungan -- jenisnya
berbeda-beda, dan justru per jenis itulah yang nanti ditanyakan.

### Potongan pembayaran punya JENIS, dan boleh menunjuk satu invoice

**4 September 2026.** Owner bertanya bagaimana potongan diperlakukan bila ada
tiga invoice -- 1jt, 3jt, 5jt -- dan pelanggan mentransfer 5.500.000 dengan
potongan.

Jawaban sistem waktu itu: potongan **larut ke kantong** uang yang membayar,
lalu dibagi tertua-dulu bersama uang riilnya. Untuk contoh itu 500 ribunya
mendarat di invoice 5jt -- tempat uangnya kebetulan habis.

**Untuk biaya bank itu benar; untuk promo tidak.**

| Jenis | Milik siapa | Perlakuan |
|---|---|---|
| Biaya admin bank | TRANSFERNYA | larut ke kantong -- pelanggan bermaksud bayar penuh, banknya memotong di jalan |
| Klaim promo | SATU INVOICE tertentu | menempel pada invoice itu |

Melarutkan promo membuat totalnya tetap benar tetapi **catatan invoice MANA
yang sebenarnya didiskon menjadi salah**, dan salahnya bergantung pada hal
yang sama sekali tidak relevan: di invoice mana uangnya kebetulan habis.

Sekarang tiap baris potongan punya:

- **`type`** -- Biaya Bank / Promo / Lainnya;
- **`invoice_id`** yang BOLEH KOSONG. Kosong adalah pernyataan, bukan
  kelalaian: "potongan ini milik transfernya".

`autoAllocate()` memasang potongan bertujuan lebih dulu ke invoicenya, baru
membagi sisa uangnya tertua-dulu. Penjaga keseimbangannya tidak berubah sama
sekali -- total alokasi tetap wajib sama dengan uang masuk ditambah SELURUH
potongan.

Ada penjaga tambahan: potongan bertujuan tidak boleh lebih besar daripada
tagihan invoice yang ditunjuknya. Kalau dibiarkan, kelebihannya diam-diam
menutup tagihan invoice LAIN -- persis kekacauan yang hendak dihindari dengan
menunjuknya.

**`type` baru DICATAT, belum dibukukan.** Ke akun mana biaya bank dan promo
mendarat masih pertanyaan terbuka -- lihat lima pertanyaan skema piutang.
Tanpa itu, potongan masih menguap dari pembukuan: kas bertambah 5,5jt, piutang
berkurang 6jt, dan 500 ribunya tidak ke mana-mana. Yang sudah berubah hanya
ini: sekarang datanya cukup untuk menjawabnya nanti.

### Alokasi pembayaran diisi sendiri, TERTUA DULU menurut TANGGAL INVOICE

**4 September 2026.** Owner bertanya untuk apa alokasi per invoice, dan apakah
memang harus ditempatkan manual.

**Alokasinya memang perlu ada.** Uang masuk dari GRUP, sementara yang punya
jatuh tempo, umur, dan status tukar faktur adalah INVOICE satu per satu. Kalau
pembayaran cuma mengurangi total utang grup tanpa menyebut invoicenya, kolom
"Sudah Jatuh Tempo" kehilangan artinya -- sistem tahu grup itu bersisa sekian,
tetapi tidak tahu dari invoice mana, jadi tidak tahu sudah lewat tempo atau
belum.

**Tetapi manualnya berlebihan.** Hampir selalu jawabannya sama: lunasi yang
paling lama menunggu, lalu turun sampai uangnya habis. Yang benar-benar butuh
keputusan manusia cuma kasus khusus -- pelanggan sedang komplain satu invoice,
atau menyebut sendiri invoice mana yang ia bayar.

Sekarang kotaknya terisi sendiri begitu nominal uang masuk atau potongannya
diisi. Ini **hanya mengisikan, bukan mengunci**: tiap kotak tetap bisa diubah,
dan penjaga keseimbangannya tidak berubah sama sekali.

**Urutannya dari TANGGAL INVOICE, bukan jatuh tempo.** Keputusan Owner, dan
bedanya nyata: invoice tukar faktur yang fakturnya belum ditukar **belum punya
jatuh tempo sama sekali**. Kalau diurutkan dari jatuh tempo ia tersingkir ke
belakang -- padahal justru dialah yang paling lama menunggu. Ada test khusus
untuk kasus itu.

Potongan ikut dihitung sebagai uang yang membayar, karena memang begitu:
tagihan yang dianggap lunas meski uangnya tidak pernah masuk.

### Baris hantu di repeater: nilai menggantung MEMBUAT ULANG baris yang sudah dihapus

**4 September 2026.** Owner menambah beberapa baris potongan di halaman
penerimaan pembayaran, menghapus semuanya, lalu menekan simpan -- dan SATU
baris muncul kembali lengkap dengan pesan kesalahan. Pembayarannya gagal
tersimpan.

Sebabnya di Livewire, bukan di kode kita. Nilai yang diketik dikirim dengan
penundaan (`wire:model` bawaan Filament). Kalau barisnya dihapus sebelum
nilainya sempat terkirim, nilai itu **menumpang permintaan berikutnya** -- dan
menulis properti bersarang ke kunci yang sudah tidak ada **MEMBUAT ULANG**
kuncinya.

Yang lahir kembali bukan barisnya yang utuh, melainkan satu field saja:

```
baris asli  : {"description": null, "amount": null}
baris hantu : {"amount": "5000"}
```

**Pembeda itulah yang dipakai untuk membuangnya**, dan ia aman: baris yang
benar-benar ditambahkan SELALU punya seluruh kunci skemanya sejak lahir.
Baris kosong tetapi utuh TIDAK dibuang -- itu baris yang sungguh ditambahkan
lalu tidak diisi, dan penggunanya berhak diberi tahu.

Diperbaiki dua lapis:

1. **Menghilangkan sebabnya** -- kedua field potongan kini `live(onBlur: true)`,
   jadi tidak ada nilai menggantung saat barisnya dihapus.
2. **Menjaring sisanya** -- `forgetGhostDeductionRows()` membuang baris yang
   tidak punya seluruh kunci skemanya, dijalankan sebelum validasi.

Lapis kedua tetap dipasang meski lapis pertama sudah menutup jalurnya, karena
yang dipertaruhkan uang: baris hantu yang lolos akan tercatat sebagai potongan
yang tidak pernah dimasukkan siapa pun.

**Waspadai pola yang sama di repeater lain.** Aplikasi ini penuh repeater, dan
sebabnya ada di Livewire -- bukan khusus halaman ini. Belum disapu; kerjakan
saat modulnya tiba gilirannya, dan periksa dengan mengetik-lalu-menghapus,
bukan dengan membaca kode.

### Nomor dokumen uang: PR# masuk, PV# keluar -- dan bukti terima yang bisa dicetak

**3-4 September 2026, keputusan Project Owner.**

```
PR#260001   uang masuk dari pelanggan   (Payment Receipt)
PV#260001   uang keluar ke pemasok      (Payment Voucher)
```

Awalannya langsung memberi tahu ARAH uangnya. Bentuk lamanya tidak:
`PAY-0001/IX/26` untuk uang masuk dan `SP#260001` untuk uang keluar -- dua
format berbeda, dan tidak satu pun menyatakan masuk atau keluar.

Bentuk barunya menaruh urutan di UJUNG, sehingga keduanya kini memakai
`DocumentNumber::next()`. Itu sekaligus membereskan dua rakitan penomoran yang
masing-masing punya cara terulang sendiri; `SupplierPayment::generateNumber()`
membaca urutannya dengan `preg_match('/(\d{4})$/')` -- persis batas yang
membuat `DocumentNumber` dibuat.

**Nomor SP# yang SUDAH TERBIT sengaja tidak ditulis ulang.** Menulis ulang
nomor dokumen yang sudah keluar adalah hal yang tidak boleh dilakukan
pembukuan, sekecil apa pun datanya. Urutan PV# berangkat dari awal, dan
keduanya hidup berdampingan karena awalannya berbeda.

**Pembayaran pelanggan yang sudah tercatat dulu TIDAK BISA DILIHAT LAGI.**
Tidak ada Resource, tidak ada daftar, tidak ada halaman. Uang masuk lalu
hilang dari pandangan; keberadaannya hanya bisa disimpulkan dari sisa tagihan
invoice yang berkurang. Kalau pelanggan bertanya "transfer saya tanggal sekian
sudah masuk belum", tidak ada satu pun layar yang bisa menjawabnya -- dan
karena tidak ada tempatnya, tidak ada pula tempat untuk tombol cetaknya.
`PaymentsRelationManager` di halaman piutang grup yang menjawabnya sekarang.

**SATU dokumen, bukan dua.** Keputusan Owner: bagian atas untuk pelanggan
(telah terima dari siapa, berapa, referensi transfernya), rincian alokasi per
invoice di bawahnya untuk keuangan. Dipecah menjadi kwitansi dan bukti bank
masuk yang terpisah hanya kalau nanti diminta.

Potongan ditampilkan terpisah di dokumen itu, dan itu bukan hiasan: uang yang
benar-benar masuk bank LEBIH KECIL daripada tagihan yang lunas, dan tanpa
barisnya pembaca akan mencocokkan kertas dengan rekening koran lalu menemukan
selisih yang tidak dijelaskan apa pun.

**CSS-nya menyatu di berkasnya, tidak memanggil CDN** seperti halaman cetak
lain. Dokumen ini diserahkan kepada pelanggan dan sering dicetak dari gudang;
halaman yang tata letaknya runtuh saat internet lambat bukan bukti pembayaran
yang bisa dipegang. Ketergantungan CDN di halaman cetak lain tetap tercatat
sebagai utang tersendiri.

**BELUM ADA: dokumen cetak untuk uang keluar (PV).** Penomorannya sudah, tetapi
vouchernya belum dibuat. Menunggu Owner memutuskan isinya.

### Daftar piutang: satu aturan, satu kueri, dan nomor yang tidak bisa terulang

**3 September 2026.** Tiga sisa temuan Receivable dibereskan sekaligus.

**Nominal dan hitungan invoice dulu dihitung dengan dua aturan berbeda.**
Nominalnya dijumlahkan lewat `join` mentah -- yang MELEWATI penyaring
hapus-lunak invoice -- sementara hitungan "N Inv" memakai `whereHas` yang
MENERAPKANNYA. Satu grup bisa menampilkan **"Rp 5.000.000 / 0 Inv"**: dua
angka bersebelahan yang saling membantah, dan tidak ada cara tahu mana yang
benar.

Sekarang keduanya berangkat dari relasi yang sama, `CustomerGroup::invoices()`
(`HasManyThrough` ke Invoice lewat Receivable). HasManyThrough menerapkan
penyaring hapus-lunak pada baris piutang MAUPUN invoicenya, jadi pertanyaan
yang sama tidak bisa lagi dijawab dua cara.

**Enam kueri per baris grup.** Tiap kolom punya `getStateUsing` dan
`description` yang berkueri sendiri-sendiri; dua puluh grup berarti seratus
dua puluh kueri hanya untuk membuka satu halaman. Keenamnya kini menjadi
subkueri agregat pada kueri tabelnya -- satu kali untuk seluruh halaman.

Pengujiannya sengaja TIDAK mematok jumlah kueri, melainkan membandingkan lima
grup lawan sepuluh grup dan menuntut angkanya SAMA. Yang dijaga bukan
angkanya, melainkan bahwa menambah baris tidak menambah kueri.

**Nomor pembayaran punya dua cara terulang**, keduanya berakhir crash karena
`payment_number` bertanda unique:

- ia mengambil pembayaran TERAKHIR menurut id lalu membaca urutannya dengan
  regex, dan mengembalikan hitungannya ke **1** begitu nomor baris itu tidak
  cocok -- data lama, impor, apa pun;
- ia tidak menghitung baris yang sudah dihapus lunak, padahal dokumen boleh
  hilang dan nomornya tetap tidak boleh dipakai ulang.

`Payment::nextNumber()` sekarang mengambil urutan TERBESAR di tahun berjalan
dari seluruh baris termasuk yang terhapus, dan tahunnya dibaca dari NOMORNYA
sendiri, bukan dari `created_at` -- dokumen bertanggal mundur akan salah
kelompok kalau dipilah dengan tanggal pembuatannya.

Bentuk `PAY-0001/IX/26` menaruh urutannya di TENGAH, jadi
`DocumentNumber::next()` tidak bisa dipakai apa adanya. Mengubah bentuknya
berarti mengubah nomor dokumen yang dipegang keuangan, dan itu keputusan
Owner -- bukan efek samping perbaikan.

**`Invoice::STATUS_PAID`** kini satu-satunya tempat kata `'Lunas'` ditulis.
Status invoice adalah kolom teks berisi lima nilai campur bahasa, dan "sudah
dibayar atau belum" ditentukan dengan membandingkannya ke teks itu di banyak
tempat; satu salah ketik berarti invoice lunas ikut terhitung sebagai piutang
tanpa satu pun gejala.

### Penjaga bilingual hanya melihat kunci yang SUDAH terdaftar

**3 September 2026.** Owner melaporkan bahasa modul Receivable belum beres.
Pemeriksaannya menemukan 17 kunci berbahasa Indonesia, 22 kunci yang tidak
pernah ada di `id.json`, dan 4 teks yang tidak lewat penerjemah sama sekali.

Yang lebih penting: **`BilingualParityTest` tidak melihat satu pun dari
semuanya.** Penjaga itu hanya memindai kunci yang sudah terdaftar di
`id.json`. Kunci yang berbahasa Indonesia DAN tidak pernah didaftarkan lolos
sepenuhnya -- padahal justru itu kasus yang paling buruk, karena Laravel
menampilkan kuncinya sendiri saat terjemahannya tidak ada. Pengguna yang
memilih bahasa Inggris membaca kalimat Indonesia utuh, tanpa satu pun gejala.

Penjaganya sekarang ikut memindai `app/` dan `resources/views/`. Registernya
naik dari 41 menjadi 75, dan **kenaikan itu bukan utang bertambah melainkan
utang yang akhirnya terlihat**: 34 kunci sudah ada di sana selama ini tanpa
pernah terhitung.

**Pola yang berulang di proyek ini:** penjaga yang memeriksa tempat yang salah
memberi rasa aman yang justru lebih berbahaya daripada tidak ada penjaga.
Sama seperti `TrashedFilter` yang tidak menyaring apa pun saat disembunyikan,
dan `Livewire::test()` yang lulus lewat pintu belakang.

**Istilah utang/piutang hanya untuk PERCAKAPAN dengan Owner**, supaya tidak
tertukar seperti hari itu. Di aplikasinya tetap Payable dan Receivable; Owner
menegaskan penamaan itu tidak bermasalah.

**Ikut dibereskan:** PDF ekspor Payable tidak punya kolom kompensasi, sehingga
di kertas `Total - Telah Dibayar` tidak sama dengan `Sisa` dan pembacanya
tidak punya cara mencocokkannya. Ekspornya tidak ikut diperbarui waktu
kompensasi ditambahkan. Judul dan kepala kolomnya juga campur -- judul
Inggris, kolom Indonesia, tanpa `__()` -- dan kini lewat penerjemah semua.

### Halaman Resource menerima MODEL, bukan angka id -- dan pengujiannya ikut tertipu

**3 September 2026.** Owner melaporkan halaman Receive Payment menjawab
**404 Not Found** di produksi, tanpa keterangan apa pun.

Penyebabnya:

```php
public function mount(int|string $record): void
{
    $this->record = CustomerGroup::findOrFail($record);   // <- 404 di sini
}
```

Filament memasang route binding untuk `{record}` di setiap route Resource,
jadi saat `mount()` berjalan parameternya **sudah berupa objek model**.
`findOrFail()` lalu mencari grup yang idnya berupa OBJEK, tidak pernah
menemukannya, dan menjawab dengan 404.

**Kenapa ini bentuk kegagalan yang paling menyesatkan.** Laravel TIDAK
mencatat 404 ke log -- `ModelNotFoundException` dan `NotFoundHttpException`
ada di daftar `dontReport` bawaan. Jadi di produksi halamannya hanya menjawab
"404 Not Found" tanpa meninggalkan satu baris pun di Log Viewer, dan terlihat
persis seperti halaman yang memang tidak ada -- padahal kodenya berjalan,
routenya terdaftar, dan datanya lengkap.

**DAN PENGUJIANNYA IKUT TERTIPU. Ini bagian terpentingnya.**

```php
Livewire::test($page, ['record' => $do->id]);   // menyerahkan ANGKA
```

Peramban tidak pernah melalui jalur itu. Jadi pengujiannya hijau sementara
halamannya 404 bagi setiap orang yang membukanya. Halaman Approve Delivery
Order punya dua pengujian yang lulus bertahun-tahun dengan cara ini, dan
ternyata **ia juga rusak** -- dibuktikan dengan mengembalikan kodenya sebentar
dan melihat pengujian HTTP yang baru langsung merah.

**Aturannya sekarang:**

- Halaman berparameter record menerima modelnya:
  `mount(int|string|Model $record)` lalu `$record instanceof Model ? $record : Model::findOrFail($record)`.
- Halaman berparameter record **WAJIB diuji lewat HTTP**
  (`$this->get('/admin/.../{id}/aksi')->assertSuccessful()`), bukan hanya
  lewat `Livewire::test()`. `Livewire::test()` boleh dipakai untuk menguji
  aksinya, tetapi serahkan MODEL, bukan id.
- `ResourcePageRecordBindingTest` menjaga polanya.

**Pelajaran yang lebih luas: pengujian yang memasuki lewat pintu belakang
tidak membuktikan pintu depannya terbuka.** Halaman Receive Payment --
halaman yang MENERIMA UANG -- tidak punya satu pun pengujian sampai hari ini,
karena mencobanya dengan tangan menuntut merangkai pelanggan, produk, Sales
Order, tally, surat jalan, bukti terima, dan invoice lebih dulu. Berkas
`ReceivePaymentPageTest` merangkai semuanya dalam hitungan detik; itu yang
seharusnya ada sejak awal.

### Piutang: pembayaran pelanggan berhenti ditimpakan ke kolom tagihan

**3 September 2026, temuan terberat sejauh ini.** Penerimaan piutang MENIMPA
`invoices.balance` dengan sisa tagihan:

```php
$invoice->balance = $invoice->balance - $allocAmount;
```

Kolom yang semula berarti "berapa yang ditagihkan" berubah makna menjadi
"berapa yang masih kurang", dan jumlah aslinya tidak disimpan di mana pun.

Sementara itu form Invoice menghitung ulang **kolom yang sama** dari barang,
biaya, dan uang muka -- tanpa tahu apa-apa tentang pembayaran. Jadi:

> Pelanggan bayar 600rb dari tagihan 1jt, sisa 400rb. Siapa pun membuka
> invoice itu dan mengubah **satu angka apa saja**, sisa tagihan melompat
> kembali ke **1jt**. Pembayarannya lenyap dari tagihan, sementara catatan
> alokasinya tetap ada. Tanpa error, tanpa peringatan.

Sudah dibuktikan lewat halaman Edit yang sungguhan, bukan dibaca dari kode:
`ReceivablePaymentIntegrityTest`.

Ini juga sebabnya penjaga hapus yang dipasang sehari sebelumnya belum cukup.
Invoice yang sudah dibayar memang tidak bisa DIHAPUS, tetapi masih bisa
DISUNTING sampai pembayarannya hilang.

**Sekarang keduanya kolom yang berbeda, mengikuti Payable:**

```
ditagihkan  = subtotal + charge - down_payment   (dihitung, tidak disimpan)
paid_amount = jumlah seluruh alokasi pembayaran   (kolom baru)
balance     = ditagihkan - paid_amount            (turunan, milik model)
```

`Invoice::recalculate()` satu-satunya yang menulis `balance`, dipanggil dari
hook `saving` sehingga berlaku apa pun yang dikirim form. Field `balance` di
form kini `->dehydrated(false)`: ia hanya ditampilkan.

Pembayaran masuk lewat `Invoice::applyPayment()`, yang menambah `paid_amount`
dan membiarkan sisanya menyusul sendiri.

**Migrasi `2026_09_03_160000` sekaligus MEMPERBAIKI data yang sudah rusak.**
`paid_amount` dihitung ulang dari `payment_allocations` -- catatan yang tidak
pernah ditimpa -- lalu `balance` dan `status` diturunkan ulang dari sana.
Invoice yang tadinya tampak lunas hanya karena balancenya pernah tertimpa nol
akan kembali bersisa, dan sebaliknya.

**Jatuh tempo invoice lunas tidak lagi dihitung ulang.** Hook `saving`
sebelumnya menghitung ulang `due_date` untuk status apa pun selain TF, jadi
membayar invoice hasil tukar faktur menggeser jatuh temponya kembali ke
tanggal invoice -- justru pada saat pelanggannya membayar. Status `'Lunas'`
kini melewati perhitungan itu; tanggalnya sudah menjadi riwayat.

Dua penjaga pola: tidak ada berkas selain modelnya yang boleh menulis
`$invoice->balance`, dan field balance di form wajib `dehydrated(false)`.

**Sisa temuan Receivable, BELUM dikerjakan** (kerapian, bukan uang):

- **Nominal dan hitungan invoice di daftar dihitung dengan dua aturan
  berbeda.** Nominalnya memakai `join` mentah yang MELEWATI penyaring
  hapus-lunak invoice; hitungan "N Inv" memakai `whereHas` yang
  MENERAPKANNYA. Satu grup bisa menampilkan "Rp 5.000.000 / 0 Inv".
- **Enam kueri per baris grup.** Tiga kolom, masing-masing punya
  `getStateUsing` dan `description` yang berkueri sendiri.
- **Lunas ditentukan dengan `status != 'Lunas'`** pada kolom teks berisi lima
  nilai campur bahasa.
- **Penomoran pembayaran masih rakitan lama** -- `whereYear` + id terbesar +
  regex, dan bila regexnya tidak cocok nomornya kembali ke 1. `payment_number`
  unique, jadi akibatnya crash. `DocumentNumber::next()` sudah ada.

### Invoice: satu rumus, arti kolom yang dikunci, dan penghapusan yang membereskan jejaknya

**3 September 2026.** Modul Invoice disisir. Temuan pentingnya:

**Rumus tagihan disalin LIMA KALI di satu berkas.** Sekali di `updateTotals()`
dan empat kali lagi sebagai nilai awal `items`, `total_discount`, `subtotal`,
dan `balance`. Salinannya sudah berbeda arah: `subtotal` versi nilai awal
berisi barang SAJA, sementara `updateTotals()` menimpanya dengan barang
DITAMBAH biaya tambahan. **Satu kolom, dua arti, tergantung apakah ada yang
sempat mengetik sesuatu di form** -- dan tidak ada cara membedakannya dari
baris yang sudah tersimpan.

Sekarang semuanya lewat `App\Support\InvoiceTotals`, dan artinya dikunci:

```
subtotal = barang, sesudah diskon barisnya
charge   = biaya tambahan, sesudah diskon barisnya
balance  = subtotal + charge - uang muka
```

Kolom `charge` sudah ada di tabel sejak awal dan tidak pernah diisi. Sekarang
ia yang menampung biaya tambahan, sehingga `subtotal` bisa kembali berarti
subtotal. Migrasi `2026_09_03_120100` menghitung ulang keduanya dari baris
anaknya. **`balance` sengaja TIDAK disentuh** -- itu angka yang benar-benar
ditagihkan, kedua rumus lama menghasilkan nilai yang sama untuknya, dan kalau
ada yang meleset yang meleset itu tagihan.

**Harga berdesimal ditagih seratus kali lipat.** `updateTotals()` membuang
titik dari harga seolah titik itu pemisah ribuan yang dipasang mask -- padahal
di Invoice **tidak ada satu pun mask uang**. Fieldnya cuma `->numeric()`. Ketik
`1234.56` dan tagihannya `123.456`, sementara harga yang tersimpan tetap
1234.56: invoice memperlihatkan harga benar dengan jumlah salah.

Pembersih yang menunggu mask yang tidak pernah ada. Sekarang masknya
benar-benar dipasang lewat `static::money()`, jadi pembersihannya benar dan
koma desimal tidak mungkin lagi masuk. Sekaligus menjawab keluhan Owner
tentang uang tanpa pemisah ribuan dan input bertombol panah.

**Diskon TIDAK dibersihkan seperti uang.** `InvoiceTotals::percent()` sengaja
tidak membuang titik. Field diskon tidak berformat, jadi titik di sana hanya
bisa berarti koma desimal -- membuangnya mengubah 2,5% menjadi 25%, bug yang
sudah pernah terjadi di Sales Order.

**Menghapus invoice dulu meninggalkan piutangnya tetap hidup.** Foreign
key-nya `cascadeOnDelete`, tetapi Invoice memakai SoftDeletes -- dan hapus
lunak adalah UPDATE, bukan DELETE. Cascade itu tidak pernah jalan. Piutangnya
tetap tercatat dan tetap ditagihkan kepada pelanggan untuk invoice yang sudah
tidak ada, dan surat jalan beserta bukti terimanya tetap bertanda 'Invoiced'.

Sekarang `deleting` ikut membatalkan piutangnya dan mengembalikan kedua
dokumen kirim ke 'Approved'; `restored` mengembalikan ketiganya. Ini juga yang
membuat bukti terimanya muncul lagi di daftar Draft Invoice, jadi
penghapusannya bisa dibetulkan.

**Invoice yang sudah dibayar tidak bisa dihapus.** Permintaan Owner, dan
mengikuti penjagaan yang sama pada PO daging dan PO bahan: pembayaran yang
menunjuk ke dokumen yang tidak ada lagi membuat uang yang sudah masuk lenyap
dari jejaknya tanpa satu pun error.

**Nomor DO di daftar bisa diklik ke halaman cetak surat jalannya.** Pemendekan
menjadi enam digit terakhir memang disengaja Owner; yang ditambahkan hanya
tautannya.

**Dashboard memberitahu berapa bukti terima yang belum ditagihkan.** Selama
belum, uangnya tidak pernah diminta -- barangnya sudah sampai, pelanggannya
sudah menandatangani, dan tidak ada tagihan yang berjalan. Hanya terlihat oleh
pemegang `create_invoices`.

**Yang BELUM dikerjakan di modul ini**, dicatat supaya tidak hilang:

- `invoices.additional_charges` (kolom cast array) menganggur berdampingan
  dengan tabel `invoice_additional_charges` yang benar-benar dipakai. Dua
  penyimpanan untuk satu hal.
- Status masih campur bahasa: `'Belum TF'`, `'Sudah TF'`, `'Belum Dibayar'`,
  `'Invoiced'`, `'Lunas'`. Label seperti `__('Proses Invoice')` dan
  `__('Tanggal Tukar Faktur')` dibungkus penerjemah tetapi sumbernya
  Indonesia, jadi tampilan Inggris tetap berbahasa Indonesia.
- `created_by = auth()->id() ?? 1` diam-diam mengatasnamakan pengguna nomor 1.
- Kolom `tax` tetap tidak pernah diisi; Wijaya Meat nonPKP, jadi itu memang
  sisa desain lama.

**JANGAN menjalankan `php artisan db:seed` di server.** `DatabaseSeeder`
mengatur ulang akun superuser dan **menyetel ulang passwordnya ke bawaan**
setiap kali dijalankan. Izin baru ditambahkan lewat MIGRASI, bukan seeder.

### Dokumen terhapus: izinnya dulu menyembunyikan TOMBOL, bukan DATA

**3 September 2026, ditemukan saat menyisir Invoice.** Sepuluh Resource
menulis ini:

```php
return parent::getEloquentQuery()
    ->withoutGlobalScopes([SoftDeletingScope::class]);
```

lalu mengandalkan `TrashedFilter` yang dibungkus
`->visible(fn () => auth()->user()->hasPermission('view_deleted_...'))` untuk
menyaring baris terhapus kembali.

**Filter yang tidak terlihat tidak menyaring apa pun.** Filament membuang
filter yang `isVisible()`-nya false SEBELUM query dijalankan. Jadi izin itu
hanya menyembunyikan tombolnya; datanya tetap masuk. Pengguna tanpa hak
melihat dokumen terhapus bercampur dengan yang hidup, tanpa penanda apa pun.

Sudah dibuktikan dengan pengujian dari sisi pengguna, bukan dibaca dari kode:
`DeletedInvoiceVisibilityTest`.

Menyisir lubang itu memunculkan **dua varian lain dari sebab yang sama**:

**Izin yang tidak mengizinkan apa-apa.** Mutation, Material Stock Take, dan
Stock Take memeriksa haknya dengan `auth()->user()->can('view_deleted_...')`.
Proyek ini tidak mendaftarkan nama izin sebagai Gate dan tidak punya
`Gate::before`, jadi jawabannya **selalu tidak** -- filternya tidak pernah
muncul untuk siapa pun, programmer sekalipun. Izinnya bisa diberikan lewat
User Management, dan pemberiannya tidak berakibat apa-apa. **Selalu
`hasPermission()`, jangan `can()`, untuk nama izin proyek ini.**

**Izin yang tidak pernah dipasang.** `view_deleted_carcasses` ada di seeder
sejak lama tanpa satu pun kode yang membacanya, sementara Carcass dan Sales
Return membuka baris terhapusnya tanpa pemeriksaan sama sekali.
`view_deleted_sales_returns` dan `view_deleted_material_stock_takes` malah
belum pernah ada di seeder, jadi tidak bisa diberikan kepada siapa pun.

Semuanya sekarang lewat `App\Support\TrashedRecords::visibleTo($query, $izin)`.
Izinnya menjadi batas yang sebenarnya, dan `TrashedFilter` kembali menjadi apa
yang seharusnya: kemudahan tampilan, bukan pengaman.

**Catatan yang perlu diingat:** `TrashedFilter` memasang `baseQuery`-nya
sendiri yang mematikan `SoftDeletingScope`, jadi filternya tetap bekerja tanpa
`withoutGlobalScopes` di `getEloquentQuery()`. Yang tetap membutuhkannya adalah
halaman Edit/View -- ia menyelesaikan recordnya lewat `getEloquentQuery()`,
sehingga pemegang izin tidak akan bisa MEMBUKA dokumen terhapus tanpa itu.

Tiga penjaga pola dipasang di `DeletedRecordVisibilityTest`: tidak ada
`withoutGlobalScopes` tanpa pemeriksaan izin, tidak ada `can('view_deleted_*')`,
dan setiap izin `view_deleted_*` yang dipakai kode benar-benar ada di seeder.

### Penanda cron JANGAN disimpan di cache, dan dua widget pengawas kini diam saat sehat

**3 September 2026.** Owner bertanya soal dua notifikasi di Dashboard --
Scheduled Reminders dan Device Notifications -- apakah memang perlu.
Pemeriksaannya menemukan dua hal.

**Yang pertama sebuah bug, dan bug yang tepat merusak alatnya sendiri.** Waktu
jalan terakhir kedua perintah terjadwal disimpan dengan `Cache::forever`.
Namanya menjanjikan selamanya, tetapi langkah deploy kami selalu menjalankan
`php artisan optimize:clear`, dan di dalamnya ada `cache:clear`. Jadi setiap
rilis menghapus penandanya, dan Dashboard mengumumkan **"Never"** walaupun
cron-nya sehat.

Ini bukan sekadar tampilan yang salah. Widget itu dipasang justru untuk
menemukan kegagalan yang tidak bergejala, dan **alarm palsu yang berulang
mengajari orang mengabaikannya**. Saat cron benar-benar mati nanti, tidak akan
ada lagi yang percaya. Alat pendeteksi yang berbohong berkala lebih buruk
daripada tidak ada alat sama sekali.

Penandanya sekarang di tabel `scheduled_run_marks` lewat `App\Support\ScheduledRun`.
Bedanya halus tapi menentukan: **cache boleh hilang karena isinya bisa dihitung
ulang; angka ini tidak bisa.** Kalau ia hilang, yang tersisa cuma
ketidaktahuan, dan ketidaktahuan di sini terbaca sama persis dengan kerusakan.

Dijaga dua test. Yang satu meniru deploy secara harfiah -- jalankan perintah,
`Cache::flush()`, lalu pastikan Dashboard masih menyatakan sehat. Yang satu
lagi menyisir seluruh `app/Console/Commands` dan menolak `Cache::forever`
muncul lagi di sana.

**Yang kedua keputusan tampilan: keduanya hanya muncul saat ada yang salah.**
Sebelumnya masing-masing memakan satu baris penuh setiap hari, dan 99% waktu
isinya "semuanya normal". `3 / 3 (100%)` tidak menyuruh siapa pun melakukan
apa pun. Pengumuman yang tidak menuntut tindakan mengajari orang melewati
baris itu -- **termasuk pada hari isinya berubah**.

Sekarang Scheduled Reminders muncul hanya bila pemeriksaannya belum pernah
jalan atau sudah lewat dua hari, dan Device Notifications hanya bila masih ada
pengguna aktif yang belum berlangganan. **Dashboard yang bersih di kedua
tempat itu berarti sehat.** Yang dijaga tetap sama, kegagalannya terlihat; yang
berubah, keberhasilannya berhenti minta tempat.

Ikut dibuang: kolom "Needing attention" di widget kesehatan, yang menampilkan
hitungan Goods Receipt menggantung padahal `PendingTaskWidget` sudah
menampilkan angka yang sama di halaman yang sama.

**Catatan untuk rilis ini.** Tabelnya berangkat kosong, jadi tepat setelah
deploy widget kesehatan akan berkata "belum pernah berjalan" sampai cron jam
08:00 berikutnya menandainya. Itu jujur dan sengaja dibiarkan: menyetempel
tabelnya dari migrasi berarti menyatakan sesuatu sudah berjalan padahal belum,
dan justru itu yang menyembunyikan cron yang memang tidak terpasang.

### Rumus saldo hutang hanya ada di SATU tempat

`Payable::recalculate()`. Sebelumnya rumus saldo dan status disalin di **enam
tempat** -- lima di model dan satu di halaman pembayaran.

Selama semuanya menghitung hal yang sama, salinan itu tidak terasa. Begitu ada
faktor baru seperti kompensasi, ia hanya berlaku di sebagian, dan **hutang yang
sama menunjukkan angka berbeda tergantung jalur mana yang menyentuhnya
terakhir**. Ada test yang menghitung jumlah salinannya.

### Kerugian penimbangan memakai harga cadangan yang dikarang

Belum diperbaiki, dicatat supaya tidak hilang. `CattleWeighing::calculateAndSaveFinancialLoss()`
mencari harga per class dengan tiga tingkat: harga di PO ini, lalu harga PO
lain dari pemasok yang sama, lalu **rata-rata harga semua class di PO ini**.

Dua tingkat terakhir menghasilkan angka yang tidak pernah disepakati untuk
pembelian itu -- rata-rata antar class sapi yang berbeda khususnya adalah
angka karangan. Hasilnya tetap tampil sebagai rupiah yang meyakinkan di
laporan kerugian.

### Urutan kerja yang diminta Owner, 1 September 2026

Tally, lalu **Delivery Order**, lalu **plandev**. Owner meminta secara khusus
supaya diingatkan mengerjakan plandev setelah Delivery Order selesai --
jangan sampai terlewat.

### Notifikasi web push memang menampilkan dua ikon di Android

Bukan bug, dan **jangan dicoba diperbaiki lagi**. Android Chrome selalu
menggambar dua: `badge` (siluet kecil, status bar dan kepala notifikasi) dan
`icon` (gambar besar berwarna di kanan). Keduanya wajib.

Melepas `icon` supaya tersisa satu **sudah dicoba dua kali** dan dua-duanya
gagal dengan cara yang sama: Android membuat avatar huruf dari nama domain,
sehingga muncul huruf "C" dari `coba.wijayameat.co.id`. Owner melaporkannya
langsung. Percobaan ketiga akan berakhir sama.

Asetnya sendiri sudah benar: `pwalogo-badge-192.png` adalah siluet kepala
sapi berlatar transparan (Android hanya membaca kanal alpha lalu mewarnainya
sendiri), dan `pwalogo-maskable-192.png` yang beralas dipakai sebagai ikon
besar karena Android memotongnya menjadi lingkaran.

Satu-satunya perbaikan yang mungkin adalah membuat ikon besarnya **berbeda**
dari logo -- misalnya gambar yang menjelaskan jenis pekerjaannya -- supaya
tidak terbaca sebagai logo yang sama dua kali. Menghilangkan salah satunya
tidak mungkin.

### Diskon itu persen BULAT di seluruh sistem

`sales_order_items.discount` bertipe bilangan bulat, dan sejak 31 Agustus 2026
`customers.default_discount` disamakan dengannya. Keputusan Owner: persen
bulat saja, karena satu-satunya diskon yang berlaku adalah 2%.

**Kalau suatu saat diskon berkoma dibutuhkan, KEDUA kolom harus dilebarkan
bersamaan.** Melebarkan yang satu saja mengembalikan persoalan yang baru
diberesi.

**Bekas masalahnya, supaya tidak dipasang lagi.** Penyimpanan Sales Order
membuang titik dari nilai diskon, sama seperti yang dilakukannya pada berat
dan harga. Untuk berat dan harga itu BENAR -- JavaScript di form memang
memasang titik sebagai pemisah ribuan. Untuk diskon itu KELIRU, karena form
sengaja tidak memformat kolom itu, sehingga titik di sana hanya mungkin
berarti koma desimal:

```
diketik 2,5%    tersimpan 25%
diketik 12,75%  tersimpan 1275%
```

Validasi tidak menangkapnya: perusakannya terjadi SESUDAH validasi berjalan,
di `afterCreate` dan `afterSave`. Ini pola yang sama dengan temuan-temuan lain
pekan ini -- penjagaan yang ada di tempat yang salah.

**Yang sudah diperiksa dan ternyata aman:** `EditSalesOrder` menghapus lalu
membuat ulang seluruh barisnya setiap kali menyimpan, sehingga ID barisnya
selalu berganti. Tidak ada tabel mana pun yang menyimpan
`sales_order_item_id`; Invoice mencocokkan lewat `sales_order_id` +
`product_id`. Jadi tidak ada yang putus, dan ini bukan bug.

**Masih terbuka, menunggu giliran modul Invoice:** di `InvoiceResource`,
diskon per baris membuang titik sebagai pemisah ribuan sementara diskon pada
baris biaya tambahan memperlakukan titik sebagai desimal. Dua field sejenis,
dua perlakuan berbeda. Dengan persen bulat dampaknya hilang, inkonsistensinya
belum.

### Notifikasi tally saat Sales Order dibuat

SO baru lahir berstatus `waiting`, dan itu persis keadaan yang ditunggu
halaman Draft Tally -- jadi begitu SO tersimpan, pekerjaannya memang sudah
menganggur di meja orang lain. Pemegang `create_tallies` diberi tahu di
`CreateSalesOrder::afterCreate()`.

**Hanya saat dibuat, tidak saat disunting.** Menyunting SO yang sama beberapa
kali tidak melahirkan pekerjaan baru, dan notifikasi berulang hanya membuat
orang berhenti membacanya. Pembuatnya sendiri dikecualikan lewat
`auth()->id()`.

### Diskon pelanggan: di pelanggan, bukan di grup dan bukan di segment

Tiga Distribution Center Lion Superindo (DCA, DCB, DCC) disepakati mendapat
diskon 2%. Dulu aturan itu ada **di dalam kode**: `InvoiceResource` mencocokkan
POTONGAN NAMA pelanggan, di empat tempat terpisah. Sejak 31 Agustus 2026
diskonnya ada di kolom `customers.default_discount`.

**Kenapa di pelanggan.** Grup LION berisi **29 pelanggan** -- tiga DC, sisanya
toko dan kantor. Diskon di tingkat grup akan mengenai 26 toko yang tidak
berhak. Segment juga tidak bisa: ia berlaku lintas perusahaan, sehingga DC
milik pelanggan lain ikut kena tanpa pernah disepakati. Harga tetap di grup
lewat price list, karena harga memang disepakati dengan perusahaannya.

**Aturan umum yang dipakai Owner untuk memilih lapisnya:** yang biasanya SAMA
untuk seluruh grup ditaruh di grup; yang berbeda antar titik kirim ditaruh di
pelanggan. TOP saat ini masih di pelanggan meski sebenarnya seragam per grup;
memindahkannya menyentuh jatuh tempo piutang, jadi ditunda sampai giliran
modul itu -- ia tidak sedang rusak, hanya diisi berulang.

**Yang penting dipahami tentang bekas masalahnya:** penggantian 2% itu terjadi
saat INVOICE dibuat, bukan saat SO dibuat. Jadi maksud "supaya pembuat SO
tidak lupa" tidak pernah tercapai -- SO tetap tertulis 0% sementara invoice
menagih 2%. Dokumen yang dipegang pelanggan tidak cocok dengan tagihannya.
Sekarang Sales Order mengisi diskonnya sendiri dari pelanggan dan
menampilkannya, lalu Invoice memakainya apa adanya.

**Nilai di SO yang menentukan, selamanya.** Kolom di master pelanggan hanya
mengisi nilai awal. Kalau diskon DC suatu saat berubah, SO lama tetap memakai
angka yang berlaku saat itu.

Migrasi `2026_08_31_180100` memindahkan keadaan yang sedang berlaku ke dalam
data supaya tagihannya tidak berubah pada saat pergantian: kolom pelanggan
diisi, dan diskon pada SO yang **belum diinvoice** ikut diisi -- rombongan
itulah yang akan tertagih kurang bila aturan lamanya dicabut begitu saja.
Invoice yang sudah terbit tidak disentuh; ia menyimpan angkanya sendiri di
`invoice_items`.

`CustomerDefaultDiscountTest` memindai seluruh `app/` untuk keputusan yang
diambil dari nama pelanggan, jadi bentuk ini tidak bisa lahir lagi.

**Verifikasinya harus di produksi, bukan di server uji coba.** Saat migrasi ini
dijalankan di `coba.wijayameat.co.id` (31 Agustus 2026) hasilnya nol baris --
server itu hanya berisi satu pelanggan dummy. Data yang sesungguhnya, 302
pelanggan dengan 29 di antaranya grup LION, ada di produksi. Di sanalah
pencocokan `%DCA%` perlu diperiksa hasilnya, karena ia juga akan mengenai
pelanggan lain yang namanya kebetulan memuat huruf itu. Kolom **Diskon** pada
daftar Customer sengaja ditampilkan untuk keperluan itu: yang tidak seharusnya
berdiskon tinggal dinolkan lewat form.

### Perintah artisan di server juga punya pager, bukan cuma git

Sudah tercatat bahwa `git` di sisi server wajib memakai `--no-pager`. Hal yang
sama berlaku untuk **artisan yang mengeluarkan tabel**: `schedule:list`,
`route:list`, dan sejenisnya membuka pager yang menunggu tombol ditekan,
sehingga sesi `ssh -tt` menggantung **selamanya** tanpa gejala apa pun.

Terjadi 1 September 2026 dan menghabiskan 30 menit sebelum Owner
menyadarinya. Salurkan lewat `grep`, `head`, atau `| cat`:

```
php artisan schedule:list | grep notify
```

**Dan jangan mengumumkan deploy selesai hanya karena commit di server sudah
benar.** Pada kejadian itu clone-nya memang sudah sampai, tetapi tugas latar
belakangnya masih menggantung di perintah berikutnya -- cache belum
dihangatkan sama sekali. Periksa tugasnya benar-benar berhenti, bukan hanya
hasil antaranya.

### Jangan mengutip pola terlarang di dalam komentar kodenya sendiri

Test penjaga di proyek ini banyak yang memindai berkas sumber untuk memastikan
sebuah bentuk TIDAK ada lagi. Menuliskan bentuk itu di dalam komentar --
sekadar untuk menjelaskan apa yang dulu salah -- membuat penjaganya menemukan
komentar itu sendiri dan gagal.

Sudah terjadi **empat kali**: pada kelas warna, pada kelas dua-kolom, pada
nama kelas CSS yang tidak berwujud, dan pada pemanggilan penjumlahan relasi.
Jelaskan bentuk lamanya dengan kata-kata, jangan disalin apa adanya.

### Batas angka di Filament tidak membatasi apa pun tanpa aturan numerik

`->minValue(0)->maxValue(100)` **hanya** menghasilkan aturan `min:0` dan
`max:100`. Tanpa aturan numerik yang menyertainya, Laravel memeriksa
**panjang karakter**, bukan nilainya -- `"500"` lolos karena cuma tiga
huruf. Ini ditemukan pada diskon Sales Order, yang dipakai Invoice sebagai
`gross * (discount / 100)`, sehingga diskon 500% menghasilkan baris tagihan
**minus** tanpa satu pun error.

Perbaikannya **tidak boleh** memakai `->numeric()`: pemanggilan itu membuat
`getType()` mengembalikan `number`, lengkap dengan tombol panah yang sudah
dilarang untuk kolom uang dan berat. Tulis aturannya manual:

```php
->rules(['numeric', 'min:0', 'max:100'])
```

`NumericRangeValidationTest` memindai seluruh `app/Filament` untuk bentuk
ini, jadi yang baru tidak bisa lahir diam-diam.

### Pelanggan tanpa grup selalu dibuatkan grup sendiri

Grup adalah satu-satunya jalan menuju harga -- price list dikunci ke
`customer_groups`. Karena itu pelanggan yang grupnya dikosongkan di form
otomatis dibuatkan grup bernama sama dengan dirinya (`ensureCustomerGroup`
pada trait `KeepsCustomerInAGroup`, dipakai bersama oleh halaman Create dan
Edit supaya keduanya tidak bisa berbeda diam-diam).

Konsekuensinya, **setiap pelanggan baru hampir selalu melahirkan grup baru
tanpa harga**, dan setiap Sales Order untuknya terisi Rp 0. Nol itu sendiri
**disengaja** -- keputusan Owner, 31 Agustus 2026: user bebas mengubah harga
saat membuat SO, jadi nol hanyalah titik awal, bukan kegagalan. **Jangan
mengubahnya menjadi penolakan.** Yang diperbaiki adalah momennya:
`PriceListInvitation::offerFor()` menawarkan pembuatan price list tepat
setelah pelanggan atau grupnya disimpan, dan sifatnya hanya menawarkan --
yang membuat pelanggan belum tentu berhak menetapkan harga.

### Yang paling penting dipahami dari sesi ini

Empat bug terpisah pekan ini punya **satu pola yang sama**: penjagaan yang diasumsikan ada padahal tidak pernah dipasang, dan kegagalannya **senyap** — tidak ada error, tidak ada gejala di layar.

| Bug | Kenapa tidak ketahuan |
|---|---|
| DP tidak terpotong dari utang | Rantai putus saat form pindah ke halaman PO; test lama memakai `source_type` yang sudah tidak dipakai |
| DP hilang saat GR dibuka kuncinya | `allocateTo()` tidak punya pasangan pelepas |
| Price List & Receivables bocor | Dua Resource berbagi model, Policy salah sasaran |
| Empat aksi uang tanpa hak akses | `ViewPurchaseProduct` punya `hasPermission` — tapi untuk tombol Print |

**Kesimpulan yang dipakai ke depan:** jangan mengandalkan pemeriksaan menyeluruh di akhir. Pemeriksaan akhir hanya menemukan yang terpikir untuk dicari. Setiap menemukan pola yang bisa menyimpang diam-diam, **pasang penjaganya saat itu juga**.

### Penjaga pola yang sudah terpasang

Semuanya memindai seluruh aplikasi, bukan cuma berkas yang kebetulan disentuh:

| Test | Menjaga |
|---|---|
| `NavigationGroupConsistencyTest` | tiga daftar grup navigasi tetap sepakat |
| `MoneyActionPermissionTest` | halaman mana pun yang membuat pembayaran wajib memeriksa hak akses |
| `ResourceAccessGateTest` | Resource yang menumpang model modul lain wajib punya `canViewAny()` |
| `BilingualParityTest` | `id.json` dan `en.json` memuat kunci yang sama persis |
| `UserPermissionFormTest` | tiap permission hanya di-seed sekali; tiap modul terpeta ke tab |
| `RequisitionTranslationCoverageTest` | teks notifikasi terdaftar dua bahasa |

### TERBUKA: metode input Material Usage perlu dirombak

Project Owner, 31 Agustus 2026, setelah mencoba modul Boning: *"masalah
input material usage ... ini gak bisa dikerjain sekarang harus sesi
tersendiri mungkin akan dihilangkan atau diubah metodenya ... gw butuh
diskusi panjang soal ini"*.

**Belum ada keputusan apa pun.** Jangan menyentuh alur input Material Usage
-- termasuk merapikannya -- sampai pembahasan itu terjadi. Kemungkinan yang
disebut Owner: dihilangkan sama sekali, atau diganti metodenya.

Yang sudah disentuh dan aman: qty pemakaian material kini wajib lebih dari
nol dan tidak lagi memakai input bertombol panah. Itu penjagaan isian, bukan
perubahan alur.

### Utang yang DIKETAHUI dan sengaja ditunda

1. **73 kunci berbahasa Indonesia** masih dipakai sebagai kunci terjemahan, tersebar sampai Repack, Sales Return, Cattle Weighing, Boning. Daftarnya di `tests/Fixtures/indonesian-translation-keys.json`, dijaga ratchet supaya tidak bertambah. **Tidak darurat** — server berjalan di `APP_LOCALE=id`, jadi pengguna sehari-hari melihat teks yang benar.
2. **Label formulir modul Request** banyak yang belum terdaftar bilingual. Pemindainya sengaja dibatasi pada teks notifikasi.
3. **Blade cetak** (`resources/views/print/`, `exports/`) hampir seluruhnya hardcode, tidak lewat `__()`. Belum diputuskan perlu bilingual atau tidak.
4. **Urutan Tab di banyak form** belum wajar — lihat bagian utang teknis di atas.
5. **Alokasi uang muka per pembayaran** tidak dicatat per pasangan. Total selalu tepat; hanya laporan alokasi per pembayaran yang belum mungkin. Kalau kelak dibutuhkan, jawabannya tabel alokasi tersendiri.
6. **B.O.M (Bill of Materials) belum ada sama sekali.** Tidak ada satu pun tempat yang menyatakan "satu unit produk jadi butuh bahan apa saja, berapa banyak". Akibatnya berantai dan diam:
   - Pemakaian bahan penolong dicatat MANUAL di Material Usage, tanpa apa pun yang membandingkannya dengan yang seharusnya terpakai. Salah input sepuluh kali lipat tidak menghasilkan gejala.
   - HPP tidak bisa dihitung utuh, karena porsi bahan penolong per produk tidak diketahui. Ini salah satu sebab susut kirim masih tercatat Rp 0.
   - Boning dan Repack tidak bisa memperkirakan kebutuhan bahan di muka, jadi kekurangan stok baru ketahuan saat produksinya berjalan.

   Belum dikerjakan karena BOM menyentuh Boning, Repack, Material Usage, dan HPP sekaligus. Perlu sesi tersendiri bersama Owner, dan mestinya SESUDAH metode input Material Usage diputuskan — lihat bagian "TERBUKA: metode input Material Usage perlu dirombak".

7. **Modul QC/QA belum ada.** Sudah tercatat `[ ]` di `checklist_modul.md` sejak awal, dan sampai sekarang tidak ada satu pun kode yang menyentuhnya. Yang sekarang berjalan hanya pemeriksaan yang menempel di dokumen lain — pH di Boning dan Goods Receipt, Grade di setiap baris stok — tanpa ada tempat yang menyatakan lulus atau tidaknya suatu batch, siapa yang memeriksanya, dan apa akibatnya kalau gagal.

   Yang membuatnya berutang bukan ketiadaannya, melainkan bahwa **kegagalan mutu saat ini tidak punya jalur**. Kompensasi pemasok sudah ada di Payable, tetapi ia dicatat sesudah kejadian, tanpa dokumen pemeriksaan apa pun yang mendasarinya. Kalau QC/QA kelak dibuat, itulah yang harus menjadi asal-usul kompensasi — bukan angka yang diketik langsung.

### Retur penjualan — keputusan 4 September 2026

**Approve dan Unlock punya izinnya sendiri**, `approve_sales_returns` dan
`unlock_sales_returns`, tidak menumpang `edit_sales_returns`. Keduanya
MENGGERAKKAN STOK: Approve memasukkan tiap barang retur ke gudang, Unlock
menariknya keluar lagi. Sebelumnya keduanya dijaga izin yang sama dengan
membetulkan tanggal.

Dipisah menjadi DUA, bukan satu, karena Unlock **menghapus baris stok yang
sudah ada** -- lebih berbahaya daripada menambah, dan biasanya dipegang lebih
sedikit orang.

**Retur yang sudah disetujui tidak boleh dihapus.** Menghapusnya tidak menarik
stoknya kembali, jadi barangnya tetap di gudang tanpa satu pun dokumen yang
menjelaskan dari mana ia datang. Urutan yang benar: buka kuncinya dulu -- di
situ stoknya ditarik dan jejaknya tercatat -- baru returnya dihapus.

**Tiga pertanyaan sebelum sebuah barcode boleh diretur** (dulu hanya satu,
"pernah ada di Tally?" -- tally mana pun):

1. benarkah barang ini yang kita kirim pada **surat jalan INI**? Dijawab dengan
   mencari barcodenya di tally milik surat jalan retur ini saja;
2. barangnya memang sedang **TIDAK di gudang**? Yang masih tercatat di stok
   berarti belum pernah keluar, dan yang belum keluar tidak bisa kembali. Ini
   juga yang menahan barcode yang sama diretur dua kali;
3. belum tercatat di **retur lain yang masih Draft**? Dua Draft bisa memegang
   barcode yang sama tanpa satu pun menyentuh stok, jadi pertanyaan kedua tidak
   menangkapnya.

Owner sudah menegaskan barang yang diretur BISA saja barang retur pelanggan
lain yang sudah direpack -- itu sebabnya pertanyaannya soal "sedang tidak di
gudang", bukan soal riwayatnya.

**Gudang tidak lagi dipaku ke angka 1.** Barang retur mendarat kembali di
gudang asalnya (`$tallyItem->warehouse_id`); kalau barangnya sendiri tidak
menyebutkan, dipakai gudang aktif pertama, bukan id 1 yang bisa saja sudah
dinonaktifkan. Tesnya sengaja menonaktifkan gudang berid 1.

**Rutin stoknya satu rumah**: `SalesReturn::approve()` dan
`SalesReturn::unlock()`. Rutin itu dulu disalin UTUH di tiga halaman -- Edit,
View, dan Input Barang -- masing-masing dengan penjagaan izin berbeda, sehingga
menambal yang satu meninggalkan dua lainnya terbuka. Pola yang sama sudah
pernah ditemukan pada saldo hutang (6 salinan) dan rumus tagihan (5 salinan).
Dijaga `SalesReturnTest::test_no_sales_return_page_moves_stock_on_its_own`.

`unlock()` memeriksa SEMUA barangnya dulu, baru menarik satu pun. Menarik
separuh lalu berhenti di tengah -- karena satu barang terlanjur dikirim lagi --
meninggalkan stok yang tidak cocok dengan dokumen mana pun.

**Urutan barcode timbang manual** dibaca dari bagian sesudah awalannya dan
diambil yang TERBESAR. Yang lama memakai `strlen >= 26` sebagai penanda sah
(padahal tidak semua barcode 26 karakter) dan membaca baris terakhir menurut
`id`; keduanya bisa melahirkan barcode kembar.

**Gudang PENERIMA dipilih, bukan ditebak.** Sempat diambil dari gudang tempat
barangnya keluar; Project Owner yang menangkapnya -- "kalo diterima dari gudang
lain gimana?". Barang retur tidak selalu mendarat di gudang asalnya. Sekarang ada
Select di tab Scan, diingat per retur, dipakai kedua tab, dengan gudang asal
sebagai nilai bawaan. Sesudah tiap pindaian yang dikosongkan HANYA barcodenya --
mengisi ulang seluruh form menghapus gudang yang baru dipilih.

### Retur memotong tagihan pelanggan -- nomor 1 bagian A, 4 September 2026

Nomor 1 dulu kelihatan tidak bisa disentuh karena dianggap satu paket dengan HPP.
Ternyata **dua urusan yang kecampur**:

- **A -- sisi pelanggan.** Berapa yang dipotong dari tagihan. Angkanya HARGA
  JUAL, dan harga jual ada di invoice. Tidak butuh HPP sama sekali.
- **B -- sisi persediaan.** Nilai barang yang masuk gudang lagi. Ini yang HPP,
  dan ini yang nyangkut boning/repack.

**A sudah dikerjakan. B masih parkir sampai BOM.**

**Bentuknya nota retur, bukan invoice yang diubah.** Invoice yang sudah terbit
ada di tangan pelanggan; menggeser angkanya membuat dokumen yang mereka pegang
tidak cocok dengan tagihan kita. Yang dikurangi `Invoice::billedAmount()` --
satu tempat, seperti biasa -- lewat `Invoice::returnedAmount()`.

Satu mekanisme untuk tiga keadaan:

| Keadaan | Yang terjadi |
|---|---|
| Invoice ada, belum lunas | nilai returnya mengurangi tagihan; sisa tagihan turun sendiri |
| Invoice belum dibuat | returnya MENUNGGU dengan `invoice_id` kosong. `Invoice::collectPendingSalesReturns()` memungutnya saat invoice untuk surat jalan itu lahir -- dipanggil dari `CreateInvoice::afterCreate()` dan dari hook `restored` |
| Invoice sudah dibayar | uangnya sudah masuk, tidak ada yang bisa dipotong. Alokasinya DILEPAS sebesar kelebihannya, dan uang itu kembali menjadi deposit lewat `Payment::unallocatedAmount()` yang sudah ada |

Keadaan ketiga sengaja TIDAK membuat kolam deposit kedua. Dua tempat yang
harus sepakat tentang "berapa uang pelanggan yang belum terpakai" selalu
berselisih pada akhirnya. Yang dilepas alokasi TERBARU dulu, supaya riwayat
pelunasan yang sudah lama tidak ikut terusik.

**Harganya SNAPSHOT.** `sales_return_items.unit_price` dan `line_amount`
direkam saat retur disetujui, dan `sales_returns.credit_amount` menyimpan
nilai nota returnya. Harga bergerak -- lewat Price List, lewat diskon
pelanggan, lewat SO berikutnya -- dan nota retur yang ikut bergerak berarti
angka yang sudah disepakati berubah sendiri di belakang punggung orang.
Dijaga `test_the_credit_does_not_move_when_the_invoice_price_changes_later`.

Sumber harganya dua, urutannya tidak boleh dibalik: baris INVOICE untuk produk
yang sama (`amount / weight`, sudah termasuk diskon barisnya), lalu baris SALES
ORDER lewat `InvoiceTotals::line()` -- rumus yang sama persis dengan
`InvoiceResource::billableLines()`, supaya angkanya tidak bisa berbeda dari
invoice yang akan terbit. Produk yang tidak ketemu di keduanya berharga NOL
dan nolnya terbaca di layar; menebak harganya lebih buruk daripada menunjukkan
bahwa ia belum berharga.

**Invoice yang dihapus MELEPASKAN returnya**, bukan menyeretnya ikut hilang.
Barangnya sudah benar-benar kembali ke gudang; yang hilang cuma invoice yang
dipotongnya, dan invoice pengganti untuk surat jalan yang sama memungutnya lagi.

#### Koreksi 4 September 2026 sore: retur lintas pengiriman

Dua koreksi Project Owner yang mengubah bentuknya.

**Retur TIDAK terjadi sebelum invoice.** "retur itu terjadi bukan di hari kirim
tapi h+1 setelah kirim atau lebih", dan "kadang retur itu terjadi malah seminggu
setelahnya setelah invoice terbit". Jadi keadaan normalnya justru INVOICE SUDAH
ADA, dan bisa juga sudah lunas. Jalur "retur menunggu invoice" tetap ada
-- jendelanya sempit antara bukti terima hari-0 dan invoice H+1 -- tetapi ia
pengecualian, bukan aturan.

**Satu retur bisa dari BEBERAPA kiriman.** "kadang ada customer yang terlalu
oper power contoh lion superindo, kadang dia retur dari beberapa kiriman,
makanya di retur itu ada unindentified delivery".

Andaian "satu retur = satu pengiriman = satu invoice" yang dipakai #234 salah,
dan akibatnya retur tanpa surat jalan tidak berfungsi sama sekali: tidak bisa
dipindai (penjaganya mencari tally lewat surat jalan returnya), dan tiap
barisnya berharga nol tanpa gejala.

**Sekarang tautan invoice ada di KARTONNYA**, bukan di returnya. Barcodenya
yang menjawab: barcode -> tally -> surat jalan -> bukti terima -> invoice, lewat
`SalesReturnItem::originDelivery()` dan `billItWasChargedOn()`. Satu retur bisa
memotong beberapa invoice sekaligus, dan itu terbaca di daftar maupun di
ringkasan barangnya.

Barcode unik **per tally**, bukan global -- satu karton yang pernah diretur lalu
dikirim lagi memakai barcode yang sama. Yang diambil **kiriman TERAKHIR**;
itulah yang sedang dikembalikan. Dijaga
`test_a_carton_shipped_twice_credits_its_latest_delivery`.

Barang **timbang ulang** tidak punya jejak itu: ia diberi barcode BARU berawalan
4 saat diretur, dan barcode baru tidak pernah ada di tally mana pun. Untuk
barang semacam itu yang menjawab surat jalan yang tertulis di returnya. Itu
sebabnya `originDelivery()` punya dua jawaban, bukan satu.

**Penjaga barcode berubah bentuk**, dari "harus ada di DO ini" menjadi: pernah
kita kirim (ada di suatu tally); dan kalau returnya menyebut sebuah surat jalan,
tally itu harus miliknya. Melonggarkannya untuk retur tanpa surat jalan tidak
berarti apa saja boleh masuk -- barcode karangan tetap ditolak.

#### Berat fisik dan berat yang dikreditkan -- koreksi 4 September 2026 malam

Batas berat yang dipasang #236 sempat MENOLAK retur yang normal, dan itu
ketahuan dari penjelasan Project Owner:

> "misal kita kirim 1 box 20kg, customer timbang ulang dan ternyata hanya
> 19.80kg, dan itu yang akan di catat customer dan ukuran pembayaran, jadi pas
> do receipt akan d sesuaikan menjadi 19.80 dasar perhitungan invoice"

Yang terpindai saat retur berat KIRIM kita (20,00, dari tally item), sementara
yang ditagihkan berat TERIMA pelanggan (19,80). Setiap box yang selisih
timbangannya sedikit pun tertolak. Dibuktikan dengan test sebelum diperbaiki.

**Kesalahannya memaksa dua angka yang memang tidak harus sama menjadi satu.**

    weight           berat FISIK yang masuk gudang    -> stok
    credited_weight  berat yang pernah DITAGIHKAN     -> uang

Batasnya berhenti menolak dan menjadi pembatas nilai: sisa jatah per (invoice,
produk) dihitung sekali lalu dipakai bersama oleh semua karton di retur itu --
kalau dibaca ulang per baris, dua karton akan melihat jatah yang sama dan
sama-sama lolos penuh. Dijaga
`test_two_cartons_in_one_return_share_the_same_budget`.

Retur berlebihan yang sungguhan tetap tertahan di PINTU MASUK, bukan di
perhitungan uangnya: barcode yang tidak pernah dikirim ditolak, dan barcode
yang sama tidak bisa dipindai dua kali.

**"Received Weight" TIDAK BOLEH DIKUNCI.** Rencana membuat Rejections
satu-satunya jalan menolak box -- sempat dicatat sebagai utang -- DIBATALKAN.
Field itu memang untuk selisih timbangan, dan mengunci besarnya perubahan akan
merusak alur yang benar. Yang tersisa hanya kemungkinan teoretis bahwa seseorang
memakainya untuk menolak satu box utuh lalu membuatkan Sales Return untuk box
yang sama; menurut Owner itu bukan yang terjadi di lapangan.

#### Barang timbang ulang

> "kadang customer retur banyak barang yang kartonnya gak utuh jadi susah dicari
> kode barcodenya, nah akhirnya di barcode ulang bahkan ditimbang ulang jadi
> barcode itu akan baru"

Barcodenya BARU, jadi tidak menunjuk ke kiriman mana pun. Asalnya dulu diambil
dari surat jalan yang tertulis di returnya -- dan retur lintas pengiriman tidak
menyebut surat jalan apa pun. Justru kombinasi itu yang paling mungkin terjadi:
pelanggan besar mengembalikan banyak barang sekaligus, dan sebagian kartonnya
rusak. Akibatnya barang semacam itu berharga NOL tanpa satu pun gejala.

Sekarang asalnya DITANYAKAN: tab Relabel punya pilihan Surat Jalan Asal
(`sales_return_items.origin_delivery_order_id`), bawaannya surat jalan returnya
kalau ada, dibatasi pada surat jalan pelanggan itu sendiri. Kosong pun boleh --
yang hilang cuma harganya, dan nolnya terbaca di layar.

`SalesReturnItem::originDelivery()` menjawab dengan urutan: yang DITULIS
orangnya, lalu barcodenya, lalu surat jalan returnya.

**Nota returnya memuat harga**, dikelompokkan per invoice, karena retur
terjadi H+1 atau lebih -- sering sesudah invoicenya terbit dan berada di tangan
pelanggan. Selama masih Draft dokumennya tidak memuat angka uang sama sekali:
nol di dokumen pelanggan terbaca "gratis", bukan "belum dihitung".

#### Retur SELALU sesudah bukti terima -- SEDANG DIUJI, belum diputuskan

> "urutannya so - tally - do - do receipt - invoice, retur itu pasti setelah do
> receipt, karena kalo belum di receipt berarti masih dalam pengiriman atau baru
> di cek customer dan itu tidak masuk retur itu masuknya tolakan akan ditangani
> di do receipt"

Aturan ini tidak pernah tertulis di kode sampai sekarang. Barcode yang surat
jalannya BELUM punya bukti terima ditolak di halaman Input Barang, dengan pesan
yang mengarahkan ke Approve DO. Inilah penjaga terakhir untuk dua pintu di
bawah -- pintunya kini benar-benar terpisah oleh WAKTU: sebelum bukti terima
Tolakan, sesudahnya Retur Jual.

Tanpa penjagaan itu, tolakan yang salah pintu memasukkan barang ke stok padahal
fisiknya belum kembali, dan memotong tagihan yang belum pernah terbit.

**JANGAN diperlakukan sebagai keputusan final.** Project Owner, 4 September
2026, sesudah penjaganya terpasang:

> "kalau ini maksudnya gak bisa input barcode kalo do nya belum ada di receipt
> akan ditolak saran gw jangan bro, itu memang bagus tapi dilapangan kita gak
> bisa saklek / tapi sementara biarin gitu dulu nanti mau gw uji sendiri, karena
> aturan itu sebenarnya belum diperlukan untuk ada"

Jadi penjaganya DIBIARKAN untuk diuji Owner sendiri, bukan karena sudah
disepakati. Yang ditunggu satu pertanyaan: apakah ia pernah menghalangi di saat
yang wajar -- misalnya barang balik cepat sementara DO-nya belum sempat
di-approve karena orangnya tidak ada atau POD-nya masih di driver.

Dua kemungkinan hasilnya, keduanya sudah jelas:

- **pernah menghalangi** -> penjaganya DICABUT. Tiga penjaga lain (pernah kita
  kirim / tidak lagi di gudang / tidak ada di retur Draft lain) tetap berdiri
  dan tidak bergantung padanya;
- **tidak pernah** -> dipasang JUGA di tab Relabel, baru ia layak disebut
  aturan.

Sebab bentuknya sekarang belum utuh: ia menutup tab Scan dan MEMBIARKAN tab
Relabel terbuka. Kalau aturannya penting ia harus menutup keduanya; kalau tidak
penting ia tidak boleh menutup satu pun. Setengah penjaga persis pola yang
diberantas sepanjang hari ini -- penjagaan di satu lapisan yang membuat orang
mengira sudah aman.

Untuk diingat: **ada DUA pintu barang kembali**, dan keduanya benar.

| | Rejections (Approve DO) | Sales Return |
|---|---|---|
| Kapan | ditolak SAAT dikirim, driver bawa pulang hari itu | sudah diterima pelanggan, dikembalikan H+1 atau lebih |
| Stok | balik lewat hapus tally item (`DO_REJECT`) | balik lewat approve retur |
| Uang | invoice belum pernah terbit, tidak ada yang dipotong | nota retur memotong invoice |

Keduanya tidak bisa dobel: barang yang sudah lewat Rejections tally itemnya
hilang (penjaga pertama menolak) dan barangnya ada di stok (penjaga kedua
menolak).

#### Jawaban Owner atas daftar ganjalan Retur Jual -- 4 September 2026

| Ganjalan | Keputusan |
|---|---|
| Barang retur langsung siap dijual lagi | Sudah ada penanganannya di lapangan, biasanya masuk jalur **Repack**. Dan **modul QC akan dibuat** -- di situ nanti rumah resminya |
| Grade & pH diwarisi dari tally lama | Sama: Repack atau metode lain **sesuai keputusan QC** |
| Retur berharga nol bisa disetujui tanpa peringatan | Biarkan nol dulu sampai HPP ada |
| HPP barang retur, nilai susut kirim | Menunggu **sesi khusus HPP** |

**Untuk yang membangun modul QC nanti:** barang retur adalah salah satu yang
harus dilewatkannya. Sekarang tiap karton retur masuk stok sebagai `IN_STOCK`
dan langsung bisa dijual lagi, dengan grade dan pH yang disalin dari tally
kiriman lamanya -- padahal justru kondisinya yang berubah, dan itu sebabnya ia
diretur. Owner menyatakan di lapangan sudah ada penanganannya lewat Repack;
yang belum ada adalah tempat di SISTEM yang menyatakan lulus atau tidaknya.

**Status `Canceled` DICABUT dari warna badge.** Ia punya warna tetapi tidak ada
satu pun kode yang menyetelnya. Pembatalan sudah punya jalurnya: retur Draft
dihapus (hapus lunak, masih terlihat oleh pemegang
`view_deleted_sales_returns`), retur Approved dibuka kuncinya dulu supaya
stoknya ditarik dan jejaknya tercatat, baru dihapus. Menambah status Canceled
yang sungguhan berarti membuat jalur KEDUA untuk hal yang sama.

**DICABUT dari daftar ganjalan:** "retur yang menunggu invoice tidak ada yang
mengingatkan". Bukti terima yang belum ditagihkan sudah muncul sendiri di
dashboard lewat `PendingTaskWidget::getDraftInvoiceCount()`, dan mengejar bukti
terima itulah yang menyelesaikan returnya. Peringatan tersendiri hanya akan
menjadi angka kedua untuk hal yang sama.

**Yang sengaja tidak dikerjakan:** alokasi yang sudah dilepas TIDAK dipasang
kembali otomatis saat retur di-unlock. Uangnya menjadi deposit dan dipakai lagi
lewat halaman Terima Pembayaran, sama seperti lebih bayar biasa. Memasangnya
kembali berarti menebak pembayaran mana yang dulu menutup invoice mana,
padahal jejaknya sudah tidak ada.

### Repack: pembanding bahan masuk lawan hasil keluar -- 4 September 2026

Sampai hari ini **tidak ada satu baris pun** yang membandingkan
`Repack::materials()` dengan `Repack::results()`. Tombol Lock hanya menyetel
penanda, jadi dokumen dengan bahan 100 kg dan hasil 500 kg bisa dikunci --
stoknya bertambah 400 kg dari udara, dan permanen karena sesudah terkunci tidak
bisa diubah.

Lebih luas: kata `weight` muncul SATU KALI di seluruh `BoningResource.php`, dan
itu `->weight('bold')` -- ketebalan huruf. `Carcass` bahkan tidak punya kolom
berat. **Boning belum dikerjakan**, dan di sana angka masuknya bahkan belum
tercatat.

**Bentuk penjagaannya**, dari dua tawaran Project Owner sendiri -- tolak keras
membuat pekerjaan berhenti saat kenyataan jomplang, peringatan biasa diabaikan:

| | Aturan |
|---|---|
| Selalu | susutnya DIHITUNG dari barisnya, tidak disimpan |
| Ambangnya | satu angka GLOBAL di tabel `settings`, berlaku untuk semua dokumen -- keputusan Owner: "ambang yang diisi qc jangan per batch, berlaku untuk semua" |
| Belum diisi | gerbangnya BELUM MENYALA -- dokumen tetap bisa dikunci, angkanya tetap tercatat |
| Diisi & dilanggar | tombol Lock MATI untuk pengguna biasa, dengan keterangan -- bukan lenyap |
| Yang menembus | pemegang `override_repack_yield`, dengan alasan tertulis yang tersimpan beserta nama dan waktunya |

Gerbangnya sengaja mati saat ambangnya kosong: belum ada satu pun data susut
yang pernah tersimpan, jadi angka apa pun yang dikarang akan salah.
Menghalangi pekerjaan dengan angka yang tidak dipilih manusia mana pun sudah
menjadi kesalahan pada penjaga berat retur hari ini -- tidak diulang.

**Dua izin sengaja dipisah.** `set_repack_yield_limit` MENENTUKAN apa yang
wajar (keputusan mutu, pemegangnya QC); `override_repack_yield` MELEWATI batas
itu untuk satu dokumen. Digabung berarti siapa pun yang boleh menembus juga
boleh menurunkan ambangnya sampai tidak ada lagi yang perlu ditembus, dan
penjagaannya lenyap tanpa satu pun catatan penembusan.

**Hasil lebih berat daripada bahan selalu di luar batas**, berapa pun
ambangnya. Itu mustahil secara fisik; tidak ada persentase yang membenarkannya.

**Warna kolom `lost` dulu TERBALIK.** Ia menghitung `hasil - bahan` lalu
memberi merah pada negatif dan hijau pada positif -- sehingga dokumen yang
hasilnya lebih berat daripada bahannya, yang hampir pasti salah ketik, terbaca
sebagai kabar baik. Sekarang yang ditampilkan susutnya, dan warnanya mengikuti
apakah ia di dalam batas.

**Aturan legacy yang hilang dikembalikan:** hasil tidak bisa diinput sebelum
bahannya ada. Owner menawarkan mencabutnya bila ada pengganti yang lebih baik;
DIPERTAHANKAN, karena tiap hasil LANGSUNG membuat baris `BeefStock` saat
dipindai, bukan saat dikunci. Penjaga di tombol Lock datang terlambat -- dua
ratus hasil sudah masuk stok sebelum ada yang memberi tahu.

**`settings` adalah tabel baru** untuk angka yang dipilih manusia. Sengaja
kecil: kunci, nilai, siapa yang terakhir mengubah. Batas POD di Tally yang
sekarang memakai `session()` -- sehingga angkanya milik satu peramban dan
berbeda antar orang -- sebaiknya kelak pindah ke sini; tidak dikerjakan
sekarang supaya perubahannya tetap satu urusan.

### Boning: berat karkasnya sudah ada, tidak ada yang membacanya -- 4 September 2026

**KOREKSI catatan sebelumnya.** Sempat tercatat bahwa `Carcass` tidak punya
kolom berat. Itu SALAH, dan salahnya karena hanya melihat tabel induk.
Beratnya ada satu tingkat di bawahnya, di `carcass_items`, per ekor:
`carcass_1`, `carcass_2`, `hides`, `tail`.

**Alur lengkapnya**, dari penjelasan Project Owner: PO Cattle -> sapi datang,
dicatat di Cattle Receiving dengan berat surat jalan per eartag -> ditimbang
ulang, selisihnya menjadi Financial Loss -> dipotong sesuai prosedur RPH,
hasilnya dicatat sebagai Carcass per ekor (belahan A, belahan B, kulit,
buntut) -> dilayukan SEMALAM -> besoknya di-boning.

**Satu dokumen karkas PASTI satu kali boning**, dan satu batch boning WAJIB
selesai dalam sehari (alasan mutu). Jadi tidak ada karkas yang terbelah antar
batch, dan susut per batch selalu utuh.

**Yang masuk boning: belahan A + belahan B + BUNTUT.** Kulit dan jeroan tidak
ikut -- keduanya dijual langsung ke kontraktor dan tidak pernah masuk ruang
boning. Buntut ikut karena ia menjadi oxtail, produk jual yang KELUAR dari
boning; kalau buntutnya tidak dihitung sebagai bahan, oxtail yang keluar
membuat hasil tampak lebih berat daripada bahannya.

#### Rendemen karkas HILANG saat ditulis ulang

Aplikasi lama menghitung `$totalCarcass / $totalLive * 100` dan mencetaknya
sebagai **Carcase Yield**. Diperiksa terhadap laporan sungguhan 27 Agustus
2026: 3.943,32 / 6.856,00 = 57,52%, cocok dengan yang tercetak. Aplikasi baru
tidak menghitungnya sama sekali -- **regresi, bukan fitur yang belum dibuat.**

#### Berat OFFAL adalah kesepakatan, bukan pengukuran

    // Offal = total carcase + total tails
    $offal = $totalCarcass + $totalTails;

Rumus ini sempat dituduh SALAH di catatan ini -- karkas ditambah buntut jelas
bukan jeroan. **Tuduhannya yang keliru, bukan kodenya.** Project Owner,
4 September 2026:

> "ketika sapi di potong, beberapa bagian seperti jeroan hati, usus, kepala,
> kaki dan lain-lain itu dipisahkan menjadi 1 item offal nah untuk beratnya
> TIDAK DITIMBANG ... total berat karkas a dan karkas b ditambah buntut yang
> total beratnya sudah ketahuan ITULAH yang jadi berat offal"

Contohnya: karkas A 100 + karkas B 110 + buntut 40 = **offal 250**. Cara ini
berlaku di rumah potong modern, dan angkanya BUKAN pengukuran melainkan
konvensi.

Jadi satu angka yang sama menjawab dua pertanyaan berbeda: berapa yang masuk
boning, dan berapa berat offal yang dijual. `Carcass::offalWeight()` sengaja
diberi nama sendiri walaupun mengembalikan angka yang sama dengan
`boningInputWeight()` -- yang membacanya menanyakan hal berbeda, dan kalau
kesepakatannya kelak berubah hanya salah satunya yang ikut berubah. **Jangan
digabung menjadi satu metode.**

**Pelajaran untuk yang membaca berikutnya:** pengetahuan umum tentang sebuah
kata ("offal artinya jeroan") bukan dasar yang cukup untuk menyebut kode
salah. Yang tampak seperti kekeliruan penamaan ternyata aturan bisnis yang
disengaja, dan menanyakannya lebih murah daripada memperbaikinya.

#### Alur satu batch boning, apa adanya

Diceritakan Project Owner 5 September 2026, sesudah dua kesimpulan keliru
berturut-turut dari potongan kalimatnya. **Baca ini utuh sebelum menyentuh
Boning.**

Hari ini 10 sapi dipotong. Jadilah karkas, offal, dan kulit -- ketiganya sudah
diketahui beratnya (offal 250, kulit 100, misalnya).

1. Orang produksi membuka modul Boning, **membuat boning dan MEMILIH karkas**
   yang tadi dibuat. Bisa memilih BEBERAPA karkas sekaligus.
2. Di dalam boning itu ia membuat **dua label**: offal 250 kg dan kulit 100 kg.
   Sekarang keduanya punya stok.
3. Gunanya: kulit dan offal **diambil kontraktor hari itu juga**. Untuk membawa
   barang ia butuh DO; untuk DO butuh Sales Order, lalu Tally; dan Tally butuh
   STOK. Label boning inilah yang membuat stoknya ada.
4. DO dibuat, barang dijual, bukti terima dicatat, invoice terbit. Selesai
   untuk hari itu -- dagingnya menunggu proses PELAYUAN semalam.
5. **Besoknya** orang produksi membuka LAGI batch boning yang sama, dan
   melanjutkan membuat label untuk item-item daging yang sudah selesai
   dilayukan.

**Jadi tidak pernah ada boning tanpa karkas.** Syarat itu sempat dicabut karena
disangka kulit dan offal dicatat di dokumen boning tersendiri. Salah baca:
keduanya diberi label DI DALAM boning yang sama. Syaratnya dikembalikan.

**Dan satu dokumen boning hidup DUA HARI** -- label by-product hari pertama,
label daging hari kedua sesudah pelayuan. Yang harus selesai dalam sehari
adalah pekerjaan memotong dagingnya, bukan umur dokumennya.

**TERBUKA dan PENTING -- susutnya belum benar.** Karena kulit dan offal ikut
menjadi label di dalam boning, `outputWeight()` menjumlahkan mereka bersama
daging, sementara `inputWeight()` hanya karkas ditambah buntut. Akibatnya tiap
batch akan terbaca hasilnya jauh melebihi bahannya. Belum menggigit karena
ambangnya masih kosong dan produk OFFAL/KULIT belum ada di master, tetapi ia
akan menggigit begitu alurnya dijalankan. Yang dibutuhkan: cara menandai
produk mana yang by-product karkas supaya dikeluarkan dari hitungan susut --
menunggu keputusan Owner.

#### Angka susut boning yang pertama

Dari dokumen sungguhan 28 Agustus 2026, sesudah OFFAL dan KULIT dikeluarkan
dari daftar produk: bahan 3.968,22 kg, hasil 3.938,38 kg, **susut 0,75%**.
Satu batch belum cukup untuk menetapkan ambang, tetapi ia menunjukkan
besarannya -- jauh lebih berguna daripada tebakan.

#### Bentuknya sama persis dengan Repack

Disengaja: dua proses yang menjawab pertanyaan yang sama sebaiknya dijawab
dengan cara yang sama, supaya yang membaca salah satunya sudah mengerti yang
lain. Ambang global di `settings`, gerbang mati selama ambangnya kosong,
penembusan butuh izin sendiri dan alasan tertulis, tombol Lock MATI dengan
keterangan alih-alih lenyap.

### Kerugian menyimpan JUMLAH, bukan hanya rupiah -- 4 September 2026

Susut kirim di halaman Approve DO sudah dicatat sejak lama, tetapi rupiahnya
DIPAKU NOL dan kilogramnya hanya ada di dalam kalimat catatan. Laporan kerugian
menampilkan Rp 0 untuk tiap susut kirim, dan angka kg-nya tidak bisa dijumlah,
disaring, atau diurut.

`financial_losses` sekarang punya `quantity` dan `unit`. Susut kirim mengisinya;
`amount` MASIH nol dan itu disengaja.

**Kenapa bukan dinilai sekarang.** Menilai susut kirim dengan harga jual
melebih-lebihkan -- perusahaan tidak kehilangan sebesar harga jual, melainkan
sebesar modalnya ditambah margin yang tidak jadi didapat. Angka yang benar HPP,
dan HPP menunggu B.O.M. Project Owner memilih memindahkan kuantitasnya sekarang
supaya saat HPP tiba rupiahnya tinggal `quantity x HPP` dari kolom yang sama,
tanpa menggali ulang ratusan catatan lama.

**Yang harus disadari:** sampai HPP ada, laporan kerugian TETAP menampilkan
Rp 0 untuk susut kirim. Kolom ini tidak membuat uangnya benar, ia membuat
datanya siap. `FinancialLoss::isNotPricedYet()` membedakan nol yang berarti
"belum dinilai" dari nol yang berarti "memang tidak rugi", dan tabelnya
menuliskan bedanya.

Cattle Weighing memakai kolom yang sama. Ia sudah menghitung rupiahnya tetapi
membuang berat susutnya begitu saja -- dua modul kerugian di satu aplikasi yang
menyimpan hal berbeda.

### Yang belum sempat diperiksa di jahitan Payable

Alur pelunasan di `ViewPayable` sudah dibaca dan terlihat wajar (memperbarui `paid_amount`, `balance`, `status`, dan pembayarannya otomatis masuk buku kas lewat model event `SupplierPayment::created`) — **tetapi belum ditutup test**. Itu titik lanjut yang paling dekat.

### Cara kerja yang disepakati Owner

- Perbaiki langsung bila penyebabnya **sudah pasti dari membaca kode**; hemat token, jangan buat probe sekali pakai.
- Tetap **buktikan dengan menjalankan** bila dugaannya menyangkut perilaku runtime yang bisa meleset — dua bug pekan ini hanya ketahuan karena dijalankan, dan salah satunya (`UNIQUE constraint` saat GR dikunci ulang) tidak terlihat sama sekali dari membaca kode. Bedanya: tulis pembuktiannya langsung sebagai test permanen, bukan probe yang dibuang.
- Deploy dikerjakan sendiri lewat SSH (`-tt`, lihat bagian akses server), lalu laporkan hasilnya.

