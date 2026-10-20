# Buku keputusan import data legacy (latihan di coba., dipakai untuk import final ke VPS)

## RINGKASAN POLA IMPORT (baca ini dulu; diperbarui 20 Oktober 2026)

**Keputusan Ayah:** VPS (produksi) TIDAK diubah dulu. Master data di coba. sudah lengkap dan dipakai langsung di VPS nanti lewat salinan; pengguna
berlatih dari data KOSONG. Bila sampai 31 Oktober ada master baru (pelanggan, dst.), VPS tetap tidak disentuh; buat salinan ulang dari coba. pada hari-H.

**Pola, per tabel, di coba.:** (1) tunjukkan perbandingan lama vs baru dan masalahnya ke Ayah, (2) dry-run, (3) laporan ke Ayah, (4) `--apply`,
(5) jalankan ulang untuk memastikan 0 baris baru, (6) catat hasil di dokumen ini. Tidak ada yang diterapkan tanpa keputusan Ayah.

**Perintah:** `legacy:import-master --source=<sql> --only=<langkah> [--apply]` (langkah: kategori, grade, supplier, kelas-sapi, grup, pelanggan, produk,
kategori-material, material, bom); `master:seed-fleet` (supir, armada), `master:seed-segments` (5 segmen), `master:seed-groups` (KARYAWAN, WARGA +62, GRATISAN,
sesudah grup legacy). Semua dry-run bawaan dan aman diulang. Snapshot SQL legacy dikirim dengan `scp` ke `~/` coba. (di luar folder web).

**Urutan latihan:** gudang -> supir/armada -> kelas sapi -> supplier -> kategori material + material -> segmen -> grup pelanggan -> pelanggan ->
kategori produk -> produk. BOM tidak diimpor (diisi QC di aplikasi). Rekening bank penagihan menunggu pembahasan dengan Hafizh.

**Aturan yang berulang:** ID legacy dipertahankan; nama lewat `TidyName` (PT/CV di belakang tanpa titik, satu spasi sesudah tanda baca); nama kembar digabung
dengan peta id lama->baru yang WAJIB dipakai importer riwayat (`SUPPLIER_MERGES`, `customerIdMap()` + `CUSTOMER_MERGES`); yang tidak jelas diimpor nonaktif
agar data legacy punya tempat menempel; kode produk legacy tidak diubah; nama terpotong legacy dipulihkan dari data lain; satu penolakan membatalkan semuanya.

**Hari-H (menunggu aba-aba Ayah):** cadangkan DB VPS -> deploy `main` -> migrate -> bersihkan sisa data uji master -> kirim snapshot (`master:export` di coba.) ->
`master:import-snapshot` dry-run -> laporan -> `--apply`. Rincian di bagian "Salinan hasil akhir coba. -> VPS" di bawah.

---

Cara kerja (disepakati dengan Ayah, 19 Oktober 2026): **cara Ayah untuk kesepakatan, cara Ruby untuk eksekusi.**

1. Per tabel ada **lembar pasangan**: kolom lama -> kolom baru, apa yang berubah, dan **potensi masalah bernomor**.
2. Ayah memutuskan tiap masalah (cukup menjawab dengan nomornya, mis. "SUP-1: 30 hari"). Keputusan dicatat di sini
   lengkap dengan tanggal.
3. Setelah sepakat: dry-run di `coba.` (laporan saja), Ayah memeriksa, baru `--apply`, lalu diperiksa di aplikasi.
4. Hari pemindahan (31 Okt - 1 Nov): snapshot terbaru ditarik, semua perintah dijalankan ulang sesuai buku ini. Karena
   datanya sudah berubah, yang dilaporkan ke Ayah hanya **masalah BARU** yang belum pernah diputuskan.

Sumber: snapshot `legacy/versi prosedural/konak/SWM-master-2026-10-19.sql` (11 tabel master, tanpa tabel users dan
tanpa hash sandi; berkas ini tidak masuk git). Legacy HANYA DIBACA. Perintah: `php artisan legacy:import-master
--source=... [--apply]` (dry-run bawaan; nomor lama dipertahankan; semua-atau-tidak-sama-sekali; aman diulang).

## Urutan (dipilih Ruby berdasarkan ketergantungan)

| # | Tabel | Butuh lebih dulu |
|---|---|---|
| 1 | Kelas sapi | - |
| 2 | Grup pelanggan | - |
| 3 | Supplier | - |
| 4 | Kategori produk | - |
| 5 | Produk | kategori produk (dan produk INDUK untuk varian) |
| 6 | Kategori material | - |
| 7 | Material (+ satuan) | kategori material, satuan |
| 8 | BOM | produk dan material |
| 9 | Segmen pelanggan | daftar dari Ayah (tidak ada padanan di legacy) |
| 10 | Pelanggan | grup dan segmen (segmen wajib diisi) |

Grade legacy TIDAK diimpor sebagai baris (diterjemahkan ke gudang + grade; lihat bagian paling bawah).

---

## 1. Kelas sapi: `cattle_class` -> `cattle_classes` (7 baris)

| Lama | Baru | Catatan |
|---|---|---|
| idclass | id | Nomor sama |
| class_name | name | |
| created_at, updated_at | (dibuat baru) | Tidak dibawa |

Masalah: **tidak ada.** Status: siap dry-run.

## 2. Grup pelanggan: `groupcs` -> `customer_groups` (56 baris)

| Lama | Baru | Catatan |
|---|---|---|
| idgroup | id | Nomor sama |
| nmgroup | name | Huruf besar |
| terms (kosong semua) | trading_terms_percent | Tidak dibawa; 0. Tidak dipakai costing lagi. |
| - | top | 0 (TOP grup tidak ada di legacy) |
| - | head_office_address, head_office_pic | Kosong |
| - | is_costing_reference, is_general_price_reference | Tidak; dipakai `legacy:import-prices` nanti |

