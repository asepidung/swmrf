# Runbook: memasang app.wijayameat.co.id di VPS

Untuk **IT Lead** yang mengeksekusi di server. Ditulis oleh tim aplikasi (Ruby dan Hafizh), yang **tidak punya
akses SSH ke VPS**. Semua perintah di bawah dijalankan **sebagai user site (`swmapp`)**, bukan root. Tidak ada nilai rahasia di berkas ini: kunci, kata sandi, dan `.env` asli dibuat di server dan tidak
pernah dikirim lewat chat atau git.

> **Nama user site:** `swmapp` (CloudPanel). Direktori situs biasanya `/home/swmapp/htdocs/app.wijayameat.co.id`.
> Di bawah ditulis `SITE=/home/swmapp/htdocs/app.wijayameat.co.id`; sesuaikan bila CloudPanel menaruhnya di tempat lain.

Lingkungan: Ubuntu 24.04, PHP 8.4, MariaDB 10.11 (dipakai bersama aplikasi lain), nginx + php-fpm lewat CloudPanel.

---

## 0. Yang TIDAK boleh dilakukan

- **Jangan restart `mariadb`, `nginx`, atau `php-fpm`.** MariaDB dipakai bersama aplikasi lain; restart memutus
  mereka. Perubahan `.env` cukup dengan `php artisan config:cache`, tidak perlu restart apa pun.
- **Jangan jalankan `git` sebagai root** di direktori situs. Semua `git`, `composer`, dan `php artisan` sebagai
  `swmapp`. File yang dibuat root membuat user situs tidak bisa menulisnya (log, cache, `storage`).
- **Jangan `git config --global --add safe.directory '*'`** dan jangan memakai `safe.directory` sebagai
  jalan pintas. Bila git mengeluh "dubious ownership", penyebabnya hampir pasti folder dimiliki user lain;
  perbaiki pemiliknya (`chown -R swmapp:swmapp`) sebagai root satu kali, bukan melonggarkan git.
- **Jangan `php artisan migrate:fresh`, `migrate:refresh`, `db:wipe`, atau `db:seed`.** `db:seed` menyetel ulang
  kata sandi superuser. Database ini dimulai dari KOSONG dengan `migrate` biasa.
- **Jangan menyentuh database aplikasi lain** di MariaDB yang sama. Buat database dan user baru khusus aplikasi ini.
- **Jangan mengubah kunci VAPID atau `APP_KEY` sesudah dibuat** (lihat bagian 2).
- **Jangan menaruh `.env`, `legacy/`, atau `storage/app/access-export.json` ke git.** Ketiganya sudah di `.gitignore`.
- **Jangan menjadikan `APP_DEBUG=true`** di produksi.

---

## 1. Prasyarat server (satu kali)

