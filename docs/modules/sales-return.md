# Modul Sales Return + Plan Sales Return

Ditulis 19 September 2026, sesudah issue #451 (6 langkah, PR #461-#465) --
sebelum ini modul Sales Return tidak punya dokumen tersendiri. Retur
sekarang DUA dokumen terpisah: **Plan** (sales, klaim) dan **Sales Return**
(gudang, fisik). Rancangan lengkap ada di issue #451; keputusan Owner
13 & 17 September tercatat di `tertunda.md` §D (ditutup sesudah dokumen ini).

## 1. Kenapa dua dokumen

Sebelum fitur ini: gudang bisa memindai retur apa saja tanpa ada yang
mencatat lebih dulu apa yang DIKLAIM pelanggan -- kredit ikut berat fisik
yang kebetulan discan, dan tidak ada cara membedakan "pelanggan memang
minta segini" dari "gudang salah timbang".

Sekarang: **Plan Sales Return** (menu Sales) mencatat klaim SEBELUM
barangnya sampai -- sales memilih customer, DO asal (atau dikosongkan untuk
*unidentified*), dan berapa kg per produk yang diklaim. **Sales Return**
(menu Warehouse, tidak berubah bentuk) tetap jalur fisik yang sudah ada --
scan barcode, timbang ulang -- tapi sekarang WAJIB menarik sebuah plan yang
sudah `Submitted`, dan kreditnya mengikuti klaim itu, bukan lagi berat
fisik.

## 2. Struktur data

- **`sales_return_plans`**: `plan_number` (`DocumentNumber`, prefix
  `SRP#`, padding 3), `plan_date`, `customer_id` (wajib, restrict),
  `delivery_order_id` (nullable, nullOnDelete -- *unidentified*), `status`
  (`Draft`/`Submitted`/`Received`/`Cancelled`), `note`, `created_by`,
  SoftDeletes, LogsActivity.
- **`sales_return_plan_items`**: `plan_id` (cascade), `product_id`
  (restrict), `claimed_weight`, `claimed_qty_pcs` (nullable), `note`. Tidak
  ada `warehouse_id`/`grade_id`/`barcode` -- plan dokumen sales, belum ada
  barang fisik yang dipegang. LogsActivity di sini yang menjaga angka klaim
  LAMA tercatat saat dinego (lihat §4).
- **`sales_returns`**: kolom lama tidak berubah, PLUS `sales_return_plan_id`
  (nullable, unique, nullOnDelete). Nullable karena retur LAMA (sebelum
  fitur ini) tidak pernah punya plan; unique menegakkan satu plan hanya
  bisa jadi satu retur.
- **`sales_return_items`**: TIDAK berubah sama sekali. Klaim tidak disalin
  ke sini -- lihat §4 kenapa.

## 3. Siklus hidup Plan

`SalesReturnPlan::submit()` (Draft -> Submitted, butuh minimal satu item),
`markReceived()` (dipanggil `SalesReturn::approve()`), `markSubmitted()`
(dipanggil saat retur yang menariknya di-unlock ATAU dihapus -- lihat di
bawah), `cancel()` (Draft/Submitted -> Cancelled, ditolak dari Received).

Guard di `SalesReturnPlan::booted()`:
- `saving()`: DO yang dipilih harus milik `customer_id` plan-nya.
- `updating()`: field APA PUN selain `status` ditolak begitu status bukan
  `Draft` lagi -- header terkunci total sesudah Submitted.
- `deleting()`: hanya `Draft` yang boleh dihapus.

Guard di `SalesReturnPlanItem::booted()`:
- `creating()`: item cuma bisa ditambah selagi plan `Draft`.
- `updating()`: `claimed_weight`/`claimed_qty_pcs` masih boleh diubah
  selagi `Draft` ATAU `Submitted` (jalur nego -- lihat §4), ditolak total
  begitu `Received`.