Masalah:
- **GRP-1.** 3 grup tanpa satu pun pelanggan: OUR BAR, YADI, CULAS. Dibawa atau dilewati? (Usul: dibawa, tidak merugikan.)
- **GRP-2.** TOP dan potongan harga per grup tidak ada di legacy (dan price list belum diimpor). Dibiarkan 0 sampai
  price list masuk. Setuju?

Status: menunggu GRP-1, GRP-2.

## 3. Supplier: `supplier` -> `suppliers` (43 baris)

| Lama | Baru | Catatan |
|---|---|---|
| idsupplier | id | Nomor sama |
| nmsupplier | name | Huruf besar |
| alamat | address (WAJIB) | 2 kosong -> "-" |
| telepon | phone | 35 dari 43 kosong |
| jenis_usaha | supplied_goods | Apa adanya (SAPI, DAGING, PLASTIK, KARTON, ...) |
| npwp | (tidak ada kolom) | 5 terisi, tidak ikut |
| iduser | (tidak dipakai) | Hanya siapa yang menginput |
| - | pic (WAJIB) | "-" |
| - | top_days (WAJIB) | 0 |
| - | is_tax_11, bank_name, account_number, account_name | Tidak kena PPN, rekening kosong |
| - | is_active | Semua aktif |

Masalah:
- **SUP-1.** TOP supplier tidak ada di legacy; menentukan jatuh tempo hutang. Ayah punya daftarnya, atau diisi nanti per supplier?
- **SUP-2.** PIC, alamat kosong, dan PPN 11% tidak ada datanya. Sementara "-" / tidak kena PPN. Setuju?
- **SUP-3.** Yang tampaknya bukan pemasok sungguhan: REGULER, MARKETPLACE, E - COMMERCE, ASEP IDUNG, SWM (STOCK IN),
  MEAT PROCESSING. Dibawa atau dilewati?
- **SUP-4.** Kemungkinan satu perusahaan ditulis beda: CV. SAMUDERA KARUNIA RIZKY (41) dan CV.SAMUDERA KARUNIA RIZKY (46);
  SINERGY INDOPACK MAKMUR PT (14) dan Sinergy Indopack Makmur (26); INDOGUNA PT (9) dan PT INDOGUNA UTAMA (32,
  mungkin berbeda). Mana yang digabung? (Legacy tidak punya tanggal diperbarui, jadi aturan "paling baru" tidak bisa
  dipakai; usul: id lebih besar.)
- **SUP-5.** Supplier yang sudah tidak dipakai: semua aktif, atau Ayah tandai yang nonaktif?

Status: menunggu SUP-1 sampai SUP-5.

## 4. Kategori produk: `cuts` -> `product_categories` (7 baris)

| Lama | Baru | Catatan |
|---|---|---|
| idcut | id | Nomor sama (1-7) |
| nmcut | name | PRIME CUT, SECONDARY CUT, BONES, OFFAL, FAT, PROCESSING, MANUFACTURING |
| - | prefix (WAJIB, unik) | = idcut. Pola kode legacy: digit pertama kode = kategori, sama dengan cara aplikasi baru membuat kode. |

Masalah:
- **KAT-1.** Beberapa produk berkode dengan digit pertama yang TIDAK sama dengan kategorinya (mis. produk PRIME CUT
  berkode 7xxxxx, produk FAT berkode 2xxxxx). Dibiarkan apa adanya (kode legacy dipertahankan)? Usul: ya.

Status: menunggu KAT-1.

## 5. Produk: `barang` -> `products` (158 baris; 149 siap, 9 tanpa kode)

| Lama | Baru | Catatan |
|---|---|---|
| idbarang | id | Nomor sama |
| kdbarang | code (WAJIB, unik) | Apa adanya. 9 baris berkode kosong atau "-" |
| nmbarang | name | Huruf besar |
| idcut | category_id | |
| kodeinduk | parent_id + structure_type | **63 produk adalah varian dari 22 produk induk.** Saat ini diperlakukan semua "main" dan kode induk hanya dicatat di catatan. |
| karton, drylog, plastik | legacy_note | Catatan spesifikasi kemasan (95 produk) |
| iduser | (tidak dipakai) | |
| - | is_active | Aktif |
| - | excluded_from_overhead | OFFAL, KULIT/HIDES otomatis ditandai oleh `costing:import-prices` |
| - | max_label_weight | Kosong (bawaan 30 kg) |

Masalah:
- **PRD-1.** **Induk dan varian.** Aplikasi baru punya "Sub Beef / Variant" (varian menempel ke induk). Legacy menyimpan
  ini di kodeinduk (mis. STRIPLOIN CUT -> induk STRIPLOIN WHL). Pasangkan jadi varian (parent_id, jenis "sub")? Usul: ya,
  semua 63 menunjuk kode yang ada, tidak ada yang menggantung.
- **PRD-2.** **9 produk tanpa kode:** STYROFOAM BESAR GARUDA, STYROFOAM BESAR, ICE GELL, DELIVERY COST, LUNG,
  STYROFOAM KECIL, FAT ETC, SODAS BUFFALO, ZZZ X PAHA DEPAN S. Sebagian bukan produk (kemasan, ongkos kirim, "ZZZ").
  Untuk tiap nama: dilewati, atau dibuatkan kode otomatis?
- **PRD-3.** **karton / drylog / plastik** (95 produk) adalah spesifikasi kemasan lama; 65 di antaranya juga punya baris
  BOM, 30 tidak. Cukup disimpan sebagai catatan, atau 30 itu dibuatkan BOM dari isiannya?