1. Situs dibuat di CloudPanel sebagai aplikasi PHP/Laravel untuk domain `app.wijayameat.co.id`, PHP **8.4**, dengan
   **document root = `public`** (`$SITE/public`). TLS (Let's Encrypt) aktif; aplikasi hanya diakses lewat HTTPS.
2. Ekstensi PHP yang dibutuhkan aktif: `bcmath` (atau `gmp`), `curl`, `gd`, `intl`, `mbstring`, `mysql`/`pdo_mysql`,
   `openssl`, `xml`, `zip`. (`openssl` dan `bcmath`/`gmp` dipakai pengiriman notifikasi push.)
3. Database kosong dan user khusus di MariaDB (`utf8mb4`, collation `utf8mb4_unicode_ci`). Catat nama database,
   user, dan kata sandi **di tempat aman**; dimasukkan ke `.env` di langkah 2.
4. `composer` v2 dan `git` tersedia untuk user `swmapp`.

---

## 2. Pasang kode dan `.env`

```bash
cd $SITE
git clone https://github.com/asepidung/swmrf.git .          # sebagai swmapp; direktori harus kosong
git checkout main
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env` (nilai rahasia diisi di server, **jangan ditempel ke chat**). Hanya kunci yang perlu diubah dari
`.env.example`:

```dotenv
APP_NAME="SWM ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.wijayameat.co.id        # WAJIB tepat: dipakai tautan di notifikasi push dan email
APP_TIMEZONE=Asia/Jakarta

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<nama database khusus>
DB_USERNAME=<user khusus>
DB_PASSWORD=<kata sandi>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync                        # BUKAN database: aplikasi tidak memakai queue worker
                                             # (notifikasi sengaja tidak di-queue, lihat TaskAlert.php)

# Kunci notifikasi push (VAPID). Dibuat SEKALI dan PERMANEN -- lihat catatan di bawah.
VAPID_SUBJECT=mailto:<alamat email admin>
VAPID_PUBLIC_KEY=<diisi>
VAPID_PRIVATE_KEY=<diisi>
```

**Kunci VAPID.** Buat satu kali dengan `php artisan webpush:vapid` (menulis ke `.env`), atau pakai kunci yang
**sama dengan coba.** bila pengguna dipindah dengan langganan notifikasinya (bagian 5). Sesudah dipakai,
**jangan diganti**: langganan yang sudah dibuat terikat pada kunci ini. Bila terpaksa diganti, perangkat
mendaftar ulang sendiri saat pengguna membuka aplikasi (tanpa prompt baru), tetapi notifikasi mati sampai itu terjadi.
Simpan salinan `.env` di tempat aman; kehilangan `APP_KEY` atau VAPID tidak bisa dipulihkan.

Setelah `.env` selesai:

```bash
php artisan config:clear
php artisan storage:link
php artisan migrate --force          # database KOSONG; bukan migrate:fresh
php artisan optimize                 # config, route, view, event cache
php artisan filament:optimize        # cache komponen dan ikon Filament
```

Pastikan `storage` dan `bootstrap/cache` bisa ditulis user `swmapp`:

```bash
ls -ld storage bootstrap/cache       # pemilik harus swmapp
```

### Penjadwal (cron)

Aplikasi punya tugas terjadwal (peringatan hutang jatuh tempo pukul 08:00, peringatan Goods Receipt yang belum
dikunci). Pasang **satu** cron per menit di CloudPanel (Cron Jobs), user `swmapp`:

```
* * * * * cd /home/swmapp/htdocs/app.wijayameat.co.id && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Tidak ada queue worker yang perlu dipasang (`QUEUE_CONNECTION=sync`).

---

## 3. Import master data dengan ID legacy (#536)

Snapshot legacy dan berkas harga **tidak ada di git** (folder `legacy/` di-ignore). Salin dari komputer tim
(Ayah/Hafizh) ke server lewat `scp`/SFTP ke `$SITE/legacy/`, mis. `legacy/<snapshot>.sql`,
`legacy/harga-terbaru-per-grup-2026-10-13.xlsx`, `legacy/costing-oktober-2026.xlsx`. **Hapus berkas-berkas ini
dari server setelah selesai** (berisi harga dan data bisnis).

Urutan, **selalu dry-run dulu** (tanpa `--apply`, tidak ada yang ditulis), baca laporannya, baru `--apply`:

```bash
cd $SITE

# 1. Master: kategori, grade, produk, bahan, supplier, kelas sapi, grup pelanggan -- ID legacy dipertahankan
php artisan legacy:import-master --source="legacy/<snapshot>.sql"
#   Bila laporan memuat "DITOLAK", BERHENTI: database tidak bersih. Jangan --apply; hubungi tim aplikasi.
php artisan legacy:import-master --source="legacy/<snapshot>.sql" --apply

# 2. Grup LION/HYPERMART/UMUM dan price list penjualan (butuh grup dari langkah 1)
php artisan legacy:import-prices
php artisan legacy:import-prices --apply          # tunjukkan laporan dry-run ke Ayah sebelum --apply

# 3. Harga patokan costing (gross + net per produk) dari Excel costing terbaru;
#    produk bernama OFFAL / KULIT / HIDES otomatis ditandai "tidak ikut kg overhead"
php artisan costing:import-prices                  # laporan
php artisan costing:import-prices --apply
```

`costing:import-prices` membaca `legacy/costing-oktober-2026.xlsx` (sheet `LJP 15 - 8 OKT`). Produk di Excel yang
tidak ada di master dilaporkan, bukan dibuat. Dapat dijalankan ulang kapan saja (menimpa harga patokan).

---

## 4. Pemindahan pengguna dan hak akses dari database coba. (#536)

Di server **coba.** (Hostinger, oleh tim aplikasi atau yang punya aksesnya), database LAMA:

```bash
php artisan access:export            # menulis storage/app/access-export.json
```

Berkas ini berisi **hash kata sandi dan kunci langganan push**: jangan masuk git, jangan dikirim lewat chat atau
email. Pindahkan lewat `scp` dari server ke server atau lewat komputer tim, taruh di
`$SITE/storage/app/access-export.json` di VPS. Lalu di VPS (database BARU, sesudah langkah 3):

```bash
php artisan access:import            # dry-run: baca laporannya
php artisan access:import --apply
rm storage/app/access-export.json    # HAPUS berkas setelahnya, di VPS dan di server asal
```

Perilaku penting: izin dicocokkan dengan NAMA, pengguna mempertahankan id-nya. Bila ingin langganan notifikasi
yang ikut terbawa tetap berlaku, VAPID di VPS harus **sama dengan coba.** (bagian 2); bila tidak, tidak masalah:
perangkat mendaftar ulang sendiri saat pengguna masuk dan membuka aplikasi.

---

## 5. Pemeriksaan sebelum diserahkan

```bash
php artisan about | head -20                     # Environment production, Debug OFF, URL benar
php artisan migrate:status | tail -5             # tidak ada yang Pending
curl -sI https://app.wijayameat.co.id/admin/login | head -3     # 200, lewat HTTPS
curl -s https://app.wijayameat.co.id/manifest.json | head -5
curl -sI https://app.wijayameat.co.id/sw.js | head -3            # 200, application/javascript
tail -n 30 storage/logs/laravel.log              # tidak ada error baru
```

---

## 6. Deploy pembaruan berikutnya

Sebagai `swmapp`, selalu berurutan; `--no-pager` wajib agar sesi tidak menggantung:

```bash
cd $SITE
git --no-pager pull origin main
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
```

Pembaruan **tidak** memerlukan restart layanan apa pun. Bila `git pull` menolak karena perubahan lokal di server,
**jangan** `git reset --hard`: laporkan ke tim aplikasi. Bila `.env` berubah, cukup `php artisan config:cache`.

---

## 7. Daftar cek pengenalan (Sabtu 17 Oktober 2026)

Dilakukan di perangkat yang akan dipakai pengguna, mis. satu laptop dan satu ponsel Android.

- [ ] **Login** ke `https://app.wijayameat.co.id/admin` dengan akun nyata (bukan programmer); menu dan izin sesuai
      perannya; tidak ada halaman error.
- [ ] **PWA terpasang di `app.`**: di Chrome ponsel, menu → "Tambahkan ke layar utama"/"Instal aplikasi"; ikon dan
      nama "SWM ERP" muncul; dibuka dari ikon tampil layar penuh (tanpa bilah alamat) dan langsung ke aplikasi.
      (Aplikasi lama di `coba.` terpisah; pastikan yang terpasang berasal dari domain `app.`.)
- [ ] **Aktifkan notifikasi**: ikon lonceng di topbar → "Izinkan" → lonceng hilang (berlangganan berhasil).
      Bila lonceng tetap ada dengan titik merah, notifikasi diblokir di pengaturan browser; buka pengaturan situs
      dan izinkan.
- [ ] **Uji kirim notifikasi**: kunci sebuah boning (atau buat dokumen yang memicu notifikasi) dengan akun lain yang
      berizin; perangkat menerima notifikasi; **mengetuknya membuka halaman di `app.wijayameat.co.id`**
      (bukan `coba.`).
- [ ] **Langganan terdaftar ulang saat login**: keluar lalu masuk lagi di perangkat yang sama; notifikasi tetap
      sampai tanpa menekan "Izinkan" lagi.
- [ ] **Cetak label**: dari boning/penerimaan, cetak satu label; ukuran dan isi benar; barcode terbaca.
- [ ] **Scan**: pindai barcode label dengan perangkat scan yang dipakai gudang; kode terbaca dan dokumen terbuka.
- [ ] **Costing**: buka Costings → Draft Costing; boning contoh tampil; buat costing, ketik harga, simpan, cetak A4.
- [ ] `storage/logs/laravel.log` tidak memuat error baru setelah semua langkah di atas.

---

## Lampiran: catatan teknis untuk tim aplikasi

- Tautan di notifikasi push dibuat dengan `CostingResource::getUrl(...)` dan sejenisnya, yang memakai `APP_URL`
  saat berjalan tanpa permintaan web (cron). Karena itu `APP_URL` HARUS `https://app.wijayameat.co.id`.
- `public/manifest.json` memakai `start_url` relatif (`/admin`) dan `public/sw.js` diregistrasi di `/sw.js`
  (lingkup seluruh domain), jadi keduanya otomatis mengikuti domain tempat aplikasi dipasang.
- Pendaftaran ulang langganan push saat login: `resources/views/push/subscribe.blade.php` menjalankan
  `ensureSubscription()` setiap halaman admin dibuka bila izin notifikasi sudah ada. Fungsi itu membuat ulang
  langganan bila kunci VAPID berubah, dan mengirim ulang ke server setiap kali (endpoint idempoten).