- `deleting()`: item cuma bisa dihapus selagi `Draft`.
- Kalau plan-nya punya `delivery_order_id`: klaim tidak boleh melebihi
  `DeliveryOrder::deliveredWeightFor($productId)` (helper baru, pola sama
  dengan `Invoice::billedWeightFor()`). Plan tanpa DO (*unidentified*)
  tidak punya batas ini.

**Halaman item** (`ManageSalesReturnPlanItems`, `ManageRelatedRecords`):
tidak ada `SalesReturnPlanItemPolicy` -- pola sama dengan
`MaterialStockTakeItem`, gerbang sesungguhnya `canAccess()` (butuh
`edit_sales_return_plans`, DAN status plan harus `Draft`/`Submitted`),
diperiksa ULANG tiap request lewat `hydrate()` bawaan
`ManageRelatedRecords`. Form penuh (termasuk pilih produk) selagi Draft,
form sempit (cuma klaim) selagi Submitted -- menambah/menghapus baris
tidak mungkin lagi begitu Submitted.

## 4. Klaim vs fisik: dibandingkan per PRODUK, bukan per baris

Keputusan Owner 19 September (dikonfirmasi lewat Hafizh di PR #463),
sesudah ditanya lebih dulu karena dua tafsir sama-sama masuk akal: **klaim
dan fisik dibandingkan di level PRODUK (agregat)**, BUKAN disalin satu-satu
ke setiap baris `sales_return_items` yang di-scan. Alasannya: alur scan
barcode (`InputReturnItems::processScan()`/`processWeigh()`) TIDAK BOLEH
berubah -- sudah teruji berat, dan satu produk bisa datang dalam banyak
karton yang tidak punya hubungan satu-satu dengan satu baris klaim.

Konsekuensinya:

- **`SalesReturnPlan::claimedWeightFor(productId)`**: jumlah klaim untuk
  satu produk (dari `sales_return_plan_items`, bisa lebih dari satu baris).
- **`SalesReturn::physicalWeightFor(productId)`**: jumlah fisik yang
  benar-benar discan untuk produk itu DI RETUR INI.
- **Scan menolak produk yang tidak diklaim** (keputusan Owner 19
  September, "supaya kredit selalu punya dasar"): `processScan()` dan
  `processWeigh()` memeriksa `$plan->claimedWeightFor($productId) > 0`
  sebelum menyimpan apa pun. Retur tanpa plan (data lama) tidak dibatasi
  ini.
- **`SalesReturn::attachToBill()`**: dasar kredit sekarang KLAIM per
  produk, bukan berat fisik kartonnya -- *"kredit = qty klaim di plan"*,
  walau fisiknya kurang. Kalau ada LEBIH dari satu karton untuk produk yang
  sama dalam satu retur, klaimnya dibagi PROPORSIONAL menurut berat fisik
  masing-masing karton (tidak ada dasar lain untuk membaginya). Tetap
  dibatasi jatah invoice/SO seperti sebelumnya (`billedWeightFor` -
  `returnedWeightFor`). Retur TANPA plan jatuh kembali ke kredit berbasis
  fisik apa adanya -- tidak ada regresi untuk riwayat.
- **`SalesReturn::approve()`** menolak SELURUH approve kalau ADA produk
  yang diklaim (`claimed_weight > 0`) tapi fisiknya nol (barangnya tidak
  pernah datang sama sekali). Produk yang di-scan tapi tidak diklaim sudah
  ditolak lebih awal, saat scan.
- **`recordClaimVarianceLoss()`** (dipanggil `approve()` sesudah
  `attachToBill()`): kalau klaim > fisik untuk sebuah produk, selisihnya
  (dalam kg, dinilai harga kredit produk itu) jadi SATU `FinancialLoss`
  (`FinancialLoss::SUMBER_RETUR = 'Sales Return'`) untuk seluruh retur --
  bukan pengurang kredit. Fisik > klaim tidak menghasilkan kerugian sama
  sekali (dicatat sebagai keadaan biasa, bukan galat).
- **`unlock()`** membalik `FinancialLoss` selisihnya juga (dihapus), TAPI
  plan TETAP `Received` -- retur masih ada, cuma dibuka kuncinya. Hanya
  MENGHAPUS retur (`deleting()` di `SalesReturn::boot()`) yang
  mengembalikan plan ke `Submitted`, dan hanya kalau plan-nya sempat
  `Received` (retur Draft yang belum pernah di-approve tidak mengubah apa
  pun karena plan-nya memang belum pernah beranjak dari `Submitted`).

## 5. Tarik Plan

`SalesReturn` tidak lagi punya tombol Create polos -- route `'create'`
dihapus total dari `SalesReturnResource::getPages()`. Satu-satunya jalur
masuk: aksi **"Tarik Plan"** di `ListSalesReturns` (modal pilih plan
`Submitted` yang belum ditarik, `whereDoesntHave('salesReturn')`), yang
menyalin `customer_id`+`delivery_order_id` ke header retur baru dan
langsung redirect ke halaman edit. `SalesReturn::booted()` (creating)
menegakkannya juga di server: menolak tanpa `sales_return_plan_id`, menolak
plan yang belum `Submitted`, menolak plan yang sudah pernah ditarik --
tiga pesan berbeda, bukan satu galat SQL unique mentah.

## 6. Nilai uang mengikuti `view_invoices`

Keputusan Owner 13 September (issue #387), dikerjakan di langkah 5:

- Kolom `credit_amount`/`invoice_numbers` di tabel `SalesReturnResource`
  `->visible()` hanya untuk pemegang `view_invoices`.
- `print/sales-return.blade.php` menyembunyikan SELURUH bagian uang
  (baris "Mengurangi Invoice", kolom harga/jumlah per baris, ringkasan
  "Nilai retur") kecuali pelihatnya juga punya `view_invoices` -- staf
  gudang yang cuma punya `view_sales_returns` melihat dokumen fisiknya
  (barang + berat), bukan berapa yang dipotong dari tagihan.
- `sales-return.label`/`sales-return.pdf` (dua route TERAKHIR yang tersisa
  dari sapuan batch 7, sengaja ditunda sampai keputusan ini ada) sekarang
  mensyaratkan `view_sales_returns` untuk membuka halamannya sama sekali.
- Layar Invoice (`InvoiceResource`): badge "Reduced by Return" di tabel,
  dan section "Reduced by Sales Returns" (daftar nomor retur + jumlahnya)
  di View/Edit -- supaya sisa tagihan yang berkurang tidak terbaca sebagai
  galat.

## 7. Izin

Empat izin baru untuk Plan (migrasi, bukan seeder --
`2026_09_19_090200_create_the_sales_return_plan_permissions.php` +
`2026_09_19_120000_...` untuk `view_deleted_...`): `view_sales_return_plans`,
`create_sales_return_plans`, `edit_sales_return_plans`,
`delete_sales_return_plans`, `view_deleted_sales_return_plans`. Belum
dilekatkan ke siapa pun -- dicatat di `tertunda.md` §G untuk peran Sales.

Izin lama Sales Return (`view_sales_returns`, `create_sales_returns`,
`edit_sales_returns`, `delete_sales_returns`, `approve_sales_returns`,
`unlock_sales_returns`) tidak berubah.

## 8. Yang TIDAK dikerjakan (keputusan Owner)

- Setelan per grup pelanggan "terima klaim apa adanya" -- satu aturan
  untuk semua, nego lewat mengubah `claimed_weight` di plan.
- Retur walk-in tanpa plan -- ditolak by design, bukan celah.
- Repack tidak disentuh sama sekali -- barang retur masuk `BeefStock
  IN_STOCK` biasa, susut seset tetap urusan Repack.
