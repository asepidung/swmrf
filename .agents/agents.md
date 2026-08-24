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

Sedang dimigrasikan dari sistem PHP prosedural ke Laravel 11 + Filament v3 dengan **Strangler Pattern**, jadi selama masa transisi kedua sistem berbagi database yang sama. Itulah alasan banyak nama tabel dan konvensi terasa "warisan" — memang sengaja, jangan dirapikan sepihak.

### Lingkungan

| Lingkungan | Keterangan |
|---|---|
| Lokal | MySQL `swmv2`, dipakai untuk pengembangan |
| Uji coba | `coba.wijayameat.co.id` (shared hosting Hostinger), **auto-deploy dari `main`**, isinya **data dummy** |
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

Paket: `laravel-notification-channels/webpush`. Perhatikan **versi ^10.0**, bukan ^11.0 — versi 11 mensyaratkan Laravel 12/13 sementara proyek ini Laravel 11.

**Keputusan ShouldQueue: TIDAK dipakai.** `QUEUE_CONNECTION` masih `sync` dan tidak ada queue worker di shared hosting. Notifikasi yang diantre tanpa worker justru menumpuk diam-diam tanpa error — persis jebakan yang diperingatkan Owner dari proyek sebelumnya. Penerimanya pun cuma segelintir orang. Bila kelak jumlah penerima membengkak atau approve terasa lambat, pindah ke queue **berbarengan** dengan menyiapkan workernya, jangan salah satu saja.

Pengiriman dibungkus try/catch di `TaskNotifier`: **notifikasi tidak boleh menggagalkan aksi bisnis yang memicunya.** Kalau layanan push mati, dokumen tetap harus tersimpan.

**Penghitung langganan di Dashboard adalah bagian penting, bukan hiasan.** Pelajaran Owner dari proyek presensi: fiturnya sempurna secara teknis, tetapi hanya **3 dari 193 orang** yang menekan "Izinkan" — berbulan-bulan tidak ada notifikasi yang sampai ke siapa pun dan tidak ada yang menyadarinya karena tidak ada yang menghitung. `PushSubscriptionCoverageWidget` menampilkan angkanya sejak hari pertama.

**Izin sengaja TIDAK diminta saat halaman dibuka.** Browser hanya memberi satu kesempatan: begitu pengguna menekan "Blokir", tidak ada cara memintanya lagi dari kode. Karena itu izin diminta lewat tombol lonceng di topbar, setelah pengguna sadar sedang menyalakan sesuatu.

**Syarat yang tidak bisa ditawar:** HTTPS wajib (kecuali localhost), iOS baru bisa sejak 16.4 dan **hanya bila aplikasinya dipasang ke layar utama** — dibuka lewat Safari biasa notifikasi tidak akan pernah muncul meski kodenya benar.

**Kunci VAPID bersifat rahasia** dan hanya ada di `.env` masing-masing lingkungan. `.env.example` sengaja diisi placeholder kosong. Membuatnya: `php artisan webpush:vapid`. Di Windows/Laragon perintah itu gagal dengan "Unable to create the key" bila `OPENSSL_CONF` belum diset ke `openssl.cnf` bawaan PHP.

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
| 3 | Setelah approve purchasing dan approve finance, balik ke Index | perlu repro — kodenya **sudah** memanggil `$this->redirect()`, tapi `$this->save(false)` dipanggil lebih dulu; bila save gagal validasi, redirect tidak pernah jalan dan pengguna tertinggal di halaman **tanpa pesan apa pun** |
| 4 | ~~Cara menolak bila ada barang tanpa harga~~ | **selesai** — purchasing mengisi sendiri, Approve dikunci di dua tahap |
| 5 | ~~Input pembayaran saat approve finance~~ | **selesai** — tersimpan sebagai dokumen `supplier_payments` dan otomatis memotong utang |
| 6 | ~~Notifikasi PWA~~ | **fondasi selesai**, Request Beef sudah pindah; modul lain menyusul sambil disisir |

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

---

## 3. Jebakan yang sudah pernah menggigit

### Pemisah ribuan di dalam Repeater: BISA, asal listenernya di luar baris

Aturan lama di `project.md` menyiratkan pemformatan hidup mustahil di dalam Repeater. **Itu keliru** — yang terlarang hanya caranya, bukan tujuannya.

`RawJs::make('$money(...)')` memasang Alpine `x-mask` pada **setiap input di dalam baris**. Itulah yang tersangkut saat baris dihapus.

Cara yang benar, dan sudah lama dipakai `SalesOrderResource` tanpa masalah: **satu listener `x-on:input` pada `Section` pembungkus**, di luar baris Repeater, yang memformat berdasarkan CSS class lewat event delegation. Alpine tidak pernah menempel ke elemen baris, jadi tidak ada yang perlu di-teardown dan bug zombie tidak pernah terjadi.

Dua hal yang wajib ikut, kalau tidak justru menimbulkan bug baru:

1. **Lepas `->numeric()`** dari field yang diformat. Itu membuat `<input type="number">`, dan browser **menolak** pemisah ribuan di dalamnya sehingga fieldnya tampil kosong. Ganti dengan `->extraInputAttributes(['inputmode' => 'numeric'])`.
2. **Parse nilai sebelum disimpan.** PHP membaca `"250.000"` sebagai `250.0`, jadi harga 250 ribu menyusut jadi 250 **tanpa error apa pun**. Di Request Beef ada empat halaman yang menyimpan item — Create, Edit, Review, ApproveFinance — dan keempatnya wajib memakai `parseNumber()`.

Sudah diverifikasi langsung di browser: mengetik `250000` menjadi `250.000` per ketikan, dan menghapus baris tidak menyisakan baris zombie.

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
ssh -p 65002 u525862761@153.92.9.218
cd /home/u525862761/domains/coba.wijayameat.co.id/public_html
```

Boleh dipakai untuk diagnosa dan perbaikan. Tetap konfirmasi sebelum aksi destruktif yang tidak bisa dibalik, meski itu data dummy — menyiapkan ulang data uji itu merepotkan.

---

## 5. Status Saat Ini

- **Test suite: 76 lolos, 0 gagal.** Sebelumnya praktis mati total. Jaga tetap hijau.
- **Modul yang benar-benar belum ada:** QC/QA Monitoring Produksi; Killing Lost dan Lost Cost; UI untuk Warehouse dan Grade; serta laporan Fast Moving Products, Sales Report, dan Stock Gudang. Status lengkap ada di `checklist_modul.md` (file lokal, tidak masuk repo).

### Tiga modul Finance sengaja dimatikan

`BankAccountResource`, `PayableResource`, dan `ReceivableResource` sama-sama punya `canAccess()` yang mengembalikan `false`, dengan komentar "disembunyikan atas instruksi owner". **Ketiganya sudah dibangun lengkap** — Receivable bahkan punya halaman Receive Payment — dan tinggal dinyalakan bila Owner membutuhkannya.

Jangan memperlakukan ini sebagai bug atau modul yang belum jadi. Kalau perlu diaktifkan, cukup ubah `canAccess()`, tapi **tanyakan dulu kepada Owner**.

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
