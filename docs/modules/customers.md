# Modul Customers (Pelanggan)

Modul **Customers** adalah manajemen basis data pelanggan yang menyimpan seluruh identitas pembeli B2B (Bisnis) maupun B2C (Konsumen Akhir). Data dari modul ini menjadi tulang punggung bagi seluruh modul hilir seperti *Sales Orders*, *Delivery Orders*, dan *Invoices*.

## 1. Arsitektur & Relasi Database
Model utama `Customer` bertindak sebagai entitas sentral (*Master Data*) untuk operasional penjualan:
- **Tabel `customers`**: Menyimpan data esensial seperti Nama, PIC (*Person In Charge*), Email, Nomor Telepon, Alamat, serta pengaturan status Aktif/Nonaktif.
- **Relasi Sentral**: Pelanggan memiliki relasi `HasMany` secara ekstensif terhadap tabel `sales_orders`, `delivery_orders`, `invoices`, `receivables` (Piutang), dan log `payments`.

## 2. Alur Logika (Business Logic)
1. **Lifecycle Pelanggan (Active/Inactive State)**: Pelanggan tidak dihancurkan dari database (*Hard Delete*) jika mereka berhenti bertransaksi. Sebaliknya, modul ini menggunakan *boolean toggle* `is_active`. Pelanggan yang tidak aktif secara otomatis disembunyikan (*filtered out*) dari *dropdown* pembuatan *Sales Order* atau faktur baru, namun riwayat transaksi masa lalu mereka tetap utuh di laporan keuangan.
2. **Standardisasi Format Kontak**: Sistem secara internal memformat atau memberikan validasi ketat terhadap pengisian struktur Email (wajib *valid email format*) dan format Nomor Telepon, mencegah *error* ketika sistem akan mengeksekusi integrasi notifikasi (misal: pengiriman faktur via email atau WhatsApp di masa depan).
3. **Penyimpanan Alamat yang Ekstensif**: Karena industri daging melibatkan pengiriman logistik (*Cold Chain*), modul mewajibkan *field* alamat untuk bisa menyimpan input teks panjang (*Long Text/Textarea*) agar memuat *waypoint* yang jelas.

## 3. UI/UX (Antarmuka Pengguna)
- **Pencarian Agresif (Global Search)**: Modul ini diintegrasikan secara penuh ke *Global Search* bawaan Filament. *User* bisa mencari "Nama Pelanggan" dari *bar* navigasi manapun (bahkan saat berada di modul lain) untuk menemukan profil pelanggan dalam sedetik.
- **Grid Layout pada Form**: Formulir pengisian profil menggunakan struktur *Grid* (2 atau 3 kolom). Detail seperti Nama, Email, dan Telepon disajikan berdampingan di bagian atas, sementara area *Address* disajikan merentang penuh (*columnSpanFull*) di bagian bawah, mencerminkan hierarki visual yang natural.
- **Visualisasi Status (Toggle)**: Menggunakan elemen *Toggle* UI untuk status aktif/nonaktif. Berbeda dengan *checkbox* kaku, *toggle* memberi kesan umpan balik (*feedback*) instan seperti aplikasi *mobile*.
- **List & Infolist Kompak**: Tabel data dirancang *responsive*. Informasi sekunder seperti Alamat dipotong (*truncated*) dengan `limit()` pada *Table View* agar tidak menghabiskan baris layar, namun bisa dibaca secara penuh pada halaman *View/Infolist*.
- **Dukungan Bilingual Terpadu**: Seperti standar ERP ini, semua antarmuka (nama form, kolom, error) diatur mengikuti preferensi bahasa lokal tanpa perlu penyetelan manual dari sisi *user*.
