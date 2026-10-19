# Buku keputusan import data legacy (latihan di coba., dipakai untuk import final ke VPS)

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
