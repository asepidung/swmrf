# UI/UX & Logic Documentation: Price List

## 1. Ikhtisar Modul
Modul **Price List** merupakan modul Master Data yang digunakan untuk mengatur daftar harga spesifik (khusus) berdasarkan **Customer Group**. Melalui modul ini, perusahaan dapat menentukan harga jual produk yang berbeda-beda untuk tiap segmentasi grup pelanggan (misal: *Wholesale*, *Retail*, *VIP*). Saat pembuatan *Sales Order*, harga akan otomatis merujuk pada *Price List* ini sesuai dengan grup pelanggan yang dipilih.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Clean Repeater UI & Pencegahan Bug Masking**:
   - Pada form pengisian harga produk (*Repeater* items), label *header* yang berulang disembunyikan menggunakan kombinasi form *Grid placeholder* di atasnya dan `->hiddenLabel()` di dalam baris *repeater*.
   - **PENTING**: Penggunaan AlpineJS mask (`RawJs::make('$money(...)')`) di dalam *repeater* **telah dihapus sepenuhnya** dan diganti menggunakan `->numeric()` standar. Hal ini secara efektif mencegah *bug zombie row* di mana fitur hapus baris gagal bekerja akibat bentrok *Morphdom* dan *AlpineJS teardown*.
2. **Dukungan Bilingual (Translasi Dinamis)**:
   - Seluruh elemen antarmuka modul (termasuk *Resource*, halaman *List*, *Edit*, dan *View*) telah dibungkus menggunakan metode pelokalan `__()`. Seluruh *heading*, nama kolom, dan nama *field* akan otomatis berganti bahasa sesuai preferensi.
3. **Pencegahan Pemilihan Produk Ganda**:
   - Sama seperti implementasi material, form produk di dalam *Price List* dilindungi dengan validasi *inline* (`disableOptionsWhenSelectedInSiblingRepeaterItems`). Pengguna tidak bisa memasukkan produk yang sama dua kali dalam satu grup harga.
4. **Ekspor Data Ekstra Cepat (OpenSpout)**:
   - Halaman daftar utama (tabel grup pelanggan) kini dilengkapi dengan tombol ekspor **Excel** menggunakan *library* **OpenSpout**. Fitur *direct stream download* ini mengeksekusi pembuatan laporan seketika tanpa membuka *modal queue* Filament.

## 3. Pengecualian Khusus Master Data
1. **Tidak Ada Date Filter Default**: Karena *Price List* tergolong *Master Data* (Bukan *Transactional* seperti Penjualan/Pembelian), modul ini tidak memerlukan *silent default date filter* pada tabel utama.
2. **Tidak Ada Halaman Detail-List Khusus**: Semua riwayat pengubahan daftar harga dan rinciannya langsung disematkan pada halaman form *Edit* & *View* (tanpa perlu *tab Detail List* terpisah).
