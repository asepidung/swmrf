# Aturan kerja di repositori ini

Berlaku untuk agen mana pun -- Claude, Gemini, atau yang lain. Ditulis
13 September 2026 setelah tinjauan lima hari kerja tanpa commit menemukan
empat kesalahan yang semuanya sudah punya aturan tetapi aturannya tidak
terbaca.

## Baca dulu, sebelum menyentuh apa pun

1. `project.md` -- aturan main yang mengikat.
2. `.agents/agents.md` **bagian 0** -- tenggat, siapa mengerjakan apa, dan
   cara merge. Lalu cari entri modul yang akan disentuh; hampir semua bentuk
   yang terlihat aneh adalah hasil keputusan yang sudah selesai.
3. `.agents/tertunda.md` -- yang sengaja belum dikerjakan. Jangan dikerjakan
   tanpa diminta.
4. `.agents/hpp.md` -- kalau menyentuh HPP, BOM, atau costing. Jangan
   menurunkan ulang rumusnya; sudah diuji ke berkas aslinya.

## Yang dilarang

- `php artisan migrate:fresh` di basis data mana pun.
- `php artisan db:seed` di server -- ia mengatur ulang kata sandi superuser.
- `php artisan test --filter=...` -- sebut path berkasnya.
- Commit langsung ke `main`. Setiap pekerjaan lewat branch `feature/issue-N`
  dengan Issue GitHub-nya, lalu PR.
- Menulis kata sandi sungguhan. **Repositori ini publik.**
- Menaikkan berkas dari `REFERENSI/` -- sudah di-ignore, jangan dilepas.
- Meninggalkan skrip sekali pakai di akar repositori (`fix_*.php` dan
  sejenisnya). Pakai folder sementara di luar repo, lalu hapus.
- Memakai Filament Shield.

## Yang wajib

- **Izin baru lahir lewat MIGRASI**, bukan hanya seeder. Seeder tidak pernah
  dijalankan di server, jadi izin yang hanya ada di seeder tidak akan pernah
  sampai ke hosting -- policy menolak semua orang dan centangnya tidak bisa
  diberikan. Contoh polanya: `create_the_fleet_permissions`.
- **Grup navigasi ditulis persis sama** dengan yang sudah ada: `MASTER DATA`,
  bukan `Master Data`. Dalam bahasa Indonesia keduanya diterjemahkan berbeda
  dan menjadi dua grup sidebar. Ini berlaku untuk Cluster juga, bukan hanya
  Resource. Ada test yang menjaganya.
- **Kalau skema kolom berubah, cari SEMUA pembacanya** -- form, tabel,
  cetakan, export, test. Mengubah `affected_count` dari angka ke teks sambil
  membiarkan cetakannya memakai `number_format()` menghasilkan error 500 saat
  tombol Print ditekan.
- **Suite PENUH sebelum push**: `php artisan test`, sekitar 3 menit.
- Setiap perubahan ditulis di `.agents/agents.md` dengan tanggal, dalam
  bahasa Indonesia, beserta ALASANNYA -- bukan hanya apa yang diubah.
- Kunci terjemahan dalam bahasa Inggris, terdaftar di `lang/en.json` DAN
  `lang/id.json`. Ada test yang menjaganya.
- Jumlah "belum diisi" disimpan `null`, bukan `0`. Keduanya berarti hal yang
  berbeda di proyek ini.

## Menulis catatan lewat terminal

Kalau menulis ke `agents.md` lewat heredoc atau echo, **hindari backslash**.
`\a`, `\n`, `\t` akan ditelan shell dan mengubah `afterStateUpdated` menjadi
`fterStateUpdated`. Itu sudah terjadi. Tulis lewat editor, atau pakai tool
penulis berkas.

## Merge dan deploy

Sampai tenggat lewat, agen **tidak me-merge sendiri**. Kerjakan sampai
membuka PR, lalu sebutkan nomor PR-nya ke Owner. Sesi yang ditunjuk Owner
(Hafizh) menjalankan suite penuh, meninjau, lalu merge dan deploy.

---

# Cara menjalankan test

Sebelum yang pertama kali: pastikan `phpunit.xml` memakai SQLite `:memory:`
(baris `DB_CONNECTION` dan `DB_DATABASE`). Kalau tidak, test akan menghapus
basis data pengembangan.

