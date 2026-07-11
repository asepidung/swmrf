# UI/UX & Logic Documentation: Repack

## 1. Ikhtisar Modul
Modul **Repack** digunakan untuk mencatat aktivitas pengemasan ulang produk. Proses ini meliputi *Input Bahan* (produk yang akan dikemas ulang), *Input Hasil* (produk akhir setelah dikemas), serta *Material Usage* (penggunaan material kemasan). Modul ini terintegrasi langsung dengan master data Produk dan Material.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Pencegahan Pemilihan Material Ganda**:
   - Sama halnya dengan modul *Boning*, *Material Usage* pada form Repack telah dilindungi menggunakan validasi *inline* (`disableOptionsWhenSelectedInSiblingRepeaterItems`).
   - Fitur ini secara proaktif menonaktifkan opsi material di *dropdown* apabila material tersebut telah dipilih pada baris data lainnya dalam repeater yang sama, guna mencegah duplikasi.
2. **Bilingual Support (Translasi)**:
   - Modul Repack (termasuk *Resource*, *Create*, *Edit*, *Input Bahan*, *Input Hasil*, dan *Material Usage*) telah sepenuhnya dibungkus dengan metode *helper* `__()`.
   - Ini memastikan antarmuka (label form, judul modal, notifikasi *toast*, *placeholder*, dsb) bisa bertransisi secara dinamis (*Seamless Bilingual*) mengikuti preferensi bahasa yang disetel oleh sistem/user.
