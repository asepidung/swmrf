# UI/UX & Logic Documentation: Repack

## 1. Ikhtisar Modul
Modul **Repack** digunakan untuk mencatat aktivitas pengemasan ulang produk. Proses ini meliputi *Input Bahan* (produk yang akan dikemas ulang), *Input Hasil* (produk akhir setelah dikemas), serta *Material Usage* (penggunaan material kemasan). Modul ini terintegrasi langsung dengan master data Produk dan Material.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Pencegahan Pemilihan Material Ganda**:
   - *Material Usage* pada form Repack dilindungi menggunakan validasi *inline* (`disableOptionsWhenSelectedInSiblingRepeaterItems`). Menghindari pemilihan material yang sama di dalam satu form.
2. **Bilingual Support (Translasi)**:
   - Modul Repack (termasuk *Resource*, *Create*, *Edit*, *Input Bahan*, *Input Hasil*, dan *Material Usage*) sepenuhnya dibungkus dengan metode *helper* `__()`.
3. **Date Range Filter (Silent Filter)**:
   - Diterapkan pada tabel utama *Repack*, di mana data secara *default* akan difilter dari awal bulan hingga hari ini. Hal ini membantu meringankan performa *loading* sistem saat data membesar.
4. **Export Excel Berbasis OpenSpout**:
   - Tombol *Export Excel* telah dirombak menggunakan **OpenSpout** untuk *direct stream download*. Proses _export_ berjalan seketika tanpa menggunakan modal atau antrean sistem (*queue*).
5. **Clean Repeater UI**:
   - Di dalam _Repeater_ (seperti `Material Usage`), label pada masing-masing field dihilangkan (`->hiddenLabel()`) dan dipindahkan ke dalam *placeholder* untuk membuat tampilan form terlihat bersih (tanpa *header* berulang).
6. **Halaman Detail (Pengecualian)**:
   - Khusus untuk modul *Repack*, pembuatan tabel detail berantai (*detail-list*) tidak diberlakukan karena ringkasan riwayat bahan dan hasil sudah memadai pada halaman tampilan _summary_.