```
php artisan test                                  # suite penuh, ~3 menit, WAJIB sebelum push
php artisan test tests/Feature/NamaTest.php       # satu berkas, saat mengembangkan
```

Jangan `--filter`. Sebut path berkasnya.

Suite penuh memakan waktu, jadi jalankan di latar belakang dan kerjakan hal
lain -- tetapi jangan mengubah berkas apa pun selagi ia berjalan, hasilnya
jadi tidak bisa dipercaya.

Test memakai SQLite, produksi MySQL. Akibatnya:

- `MONTH()`, `YEAR()`, dan fungsi khas MySQL lain gagal di test. Ambil datanya
  mentah, kelompokkan di PHP.
- SQLite tidak bisa mengubah kolom di tempat; ia membangun ulang tabelnya. Itu
  gagal bila ada VIEW yang bergantung. Migrasi yang mengubah kolom tabel
  ber-VIEW harus menjatuhkan dan memulihkan VIEW-nya (baca SQL-nya dari
  `sqlite_master`, jangan menyalin definisinya).
- `->change()` menulis ulang SELURUH definisi kolom. Nullability dan default
  yang tidak disebut ulang akan hilang. Baca dulu definisi aslinya.
- **SQLite tidak menolak kolom yang tidak ada.** `"driver" is null` pada tabel
  tanpa kolom `driver` tidak menghasilkan galat -- SQLite diam-diam
  memperlakukannya sebagai string `'driver'`. MySQL menolak dengan "Unknown
  column". Akibatnya query yang masih menanyakan kolom yang sudah dibuang
  LOLOS di suite dan JATUH di produksi. Ini terjadi 13 September 2026 pada
  Dashboard. Karena itu: setiap kali kolom dibuang, cari SEMUA pembacanya
  dengan grep (`'nama_kolom'`, `->nama_kolom`), dan tulis test yang hanya
  bisa hijau kalau kolom BARU-nya yang dibaca -- bukan test yang kebetulan
  hijau lewat jalur lain.

Fixture yang sering membuat test merah tanpa sebab yang jelas:

- Kolom NOT NULL yang lupa diisi (`suppliers.address`, `suppliers.pic`,
  `invoices.sales_order_id`, `customers.customer_segment_id`).
- Nama unik yang dibuat dua kali -- pakai `firstOrCreate`.
- Method `protected` yang mau diuji -- pakai `ReflectionMethod`, jangan
  diubah jadi `public` demi test.

---

# Cara membuat penjaga (test yang menjaga aturan)

Penjaga adalah test yang menahan sebuah KELAS kesalahan, bukan satu kejadian.
Di proyek ini penjaga sudah beberapa kali menyelamatkan dari kesalahan yang
sama berulang di tempat berbeda.

## Empat syaratnya

1. **Menyisir seluruh pohon, bukan menyebut satu berkas.** Penjaga yang
   memeriksa `CustomerResource.php` saja tidak menahan kesalahan yang sama di
   `SupplierResource.php` besok. Pakai `glob()` atau
   `RecursiveDirectoryIterator` atas `app/Filament/`, `app/`, atau
   `resources/views/`.

2. **Buang komentar sebelum memindai.** Ini jebakan yang sudah menjerat lima
   kali: penjaga menuduh komentar yang justru MENJELASKAN kesalahan yang
   sedang diperbaiki. Untuk PHP pakai `token_get_all` dan lewati `T_COMMENT`
   serta `T_DOC_COMMENT`; untuk Blade pakai regex `{{-- --}}` dan `<!-- -->`.
   Ganti dengan baris kosong sejumlah yang sama supaya nomor barisnya tetap
   benar.

3. **Buktikan ia menggigit.** Sisipkan satu pelanggaran dengan sengaja,
   pastikan test MERAH, pulihkan, pastikan HIJAU kembali. Penjaga yang belum
   pernah merah belum terbukti menjaga apa-apa. Tulis pembuktian itu di
   catatan.

4. **Biarkan hidup.** Penjaga sekali pakai yang dihapus setelah dipakai
   tidak menahan kesalahan yang sama muncul kembali minggu depan.