Status: menunggu PRD-1 sampai PRD-3 (dan KAT-1).

## 6. Kategori material: `rawcategory` -> `material_categories` (28 kategori, 10 diimpor)

Diimpor (kategori produksi/kemasan): KARTON, PLASTIK, LABEL, STYROFOAM, LAKBAN, MIKA, PACKAGING SUPPORT, PRODUKSI, TRAY,
MEAT PROCESSING. Nomor lama dipertahankan.

**Tidak diimpor** (18 kategori, berisi 76 barang): LAIN-LAIN (32 barang), PERCETAKAN (10), ATK (16), PERAWATAN ARMADA (5),
SPAREPART MOBIL (4), ALAT PENDUKUNG SABLON (3), MAINTENANCE (2), INTERNET (2), RAM (1), LEGALITAS DOKUMEN (1), dan 8
kategori kosong. Keputusan Ayah (via Hafizh): ATK dan alat sablon adalah **Pengeluaran** (modul Expenses), bukan master
material.

Masalah:
- **KMT-1.** **LAIN-LAIN (32 barang) dan PERCETAKAN (10)** ambigu: berisi barang yang mungkin dipesan lewat Request Material
  (mis. material baru "TOREN" ada di LAIN-LAIN, id 229) atau murni pengeluaran. Mana yang material, mana pengeluaran?
- **KMT-2.** Kategori yang tidak diimpor: namanya dijadikan **kategori Pengeluaran** (Expense Categories), atau cukup
  dilupakan?

Status: menunggu KMT-1, KMT-2.

## 7. Material: `rawmate` -> `materials` (227 baris; 151 di kategori yang diimpor)

| Lama | Baru | Catatan |
|---|---|---|
| idrawmate | id | Nomor sama |
| nmrawmate | name | Huruf besar |
| kdrawmate (RMxxxx) | code (WAJIB, unik) | **Tidak dibawa**: aplikasi baru membuat kode sendiri (MTR001, ...) |
| idrawcategory | material_category_id | |
| unit | material_unit_id (WAJIB) | Satuan dibuat dari nama: PCS, BOX, PACK, KG, IKAT, SET |
| barmin | min_stock | |
| stock | (tidak dibawa) | Stok awal dibahas terpisah (tahap stok awal) |
| - | is_active, show_in_stock | Aktif, tampil di stok |

Masalah:
- **MAT-1.** **58 material tanpa satuan** (semuanya kategori MEAT PROCESSING: bumbu, tepung, garam, gula, kecap, minyak,
  casing sosis). Satuan wajib diisi di aplikasi baru. Mohon satuannya per kelompok (mis. tepung/bumbu = KG, kecap/minyak =
  LITER, casing = PCS). Daftar lengkap 58 nama ada di dry-run.
- **MAT-2.** Kode lama RMxxxx tidak ikut. Orang gudang terbiasa dengan kode itu? Kalau ya, kita sediakan tempat menyimpannya.
- **MAT-3.** Material baru sejak 16 Oktober: TOREN (229, kategori LAIN-LAIN) ikut keputusan KMT-1.

Status: menunggu MAT-1 sampai MAT-3 (dan KMT-1).

## 8. BOM: `bom_rawmate` -> `product_materials` (417 baris, semua aktif)

| Lama | Baru | Catatan |
|---|---|---|
| idbom | id | Nomor sama |
| idbarang | product_id | Produk harus sudah diimpor |
| idrawmate | material_id | Material harus sudah diimpor (kategori produksi/kemasan) |
| qty | quantity | Semua bilangan bulat |
| (tidak ada) | basis (WAJIB: box atau piece) | **Legacy tidak menyatakan dasar hitung.** Sementara "box". |
| is_active | (hanya yang aktif) | Semua aktif |

Masalah:
- **BOM-1.** **Dasar hitung (per box atau per potong).** Legacy tidak mencatatnya; 197 baris bertanda TINJAU (PLASTIK, LABEL,
  MIKA biasanya per potong). Aturan per kategori: KARTON = per box; PLASTIK, LABEL, MIKA = per potong; lainnya?
- **BOM-2.** Baris BOM yang menunjuk produk/material yang tidak diimpor (karena PRD-2, KMT-1, MAT-1) akan dilewati dan
  dilaporkan. Jumlahnya baru diketahui setelah keputusan sebelumnya.

Status: menunggu BOM-1.

## 9. Segmen pelanggan: `segment` -> `customer_segments` (TIDAK ada padanan)

**Arti berbeda.** `segment` di legacy (4 baris: BNI PT, BCA, BNI, BCA PT) adalah **rekening bank penagihan**: nama bank,
nama pemilik rekening, nomor rekening. Dipilih per pelanggan, dan menentukan rekening yang tercetak di invoice. Di
aplikasi baru, `customer_segments` adalah **kelompok pelanggan** (WAJIB diisi tiap pelanggan).

Masalah:
- **SEG-1.** Kelompok pelanggan apa yang Ayah mau (mis. Hotel, Restoran, Retail, Perorangan, Distributor)? Usul sementara:
  satu segmen "UMUM" untuk semua.
- **SEG-2.** **Rekening penagihan**: di aplikasi baru, rekening yang tercetak di invoice datang dari mana? Empat rekening
  legacy itu dimasukkan sebagai akun bank (modul Bank Accounts), dan pelanggan dikaitkan ke rekeningnya? Ini menyangkut
  uang dan invoice, jadi perlu dibahas khusus (mungkin dengan Hafizh).

Status: menunggu SEG-1, SEG-2.

## 10. Pelanggan: `customers` -> `customers` (306 baris)

