# UI/UX & Logic Documentation: Supplier Module

## 1. Ikhtisar Modul
Modul **Supplier** adalah bagian dari *Master Data* yang digunakan untuk mencatat dan mengelola profil pemasok/vendor yang bekerja sama dengan perusahaan. Modul ini penting sebagai fondasi relasi pada proses pembelian (Purchase) dan penerimaan barang (Goods Receipt).

## 2. Struktur Database
Tabel `suppliers` memiliki atribut dasar profil dan juga telah diperluas untuk mengakomodasi pencatatan tipe barang yang disuplai:
- **Pembaruan Migration**: Ditambahkan kolom `supplied_goods` bertipe `string` (nullable).
- **Fungsi Kolom Baru**: Digunakan untuk mencatat komoditas atau jenis barang (*notes*) yang biasa dikirimkan oleh pemasok terkait (contoh: Sapi Hidup, Karton, Plastik Kemasan, Tali, dsb).

## 3. UI/UX Rules & Behavior
1. **Autofocus Form**:
   - Kolom `name` dibekali atribut `->autofocus()`. Saat pengguna menekan tombol Create, kursor akan otomatis siaga pada input Nama, memangkas kebutuhan klik ekstra.
2. **Penerjemahan Bahasa (Bilingual)**:
   - Seluruh elemen antarmuka (label *Table*, *Form*, *Radio Button*, dan notifikasi) dibungkus menggunakan *Closure* dinamis: `fn() => __('...')`. Memastikan proses pergantian bahasa (*runtime translation*) beroperasi optimal tanpa isu *caching*.
3. **Validasi Wajib Isi (No Default Value)**:
   - **Term of Payment (TOP)**: Nilai bawaan (default `0`) pada input bilangan bulat `top_days` ditiadakan. Kolom ini dipaksa (*required*) untuk diisi guna mencegah data masuk secara tidak disengaja tanpa kejelasan TOP.
   - **Pajak (Tax 11%)**: Status pajak `is_tax_11` sebelumnya menggunakan komponen *Toggle*. Karena sifatnya krusial, komponen diubah menjadi **Radio Button** (Yes/No) tanpa nilai *default* dan diatur menjadi wajib diisi (*required*). Hal ini memaksa *user* untuk secara sadar menentukan status pajak pemasok.
4. **Standarisasi Tombol Aksi Halaman Edit**:
   - Mengikuti *Project Guidelines*, tombol 'Cancel' standar di bagian bawah form dinonaktifkan (*hidden via override getFormActions*).
   - Tombol pembatalan dipindah ke *Header Actions* berwujud tombol **Back** berwarna *gray*, mendampingi tombol aksi **Delete**.
5. **Fitur Ekspor Langsung (*Direct Stream*)**:
   - Implementasi ekspor data (*custom action*) diletakkan pada Header tabel menggunakan library **OpenSpout**.
   - Sistem unduhan bekerja secara seketika (*synchronous stream download*), melewati *Queue/Modal* bawaan Filament Exporter sehingga menyajikan pengalaman *one-click download* file `.xlsx` yang mulus dan cepat.
6. **Search & Filter Interaktif**:
   - Kolom baru `Supplied Goods` diintegrasikan pada komponen tabel dengan sifat *Searchable*. Hal ini memungkinkan pencarian teks (*text filtering*) yang fleksibel untuk memilah supplier berdasarkan komoditas yang disuplainya.


### Pencegahan Duplikasi Data (Unique Validation)
- Semua *field* utama pengenal identitas seperti `name` (dan `code` jika ada) pada form *Create/Edit* telah dilengkapi dengan atribut `->unique(ignoreRecord: true)`. Hal ini bertujuan untuk menangkap kesalahan input data ganda (duplikat) secara elegan (*graceful validation error*) di sisi UI Form, sehingga mencegah *fatal error 500* (Constraint Violation) di level *Database Hosting/Production*.
