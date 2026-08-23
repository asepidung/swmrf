# Wijaya Meat (SWM) — Aturan Main Proyek

> Dokumen ini adalah **aturan main** (high-level instructions) untuk seluruh pengembangan sistem ERP Wijaya Meat.
> Isinya sengaja ringkas dan bersifat perintah: *apa yang wajib dan apa yang dilarang*.
>
> **Alasan di balik setiap aturan, riwayat keputusan, dan konteks onboarding ada di [`.agents/agents.md`](.agents/agents.md).**
> **Penjelasan rinci tiap modul ada di [`docs/modules/`](docs/modules/).**

---

## 1. Konteks Proyek

Sistem ERP untuk **Wijaya Meat**, produsen daging. Merupakan modernisasi sistem lama berbasis PHP prosedural + AdminLTE 3 + jQuery, dikerjakan bertahap dengan **Strangler Pattern**.

- **Stack:** Laravel 11, Filament v3, MySQL.
- **Siklus bisnis:** Master Data → Procurement → Production (Sapi → Karkas → Boning/Repack/Relabel) → Inventory → Sales → Finance.
- **Status pajak:** Wijaya Meat berstatus **nonPKP**. Invoice dan penjualan **tidak dikenai PPN**. Pajak hanya relevan pada pembelian material.
- **Database transisi:** selama migrasi, sistem baru berbagi sumber data dengan sistem lama. **Dilarang mengubah struktur tabel fundamental** yang masih dipakai aktif oleh sistem lama tanpa instruksi eksplisit.
- **Referensi legacy:** `legacy/versi prosedural/` berisi sistem lama yang modulnya sudah lengkap. Rujuk ke sana bila logika bisnis di sistem baru terasa ambigu.

---

## 2. Cara Bekerja

### Sebelum menulis kode

1. Baca dokumen ini, lalu [`.agents/agents.md`](.agents/agents.md).
2. Baca [`docs/modules/`](docs/modules/) untuk modul yang disentuh.
3. Sajikan **Implementation Plan dalam Bahasa Indonesia**: analisis masalah, file yang akan diubah, dan langkah penyelesaian. **Tunggu persetujuan Project Owner.**

### Sikap yang diharapkan

- **Jangan jadi yes-man.** Bila instruksi berpotensi menimbulkan bug atau ada pendekatan yang lebih baik, tahan eksekusi dan sampaikan keberatan beserta opsinya.
- **Jangan berasumsi sepihak.** Bila ada yang ambigu, berhenti dan berikan beberapa pilihan penyelesaian.
- **Utamakan praktik terbaik.** Dokumen ini bukan batas atas kualitas. Bila ada cara yang jelas lebih baik, pakai — tapi **sebutkan penyimpangannya beserta alasannya**, jangan diam-diam.
- **Diksi diserahkan kepada implementor.** Untuk penamaan label, status, dan istilah UI, pilih sendiri yang paling relevan lalu sebutkan pilihannya. Tetap bertanya bila kata itu membawa konsekuensi bisnis.

### Git dan rilis

- Kerjakan di branch `feature/issue-[nomor]`. **Dilarang commit langsung ke `main`.**
- Setiap rencana yang disetujui wajib punya GitHub Issue. Setiap branch yang selesai diajukan sebagai Pull Request.
- **Push ke `main` sama dengan deploy.** GitHub Actions otomatis menjalankan `git pull`, `composer install`, **`php artisan migrate --force`**, lalu cache warming ke server uji coba. Perlakukan merge sebagai aksi deploy ke lingkungan hidup.
- Kode PHP mengikuti PSR-12.

### Setelah selesai

- Baca ulang Issue-nya, pastikan tidak ada spesifikasi yang terlewat.
- Bila muncul aturan main baru dari pekerjaan itu, perbarui dokumen ini dan catat keputusannya di [`.agents/agents.md`](.agents/agents.md).

---

## 3. Larangan Keras

- **Dilarang menjalankan `php artisan migrate:fresh`** pada database mana pun. Selalu migrasi inkremental (`php artisan migrate`). Menyiapkan ulang data dummy itu merepotkan.
- **Dilarang menjalankan `php artisan test`** sebelum memverifikasi `phpunit.xml` memakai SQLite `:memory:` atau database testing terpisah. `RefreshDatabase` tidak boleh menyentuh MySQL utama.
- **Dilarang menulis password sungguhan** di seeder, dokumentasi, atau file mana pun. **Repositori ini publik.** Password bawaan akun superuser wajib tetap `1234` agar `CheckPasswordChange` memaksa penggantian pada login pertama.
- **Dilarang menyisipkan JavaScript global hook** (seperti `Livewire.hook`) untuk mem-*bypass* error bawaan framework seperti 419. Biarkan framework menampilkannya secara natural agar akar masalahnya terlihat.
- **Dilarang memakai Filament Shield** atau `shield:generate`. Sistem hak akses dibangun custom.

---

## 4. Standar Wajib Semua Modul

### Integritas data dan konkurensi

Setiap operasi yang menyisipkan barcode atau memutasi stok:

1. **Pengecekan duplikat wajib berada di dalam `DB::transaction()` dan memakai `->lockForUpdate()`.** Pengecekan di luar transaksi tidak mengikat.
2. Pengecekan di luar transaksi **boleh dipertahankan sebagai *fast-path*** agar pesan ke operator tetap ramah dan spesifik. Anggap itu lapisan UX, bukan penjagaan.
3. **Generator nomor urut barcode wajib dikunci** dengan `->lockForUpdate()`.
4. **Pembacaan yang menjadi dasar keputusan mutasi wajib dikunci.**
5. Kolom `barcode` pada tabel transaksional **sengaja tanpa index unique** — barang keluar-masuk berkali-kali lintas dokumen. Karena itu pencegahan duplikat **wajib berlingkup per dokumen** (`where('mutation_id', ...)`), bukan global.

