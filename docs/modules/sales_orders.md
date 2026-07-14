# UI/UX & Logic Documentation: Sales Order

## 1. Ikhtisar Modul
Modul **Sales Order (SO)** digunakan untuk merekam pesanan penjualan dari *Customer*. Data SO merupakan *trigger* utama bagi operasional gudang dan pengiriman melalui *Delivery Plan* dan *Delivery Order*. SO mengikat data *Customer*, *Product*, beserta perhitungan diskon dan bobot (*weight*).

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Dukungan Bilingual**:
   - Seluruh elemen modul (seperti nama tombol, nama kolom tabel, notifikasi peringatan, label filter) diimplementasikan dengan `__()` sehingga tampil secara dinamis sesuai konfigurasi bahasa (*Seamless Bilingual*).
2. **Date Filter Default (Silent Filtering)**:
   - Filter **Delivery Date** secara otomatis mengatur rentang dari awal bulan berjalan hingga hari ini (jika *user* tidak memberikan input manual). Ini mencegah penarikan data yang masif secara bersamaan.
3. **Ekspor Data Ekstra Cepat (OpenSpout)**:
   - Pembuatan ekspor Excel tidak lagi menggunakan antrean (queue/exporter) bawaan yang lambat. Tombol `Export Excel` langsung mengunduh hasil secara asinkron menggunakan *library* **OpenSpout**.
4. **Estetika Dokumen PDF (Modern UI)**:
   - Format cetak (*print* PDF) disesuaikan agar terasa elegan, premium, dan tidak kaku:
     - Header biru diganti hitam tebal.
     - Menggunakan standar font modern (Google Font: **Inter**).
     - Otomatis menampilkan *Down Payment* (DP) di rincian pesanan (jika nilainya di atas 0).
5. **Kolom Integrasi Pengiriman (DO Sent)**:
   - Pada halaman **Sales Order Items Detail** (*detail-list*), ditambahkan sebuah kolom khusus bernama **DO Sent**.
   - Kolom ini menjumlahkan total kuantitas barang yang nyata-nyata telah dikirim (tercatat di dalam dokumen *Delivery Order*), mengecualikan dokumen yang berstatus batal (*cancelled*). Jika pengiriman belum terdata, akan ditampilkan strip (`-`).