| Lama | Baru | Catatan |
|---|---|---|
| idcustomer | id | Nomor sama |
| nama_customer | name | Huruf besar, harus unik (lihat keputusan di bawah) |
| alamat1, alamat2, alamat3 | address | Digabung, spasi dirapikan |
| telepon | phone | "" dan "-" jadi kosong |
| top | top | Hanya 1-60 hari; selain itu 0 (12 pelanggan: 0 atau -1) |
| idgroup | customer_group_id | Nomor grup lama; 1 pelanggan tanpa grup -> dibuatkan otomatis |
| idsegment (rekening bank) | customer_segment_id (WAJIB) | Tidak sama artinya: lihat SEG-1/SEG-2 |
| nkv, halal, sv, joss, phd, ujilab, invoice | required_documents | Dipetakan satu per satu |
| tukarfaktur (YES/NO) | invoice_exchange | |
| sales_referensi, email, catatan, pajak | (tidak ada) | Kosong di data sekarang |
| - | default_discount | Grup LION 2%, lainnya 0 |
| - | pic | Kosong (tidak ada di legacy) |
| - | is_active | Semua aktif (lihat CUS-1) |

**Keputusan Ayah, 19 Oktober 2026** (nama kembar, bagian dari tabel ini):

- **Nama kembar yang sama dengan pelanggan lain: digabung, yang dipakai adalah yang PALING BARU diperbarui**
  (`tanggal_update` terbaru; sama -> id terbesar). Bukan id terkecil: tiga cabang HPM yang lama masih TOP 4 hari,
  yang baru TOP 14 hari.
  - ABUBA - CIPETE: 218, 219 -> pakai 219
  - ABUBA - CITRA GARDEN: 226, 227 -> pakai 227
  - BULL & VINE STEAKHOUSE: 229, 230 -> pakai 230
  - HPM CYBERPARK KRWC: 121, 204 -> pakai 204
  - GRANDLUCKY - LIVING PLAZA CINERE: 209, 213 -> pakai 213
  - HPM PURI INDAH: 146, 207 -> pakai 207
  - HPM GADING SERPONG: 148, 205 -> pakai 205
  - HPM KARAWACI: 150, 208 -> pakai 208
  - NENENG(IBU): 194, 294 -> pakai 294
- **CIPTA BOGA PRIMA PT (135, 182, 188): bukan kembar biasa, tiga alamat kirim berbeda -> TETAP TIGA pelanggan**
  dengan nama diberi pembeda: 135 "CIPTA BOGA PRIMA PT (PASAR REBO)", 182 "CIPTA BOGA PRIMA PT (GEDANGAN)",
  188 "CIPTA BOGA PRIMA PT (VIA EKSPEDISI ELVANO)".
- **Peta id lama -> id yang dipakai** (mis. 204 untuk 121) WAJIB dibuat dan dipakai importer riwayat, dokumen terbuka,
  dan stok awal, supaya transaksi lama (invoice, Sales Order) tidak kehilangan pelanggannya. Dihitung otomatis dari
  snapshot dengan aturan yang sama (bukan ditulis tangan), dan dites.
- Pelanggan baru di aplikasi: nama yang sama persis ditolak. Yang beda hanya spasi/tanda baca ("CV. X" vs "CV.X")
  lolos formulir; cabang dari satu perusahaan harus diberi pembeda di nama seperti contoh Cipta Boga. (Sejak 19 Oktober
  ada peringatan "nama mirip" di semua formulir master data.)

Masalah (belum diputuskan):
- **CUS-1.** Pelanggan yang sudah tidak aktif: semua masuk aktif, atau Ayah tandai? (Banyak yang terakhir diperbarui 2023-2024.)
- **CUS-2.** PIC kosong dulu, atau ada sumbernya?
- **CUS-3.** Diskon bawaan 2% hanya untuk grup LION, lainnya 0. Setuju?
- **CUS-4.** Pelanggan TOP 0 atau -1 (12 orang, mis. FREE SAMPLE) jadi 0 hari (tunai). Setuju?
- CUS-5 = SEG-1 dan SEG-2 (segmen dan rekening).

Status: menunggu CUS-1 sampai CUS-4 dan SEG-1/SEG-2. Pelanggan diimpor PALING AKHIR.

---

## Tidak diimpor sebagai baris: Grade (keputusan Owner, 16 Oktober 2026)

Grade legacy (6: J01, J02, P01, P02, J03, P03) menggabungkan gudang + kondisi, jadi tidak diimpor. Diterjemahkan lewat
`App\Support\LegacyGradeMap` ke (gudang menurut kode, grade baru): 1 J01 -> JONGGOL+CHILL, 2 J02 -> JONGGOL+FROZEN,
5 J03 -> JONGGOL+A, 3 P01 -> PERUM+CHILL, 4 P02 -> PERUM+FROZEN, 6 P03 -> PERUM+A. Dipakai oleh import stok awal,
dokumen terbuka, dan riwayat. `legacy:import-master` hanya memverifikasi terjemahan ini.

## Catatan teknis untuk hari pemindahan

- Urutan perintah ikut tabel di atas; setelah `legacy:import-master` jalan `legacy:import-prices` (price list),
  `costing:import-prices` (harga patokan costing), dan `legacy:import-users` (akun).
- Setiap perintah dry-run bawaan; baca laporan, jawab hanya masalah BARU, baru `--apply`.
- Jangan menjalankan `migrate:fresh`/`db:seed` di VPS (lihat pengecualian untuk coba. di `agents.md`).
- Bila snapshot hari pemindahan membawa nama kembar baru, ikuti aturan di bagian Pelanggan (paling baru, kecuali beda
  alamat kirim -> beri pembeda).
  lolos formulir; cabang dari satu perusahaan harus diberi pembeda di nama seperti contoh Cipta Boga.

