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

### Piutang: tidak diimpor; berikutnya main alur (Ayah, 20 Oktober 2026)

Piutang legacy TIDAK diimpor: fiturnya tidak dipakai di aplikasi lama sehingga datanya tidak akurat. Modul yang sudah dipindah ke coba.: master lengkap + stok daging (589 barcode).
Langkah berikutnya: main ALUR di coba. dengan data impor (Request -> PO -> GR; SO -> Tally (barcode legacy direlabel) -> DO -> Invoice; boning/repack dari stok legacy) untuk
menguji bahwa data hasil pindahan berfungsi. Dokumen terbuka (SO/DO/PO yang masih berjalan saat legacy ditutup) diputuskan Ayah sesudah main alur: diketik ulang atau dipindah.

### Daftar harga: tidak diimpor dari legacy (Ayah, 20 Oktober 2026)

Daftar harga legacy tidak berjalan dengan benar sehingga tidak bisa dipercaya: TIDAK diimpor dan TIDAK ikut salinan master ke VPS (`MasterSnapshot::TABLES` memang tidak memuat
`price_lists`/`price_list_items`/costing). Ayah mengisi daftar harga sendiri dengan caranya. `legacy:import-prices` dan `costing:import-prices` tidak dipakai untuk latihan maupun hari-H
kecuali Ayah meminta. Konsekuensi untuk latihan alur: SO dan Invoice butuh daftar harga grup yang diisi Ayah di coba. lebih dulu; HPP stok butuh costing yang dikunci tim.

### Request material terbuka -> material_requisitions (Ayah, 21 Oktober 2026; langkah 1 dari request -> PO -> GR)

`legacy:import-requests --source=<SWM-belanja-...sql> --fallback-user=<username> [--apply]` (dry-run bawaan, contoh pasangan lama -> baru, aman diulang, nomor dokumen yang sudah ada tidak ditimpa).
- Arti status legacy (dari kode legacy, via Hafizh): Request = dibuat; Waiting = diterima purchasing (TOP terisi); Ordering = disetujui, PO belum dibuat; PO Created; Partial = ada GR; Completed = PO ditutup.
  `is_deleted` bukan 0 = dihapus (127 = MySQL memotong tanggal). Terbuka = tidak dihapus dan Request/Waiting/Ordering (10 request, 17 baris di data 20 Okt).
