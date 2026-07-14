# Modul Invoice

Modul **Invoice** adalah komponen krusial dalam sistem ERP Wijaya Meat (SWM) yang menangani penagihan kepada pelanggan (*Customer*) setelah proses pengiriman barang selesai (*Delivery Order Receipt*). Modul ini terintegrasi langsung dengan Penjualan (*Sales Order*), Pengiriman (*Delivery Order*), serta modul Pembayaran.

## 1. Arsitektur & Relasi Database
Secara arsitektur, modul ini dibangun di atas model utama `Invoice` yang berelasi dengan:
- **Customer** (`BelongsTo`): Entitas pelanggan yang ditagih.
- **Sales Order** (`BelongsTo`): Rujukan dokumen awal penjualan.
- **Delivery Order Receipt** (`BelongsTo`): Rujukan bukti terima barang yang menjadi dasar penagihan riil (berdasarkan bobot timbangan aktual).
- **InvoiceItem** (`HasMany`): Rincian produk daging yang ditagih, mewarisi kuantitas aktual dari dokumen DO.
- **InvoiceAdditionalCharge** (`HasMany`): Tabel khusus untuk mencatat biaya tambahan non-inventori (seperti *Delivery Cost*, *Ice Gel*, *Styrofoam*).
- **PaymentAllocation** (`HasMany`): Riwayat alokasi pembayaran yang mengurangi sisa tagihan (*balance*).

## 2. Alur Logika (Business Logic)
- **Pembuatan Invoice**: Faktur dibuat tidak dari nol secara manual, melainkan di-*generate* berdasarkan `Delivery Order Receipt` yang telah tervalidasi. Seluruh *items* dan *weight* otomatis ditarik.
- **Kalkulasi Otomatis (Live Update)**: Saat ada penambahan atau pengubahan data pada *repeater* Biaya Lain-lain (maupun diskon), sistem mengeksekusi metode `updateTotals()` untuk menghitung ulang secara berjenjang:
  - `Gross` = `Qty/Weight` × `Price`
  - `Discount Rp` = `Gross` × (`Discount %` / 100)
  - `Amount` = `Gross` - `Discount Rp`
  - `Grand Total` = `Subtotal` (Total Produk) + `Total Charges` (Biaya Lain-lain)
  - `Balance` = `Grand Total` - `Down Payment`
- **Konsep Single Table Reconciliation**: Untuk mempermudah pelaporan keuangan dan rekonsiliasi, baris produk daging (`invoice_items`) dan baris ongkos/biaya tambahan (`invoice_additional_charges`) digabungkan secara virtual menggunakan **MySQL Database View** (`invoice_reconciliation_view`). Hal ini menipu framework seolah-olah mereka berasal dari tabel yang sama, sehingga dapat ditarik dan di-*export* secara bersamaan.

## 3. UI/UX (Antarmuka Pengguna)
Pengembangan UI/UX pada modul ini sangat memperhatikan standar kecepatan input operasional:
- **Form Ergonomis**: Penggunaan `Repeater` untuk *Items* dan *Additional Charges*. Label kolom disembunyikan menggunakan `hiddenLabel()` untuk menyerupai form tabular klasik yang ringkas. Input uang otomatis di-*masking* dengan pemisah ribuan agar mudah dibaca (*RawJs* pada form standar).
- **Flat List View (Charges Detail)**: Terdapat halaman khusus **"Charges Detail"** yang menyajikan seluruh rincian item penagihan lintas faktur dalam satu tabel datar (*flat list*). Halaman ini dirancang untuk tim Finance melakukan *filtering* tanggal (secara *silent*) dan mengeksekusi *export* massal.
- **Print Layout Responsif**: Tampilan cetak (*print view*) `invoice.blade.php` didesain secara khusus untuk responsif (*mobile-friendly*) menggunakan `.table-responsive` dengan CSS yang secara dinamis menyembunyikan elemen UI (seperti tombol aksi melayang) saat dikirim ke fungsi *Print Spooler* peramban.

## 4. Fitur Ekspor & Integrasi
- **Export Excel Custom**: Ekspor Excel pada halaman utama maupun halaman *Detail List* menggunakan *Direct Stream Download* (OpenSpout) via kelas `InvoiceExporter` dan `InvoiceItemExporter` untuk performa cepat tanpa fitur antrean (*queue*) yang memberatkan.
- **Activity Logging**: Segala perubahan status penagihan direkam otomatis oleh `Spatie\Activitylog`.
