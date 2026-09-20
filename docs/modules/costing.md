# Modul Costing (Mesin HPP)

Ditulis 20 September 2026, sesudah issue #480 (4 langkah, PR #481-#484) --
modul baru. Riset lengkap dan seluruh keputusan Owner ada di `.agents/hpp.md`
(797+ baris) -- dokumen ini hanya ringkasan yang bisa dibaca sekali jalan;
kalau ada yang tampak bertentangan, `hpp.md` yang benar.

## 1. Prinsip: HPP per lot boning, metode Relative Sales Value

Satu sapi menghasilkan puluhan produk sekaligus dari satu biaya beli yang
sama. Tidak ada cara "benar" tunggal untuk membagi biaya itu ke tiap
potongan -- ini masalah *joint product costing* yang klasik, dan metode
yang dipakai di sini adalah alokasi menurut **nilai jual relatif**:

```
biaya_beli        = SUM per kelas sapi (berat_terima_kelas x harga_kelas)
net_i             = gross_i x (1 - trading_terms grup acuan produk i)
total_nilai_jual  = SUM (net_i x kg_i)
k                 = biaya_beli / total_nilai_jual
HPP_i / kg        = net_i x k
margin%           = 1 - k                              -- SAMA untuk SETIAP produk
laba_lot          = total_nilai_jual - biaya_beli - (overhead_per_kg x total_kg)
```

**Tujuannya menilai PERSEDIAAN** (nilai stok di gudang, harga pokok saat
barang keluar) -- keterangan eksplisit Owner (`hpp.md` §11.6). Untuk
keperluan itu metode ini sah dan memadai apa adanya. Untuk pertanyaan
LAIN -- "produk mana yang paling menguntungkan", "harga jual produk ini
kemurahan atau tidak" -- metode ini **tidak bisa menjawab**, bukan karena
implementasinya kurang, melainkan karena rumusnya sendiri (§7 di bawah).

## 2. Struktur data

- **`costings`**: `costing_number` (`HPP#{yy}0001`, unik), `costing_date`,
  `boning_id` (unik -- satu boning satu costing), `purchase_cost`,
  `total_sales_value`, `ratio_k` (6 desimal), `overhead_per_kg` (per
  costing, BUKAN tetapan aplikasi -- bawaannya nilai costing terakhir,
  `Costing::defaultOverheadPerKg()`), `total_kg`, `profit`, `status`
  (`Draft`/`Locked`), `note`, `created_by`, `locked_by`, `locked_at`.
  SoftDeletes, LogsActivity.
- **`costing_items`**: satu baris per produk yang MUNCUL di boning ini
  (bukan seluruh katalog produk seperti sheet legacy) -- `product_id`,
  `weight_kg`, `reference_group_id` (nullable = harga umum),
  `gross_price`, `trading_terms_percent`, `net_price`, `sales_value`,
  `hpp_per_kg`, `flag` (`NO_REFERENCE`/`NO_PRICE`/null).
- **`costing_cattle`**: biaya beli PER KELAS SAPI, tidak pernah satu harga
  dikali berat total (`hpp.md` §6) -- `cattle_class_id`, `head_count`,
  `received_weight`, `price_per_kg`, `amount`.
- **`customer_groups.trading_terms_percent`** / `is_costing_reference` /
  `is_general_price_reference`: potongan gross->net hanya berlaku untuk
  grup yang ditandai `is_costing_reference` (praktiknya: hanya LION 6% dan
  HYPERMART 16,45%, `hpp.md` §9) -- menandai grup lain akan menggeser HPP
  seluruh produk lain lewat `k` yang dibagi bersama (§7 di bawah), jadi
  ditegakkan di kode (`CustomerGroup`/`Product::booted()`), bukan
  diserahkan pada kebiasaan. **Tepat satu** grup boleh
  `is_general_price_reference = true` -- itulah "harga umum".
- **`products.costing_customer_group_id`**: grup acuan harga produk ini
  untuk costing. **Kosong = harga umum** -- keadaan paling BANYAK, bukan
  pengecualian (`hpp.md` §10).

Seluruh angka di `costing_items`/`costing_cattle` adalah **SNAPSHOT** --
harga dikunci saat costing dibuat/dihitung ulang, tidak pernah menunjuk
balik ke `price_lists` yang bisa berubah bulan depan.

## 3. Sumber angka, replikasi persis `CattleWeighing::calculateAndSaveFinancialLoss()`