## Pesan gagalnya

Sebut berkas dan barisnya, dan jelaskan AKIBATNYA bagi pengguna -- bukan
sekadar "pola X ditemukan". Yang membaca pesan itu enam bulan lagi harus tahu
kenapa ia dilarang tanpa membuka riwayat.

## Contoh yang bisa ditiru

| Penjaga | Menahan apa |
|---|---|
| `ResponsiveColumnSpanTest` | `columnSpan(N)` polos yang memecah baris di HP |
| `SilentDateFilterDefaultTest` | filter tanggal yang query-nya membatasi tapi formnya kosong |
| `NavigationGroupConsistencyTest` | grup sidebar yang berbeda ejaan antara panel, Resource/Cluster, dan form izin |
| `BilingualParityTest` | kunci `__()` yang tidak terdaftar di kedua berkas bahasa |
| `UserPermissionFormTest` | izin yang dibaca kode tapi tidak ada, dan izin yang ada tapi tidak dibaca kode |
| `ActionAuthorizationTest` | tombol yang mengubah keadaan tanpa memeriksa siapa yang menekan |
| `SwallowedFailureTest` | `catch` yang menelan kegagalan tanpa jejak |

Semuanya di `tests/Feature/`. Tiru bentuknya, terutama cara mereka membuang
komentar dan cara mereka menyusun pesan gagal.

---

# Jebakan yang sudah pernah menjerat

Ditulis supaya tidak perlu ditabrak ulang. Tiap baris punya cerita di
`agents.md`.

- **Grup navigasi baru harus didaftarkan di DUA tempat**:
  `Permission::moduleGroups()` dan `AdminPanelProvider::navigationGroups()`.
  Satu saja tidak cukup, dan tidak ada error yang memberi tahu.
- **`null` dan `0` berbeda arti.** Kosong berarti "belum diisi"; nol berarti
  "diisi, hasilnya nol". Jangan memaksa `default(0)` pada kolom yang
  seharusnya boleh kosong -- itu pernah menjatuhkan 645 test sekaligus.
- **Satu aturan, satu rumah.** Aturan yang disalin ke banyak tempat akan
  berbeda diam-diam. Contoh rumahnya: `ShelfLife`, `DocumentNumber`,
  `QcReport::DOKUMEN`, `LihatLaporanQc::make()`, `ProductMaterial::BASIS`.
- **Keadaan yang bisa diturunkan tidak disimpan sebagai kolom.** Dua sumber
  kebenaran akan bertengkar. Pernah ditolak: kolom `weighing_skipped`; yang
  dipakai `weighingWasSkipped()` yang menghitung dari datanya.
- **Nomor dokumen tidak pernah dihitung dari `COUNT`.** Ia terulang begitu
  satu baris dihapus. Pakai `DocumentNumber::next()`.
- **`columns(N)` polos itu SUDAH responsif (`lg`); `columnSpan(N)` polos
  TIDAK (`default`).** Keduanya sering tertukar.
- **Filter tanggal mengikuti pola `CashBookResource`**: `->default()` ada di
  form, query memakai nilai yang sama, chip hanya tampil bila berbeda dari
  default. Form kosong dengan query yang membatasi adalah kebohongan.
- **`@livewire` di Blade menabrak penjaga bahasa.** Lewatkan widget lewat
  `getHeaderWidgets()` dan `getWidgetData()`.
- **`Already up to date` di server bukan kegagalan.** Hosting menarik `main`;
  kalau PR belum di-merge, `main` memang belum berubah.
- **Kalau SSH ke hosting timeout, kemungkinan besar IP-nya diblokir
  Hostinger**, bukan servernya mati. Sudah terjadi dua kali; jalan keluarnya
  hotspot.

---

# Deploy

Setelah PR di-merge ke `main` -- dan hanya sesudah itu:

```
# lokal
git checkout main && git pull
php artisan migrate                       # bila membawa migrasi

# hosting
ssh -tt -p 65002 u525862761@153.92.9.218
cd ~/domains/coba.wijayameat.co.id/public_html
git pull && php artisan migrate --force && php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`-tt` wajib. Selalu `git --no-pager` di server. **Deploy belum selesai sebelum
KEDUA sisi dimigrasi.**