## Supir dan armada (data awal dari Owner, 19 Oktober 2026; tidak ada di legacy)

Perintah `php artisan master:seed-fleet` (dry-run bawaan; `--apply` menulis; aman diulang; nama/plat dibandingkan longgar;
plat yang sama dengan jenis berbeda = konflik, tidak ditimpa). Datanya ada di `App\Console\Commands\SeedFleet`:

- Supir (tabel `drivers`: name): ACEP NURFADILLAH, RESKHA PERMANA.
- Armada (tabel `vehicles`: vehicle_type, police_number): CANTER - F 8485 HD, TRAGA - F 9385 FI, L-300 - F 8489 HV,
  DUTRO - F 9201 F.
- Semua aktif. Tidak ada masalah yang perlu keputusan. Hasil yang harus cocok: 2 supir dan 4 armada.

## Catatan latihan di coba. (19 Oktober 2026)

Perintah per tabel: `php artisan legacy:import-master --source=<berkas.sql> --only=<langkah> [--apply]`.
Langkah: kategori, grade, supplier, kelas-sapi, grup, produk, material, bom. Snapshot dikirim ke `~/` di coba.
(`scp`, di luar folder web). Dry-run dulu, lihat laporan, baru `--apply`.

- Kelas sapi: DITERAPKAN di coba. Hasil 7 baris (id 1 STEER, 2 BULL, 3 HEIFER, 4 COW, 5 PREMIUM, 6 NONPREMIUM, 7 BULL & STEER).
  Dijalankan ulang: 0 dibuat (aman diulang).

### Supplier (keputusan Ayah, 19 Oktober 2026)

- Semua 43 supplier dibawa, aktif, TOP 0, PIC "-", tidak kena PPN. Yang tidak jelas (REGULER, ASEP IDUNG, SWM (STOCK IN),
  MEAT PROCESSING) tetap diimpor supaya data legacy punya tempat menempel; dinonaktifkan nanti di aplikasi.
- Digabung (id lama -> id yang dipakai; konstanta `ImportLegacyMaster::SUPPLIER_MERGES`, WAJIB dipakai importer riwayat):
  41 -> 46 (CV. SAMUDERA KARUNIA RIZKY, alamat dan NPWP sama persis), 14 -> 26 (SINERGY INDOPACK MAKMUR),
  9 -> 32 (INDOGUNA PT -> PT INDOGUNA UTAMA, kata Ayah satu perusahaan), 18 -> 19 (E - COMMERCE -> MARKETPLACE).
  Jenis usaha supplier yang digabung dipindah ke yang selamat. Hasil yang harus cocok: 39 supplier.
- NPWP (5 terisi) tidak dibawa: aplikasi tidak punya kolomnya.

### Aturan penulisan nama (keputusan Ayah, 19 Oktober 2026) -- `App\Support\TidyName::company()`

Huruf besar semua; PT dan CV tanpa titik dan SELALU di belakang nama ("PT. ARDANA PERMATA ANUGERAH" -> "ARDANA PERMATA ANUGERAH PT")
supaya daftar urut menurut nama inti (pola yang sama dengan "ASEP BPK"); satu spasi sesudah tanda baca; tanpa spasi di dalam
kurung. "H. DONI" dan BPK/IBU dibiarkan. Dipakai di supplier; DIPAKAI JUGA untuk pelanggan, produk, material saat gilirannya.
Supplier 26 memakai nama lengkap "SINERGY INDOPACK MAKMUR PT" (hasil gabungan 14 dan 26).
Pelanggan baru yang diketik manual di aplikasi belum dirapikan otomatis (hanya diperingatkan bila mirip).

### Material (keputusan Ayah via Hafizh, 19 Oktober 2026)

- Material dipisah per tujuan (daftar per item: `legacy/material-dipilah-2026-10-19.xlsx`, tidak masuk git): REQUEST 151 ->
  `materials`; OPERASIONAL 56 -> bukan material, cukup kategori Expense; ASET 9 -> kategori Expense "ASET"; HAPUS 10.
  Latihan sekarang: impor yang ada dulu (kategori yang diimpor ditambah PERCETAKAN); pemilahan per item dikerjakan
  setelahnya.
- Kode material: **MTR + id** (kdrawmate RMxxxx legacy selalu sama dengan id-nya: RM0001 -> MTR001). Kode RM tidak
  disimpan. `Material::booted()` kini menghormati kode yang sudah diisi (importer mengisinya).
- Langkah importer: `--only=kategori-material` (hanya kategori) lalu `--only=material` (kategori + material).

### Material: satuan kosong (keputusan Ayah, 19 Oktober 2026)

- 6 barang yang bukan bahan produksi tetapi sudah ada transaksinya (FOOT SWITCH 56, PULPEN 66, PADMAN 74, BOX CONTAINER 3003/2002
  176/177, BROSUR 178): tetap diimpor, satuan PCS, **nonaktif**. Pemilahan per item (operasional/aset/hapus) dibahas Ayah dengan
  Hafizh; material "kotor" itu dirapikan setelahnya (nonaktifkan/pindah ke Expense), bukan lewat importer.
- Bahan MEAT PROCESSING tanpa satuan: LITER untuk KECAP/SAUS/MINYAK, selain itu KG. TALI KASUR (180): PCS. Food colour: KG.
- Satuan kosong di kategori lain tetap dilewati dan dilaporkan.

## Segmen pelanggan (keputusan Ayah, 19 Oktober 2026; tidak ada di legacy)