- **Berat**: SELALU `cattle_receiving_items.initial_weight` (berat surat
  jalan/terima), tidak pernah berat timbang ulang -- perusahaan membayar
  pemasok sesuai yang dikirim/diterima, bukan hasil timbang ulang
  (`hpp.md` §6). Cakupannya CarcassItem milik `Boning` ini secara spesifik
  (lewat `BoningCarcass` -> `Carcass` -> `CarcassItem`), bukan seluruh
  `CattleReceiving`-nya -- satu PO bisa menaungi lebih banyak ekor
  daripada yang benar-benar dipotong untuk lot ini.
- **Harga per kelas**: 3 lapis fallback -- (1) `PurchaseCattleItem` milik
  PO lot ini sendiri, (2) `PurchaseCattleItem` terakhir untuk kelas+
  supplier yang sama, (3) rata-rata harga kelas di PO ini. Harga TIDAK
  PERNAH diambil dari harga terakhir yang dipakai atau dirata-ratakan
  lintas kelas -- kelas sapi berbeda bisa punya harga berbeda dalam SATU
  PO yang sama (`hpp.md` §6, contoh `CPO-260112`: HEIFER 61.700, STEER
  62.000).

## 4. Siklus Draft -> Locked

- **Draft**: `overhead_per_kg` dan `costing_date` bisa diedit. Tombol
  "Recalculate" menjalankan ulang `CostingCalculator` dengan overhead dari
  form dan mengganti seluruh `costing_items`/`costing_cattle` (snapshot
  lama dibuang, bukan ditambah).
- **Lock** (izin `lock_costings`, terpisah dari `edit_costings`) menolak
  kalau masih ada produk berflag `NO_PRICE` -- costing dengan produk tanpa
  harga sama sekali tidak boleh jadi acuan nilai persediaan. Sesudah
  Locked, TIDAK ADA field yang bisa berubah lagi kecuali `status`/
  `locked_by`/`locked_at` (ditegakkan model, bukan cuma tampilan).
- **Unlock** (izin `lock_costings` juga) mengembalikan ke Draft.
- **Delete** hanya untuk Draft.

## 5. Dua tanda transparansi: `NO_REFERENCE` dan `NO_PRICE`

Keputusan Owner (`hpp.md` §10, §15.5): produk yang SEHARUSNYA punya grup
acuan tapi belum diisi **tetap dihitung**, bukan ditolak maupun diam-diam
jatuh ke harga umum tanpa jejak -- pekerjaan tidak berhenti, tapi
asumsinya kelihatan di layar DAN di cetakan.

- **`NO_REFERENCE`**: `costing_customer_group_id` kosong -> jatuh ke harga
  umum (`CustomerGroup::generalPriceReference()`). Ini sebenarnya keadaan
  NORMAL (kosong = harga umum), tapi tetap diberi flag supaya siapa yang
  membaca costing tahu produk ini dinilai memakai harga umum, bukan
  menebak dari kolom yang kosong.
- **`NO_PRICE`**: gross price tidak ditemukan sama sekali di price list
  manapun yang relevan (baik grup acuannya maupun harga umum). Baris ini
  `hpp_per_kg = 0` dan MENOLAK `lock()` sampai harganya diisi.

## 6. Layar

- **`CostingResource`** (grup PRODUCTION, dekat Boning): tidak ada tombol
  Create polos -- satu-satunya jalan lahir lewat aksi "Create Costing" di
  `ListCostings` (pola sama dengan "Tarik Plan" di Sales Return), memilih
  `Boning` yang sudah `kunci` dan belum punya costing.
- **Edit** (Draft saja, dialihkan ke View kalau sudah Locked): form
  snapshot header + Section "Summary" (Placeholder purchase_cost/
  total_sales_value/ratio_k/margin%/total_kg/profit), tombol Recalculate
  dan Lock.
- **View**: sama, plus tombol Unlock (untuk yang sudah Locked).
- Dua **RelationManager** read-only: "Products" (dengan badge flag) dan
  "Cattle Purchase Cost".
- Filter status, filter tanggal senyap (default bulan berjalan, pola
  `CashBookResource`).

## 7. Cetak dan ekspor

- **`print.costing`** (izin `view_costings`): blok biaya beli sapi per
  kelas, tabel alokasi nilai jual per produk (dengan catatan
  `NO_REFERENCE`/`NO_PRICE` tercetak jelas per baris), ringkasan bawah
  (biaya beli, total nilai jual, rasio k, margin%, overhead, laba).
