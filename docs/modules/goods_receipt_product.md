# Modul Goods Receipt Product (Penerimaan Barang - Produk)

Modul **Goods Receipt Product (GRP)** adalah varian penerimaan barang (*Inbound Logistics*) yang secara khusus menangani kedatangan **Produk Daging/Sapi Olahan Jadi** (seperti *Frozen Meat*, Daging Box, atau produk kemasan) yang dipesan dari pemasok eksternal, BUKAN dari proses pemotongan karkas internal. Modul ini menjembatani dokumen *Purchase Order* (PO) dengan inventaris gudang daging beku/dingin (*Cold Storage*).

## 1. Arsitektur & Relasi Database
Model `GoodsReceiptProduct` memiliki susunan arsitektur relasional yang identik dengan GRM, namun bermuara pada entitas stok yang berbeda:
- **Purchase Order (PO)** (`BelongsTo`): Merujuk pada PO tipe "Produk" yang menjadi landasan sah pengiriman ini.
- **Supplier** (`BelongsTo`): Pemasok eksternal tempat asal produk daging ini.
- **GoodsReceiptProductItem** (`HasMany`): Rincian nama produk, berat (kg), kuantitas (karton/box), dan atribut spesifik daging yang diterima.
- **Product Stock / Inventory** (`HasMany` / Mutasi): Berbeda dengan GRM yang mengubah tabel material, modul ini menyuntikkan (menambah) saldo stok pada tabel master `Products`.

## 2. Alur Logika (Business Logic)
1. **Pengukuran Ganda (Dual-Metrics / UOM)**: Daging adalah produk dengan variansi berat (*Catch Weight*). Karena itu, alur logika pada GRP mewajibkan staf memasukkan **Kuantitas** (berapa jumlah box) DAN **Total Berat Aktual** (kg). Sistem akan otomatis menghitung Harga Total berdasarkan parameter penagihan yang disepakati (apakah bayar per kg atau bayar per box).
2. **Kategorisasi Suhu/Batch (Cold Chain Logic)**: Pada modul ini, seringkali ditambahkan atribut opsional untuk menyimpan nomor *Batch/Lot* produksi dari pemasok dan kondisi suhu saat kedatangan (Chilled/Frozen) untuk tujuan *Quality Control* (QC) dan *Traceability*.
3. **Mekanisme Tally (Opsional)**: Untuk kedatangan produk skala besar, alih-alih menginput total berat secara gelondongan, GRP dapat dihubungkan ke fitur *Tally* (pencatatan per satu koli/box). Hasil rekapitulasi *Tally* akan otomatis mengisi total berat di dokumen GRP ini.
4. **Pembaruan HPP (COGS Update)**: Penerimaan produk baru dengan harga berbeda seringkali memicu logika *Average Costing* di latar belakang. Sistem ERP akan mengkalkulasi ulang Harga Pokok Penjualan (HPP) produk tersebut di *database* master menggunakan rumus Rata-Rata Tertimbang (*Weighted Average*).

## 3. UI/UX (Antarmuka Pengguna)
- **Komponen Input yang Terpisah**: Untuk mengakomodasi logika ukur ganda (UOM), pada *Repeater* (tabel input) *items*, kolom Kuantitas (Box/Pcs) dan kolom Berat (Kg) diletakkan bersebelahan menggunakan `Grid` atau blok terpadu, mengurangi perpindahan kursor bolak-balik bagi petugas.
- **Perhitungan Total Real-time**: Menggunakan manipulasi *Livewire* (`->live()`), setiap kali pengguna mengedit berat atau kuantitas, sistem akan merender ulang subtotal harga dan Total Keseluruhan (*Grand Total*) di pojok bawah secara *real-time* tanpa perlu menekan tombol *Save* terlebih dahulu.
- **Sinkronisasi Dokumen Hulu**: Mirip dengan modul penerimaan lain, pemilihan *Purchase Order* di *header* akan langsung mengekstraksi dan menyebarkan (meng-kopi) data barang dari PO masuk ke formulir penerimaan. Sistem juga mewarnai field *Read-Only* (seperti nama produk bawaan PO) dengan *background* abu-abu halus agar *user* paham bahwa field tersebut dilarang dimodifikasi.
- **Bilingual Terkalibrasi**: Seluruh antarmuka tunduk pada pengaturan bahasa global (*Translation File*). Kesalahan input seperti "Berat Melebihi Toleransi" akan muncul dengan bahasa Indonesia atau Inggris yang natural sesuai pengaturan profil pengguna.