Tabel `segment` legacy = 4 rekening bank penagihan (BNI PT, BCA, BNI, BCA PT), BUKAN kelompok pelanggan; tidak diimpor
sebagai segmen (SEG-2: rekening dibahas terpisah dengan Hafizh, menyangkut invoice). Segmen pelanggan dibuat dari data Ayah
lewat `php artisan master:seed-segments` (dry-run bawaan, `--apply`, aman diulang): MODERN MARKET, HOREKA, DISTRIBUTOR, RETAIL,
INDUSTRI. Hasil yang harus cocok: 5 segmen.
Masih terbuka untuk impor pelanggan: tiap pelanggan WAJIB punya segmen; legacy tidak punya datanya, jadi Ayah menentukan
pemetaannya (per pelanggan atau per grup) sebelum pelanggan diimpor.

## Grup pelanggan (19 Oktober 2026)

`--only=grup`, ID = idgroup legacy (56 grup, ID 34 tidak ada di legacy). Dibawa semua, termasuk 3 grup tanpa pelanggan (OUR BAR,
YADI, CULAS); TOP dan potongan 0 (dibiarkan sampai price list masuk). Nama dirapikan dengan `TidyName` (mis. CV BERKAH -> BERKAH CV).
Nama grup di legacy TERPOTONG 20 huruf; dipulihkan dari nama pelanggan di grup itu (`ImportLegacyMaster::GROUP_RENAMES`):
43 REPUBLIK MANOR PROPERTINDO PT, 45 SWISSOTEL LIVING JAKARTA, 50 ANUGERAH DIRGA CIPTA PT, 56 MITLOIN GLOBAL GROUP PT.
Grup baru tambahan Ayah (di luar legacy, ID sesudah 57): KARYAWAN, WARGA +62, dan satu grup untuk gratisan/tamu (nama menunggu Ayah).
Grup tambahan Ayah: `php artisan master:seed-groups` (dry-run bawaan, `--apply`, aman diulang): KARYAWAN, WARGA +62, GRATISAN.
WAJIB dijalankan SESUDAH `legacy:import-master --only=grup` supaya ID grup legacy tidak tergeser (hasil: ID 58, 59, 60).

### Pelanggan: keputusan Ayah selanjutnya (19 Oktober 2026)

- Segmen menurut GRUP (konstanta `GROUP_SEGMENTS`): MODERN MARKET (HPM, LION, AEON, GRAND LUCKY, GS THE FRESH, THE FOODHALL,
  CARREFOUR, GELAEL), HOREKA (ABUBA, CHEF NALDO, EL ASADOR, PULLMAN, YUAN GARDEN HOTEL, ABOUT US BRASSERIE, SWISSOTEL, IBIS,
  MULLIGAN, SMOKEHOUSE BBQ, CORNER HOUSE GROUP), sisanya RETAIL (termasuk REGULER dan UNGROUP); dirapikan Ayah di aplikasi.
- Digabung tambahan: 54 "FREE / SAMPLE" -> 127 "Free Sample" (`CUSTOMER_MERGES`). Hasil yang harus cocok: 295 pelanggan
  (306 - 1 tanpa nama - 9 kembar - 1 gabungan).
- Diskon bawaan 2% HANYA untuk LION DCA (6), DCB (28), DCC (214), bukan seluruh grup LION.
- Semua aktif; TOP di luar 1-60 jadi 0; PIC kosong. Peta id lama -> id: `ImportLegacyMaster::customerIdMap()`.

## Produk (keputusan Ayah, 20 Oktober 2026): impor SESUAI LEGACY

Kode produk legacy dipertahankan apa adanya (mengubah kode mengganggu temuan barang dan stock opname). Kategori = idcut legacy;
induk-turunan = `kodeinduk` legacy -> `parent_id` + `structure_type` sub (63 turunan dari 22 induk); karton/drylog/plastik masuk
`legacy_note`. Tidak diimpor: STYROFOAM BESAR GARUDA, STYROFOAM BESAR, STYROFOAM KECIL, ICE GELL, DELIVERY COST (dipakai sebagai
Biaya Tambahan di invoice; riwayat invoice lama nanti dipetakan ke Biaya Tambahan). Diimpor NONAKTIF dengan kode sementara
TMP-<id>: LUNG, FAT ETC, SODAS BUFFALO, ZZZ X PAHA DEPAN S (kemungkinan sudah punya transaksi; ZZZ tidak digabung ke PAHA DEPAN
karena Ayah belum yakin sama). Hasil yang harus cocok: 153 produk (158 - 5 non-daging), 4 di antaranya nonaktif.
Perbaikan kategori/induk-turunan dari lembar kerja tim kantor (`legacy/lembar-kerja-produk-2026-10-20.xlsx`) menyusul sebagai
tabel koreksi; kode TIDAK diubah.

### Produk: keputusan akhir (Ayah, 20 Oktober 2026)

Produk TIDAK dikoreksi: yang dipakai adalah hasil impor sesuai legacy apa adanya (153 produk, kode, kategori, induk-turunan). Lembar kerja
koreksi, template generate kode, dan padanan AUS-MEAT tidak dipakai (terlalu rumit). Handbook AUS-MEAT hanya panduan; kode tetap milik
sendiri. Bila nanti ada koreksi, dikerjakan langsung dari aplikasi di coba. dan ikut tersalin ke VPS bersama master lainnya.

### BOM: tidak diimpor (keputusan Ayah, 20 Oktober 2026)

Data BOM legacy (417 baris `bom_rawmate`) tidak meyakinkan (basis box/pcs tidak jelas, banyak bahan di luar cakupan), jadi BOM TIDAK
diimpor. Diisi tim QC langsung dari aplikasi (tombol BOM di daftar produk), lewat izin `view/create/update/delete_product_materials`
(plus `view_products` dan akses melihat material). Catatan kemasan legacy (karton/drylog/plastik) sudah ada di catatan tiap produk sebagai
acuan. Langkah `--only=bom` di importer tetap ada tetapi tidak dipakai untuk latihan maupun import final.

