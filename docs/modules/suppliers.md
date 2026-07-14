# Modul Suppliers (Pemasok)

Modul **Suppliers** (Pemasok/Vendor) adalah pangkalan sentral (*Master Data*) untuk semua entitas eksternal yang memasok barang dan jasa ke dalam perusahaan. Ini meliputi peternak sapi, penyedia *packaging*, hingga vendor layanan eksternal. Semua aliran masuk pembelian hulu (Sapi & Barang) secara mutlak harus bersandar pada profil vendor yang divalidasi di dalam modul ini.

## 1. Arsitektur & Relasi Database
Model `Supplier` berstatus sebagai entitas akar bagi semua modul rantai pasok masuk (*Inbound Logistics*):
- **Tabel `suppliers`**: Menyimpan identitas legal (Nama PT/Orang, PIC, NPWP/Nomor Pajak jika ada), kontak operasional (Alamat, Telepon, Email), dan sakelar ketersediaan (Aktif/Non-Aktif).
- **Purchase Orders (Cattle / Material / Product)** (`HasMany`): Relasi transaksi pembelian yang membanjiri sistem ERP ini semuanya merujuk pada satu *Supplier*.
- **Goods Receipts & Cattle Weighing** (`HasMany` / `HasManyThrough`): Dokumen fisik saat sapi dan barang tiba juga menautkan diri pada profil *Supplier* untuk verifikasi hutang dagang (*Accounts Payable*).

## 2. Alur Logika (Business Logic)
1. **Dinding Penahan Transaksi (Lifecycle Status)**: Seorang *Supplier* dapat ditandai sebagai `is_active` (Ya/Tidak) atau *Non-Aktif* karena alasan performa pengiriman buruk atau masa kontrak habis. Saat *Supplier* dinonaktifkan, profil mereka langsung dilenyapkan dari *dropdown* pilihan pada layar pembuatan *Purchase Order* baru, mencegah pembelian liar oleh staf pengadaan. 
2. **Kekebalan Arsip Historis (Immunity on Soft Delete)**: Untuk *Supplier* yang dinonaktifkan, sejarah panjang tagihan faktur dan PO mereka di masa lalu tetap aman dan bisa ditarik laporannya. 
3. **Ketertiban Format Kontak (Standardisasi)**: Kolom isian Email (memaksa domain yang sah) dan Nomor Handphone ditata untuk seragam demi menjaga keabsahan kontak, krusial saat modul ini dihubungkan dengan integrasi eksternal ke depannya (seperti kirim notifikasi pencairan dana otomatis via API ke pemasok).
4. **Pencegahan Pembaruan Ganda Secara Bebas**: Penghapusan mutlak sebuah profil pemasok ditolak jika sistem menemukan ikatan antara pemasok tersebut dengan dokumen hutang atau tagihan. Opsi satu-satunya adalah mematikan sakelar (status).

## 3. UI/UX (Antarmuka Pengguna)
- **Akses Pencarian Kilat (Global Integration)**: Disematkan secara utuh ke balok penelusuran global (pencarian utama) pada atap aplikasi. Jika divisi Keuangan sedang ingin mencari data kontak pemasok untuk ditagih, mereka cukup mengetik nama PT di *search bar* manapun untuk langsung meluncur ke profil *Supplier* bersangkutan.
- **Form Struktur Grid Lebar**: Tata letak isian dirancang lapang. Identitas inti ditempatkan bersebelahan di blok atas, disusul informasi alamat berukuran lebar (*columnSpanFull*) di bawah. Model blok *Grid* ini mereplika kenyamanan visual *file* laci dokumen.
- **Desain Interaksi Tombol (Toggle)**: *Switch* (Toggle) geser dipakai untuk menggantikan desain kotak centang (*Checkbox*) tradisional pada atribut Aktif/Tidak. Sentuhan kecil ini meningkatkan persepsi kemewahan (*premium feel*) aplikasi *web*.
- **Transparansi Visual pada Daftar**: Halaman indeks (Daftar Tabel) mereduksi kebisingan informasi (*information overload*). *Field* tambahan seperti Alamat sengaja dipotong menggunakan logika pembatas huruf (`limit(50)`). Informasi diletakkan secukupnya agar *User* bisa mengekspor atau melihat baris ratusan vendor tanpa perlu menekan tombol penggulung halaman terlalu sering. Tentu dengan pelabelan dwibahasa bawaan untuk pasar Indonesia.