- Pemasangan: norequest -> document_number (apa adanya; baru memakai MR#yy); idsupplier/idrawmate nomor sama; iduser -> user_id bila akun ada, selain itu akun cadangan + nama pemohon asli di catatan;
  status Request -> Requested, Waiting -> Pending Finance, Ordering -> Pending Finance (di aplikasi baru PO lahir saat finance menyetujui; tidak ada "disetujui menunggu PO");
  top -> terms_of_payment; total = jumlah subtotal baris + taxrp; baris bahan di luar material impor dilewati dan dilaporkan; note "0" dianggap kosong.
- Berikutnya: PO (request induk dari PO terbuka ikut dibawa), lalu GR. PO terbuka: stat 0 (utuh) dan stat 3 dengan sisa; PO sebelum 1 September 2026 dibawa berstatus dibatalkan (keputusan Ayah).

### Keputusan: SELURUH RIWAYAT ikut pindah (Ayah, 21 Oktober 2026)

Legacy akan ditutup; hanya Ayah yang masih bisa membukanya, dan itu pun hanya setahun. Maka Ayah ingin SEMUA riwayat dokumen pindah ke aplikasi baru (bukan hanya dokumen terbuka).
Ini mengubah catatan lama "transaksi TIDAK diimpor" (`.agents/legacy-db.md`). Pengecualian yang sudah diputuskan: piutang (datanya tidak akurat) dan daftar harga (diisi Ayah sendiri) tetap tidak diimpor.
Urutan modul (Ayah): request material-PO-GR; request beef-PO-GR; PO cattle-receive-weighing-carcass-boning; SO-Tally-DO-Invoice; repack; stock opname.
Prinsip impor riwayat (usulan Ruby, menunggu persetujuan fase): (1) FASE A wajib selesai sebelum 1 November: master (selesai), stok daging (selesai), dokumen terbuka;
(2) FASE B riwayat boleh menyusul sesudah legacy ditutup dan datanya beku, per modul, tanpa menahan operasional; (3) dokumen riwayat ditulis lewat DB::table (tanpa event model),
jadi TIDAK membuat pergerakan stok, riwayat aktivitas palsu, atau nomor baru; (4) nomor dokumen legacy dibawa apa adanya; (5) tabel penghubung id lama -> id baru untuk setiap dokumen
supaya impor bisa diulang dan dokumen anak menemukan induknya; (6) status diterjemahkan; yang tidak punya padanan dicatat. Ukuran terbesar di legacy: tallydetail 163 ribu, labelboning 175 ribu,
detailhasil 37 ribu, stocktakedetail 23 ribu, invoicedetail 23 ribu, salesorderdetail 22 ribu baris.

### Fase A/B menjadi SATU jalur latihan dengan cadangan (Ayah dan Ruby, 21 Oktober 2026)

Alasan Ayah: coba. dipakai untuk menemukan ATURAN impor; menjalankannya di VPS cepat (stok 589 baris selesai dalam hitungan detik). Yang menentukan bukan lamanya menjalankan, melainkan berapa modul
yang aturannya sempat dilatih dan diperiksa sebelum 1 November. Maka: semua modul dilatih di coba. menurut urutan Ayah; pada hari-H dijalankan berurutan menurut prioritas (master, stok, dokumen terbuka,
lalu riwayat per modul) selagi aplikasi dalam maintenance mode. Modul yang aturannya belum siap/diperiksa otomatis menjadi "Fase B" (menyusul sesudah legacy beku, selagi legacy masih bisa dibuka Ayah setahun).
Aplikasi boleh dibuka (`artisan up`) begitu bagian kritis (master, stok, dokumen terbuka) selesai dan diperiksa; riwayat yang tersisa boleh jalan sesudahnya karena ditulis tanpa event model dan memakai
nomor dokumen legacy (awalan berbeda dari nomor baru). Tabel besar dimuat bertahap (chunk). Id master tetap sama dengan legacy; yang digabung lewat peta (`SUPPLIER_MERGES`, `customerIdMap()`);
dokumen yang menunjuk pelanggan/supplier yang hilang DILAPORKAN, bukan dibuang diam-diam. Ayah memastikan tidak akan menghapus pelanggan.

## Sales Order (`legacy:import-sales-orders`) — diterapkan di coba. 21 Okt 2026
- Sumber: `salesorder` + `salesorderdetail` (+ `do` hanya untuk membaca status DO). 8.224 SO, 22.083 baris (1 baris STYROFOAM KECIL dilewati: bukan produk impor, dicatat di note SO). Berat 2.570.361 kg, nilai ±Rp 310,5 miliar.
- Pasangan: sonumber→so_number (nomor legacy dipertahankan), idcustomer→customer_id lewat peta penggabungan, deliverydate, po→po_number, alamat→shipping_address, note ("-" = kosong), down_payment 0, pembuat = akun pengganti bila akun legacy tidak ada (coba.: `opik`, bukan saepullrock).
- Status: Delivered→completed, Cancel/Rejected→cancelled (Rejected diberi catatan), Waiting→waiting, On Process→processing, On Delivery→on_delivery, DRAFT→completed. SO "basi" (belum Delivered tetapi semua DO-nya sudah Invoiced) → completed (5 SO).
- Diskon legacy dianggap persen; nilai > 100 (4 baris, tampak rupiah, harga 0) tidak dibawa, dicatat di note baris.
- Tanpa efek model (tugas/notifikasi/stok). Mengisi `legacy_import_map` (sales_order, sales_order_item) untuk Tally/DO/Invoice. Idempoten (rerun: 0 dibuat, 8.224 dilewati).

## Tally (`legacy:import-tallies`) — diterapkan di coba. 21 Okt 2026
- Sumber: `tally` + `tallydetail` (snapshot `tarik.sh tally`): 8.042 tally, 165.617 karton. Dibawa 8.041 tally (1 tally KOSONG pada SO yang punya tally lain dilewati: TS00031/SO 176; aplikasi baru satu tally per SO) dan semua kartonnya (6.279.129 kg). Penghubung ke SO lewat `legacy_import_map` (sales_order).
- Tally: notally→tally_number (kembar 206 diberi -2/-3 menurut idtally), sealnumb→seal_number, stat DO→do (Tally::STATUS_DELIVERED), Rejected→locked, kosong→processing (2), pembuat = akun pengganti (legacy tidak mencatat), created_at = creatime.
- Karton: barcode apa adanya; gudang+grade lewat LegacyGradeMap; origin lewat `ImportLegacyStock::ORIGIN_NAMES` (kosong → digit awal barcode); pH kosong tetap kosong (102.965); exp kosong tetap kosong.
- Aturan temuan: grade KOSONG (1.559 karton, 29 tally awal 2023) → J01 (JGL+CHILL), asumsi; barcode yang sama di dua tally (235) SAH dan dibawa apa adanya (koreksi Ayah: karton dikirim, batal/tolak/retur, balik ke gudang, dikirim lagi; keunikan hanya per tally = tally_id+barcode, bukan global — awalnya saya salah mengira global dan memberi akhiran -2, sudah dibalik); pack_date 0000-00-00 (1) → tanggal scan; batch_no tidak dibawa. Tanpa efek stok. Idempoten.

## Delivery Order + tanda terima (`legacy:import-delivery-orders`) — diterapkan di coba. 21 Okt 2026
- Sumber (`tarik.sh do`): `do` 8.532 + `dodetail` 22.658, `doreceipt` 8.513 + `doreceiptdetail` 22.595. Semua dibawa (berat DO 6.679.106 kg).
- DO: donumber→delivery_order_number (nomor legacy), idso/idtally→sales_order_id/tally_id lewat `legacy_import_map` (496 DO lama tanpa tally = kosong; 1 tally tidak ketemu = kosong), po, deliverydate, sealnumb, note, pembuat = akun pengganti.
- Status: Invoiced→Invoiced (8.507), Approved→Approved (4), Unapproved→Ready (11), Rejected→dihapus-lunak + catatan "Ditolak di legacy" (10), seperti penolakan di aplikasi baru.
- Supir/plat: legacy teks bebas (97 ejaan supir). Dicocokkan ke master `drivers`/`vehicles` (nama tanpa awalan "H.", plat tanpa spasi). Yang tidak cocok dikosongkan dan teks aslinya dicatat di note DO ("Supir legacy: … | Plat legacy: …"). Di coba. master supir hanya 2 nama lengkap (ACEP NURFADILLAH, RESKHA PERMANA) sedangkan legacy memakai panggilan (MPE, RESKA, TAUFIK, SANDI, KOSWARA…): 0 supir cocok, 3.566 plat cocok. Perlu daftar panggilan→nama dari Ayah atau master supir diisi dulu sebelum impor di VPS.
- Tanda terima: receipt_number = donumber DO-nya (kembar 1 diberi -2), delivery_order_id lewat peta, sales_order_id dari idso/DO, total_box=xbox, total_weight=xweight, status apa adanya; 1 tanda terima tanpa DO di legacy.
- Total DO (xbox/xweight) dan berat terima (rweight) tidak punya kolom di aplikasi baru. Tanpa efek model (status SO/tally tidak berubah, tidak ada stok).
- Koreksi tally (21 Okt): tally ber-DO berstatus `do` (Tally::STATUS_DELIVERED), bukan `locked`; impor tally sudah diperbaiki, data coba. perlu satu kali UPDATE (tally locked kecuali 9 yang Rejected → do).
- Keputusan Ayah (21 Okt): supir/kendaraan di master = hanya milik perusahaan; supir lain di legacy adalah "supir tembak" sementara, jadi TIDAK dibuat master. Pilihan B: DO tetap dengan teks supir/plat di note (tanpa tautan master); yang kebetulan cocok tertaut otomatis.

## Invoice (`legacy:import-invoices`) — diterapkan di coba. 21 Okt 2026
- Sumber (`tarik.sh inv`; tabel `piutang` TIDAK ada di produksi, jadi tidak ditarik): `invoice` 8.508 + `invoicedetail` 22.730. Dibawa semua: 22.614 item (+116 baris non-daging), 6.687.157 kg, subtotal Rp 341,65 miliar.
- Pasangan: noinvoice→invoice_number (nomor legacy), iddoreceipt→delivery_order_receipt_id (peta), SO dari tanda terima→DO; invoice_date, duedate, top→term_of_payment, pocustomer→po_number, donumber→delivery_order_number, tgltf→invoice_exchange_date, xweight→total_weight, xdiscount→total_discount, downpayment→down_payment, tax 0; pembuat = akun pengganti; satu baris `receivables` per invoice (seperti CreateInvoice).
- Item: idbarang→product_id, weight, price, discount→discount_percent, discountrp→discount_rp, amount; box 0 (legacy tidak mencatat); subtotal = jumlah amount item.
- Barang NON-DAGING di baris invoice (STYROFOAM, ICE GELL, DELIVERY COST; produknya tidak diimpor) → `invoice_additional_charges` (116 baris, 71 invoice); charge legacy (>0) → baris "Biaya tambahan (legacy)". invoices.charge = jumlah semuanya. (Awalnya baris ini terbuang dan tagihan berubah; sudah diperbaiki.)
- Invoice dari DO lama TANPA SO (494, DO 2023 tanpa SO di legacy): dibuat SO pengganti `SO-LGC-<nomor DO>` (completed, isi dari baris invoice) karena sales_order_id wajib.
- Status tukar faktur: "Sudah TF"→Sudah TF, "Belum TF"→Belum TF, "-"→Belum Dibayar. PEMBAYARAN: legacy tidak pernah mencatat pelunasan, jadi opsi `--payment` wajib. KEPUTUSAN AYAH (21 Okt): `settled` — semua riwayat Lunas (paid_amount = tagihan, saldo 0, tanpa catatan pembayaran); akunting mencari yang belum lunas mulai 1 November.
- 22 invoice jumlah itemnya berbeda dari xamount legacy: memakai jumlah item, selisih dicatat di note. 12 tanpa jatuh tempo.
- Pola 4 langkah SO→Tally→DO→Invoice selesai di coba.; urutan wajib: SO, Tally, DO, Invoice (penghubung `legacy_import_map`: sales_order, tally, delivery_order, delivery_order_receipt, invoice). Cabang berikutnya: cek dampak modul spek customer (#579) pada impor SO.

## Repack (`legacy:import-repacks`) — diterapkan di coba. 21 Okt 2026
- Sumber (`tarik.sh repack`): `repack` 1.229, `detailbahan` 29.743, `detailhasil` 34.079 (hanya yang tidak dihapus). Semua dibawa: bahan 532.077 kg, hasil 534.007 kg.
- Pasangan: norepack→doc_no (kembar 2 diberi -2), tglrepack, kunci (semua LOCKED), pembuat = akun pengganti, created_at = dibuat. Bahan→`repack_materials` (gudang+grade lewat LegacyGradeMap, origin lewat ORIGIN_NAMES, status IN_STOCK); hasil→`repack_results`.
- Aturan temuan: grade kosong (25) → J01; pod kosong/0000 (174) → tanggal scan; origin 0 (3) → digit awal barcode; catatan baris hasil (45) digabung ke note repack; batch_no hasil tidak dibawa; 8 repack tanpa bahan dan 6 tanpa hasil tetap dibawa; barcode yang sama boleh jadi bahan di dua repack (82) dan rantai hasil→bahan.
- Tanpa efek: stok, pergerakan stok, valuasi/HPP, kerugian finansial.
- Keputusan Ayah (21 Okt): SEMUA riwayat repack ditutup (LOCKED, kunci 1), walau di legacy hanya 35 dari 1.229 yang dikunci. Importer menulis LOCKED; di coba. satu UPDATE sudah menyamakan 1.194 yang tadinya OPEN.

## Stock opname — DILEWATI (keputusan Ayah, 21 Okt 2026)
- Tidak diimpor. Hasil opname lama sudah tercermin di stok yang dipindahkan; dokumennya hanya arsip dan tidak ada modul yang bergantung padanya. Legacy tetap bisa dibuka Ayah sebagai arsip.
- Daftar modul riwayat yang dipindahkan: request/PO/GR material, request/PO/GR beef, PO sapi–receive–timbang–karkas–boning, SO, Tally, DO + tanda terima, Invoice, Repack.

## Gladi resik penuh di coba. — 22 Okt 2026 (reset -> impor semua dari awal)
Urutan yang dijalankan (skrip lokal `legacy/tarik/gladi-run.sh <awal> <akhir>`, dikirim ke `~/gladi/run.sh` di coba.; tiap langkah dry-run lalu `--apply`, berhenti bila ada penolakan):
0. backup DB (mysqldump, nilai `.env` dibersihkan dari `\r`) + `master:export` (menyimpan master yang sudah Ayah lengkapi) -> `app:reset-training-data --apply` -> `master:import-snapshot --apply`.
1 stok beef, 2 stok material, 3 request material, 4 request beef, 5 PO material, 6 PO beef, 7 GR material, 8 GR beef, 9 PO sapi, 10 receive+timbang, 11 karkas, 12 boning (+label 2023-2026),
13 SO, 14 Tally, 15 DO+tanda terima, 16 Invoice (`--payment=settled`), 17 Repack, 18 Retur penjualan.
Waktu mesin (tanpa waktu manusia): tarik 15 snapshot 70 dtk, kirim ke coba. 20 dtk, backup 2 dtk, reset 3 dtk, master 2 dtk, impor 1-18 sekitar 10 menit. Terlama: boning 94+94 dtk, tally 84+91 dtk, repack 32+32 dtk, SO 14 dtk.
Hasil: 0 DITOLAK di semua langkah setelah satu perbaikan. TEMUAN gladi: data legacy bergerak antara hari; tally baru berstatus `Approved` (disetujui, belum jadi DO) belum dikenal -> dipetakan ke `locked` (sudah di main). Pelajaran hari-H: status/nilai BARU bisa muncul kapan saja; dry-run berhenti di penolakan pertama, jadi sisakan waktu untuk perbaikan kecil.
Total akhir coba.: stok beef 356, stok material 66, request 357+190, PO 353+192, GR 140+179, PO sapi 109 (104 + 5 pengganti), receive 280 (104 + 176 pengganti), karkas 320, boning 445 (169.393 label), SO 8.725 (8.231 + 494 pengganti dari invoice lama), tally 8.050 (165.810 karton), DO 8.540, tanda terima 8.521, invoice 8.518 (semua Lunas), repack 1.231 (semua LOCKED), retur 34.
Spek customer yang Ayah isi di coba. tidak ikut `master:export` (tabel `customer_product_specs` tidak ada di `MasterSnapshot::TABLES`) dan terhapus oleh reset; bila perlu dibawa ke VPS, tabel itu harus ditambahkan ke snapshot.

## Keputusan Ayah 22 Okt 2026: tukar faktur, hutang, dan piutang riwayat
- **Tukar faktur = pengecualian.** Invoice yang BELUM tukar faktur di legacy (status "Belum TF") tidak dianggap lunas: diimpor Belum TF, belum dibayar (saldo = tagihan), jatuh tempo kosong sampai ditukar (`ImportLegacyInvoices`). Di legacy hanya 1 invoice seperti itu. Invoice Lunas tidak lagi dihitung "belum ditukar" di dashboard, badge TTF, dan filter.
- **Piutang dan hutang riwayat dianggap LUNAS semua.** Invoice sudah Lunas (`--payment=settled`). Hutang: `legacy:import-payables --before=<tanggal go-live> --fallback-user=<akun> [--apply]` membentuk baris hutang LUNAS (status paid, dibayar penuh, saldo 0) untuk GR material dan GR beef sebelum tanggal itu (nilai = jumlah item + PPN supplier PKP, jatuh tempo = tanggal terima + TOP). Penerimaan sapi tidak dibuatkan hutang riwayat (nilainya bergantung harga kelas sapi dan pembayaran PO sapi). Jalankan SESUDAH impor GR.
- **Satu cara untuk akunting mengubah statusnya: aksi "Buka kembali"** (daftar Invoice dan daftar Hutang) pada dokumen yang Lunas: akunting mengisi SISA yang belum dibayar dan alasan; yang sudah dibayar menjadi `paid_amount`, sisanya kembali menjadi tagihan, status kembali ke Belum TF/Sudah TF/Belum Dibayar (invoice) atau partial/unpaid (hutang), tercatat di riwayat aktivitas. Izin terpisah, tidak diberikan otomatis: `reopen_settled_invoices` dan `reopen_settled_payables` (label "Buka kembali yang dianggap lunas"); Ayah mencentang untuk peran akunting. Penjagaan ada di model (`Invoice::reopenSettled`, `Payable::reopenSettled`), bukan hanya di tombol. Setelah dibuka, pelunasan memakai alur normal (Terima Pembayaran).
- Urutan hari-H bertambah satu langkah: setelah GR material dan GR beef, jalankan `legacy:import-payables`.

## Rekonsiliasi otomatis (`legacy:reconcile`) — 22 Okt 2026
- `php artisan legacy:reconcile --dir=<folder snapshot> [--only=stok,boning,so,tally,do,invoice,repack,retur]`: HANYA BACA. Membandingkan angka snapshot legacy dengan aplikasi baru per modul: jumlah dokumen, jumlah baris, total berat (kg), total rupiah (invoice). Sisi aplikasi baru dibatasi pada dokumen hasil impor lewat `legacy_import_map`; sisi legacy memakai aturan yang sama dengan importer (baris tidak dihapus, produk yang ada), jadi selisih yang muncul adalah selisih sungguhan. Keluar kode 1 bila ada selisih; dasar keputusan "boleh buka VPS atau belum" pada 2 Nov.
- Hasil di coba. setelah gladi resik: 29 pemeriksaan, SEMUA COCOK (stok 356 barcode/4.452 kg, boning 445/169.393 label, SO 8.231, tally 8.050/165.810 karton, DO 8.540 + tanda terima 8.521, invoice 8.518 senilai Rp 341,94 miliar, repack 1.231, retur 34). Rekonsiliasi pertama sempat menunjukkan selisih boning 11 karena snapshot memuat boning yang dihapus di legacy; yang keliru rekonsiliasinya (sudah diperbaiki), bukan datanya.
- Belum dicakup: modul pembelian (request/PO/GR), sapi (PO, receive, karkas), stok material, hutang. Tambahkan bila diperlukan.
- Urutan hari-H: ... impor semua modul -> `legacy:import-payables` -> `legacy:reconcile --dir=...` -> baca HASIL -> baru VPS dibuka.

## Keputusan Ayah 22 Okt 2026: spek customer tidak dibawa ke VPS
Tabel `customer_product_specs` TIDAK ditambahkan ke `MasterSnapshot::TABLES`. Pengisian spek baru dimulai 2 November setelah go-live, jadi VPS mulai tanpa spek. Customer (marketing) dan BOM (QC) selesai dikerjakan 20 Okt dan akun mereka sudah dinonaktifkan; keduanya ikut salinan master.

## Karkas hasil impor tidak masuk Draft Boning (keputusan Ayah, 22 Okt 2026)
Boning legacy tidak punya hubungan ke karkas, jadi `legacy:import-carcasses` menandai semua karkas yang diimpor `carcasses.legacy_boned = 1` (migrasi `2026_10_22_200000` mengisinya juga untuk yang sudah ada di `legacy_import_map`). Karkas bertanda itu tidak muncul di Draft Boning maupun pilihan karkas di form boning. Untuk kejadian luar biasa (mis. sapi dipotong menjelang go-live, boning-nya sesudahnya): aksi **"Buka untuk boning"** di daftar Karkas (izin `reopen_carcass_for_boning`, alasan wajib, tercatat di riwayat aktivitas). Karkas yang baru diinput sesudah go-live muncul di Draft Boning seperti biasa. Boning kini dibuat dari **Draft Boning** (pilih beberapa karkas, aksi "Create Boning"); tombol Create di daftar boning diganti tombol Draft Boning.

## KOREKSI stok material dan temuan nilai PO material — 23 Okt 2026
- **Stok material salah sumber, sudah diperbaiki.** Versi pertama `legacy:import-material-stock` memakai tabel `stockraw` (buku lain, angka pcs) dikurangi `raw_usage`; hasilnya DRY LOG 50.435 padahal layar legacy 17 Box, PADMAN 180.010 padahal 0; 88 dari 227 material meleset. Rumus layar legacy (`stockraw/index.php`) = jumlah penerimaan `grrawdetail` (GR dan baris tidak dihapus) DIKURANGI `raw_stock_out_detail` (dokumen tidak dihapus), dalam satuan beli (Box/Pcs/Ikat). Terverifikasi persis di 6 material contoh Ayah (DRY LOG 17, ICE GELL 28, KANEBO 0, LAKBAN MERAH 71, LAKBAN PUTIH 35, STRAPING BAND 6). Importer sekarang butuh dua snapshot: `--source` (stokmaterial) dan `--grs` (belanja). Catatan Ayah: dry log dihitung BOX; pcs hanya untuk waste (Content per Unit). Coba. masih berisi stok versi lama sampai dikosongkan dan diimpor ulang (`material_stocks` dikosongkan lalu `legacy:import-material-stock --grs=...`); hari-H memakai reset sehingga tidak terdampak.
- **Nilai PO material yang janggal berasal dari DATA LEGACY, bukan dari kode.** Kode aplikasi baru menyalin harga/qty/subtotal apa adanya (diuji: harga yang diketik 80.000 tersimpan 80000). Di legacy, harga PO sering per PCS sementara qty per BOX, atau qty per pcs untuk bahan yang sama: DRY LOG qty 20 x Rp 710 = Rp 14.200 (harga per pcs, qty box) padahal baris lain qty 50.000 x Rp 710 (pcs); PADMAN qty 10 x Rp 975 = Rp 9.750; SARUNG TANGAN KARET qty 8 x Rp 80 = Rp 640 (satu-satunya baris berharga untuk bahan itu; tampaknya harga dalam ribuan). Dari 621 baris PO legacy berharga, 44 bernilai < Rp 100.000 (daftar: `legacy/analisis/po-material-nilai-janggal.csv`, tidak ikut git); 375 baris lain berharga 0. Tidak ada perbaikan otomatis (tebakan satuan berisiko); riwayat hutangnya sudah dicatat lunas. Opsi: dibiarkan sebagai riwayat, atau Purchasing mengoreksi 44 baris itu lewat daftar.
- **Request material, review Purchasing:** harga wajib > 0 per baris sebelum "Approve (Send to Finance)"; tanpa item juga ditolak (sama dengan request beef).
- **Beef Stock:** filter dipindah ke ikon corong (Dropdown); pengaman tanggal tetap lewat kalimat posisi di atas tabel dan lencana filter.

## KOREKSI total PO (impor) — 23 Okt 2026, sesudah pertanyaan Ayah "hitung ulang saja qty x harga"
- Subtotal baris PO legacy SUDAH persis qty x harga (0 dari 996 baris meleset), dan aplikasi baru menyalinnya apa adanya. Yang salah ada di TOTAL header: importer lama memakai kolom `taxrp` legacy, yang salah hitung (PPN 11% dikenakan lagi di atas total yang sudah ber-PPN: 12,21%). Akibatnya total PO hasil impor lebih besar dari total legacy (selisih seluruhnya Rp 45,6 juta di 119 PO) dan baris "pajak" di ringkasan PO (total - jumlah baris) ikut salah.
- Aturan sekarang (`ImportLegacyPos`): total = jumlah (qty x harga) baris yang dibawa + PPN 11% bila PO itu ber-PPN. PPN dibaca dari `xamount` legacy: = jumlah baris x 1,11 -> ber-PPN (116 PO); = jumlah baris -> tanpa PPN (144 PO); selain itu (4 PO yang barisnya diubah sesudah terbit) mengikuti kolom `tax` legacy. `taxrp` tidak dipakai. 89 PO tidak punya baris di snapshot (tanpa total). Berlaku juga untuk PO beef. Coba. masih berisi total lama sampai impor ulang; hari-H memakai reset.
- Pemilihan satuan (pcs lawan box) di baris PO legacy tetap kesalahan input Purchasing legacy dan tidak dikoreksi otomatis: 'Content per Unit' masih 1 untuk 161 material, jadi faktor konversinya belum ada.

## Boning impor tidak ikut Draft Costing — 23 Okt 2026 (temuan Ayah nomor 6)
Draft Costing memuat semua boning yang belum punya costing tersimpan, jadi ratusan boning impor akan tampil sebagai "menunggu costing". Kolom `bonings.legacy_imported` (migrasi `2026_10_23_200000`, diisi true untuk boning di `legacy_import_map` dan oleh `legacy:import-bonings`) menyingkirkannya dari `Costing::boningsWaiting()`. Kejadian luar biasa (mis. boning 1 November yang memang perlu dicosting): aksi **"Buka untuk costing"** di daftar Boning (izin `reopen_boning_for_costing`, alasan wajib, tercatat di riwayat aktivitas). Izin baru ini perlu dicentang Ayah di VPS.

## Bahan penolong — 23 Okt 2026 (temuan Ayah nomor 2)
Kolom `materials.is_auxiliary` (migrasi `2026_10_23_300000`; diisi true untuk material yang sudah dipakai di BOM) dan toggle "Bahan Penolong" di master Material. Dropdown material di Bill of Material dan Material Waste memakai `MasterSelect::auxiliaryMaterial()` sehingga hanya bahan penolong yang tampil. Sisa material perlu ditandai QC/Purchasing di master Material (di VPS maupun coba, sebelum BOM diisi).

## Keputusan Ayah 23 Okt 2026 (malam) — jalur Request/Expenses, bahan penolong, pricelist
- **VPS dinaikkan ke main `32d31694`** (backup `~/backup/app-20261023-0431-pra-deploy.sql.gz`). Izin yang harus dicentang Ayah di VPS: `reopen_settled_invoices`, `reopen_settled_payables`, `reopen_carcass_for_boning`, `reopen_boning_for_costing`.
- **Data bawaan 2 Nov selain data legacy:** user dan hak akses (`access:export`), BOM, dan ceklis bahan penolong (`materials.is_auxiliary`, ikut `master:export` karena seluruh kolom tabel `materials` disalin). Syarat: ceklis di coba harus sudah lengkap sebelum ekspor.
- **Request vs Expenses:** file `RAW MATERIAL - dipilah.xlsx` (Hafizh, 226 item: REQUEST 151, OPERASIONAL 37, ABU-ABU 19, ASET 9, HAPUS 10). Flag "Tampil di stok" TIDAK bisa jadi pembeda (86 dari 151 item REQUEST flag-nya mati). Batas = isi master Material: hanya item REQUEST yang aktif; sisanya dinonaktifkan (bukan dihapus, PO lama menunjuknya). ATK dan alat sablon (ABU-ABU) ke Expenses. Aturan user: "ada di pilihan Request -> Request; tidak ada -> Expenses". Barang baru default Expenses; yang menentukan jalur dan menolak pengajuan yang keliru adalah PURCHASING (bukan QC). Belum dikerjakan: nonaktifkan item di master coba dari file itu; periksa modul Expenses (nama bebas, peringatan nama mirip) dan aksi reject di review Request. Ayah akan mencoba modul Expenses dulu.
- **Pricelist otomatis dari riwayat Sales Order** (bukan dari costing), keputusan Ayah: per GRUP customer, satu grup satu harga per produk walau customer di dalamnya beda-beda. Usulan aturan: harga paling sering di SO grup itu dalam 3 bulan terakhir; kosong -> mundur ke SO terbaru, lalu sebelumnya; tak pernah dibeli -> grup acuan umum atau kosong. Harga yang ditimpa manual disalin lewat salinan master; yang otomatis dihitung ulang di VPS setelah impor SO. Dikerjakan TANGGAL 1 (harga masih bisa berubah); perintah hitungnya disiapkan dan diuji di coba sebelum itu, lalu jadi satu langkah di urutan hari-H setelah impor SO.
- Pengecekan data (23 Okt, coba.): `sales_order_items` 23.447 baris, 21.750 berharga > 0 (93%); kolom `price`, `discount`, `spec`. Tabel pricelist sudah per grup: `price_lists(customer_group_id)` dan `price_list_items(price_list_id, product_id, price)`, saat ini 4 baris isi. 1.697 baris SO tanpa harga (diperiksa dulu: bukan produk berbayar atau memang gratis).
- Target 1 Nov: efisien, lancar, benar. Rencana: skrip hari-H satu urutan (tambah hutang riwayat, penandaan bahan penolong, pricelist), gladi ulang penuh 1-2 hari sebelumnya, `legacy:reconcile` diperluas ke pembelian/material/hutang.

## Price list otomatis dari riwayat SO (24 Oktober 2026)

Perintah `php artisan pricelist:build-from-sales` (dry-run bawaan, `--apply` menulis, `--overwrite` menimpa harga berbeda, `--group=` satu grup). Harga produk di sebuah grup = harga baris SO terbaru (tanggal kirim, lalu nomor SO) di antara semua customer grup itu. Harga 0 dilewati kecuali grup GRATISAN; SO batal/terhapus tidak dihitung; produk tanpa riwayat berharga dibiarkan kosong (isi manual). Harga yang sudah ada di price list (mis. dari `legacy:import-prices`) tidak ditimpa tanpa `--overwrite`. Dijalankan hari-H SESUDAH import SO, dan dicoba di coba lebih dulu.

## Kategori Expense awal (26 Oktober 2026)

`php artisan expenses:seed-categories` (dry-run bawaan, `--apply` menulis, aman diulang): 18 kategori disusun dari contoh petty cash Februari 2026 dari finance (196 baris; semuanya bisa dipetakan) dan item non-REQUEST di file pemilahan material (ATK, alat sablon, operasional, aset). Kategori Expense BUKAN bagian snapshot master, jadi perintah ini dijalankan di tiap lingkungan, termasuk hari-H. Kategori lama (ATK, AIR MINUM, MATERAI, OBAT, ONGKIR DRIVER, PARKIR) tidak disentuh; nonaktifkan lewat layar bila sudah tidak dipakai. Contoh Excel hanya bahan membuat kategori, tidak diimpor sebagai data.

**Lanjutan 26 Oktober 2026 (malam):** (1) `php artisan materials:deactivate-non-request` (dry-run bawaan, `--apply`) menonaktifkan 75 item non-REQUEST (OPERASIONAL 56, ASET 9, HAPUS 10) menurut file pemilahan Hafizh; nama tertanam di perintah. Jalankan SEKALI di coba lalu ikut ke VPS lewat `master:export` (kolom `is_active`). (2) `expenses:seed-categories --apply` kini juga menonaktifkan 6 kategori lama (ATK, AIR MINUM, MATERAI, OBAT, ONGKIR DRIVER, PARKIR). (3) Plastik shortrib/oxtail dan karung selama ini dibeli mendadak di pasar tanpa Request; tetap kategori Expense "Perlengkapan Produksi". (4) Hari-H: kategori Expense harus sudah terisi oleh kita (`expenses:seed-categories --apply`) -- masukkan ke runbook.