### Pelanggan digabung tambahan (Ayah, 20 Oktober 2026, saat meninjau di coba.)

156 (AGUNG BPK) -> 169 (AGUNG SEDAYU BPK), 134 (BAZAR) -> 221 (BAZAAR), 18 (SHERLINDO MAKSIMA SUKSES PT) -> 143 (SHERLINDO); yang dipakai
yang lebih baru diperbarui. Dicatat di `ImportLegacyMaster::CUSTOMER_MERGES` (peta id untuk riwayat). Hasil yang harus cocok: 292 pelanggan.
Catatan: Ayah mengubah nama di coba. (mis. AGUNG (BPK)); hasil akhir master disalin dari coba., bukan impor ulang pelanggan.
Formulir pelanggan: kolom Grup 5/12, Segmen 3/12 (sebelumnya 4/4).

## Salinan hasil akhir coba. -> VPS (`master:export` dan `master:import-snapshot`, 20 Oktober 2026)

Ayah dan tim memperbaiki master di coba. (nama, grup, segmen, pelanggan, BOM dari QC), jadi produksi menerima SALINAN coba., bukan impor ulang.
- `php artisan master:export` (di coba.): menulis `storage/app/master-snapshot-<waktu>.json` (hanya membaca basis data; berisi data pelanggan, JANGAN masuk git).
- `php artisan master:import-snapshot <berkas>` (di VPS): dry-run bawaan (semua dijalankan lalu dibatalkan), `--apply` menulis. id dipertahankan; baris
  yang sama persis dilewati; isi berbeda pada id yang sama = DITOLAK; satu penolakan membatalkan semuanya; checksum dan kolom tabel diperiksa.
- Tabel yang ikut (urut): warehouses, grades, cattle_classes, drivers, vehicles, suppliers, material_units, material_categories, materials,
  product_categories, customer_groups, products, product_materials, customer_segments, customers. TIDAK ikut: pengguna, izin, rekening bank
  (bank_accounts), kategori pengeluaran (expense_categories), transaksi, riwayat. Penulisan tanpa event model, jadi tidak membuat catatan riwayat palsu.
- Syarat di VPS: kode terbaru sudah di-deploy dan dimigrasi (kolom harus sama); tabel master di VPS kosong atau identik (sisa data uji dibersihkan dulu).
- Urutan hari-H (usulan, menunggu aba-aba Ayah): cadangkan basis data VPS; deploy `main`; migrate; bersihkan sisa data uji master; scp snapshot ke VPS;
  `master:import-snapshot` dry-run -> laporan ke Ayah -> `--apply`; periksa jumlah di aplikasi (292 pelanggan, 153 produk, 161 material, dst.).

### Gladi salinan master (20 Oktober 2026, di MySQL kosong lokal; VPS tidak disentuh)

Jalur lengkap diuji di basis data kosong: `migrate --seed` (27 detik), `master:import-snapshot` dry-run 2 detik, `--apply` 3 detik, jalankan ulang = 0 baris
baru (292 pelanggan, 153 produk, 161 material, 367 baris BOM, 63 turunan produk terhubung tanpa yatim). Ditemukan dan diperbaiki: kolom JSON
(`customers.required_documents`) ditulis rapat di coba. tetapi berspasi di MySQL sehingga 14 pelanggan tampak "berbeda"; kini dibandingkan menurut isi.
Perkiraan waktu hari-H: deploy + migrate beberapa menit, salinan master kurang dari satu menit.

## Rencana hari pemindahan (disampaikan Hafizh, 20 Oktober 2026; MENUNGGU konfirmasi Ayah di chat Ruby, JANGAN dieksekusi)

Jadwal menurut Hafizh: malam ini dan besok pagi latihan modul; Rabu #579 (spek customer); Kamis 10:00 pelatihan di VPS; 31 Oktober VPS sudah bersih dari data
latihan; 1 November 00:00 Ayah menutup legacy lalu data dipindah ke VPS dan harus selesai hari itu juga (Ayah sedang piknik, jadi langkahnya harus sudah terlatih).

1. **Maintenance mode selama pindah data; akun pengguna TIDAK dinonaktifkan.** `php artisan down --secret=<acak>` sebagai `swmapp`; Ayah dan Ruby memeriksa
   hasil lewat URL secret; selesai: `php artisan up`. Secret dibuat saat itu juga dan dikirim langsung ke Ayah, TIDAK ditulis di repo atau dokumen. Driver
   maintenance = file (bawaan `config/app.php`); cron `schedule:run` tidak jalan selama down. Alasan: status aktif/nonaktif akun harus dibawa apa adanya.
   Dilatih sekali di LOKAL (bukan di coba. selagi tim bekerja di sana) sebelum hari-H.
2. **Password 1234.** Password yang sudah diganti peserta pelatihan TIDAK direset saat pindah data. Sebelum `artisan up`, laporkan ke Ayah daftar akun aktif
   yang masih memakai 1234; Ayah memutuskan apakah akun itu dinonaktifkan sementara (risiko: orang lain masuk lebih dulu dengan 1234).
3. **Bersih dari data latihan sebelum 31 Oktober.** `migrate:fresh` di VPS TETAP dilarang. Usul: cadangkan database VPS SEBELUM pelatihan Kamis, lalu kembalikan ke
   cadangan itu sebelum data asli masuk. Caranya disepakati dengan IT Lead (MariaDB dipakai bersama aplikasi lain; yang dipulihkan hanya basis data aplikasi ini).