### Hak akses

Setiap Resource atau Cluster baru wajib:

1. Mendaftarkan permission di `DatabaseSeeder.php` beserta `module_name`.
2. Membuat Policy manual di `app/Policies` dan mendaftarkannya di `AppServiceProvider` bila nama model tidak otomatis terhubung.
3. Menambahkan `canViewAny()` dan `shouldRegisterNavigation()` secara eksplisit agar menu benar-benar tersembunyi bagi yang tidak berhak.
4. Untuk modul ber-`TrashedFilter`, mendaftarkan permission `view_deleted_{module_name}` dan membatasi visibilitas filternya.

### Tabel (halaman Index)

- **Date Range Filter wajib** untuk modul transaksional, default **tanggal 1 bulan berjalan sampai hari ini**, diterapkan diam-diam lewat hook `query()` tanpa badge indikator.
- **Filter dropdown relasional** yang relevan (Customer pada Penjualan, Supplier pada Pembelian).
- **Clickable rows** lewat `recordUrl()`. Jangan tampilkan tombol Edit statis di dalam tabel.
- **Soft Deletes:** sediakan `TrashedFilter`, beri penanda visual lewat `recordClasses()`, dan arahkan baris terhapus ke halaman View read-only, bukan Edit.
- **Ekspor wajib Excel dan PDF.** Excel **dilarang** memakai Filament Exporter karena memicu queue yang lambat — wajib Custom Action *direct stream download* via `OpenSpout\Writer\XLSX\Writer`. PDF dirender lewat blade view dengan `dompdf`. Tombol diletakkan di `table()->headerActions()` berwarna `Success`.

### Form

- `autofocus()` pada field pertama. Urutan field harus nyaman dinavigasi murni dengan `Tab`.
- **Setelah Create atau Edit berhasil, alihkan kembali ke halaman Index.**
- **Masking angka:** pakai `RawJs::make('$money(...)')` pada form biasa. **DILARANG KERAS di dalam `Repeater`** karena memicu bug Livewire Morphdom yang menyisakan baris "zombie" yang tidak bisa dihapus di browser. Di dalam Repeater cukup `->numeric()`.
- **Repeater bersih:** sembunyikan label tiap baris dengan `->label('')` dan `->hiddenLabel()`, cukup pakai `->placeholder()`.
- Pada halaman Edit, sembunyikan tombol Cancel bawaan lewat `getFormActions()` dan letakkan Cancel di Header Actions.

### Navigasi dan tampilan

- Tombol buat data baru diberi label **"Create"**, bukan "New [Model]".
- **Warna dan posisi:** Create `Primary` di Page Header, Detail `Info` di sampingnya, Export `Success` di header tabel.
- **Halaman Detail (Flat List):** modul Induk-Anak wajib punya Custom Page `detail-list` berisi rekap seluruh item anak dalam tabel datar, lengkap dengan silent date filter dan Export.
- **Halaman bertipe paksaan** yang menahan pengguna sampai suatu tindakan selesai wajib memakai layout `simple` agar sidebar tidak tampil, dengan topbar tetap ada supaya pengguna masih bisa Sign out.
- **Dashboard minimalis.** Dilarang menampilkan widget bawaan framework seperti `FilamentInfoWidget`.

### Lain-lain

- **Bilingual:** seluruh teks UI wajib lewat `__()` dan terdaftar di `lang/id.json` serta `lang/en.json`.
- **Activity Log:** model transaksional dan master data sensitif wajib memakai `spatie/laravel-activitylog`.
- **Notifikasi:** memakai Livewire Global Poller (`GlobalTaskPoller`) berbasis perbandingan timestamp, tanpa tabel notifikasi khusus. Untuk setiap modul transaksional baru, **tanyakan dulu kepada Project Owner** apakah modul itu butuh notifikasi.

---

## 5. Referensi Cepat

### Struktur barcode

26 karakter, tersusun dari:

`origin(1) + tanggal ddmmyy(6) + kode produk(6) + grade(1) + berat(4) + pcs(2) + pH(2) + counter(4)`

Digit pertama menandakan asal barang:

| Digit | Asal |
|---|---|
| 1 | Boning |
| 2 | Repack Stock (R-STCK) |
| 3 | Repack Import |
| 4 | Repack Return |
| 5 | Repack Trading |
| 6 | Relabel Tally |
| 7 | Pembelian Trading Lokal |
| 8 | Pembelian Trading Import |

### Lokasi penting

| Apa | Di mana |
|---|---|
| Resource Filament | `app/Filament/Admin/Resources` |
| Cluster | `app/Filament/Clusters` |
| Penjelasan tiap modul | `docs/modules/` |
| Riwayat keputusan dan alasannya | `.agents/agents.md` |
| Sistem lama sebagai rujukan | `legacy/versi prosedural/` |

### Jebakan yang sudah diketahui

- **Error 419 / Page Expired:** jangan mencampur `localhost` dengan `127.0.0.1` dalam satu sesi. Samakan dengan `APP_URL`.
- **Baris "zombie" di Repeater:** disebabkan `RawJs` mask di dalam Repeater. Lihat aturan Form di atas.
- **Migrasi yang memakai sintaks khusus MySQL** akan mematikan seluruh test suite, karena testing berjalan di SQLite. Gunakan sintaks yang didukung kedua driver.
