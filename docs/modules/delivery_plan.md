# Modul Delivery Plan (Rencana Pengiriman)

Modul **Delivery Plan** berfungsi sebagai papan strategi (*Dashboard Planning*) bagi departemen logistik. Modul ini bertujuan mengonsolidasikan dan menjadwalkan pesanan-pesanan penjualan (*Sales Orders*) yang siap dikirim agar bisa dipetakan ke dalam berbagai rute, armada kendaraan, dan gelombang pengiriman yang paling efisien.

## 1. Arsitektur & Relasi Database
Model `DeliveryPlan` merupakan entitas perantara (*intermediary orchestrator*) sebelum penciptaan *Delivery Order* fisik:
- **Tabel `delivery_plans`**: Menyimpan ID, Tanggal Rencana (*Plan Date*), Area/Rute Tujuan, dan catatan logistik.
- **PlannedItems / PlanDetails** (`HasMany`): Menyimpan rujukan item dari *Sales Order* mana saja yang diagendakan pada tanggal dan rute tersebut.
- **Vehicle & Driver** (`BelongsTo` / Ekstraksi): Merelasikan rencana rute dengan armada spesifik yang akan mengeksekusi rencana tersebut.

## 2. Alur Logika (Business Logic)
1. **Konsolidasi Multi-Order (Batching)**: Satu *Delivery Plan* dapat merangkum berbagai *Sales Order* dari pelanggan yang berbeda, asalkan mereka berada di jalur atau rute logistik yang sama. Hal ini mengefisiensikan biaya bahan bakar armada (*Cold Truck*).
2. **Kalkulasi Muatan (Capacity Logic)**: Modul mengumpulkan total tonase (bobot kg) dari seluruh order yang masuk ke dalam sebuah rencana. Administrator logistik dapat melihat total bobot ini untuk memastikan tidak melanggar batas tonase maksimal kendaraan (*Overload Protection*).
3. **Eksekusi Sekali Klik (DO Generation)**: Setelah rencana disetujui, alih-alih membuat *Delivery Order* (DO) satu per satu secara manual, pengguna dapat menggunakan *Action* khusus (misal: "Generate DOs"). *Action* ini secara rekursif akan menciptakan dokumen-dokumen DO secara otomatis untuk setiap pelanggan berdasarkan kerangka rencana pengiriman ini.
4. **Pencegahan Pemesanan Ganda**: Item pesanan penjualan yang sudah terdaftar di satu *Delivery Plan* berstatus "Aktif", secara logika akan diblokir atau disembunyikan dari daftar pilihan rencana pengiriman lainnya, mencegah barang yang sama dikirim dua kali.

## 3. UI/UX (Antarmuka Pengguna)
- **Tampilan Board/Timeline (Visualisasi Rencana)**: Mengingat fungsinya sebagai modul *planning*, data sangat cocok diorganisir menggunakan UI berbasis tanggal (*Date Picker/Filter*). Pengguna dapat dengan mudah memfilter rencana hari ini, besok, atau minggu depan.
- **Pemilihan Item Lintas Dokumen (Cross-document Selection)**: Modul menyediakan *modal/drawer* khusus yang merangkum *List* dari semua SO yang berstatus *Pending/Processing* beserta sisa stoknya. Pengguna (*Dispatcher*) cukup memberikan ceklis (☑️) untuk memindahkan SO-SO tersebut ke dalam wadah *Delivery Plan*.
- **Indikator Beban (Load Bar)**: Halaman *View* atau *Index* dapat memunculkan infografik simpel (seperti *Progress Bar* persentase) yang merepresentasikan seberapa penuh kapasitas truk saat ini berbanding muatan pesanan.
- **Dukungan Lokalisasi**: Seperti standar seluruh sistem ERP, bahasa dan notifikasi (*Toast Messages*) mematuhi konfigurasi bilingual, sehingga staf gudang lokal dapat mengoperasikannya secara intuitif dalam bahasa Indonesia.