**Urutan hari pemindahan (usulan, belum disetujui):** `artisan down --secret` -> cadangan basis data -> deploy `main` (pull, composer, migrate, optimize) -> master dari
snapshot coba. (`master:import-snapshot`) -> langkah selisih master (lihat bawah) -> modul transaksi sesuai pola -> periksa lewat URL secret -> laporan akun
yang masih 1234 ke Ayah -> `artisan up`.

**Tambahan pola untuk modul di luar master:**
- Dokumen setengah jalan (mis. SO terkirim sebagian): saran Hafizh, bawa SISANYA saja; keputusan tetap di Ayah per modul.
- Hari-H butuh **langkah selisih master**: pelanggan/produk/dst. yang ditambahkan di legacy sesudah snapshot 19 Oktober harus diimpor lebih dulu dengan aturan yang
  sama (nama dirapikan, peta gabungan, segmen menurut grup) SEBELUM dokumen masuk; baris master yang berubah dilaporkan ke Ayah.
- Dari Ruby (modul di luar master): id baru tidak selalu sama dengan id lama, jadi dipakai tabel penghubung lama->baru supaya impor bisa diulang tanpa dobel,
  tabel terjemahan status, dan peta id yang sudah ada (`customerIdMap`, `SUPPLIER_MERGES`).

### Gladi maintenance mode (20 Oktober 2026, lokal; coba. dan VPS tidak disentuh)

`php artisan down --secret=<acak>`: alamat biasa (`/`, `/admin/login`) = HTTP 503; membuka `/<secret>` memberi cookie `laravel_maintenance` (redirect 302 ke `/`) dan
sesudahnya halaman normal terbuka (200) hanya untuk pemegang cookie; `php artisan up` mengembalikan semuanya dan berkas `storage/framework/down` hilang.
Driver `file` (bawaan). Hasil gladi memenuhi rencana hari-H (Ayah dan Ruby masuk lewat secret, orang lain melihat 503).

## Modul pertama di luar master: STOK AWAL DAGING (draft lembar pasangan; menunggu data; dipilih Ayah 20 Oktober 2026 "yang paling mudah")

Sumber `stock` (586 baris di legacy 18 Sep; barcode yang sedang ada di gudang) -> tujuan `beef_stocks` (satu baris per barcode, status IN_STOCK).

| Lama (`stock`) | Baru (`beef_stocks`) | Catatan / pola perbedaan |
|---|---|---|
| kdbarcode | barcode | Panjang 18/19/20 digit bercampur; validasi pemindai baru bisa menolak: putuskan relabel atau longgarkan untuk barcode lama |
| idbarang | product_id | id sama (produk diimpor ber-ID legacy); produk non-daging tidak diimpor -> baris dilaporkan |
| idgrade (J01..P03) | warehouse_id + grade_id | DIPECAH lewat `LegacyGradeMap` (gudang + grade baru) |
| qty | weight | kg |
| pcs | qty_pcs | |
| ph | ph_level | |
| pod, creatime | pack_date / exp_date (?) | arti `pod` belum pasti; tanggal kemas/kedaluwarsa asli ada di `labelboning` (packdate, exp) untuk barcode yang sama |
| origin (digit asal) | origin | cocokkan dengan konstanta origin aplikasi baru |
| price | `barcode_costs` (HPP) | bukan kolom `beef_stocks`; stok awal dinilai dari costing terkunci terakhir (keputusan Owner 11 Okt), `price` legacy hanya pembanding |
| (tidak ada) | batch_no, note | batch dari boning bila ada; selain itu kosong |

Perlu data tambahan dari legacy (hanya baca): `stock` penuh + baris `labelboning`, `relabel`, `trading`, `stockin`, `boning` yang barcode/lotnya ada di `stock`.
Keputusan menunggu Ayah: stok awal diambil dari `stock` legacy atau dari opname fisik (586 barcode lama jadi daftar bantu)?

### Stok daging legacy -> beef_stocks (keputusan Ayah, 20 Oktober 2026)

Bukan modul stok awal: hanya memindahkan DATA barang yang ada di gudang menurut legacy. Perintah `legacy:import-stock --source=<SWM-stok-...sql> [--apply]`
(dry-run bawaan, contoh pasangan lama -> baru, aman diulang, satu penolakan membatalkan semua). Berkas ditarik dengan `bash legacy/tarik/tarik.sh stok`
(Ayah mengizinkan Ruby menarik sendiri, 20 Oktober 2026; HANYA BACA; folder `legacy/tarik/` tidak ikut git).
- Pemasangan: kunci = barcode (dibawa apa adanya, 18-20 digit); product_id = idbarang; gudang + grade lewat `LegacyGradeMap` (J01 -> JGL+CHILL, J02 -> JGL+FROZEN);
  weight = qty; qty_pcs = pcs (kosong -> 0); ph_level = ph; pack_date = pod (tanggal kemas); exp_date = dari tabel sumber bila ada (hampir tidak ada); batch dari tabel sumber.
- Origin: 1 BONING, 2 TRD-LC, 3 R-STCK, 6 R-RTRN (aturan aplikasi `BarcodeHelper::LEGACY_ORIGIN`); **7 (stock-in manual) -> -FOUND** (keputusan Ayah; aturan lama memetakan ke R-TRDG).
- Batch: boning = `boning.batchboning` (mis. BN26083); repack = `repack.norepack` (mis. RPC-26026); GR beef = `grbeef.grnumber`; stock-in dan retur tanpa sumber = kosong.
- `creatime` legacy tidak dipakai (tabel dibangun ulang dari opname 5 Okt); `price` kosong semua (nilai awal lewat `BarcodeCosts::valueOpeningStock()`); tidak ada catatan pergerakan stok.
- Stok berubah tiap hari: untuk hari-H tarik ulang saat legacy ditutup.
