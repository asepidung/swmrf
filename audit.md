# 🕵️‍♂️ Comprehensive Audit Report: SWM Refactory (D:\WebApps\swmrf)

Laporan audit ini disusun berdasarkan **Global Rules & Project Overview** (`project.md`) yang menjadi acuan mutlak proyek ini. Secara arsitektur, proyek ini sudah berjalan dengan baik, namun ditemukan beberapa **pelanggaran aturan UI/UX dan Kinerja** yang berpotensi menimbulkan *bug* atau memperlambat pengguna.

Berikut adalah temuan utama yang tidak sesuai dengan instruksi `project.md`:

---

## 1. 🚨 Pelanggaran Kinerja: Ekspor Excel Menggunakan Filament Exporter
**Aturan `project.md`**: Ekspor Excel **DILARANG** menggunakan bawaan Filament Exporter karena memicu modal/queue yang lambat. Wajib menggunakan `OpenSpout\Writer\XLSX\Writer` (Direct Stream Download).
**Fakta Temuan**: 
Ditemukan **19 lokasi** yang masih menggunakan method `->exporter()` bawaan Filament, sehingga akan memicu Job Queue yang dilarang.
**Contoh Lokasi Terdampak**:
- `SalesOrderDetailList.php` (Line 114)
- `PurchaseProductResource.php` (Line 202)
- `PurchaseMaterialResource.php` (Line 209)
- `InvoiceResource.php` (Line 663)
- `BeefStockResource.php` (Line 236)
**Tindakan Perbaikan**: Ganti seluruh `\Filament\Tables\Actions\ExportAction` yang menggunakan `->exporter()` dengan aksi kustom `\Filament\Tables\Actions\Action::make('excel')` yang merender *stream download* secara langsung menggunakan OpenSpout (seperti yang dilakukan pada export PDF).

## 2. 🚨 Pelanggaran Bug UI (Zombie Row): Penggunaan RawJs Mask pada Repeater
**Aturan `project.md`**: Dilarang keras menggunakan `RawJs::make('$money(...)')` di dalam form **Repeater** karena memicu *bug* Livewire Morphdom (baris tidak bisa dihapus di browser). Cukup gunakan `->numeric()`.
**Fakta Temuan**: 
Ditemukan **16 lokasi** yang masih memaksakan penggunaan *masking* ini untuk *currency/numeric*. Mayoritas berada di form transaksi yang kompleks.
**Contoh Lokasi Terdampak**:
- `InvoiceResource.php` (Lines 140, 164, 176, 316, 350, 394, 437)
- `ProductRequisitionResource.php` (Lines 111, 122)
- `MaterialRequisitionResource.php` (Lines 118, 129)
- `GoodsReceiptMaterialResource.php` (Line 113)
**Tindakan Perbaikan**: Hapus `->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))` pada elemen `TextInput` yang berada di dalam skema `Repeater::make()`.

## 3. ⚠️ [REJECTED] Pelanggaran UX: Filter Tanggal Tidak *Silent* (Ada Indikator Badge)
**Aturan `project.md`**: Filter tanggal bawaan (awal bulan hingga hari ini) harus bersifat *silent filtering*.
**Fakta Temuan Asli**: Ditemukan method `->indicateUsing()` pada filter tanggal yang dianggap melanggar *silent filtering*.
**Analisis Ulang & Keputusan**: Poin audit ini **DITOLAK**. Implementasi saat ini menggunakan blok kondisional `if ($data['delivery_from'] ?? null)` di dalam `indicateUsing()`. Artinya, *badge* TIDAK akan muncul saat filter berjalan secara *default* (karena input kosong/null), sehingga *silent filtering* sesungguhnya SUDAH BERJALAN dengan benar sesuai `project.md`. 
Jika kita menghapus `indicateUsing()` secara total, *badge* tidak akan muncul saat *user* **secara manual** memfilter tanggal, yang mana merusak pengalaman pengguna (UX) standar Filament.
**Tindakan Perbaikan**: **TIDAK ADA**. Kode dipertahankan.

---
**Status Audit Tambahan**: Menunggu perbaikan.

## 4. 🚨 Logika Database (TOCTOU Race Condition): Pengecekan Barcode di Luar Transaksi
**Lokasi Temuan**: `ScanGoodsReceiptProduct.php`, `LabelingGoodsReceiptProduct.php` (dan modul pindai lainnya).
**Masalah**: Anda melakukan pengecekan duplikasi *barcode* (seperti `BeefStock::where('barcode', $barcode)->exists()`) **SEBELUM** memasuki blok `DB::transaction()`.
**Dampak**: Jika dua permintaan (misal klik ganda atau *glitch* jaringan dari *scanner API*) masuk di detik yang bersamaan, keduanya akan melihat *barcode* belum ada, lalu keduanya masuk ke dalam transaksi dan berhasil menyimpan dua *barcode* yang sama persis secara berbarengan.
**Tindakan Perbaikan**: Pindahkan semua *query* pengecekan duplikasi ke **dalam** blok `DB::transaction()` agar terlindungi oleh antrean koneksi.

## 5. 🚨 Concurrency: Tidak Adanya Mekanisme Pesimistik (`lockForUpdate`)
**Lokasi Temuan**: Seluruh file *Resource* (Terutama saat memotong stok atau mengecek sisa PO).
**Masalah**: Meskipun Anda sudah menggunakan `DB::transaction()` di seluruh aplikasi (yang merupakan perbaikan bagus dari versi lawas), Anda **sama sekali tidak menggunakan `lockForUpdate()`**.
**Dampak**: `DB::transaction()` tanpa *locking* hanya mencegah data sebagian tersimpan (*rollback* jika *error*). Transaksi **tidak mencegah** dua *user* membaca sisa stok (misal sisa PO = 100Kg) secara bersamaan. Jika dua admin Gudang men- *submit* penerimaan 100Kg di waktu bersamaan, total yang tersimpan menjadi 200Kg karena keduanya merasa stok masih 100Kg.
**Tindakan Perbaikan**: Saat melakukan operasi mutasi stok yang bergantung pada nilai batas atas, *query* pembacaan wajib dikunci dengan `->lockForUpdate()`, misalnya:
`$poItem = PurchaseProductItem::where('id', $id)->lockForUpdate()->first();`

---
**Status Audit**: Selesai.
**Tindakan yang Dibutuhkan**: Laporan ini siap diserahkan kepada pengembang atau *AI Model* berikutnya untuk segera melakukan *refactoring* massal terhadap ke-5 pelanggaran krusial di atas.
