# 🕵️ Audit Report: SWM Refactory (D:\WebApps\swmrf)

Laporan ini disusun berdasarkan **Global Rules & Project Overview** (`project.md`).
Diverifikasi ulang dan ditutup pada **21 Agustus 2026** lewat issue #45.

**Status keseluruhan: SELESAI.** Seluruh temuan yang masih berlaku sudah diperbaiki.

---

## Ringkasan Temuan

| # | Temuan | Status |
|---|--------|--------|
| 1 | Ekspor Excel memakai Filament Exporter | ✅ Selesai (sebelum #45) |
| 2 | RawJs mask di dalam Repeater (zombie row) | ✅ Selesai di #45 |
| 3 | Filter tanggal tidak *silent* | ⛔ Ditolak — kode dipertahankan |
| 4 | TOCTOU: cek duplikat barcode di luar transaksi | ✅ Selesai di #45 |
| 5 | Tidak adanya `lockForUpdate` | ✅ Selesai di #45 |

---

## 1. ✅ Ekspor Excel Menggunakan Filament Exporter

**Aturan**: Ekspor Excel dilarang memakai Filament Exporter (memicu modal/queue yang lambat); wajib `OpenSpout\Writer\XLSX\Writer` dengan *direct stream download*.

**Temuan awal**: 19 lokasi memakai `->exporter()`.

**Status**: **Selesai** sebelum issue #45. Verifikasi ulang: `->exporter(` tidak ditemukan lagi di `app/Filament`.

## 2. ✅ Bug UI (Zombie Row): RawJs Mask pada Repeater

**Aturan**: Dilarang memakai `RawJs::make('$money(...)')` di dalam `Repeater` — memicu bug Livewire Morphdom yang menyisakan baris "zombie".

**Temuan awal**: 16 lokasi.

**Status**: **Selesai**. Saat verifikasi ulang tersisa 3 pemakaian `$money()`, dan hanya **satu** yang benar-benar berada di dalam Repeater:

- `PurchaseCattleResource.php` — field `price` di dalam `Repeater::make('items')` → mask dihapus, cukup `->numeric()`.

Dua sisanya (`FinancialLossResource`, `SalesOrderResource`) berada di form level atas, bukan di dalam Repeater. Itu **justru sesuai** aturan `project.md` dan sengaja dipertahankan.

## 3. ⛔ [DITOLAK] Filter Tanggal Tidak *Silent*

**Temuan awal**: `->indicateUsing()` pada filter tanggal dianggap melanggar *silent filtering*.

**Keputusan**: **Ditolak.** Implementasi memakai blok kondisional `if ($data['delivery_from'] ?? null)`, sehingga badge tidak muncul saat filter berjalan default (input kosong) — *silent filtering* sudah berjalan benar. Menghapus `indicateUsing()` justru merusak UX saat user memfilter secara manual.

**Tindakan**: Tidak ada. Kode dipertahankan.

## 4. ✅ TOCTOU: Pengecekan Barcode di Luar Transaksi

**Masalah**: Pengecekan duplikasi barcode dilakukan **sebelum** `DB::transaction()`, sehingga dua permintaan bersamaan (klik ganda / glitch scanner) bisa sama-sama lolos.

**Status**: **Selesai**. Sebagian sudah diperbaiki sebelum #45; sisanya ditutup di #45:

| Lokasi | Tindakan |
|---|---|
| `InputReturnItems::processScan()` | Tidak punya transaksi sama sekali → dibungkus `DB::transaction` + cek berkunci |
| `ScanGoodsReceiptProduct` | Ditambah cek `items()` ber-`lockForUpdate` di dalam transaksi |
| `ScanMutation` | Ditambah cek `MutationItem` ber-`lockForUpdate` di dalam transaksi |

**Pola yang dipakai** (lebih baik daripada sekadar memindahkan cek ke dalam transaksi): pengecekan di luar transaksi **dipertahankan sebagai fast-path** agar pesan ke operator tetap ramah dan spesifik, lalu ditambahkan pengecekan **otoritatif ber-`lockForUpdate` di dalam transaksi** sebagai penjagaan yang sebenarnya.

## 5. ✅ Concurrency: Mekanisme Pesimistik (`lockForUpdate`)

**Catatan koreksi**: klaim audit awal ("sama sekali tidak menggunakan `lockForUpdate`") **tidak akurat**. Saat verifikasi ulang, `lockForUpdate` sudah dipakai di 25 lokasi. Yang tersisa adalah 4 celah spesifik, dan semuanya sudah ditutup di #45:

| Lokasi | Masalah | Tindakan |
|---|---|---|
| `LabelingBoning.php` | Counter barcode dibaca tanpa lock | + `lockForUpdate()` |
| `InputReturnItems.php` | Counter barcode dibaca tanpa lock | + `lockForUpdate()` |
| `EditSalesReturn.php` | Validasi stok sebelum dihapus tanpa lock | + `lockForUpdate()` |
| `ViewSalesReturn.php` | Validasi stok sebelum dihapus tanpa lock | + `lockForUpdate()` |

Pola generator barcode kini seragam dengan `LabelingGoodsReceiptProduct` dan `InputHasilRepack` yang sejak awal sudah benar.

---

## Keputusan Desain: Barcode Sengaja Tanpa Index Unique

Kolom `barcode` pada `sales_return_items`, `mutation_items`, `repack_results`, dan `repack_materials` **sengaja tidak diberi index unique**. Keputusan Project Owner, 21 Agustus 2026.

Alasannya: tabel-tabel itu bersifat transaksional. Satu barang fisik bisa keluar-masuk berkali-kali — diretur, dimutasi, di-repack ulang — sehingga barcode yang sama sah muncul berulang kali lintas dokumen. Index unique global justru akan memblokir alur bisnis yang benar.

Bandingkan dengan tabel lain agar tidak keliru menyamakan:

| Tabel | Bentuk | Alasan |
|---|---|---|
| `beef_stocks` | `unique(barcode)` | Tabel stok berjalan: satu baris per barang yang sedang ada di gudang, dihapus saat keluar |
| `tally_items` | `unique(tally_id, barcode)` | Unique **berlingkup dokumen** |
| `stock_take_items` | `unique(stock_take_id, barcode)` | Unique **berlingkup dokumen** |
| `sales_return_items`, `mutation_items`, `repack_results`, `repack_materials` | tanpa unique | Transaksional, barang berulang lintas dokumen |

**Implikasi untuk implementor:** pencegahan duplikat sepenuhnya berada di level aplikasi dan **wajib berlingkup per dokumen** (`where('mutation_id', ...)`, `where('sales_return_id', ...)`), bukan global. Jaga lingkup ini saat menyentuh pengecekan duplikat — itulah sebabnya cek berkunci yang ditambahkan di #45 semuanya memakai penyaring dokumen induk.

---

## Utang Teknis Terbuka

### ~~PPN belum diimplementasikan di modul Invoice~~ — DIBATALKAN, memang benar

Sempat dicatat sebagai utang teknis, lalu **dibatalkan** atas keterangan Project Owner (21 Agustus 2026).

Wijaya Meat adalah produsen daging berstatus **nonPKP**, sehingga **invoice dan penjualan memang tidak dikenai PPN**. Pajak hanya relevan pada pembelian material lain di sisi procurement. Jadi absennya perhitungan pajak di `InvoiceResource::updateTotals()` adalah perilaku yang benar, bukan fitur yang tertinggal.

Kolom `invoices.tax` dan flag `customers.is_taxable` merupakan sisa desain lama yang tidak terpakai di sisi penjualan. Jangan diperlakukan sebagai fitur yang menunggu dikerjakan.

### Kolom `charge` sudah mati tapi masih diekspor

Kolom skalar `invoices.charge` sudah digantikan repeater relasi `additionalCharges` (tabel `invoice_additional_charges`). Tidak ada lagi yang menulis ke `charge`, namun `InvoiceExporter` masih mengekspor kolomnya sehingga hasil ekspor selalu bernilai nol untuk kolom itu.

### Status Test Suite

**Sudah pulih.** Sebelum #47: 73 gagal / 2 lolos. Sesudah: **75 lolos, 0 gagal, 424 assertions.**
