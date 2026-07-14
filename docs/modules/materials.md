# Modul Materials (Master Data Material)

Modul **Materials** adalah ensiklopedia pusat (*Master Data*) untuk semua barang *non-daging* dan *non-sapi* yang digunakan dalam ekosistem Rumah Potong Hewan (RPH). Ini mencakup bahan pengemasan (*Packaging* seperti kardus, plastik vakum), bumbu, perlengkapan higienitas (sarung tangan, masker), dan alat-alat operasional (pisau, dll).

## 1. Arsitektur & Relasi Database
Model `Material` merupakan akar (pondasi) dari segala pergerakan barang non-daging di seluruh sistem:
- **Tabel `materials`**: Menyimpan spesifikasi esensial barang seperti SKU/Kode Barang, Nama, Kategori, Satuan Ukur Utama (UOM - misal: *Pcs*, *Roll*, *Pack*), dan Harga Satuan Dasar (HPP).
- **Material Category** (`BelongsTo`): Pengelompokan jenis barang (misal: "Packaging", "Ingredients", "Consumables").
- **Relasi Transaksional**: Digunakan secara masif sebagai titik acuan (Foreign Key) pada dokumen *Purchase Order*, *Goods Receipt Material*, dan *Material Usage*.

## 2. Alur Logika (Business Logic)
1. **Single Point of Control**: Data pada modul ini bersifat murni administratif. Karena merupakan data statis, pembuatan atau pengeditan nama material di sini akan otomatis memperbarui tampilan di semua dokumen sejarah maupun dokumen baru (kecuali pada cetakan faktur historis/nota yang menggunakan data arsip tersendiri).
2. **Kalkulasi Stok Instan**: Meskipun saldo stok sejati dihasilkan dari kalkulasi transaksi keluar-masuk (GRM dikurangi Material Usage), profil Master Material ini dapat menyertakan fungsi agregat/pembantu (seperti *Accessor*) untuk dengan cepat memunculkan "Current Stock" tanpa memaksa sistem memproses ulang jutaan baris *log* transaksi secara memberatkan.
3. **Penghapusan Bersyarat (Protected Delete)**: Untuk memastikan sistem tetap kohesif, material yang sudah pernah dibeli (masuk PO) atau dipakai (*Usage*) dilarang dihapus dari *database* (Hard Delete). Jika barang tidak lagi diproduksi/dibeli, administrator hanya diperbolehkan mengubah statusnya menjadi *Inactive/Discontinued* (bersembunyi secara *soft-delete* atau *toggle*).
4. **Pencegahan Redundansi**: Logika sistem memaksa pembuatan *Kode Material* (SKU) harus 100% unik dan distandardisasi menjadi huruf kapital secara otomatis melalui *Mutator*.

## 3. UI/UX (Antarmuka Pengguna)
- **Visualisasi Gambar dan Status**: Pada halaman tabel (List/Index), selain memunculkan kode dan nama, modul ini dapat menampilkan kolom khusus (seperti kolom *Image Avatar* untuk foto kotak kemasan atau logo barang) untuk mempercepat pengenalan visual bagi staf gudang baru.
- **Filter Agresif & Pencarian Global**: Karena material bisa mencapai ratusan *item*, tabel dilengkapi filter *Sidebar* khusus (Filter berdasarkan "Kategori" atau "Satuan"). Material ini juga terindeks dalam *Global Search* di pojok kanan atas layar (*top navigation*).
- **Pengaturan *Card* pada Form**: Saat menambah barang baru (*Create*), isian dibagi menjadi beberapa *Card* (Kartu): "Informasi Utama" (Nama, SKU), "Atribut Logistik" (UOM, Kategori), dan "Akuntansi" (Harga HPP standar). Pemecahan *layout* ini mengurangi beban kognitif pada administrator.
- **Penggunaan Bilingual**: Teks, opsi status (Aktif/Nonaktif), dan notifikasi validasi telah ditautkan pada *dictionary/lang* yang mendukung fitur ganti bahasa (Inggris - Indonesia).