- Ekspor **Excel** dan **PDF** untuk daftar costing di `ListCostings`,
  mengikuti filter tabel yang aktif (pola sama dengan `ExpenseResource`).

## 8. Rendemen karkas ikut berubah (langkah 3)

`Carcass::yieldPercent()` pembaginya pindah dari berat TIMBANG ULANG ke
**berat terima** (`Carcass::receivedWeight()`, baru) -- keputusan Owner
17 September 2026 (`hpp.md` §15.1), supaya konsisten dengan dasar biaya
beli HPP dan dengan laporan carcass legacy. `liveWeight()` (timbang ulang)
tetap ada untuk urusan susut perjalanan yang memang pertanyaan berbeda.

Konsekuensinya ditegakkan transparan sesuai instruksi Owner ("putuskan di
PR-nya, jangan diam-diam"): aturan lama "satu ekor belum ditimbang ulang
-> rendemen tampil `-`" (`Carcass::hasUnweighedCattle()`) **dihapus** --
alasannya hilang begitu berat terima (selalu ada sejak sapi datang) jadi
pembagi. Sekalian ditemukan dan diperbaiki bug terkait: kolom cetakan
Carcass berlabel "Receive Wt" sebelumnya diam-diam mengisi berat timbang
ulang, bukan berat terima seperti labelnya sendiri.

## 9. Batasan metode yang TIDAK diperbaiki kode (bukan bug)

Dicatat supaya tidak ditemukan ulang dan dikira cacat implementasi (lihat
`hpp.md` §11 untuk penjelasan lengkap tiap satunya):

- **Margin% selalu sama untuk SETIAP produk** (`1 - k`) -- Topside, tulang,
  dan Oxtail sama persis, karena rumusnya memang menurunkan margin dari
  satu angka rasio yang sama. Costing ini tidak bisa menjawab "produk mana
  yang paling menguntungkan".
- **HPP mengikuti harga jual**, bukan biaya -- menaikkan harga jual sebuah
  produk menaikkan HPP-nya sekitar segitu juga, marginnya tetap sama.
  Tidak bisa dipakai mendeteksi harga yang kemurahan.
- **Satu produk yang dinilai memakai grup di luar LION/HYPERMART/harga
  umum akan menggeser HPP SELURUH produk lain** lewat `k` yang dibagi
  bersama -- inilah kenapa batasan grup acuan ditegakkan di kode
  (`Product::booted()`), bukan diserahkan pada kebiasaan operator.
- **Susut (kirim maupun timbang sapi) SENGAJA tidak dihitung dua kali.**
  Biaya beli dihitung dari berat surat jalan, jadi kilogram yang menyusut
  sudah terbenam di dalam HPP produk. `financial_losses` untuk susut ini
  SENGAJA tetap Rp 0 selamanya -- mengisinya dengan `quantity x HPP` akan
  menghitung kerugian yang sama dua kali (`hpp.md` §11.4, §15.5, keputusan
  final Owner, bukan pekerjaan yang masih menunggu).

## 10. Yang TIDAK dikerjakan issue ini (di luar cakupan, bukan lupa)

- **Nilai rupiah barang retur** memakai HPP -- belum ada satu baris kode
  pun yang mengaitkan `SalesReturn` ke `Costing`. Lihat `tertunda.md` §A.
- **Killing Lost** dan **Lost Cost** -- modulnya belum ada sama sekali.
- **Bahan penolong (BOM) masuk ke HPP** -- `overhead_per_kg` sekarang
  angka manual per costing, pengganti sementara. Begitu BOM (#344) benar-
  benar dihitung, bukan cuma angkanya yang berubah tapi POSISINYA di rumus
  juga pindah (dari pengurang laba menjadi bagian dari HPP) -- keputusan
  yang harus diambil Owner, bukan implementor (`hpp.md` §9).
- **Pertanyaan accounting** di `hpp.md` §12 (peninjauan overhead, dll.) --
  belum pernah ditanyakan, tidak menghalangi pemakaian modul ini.

## 11. Izin

`view_costings`, `create_costings`, `edit_costings`, `delete_costings`
(langkah 1); `lock_costings` (langkah 2, sengaja ditunda migrasinya sampai
ada UI yang membacanya -- pola `view_deleted_*` yang sudah ada di modul
lain).
