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

## Utang Teknis Terbuka

### Index unique yang hilang pada kolom `barcode`

Kolom `barcode` pada tabel berikut **belum** punya index unique:

- `sales_return_items`
- `mutation_items`
- `repack_results`
- `repack_materials`

Padahal `beef_stocks`, `boning_items`, dan `tally_items` sudah punya. Index unique adalah jaring pengaman terakhir bila logika aplikasi bocor.

**Status**: **Ditunda** atas keputusan Project Owner. Butuh migrasi, dan bila data yang ada sudah mengandung barcode kembar, `php artisan migrate --force` di pipeline auto-deploy akan gagal di tengah jalan. Sebelum dikerjakan, wajib dicek dulu ada-tidaknya duplikat di database lokal maupun server.
