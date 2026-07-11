# UI/UX & Logic Documentation: Materials Module

## 1. Ikhtisar Modul
Modul **Materials** digunakan untuk mengelola data bahan penolong (non-daging) yang digunakan dalam proses produksi, pengemasan, atau persediaan umum. Modul ini tergabung dalam satu *Cluster* utama bersama dengan **Material Category** dan **Material Unit**.

## 2. Struktur Database Utama
- **Tabel `materials`**:
  - `code` (string): Kode bahan (Dihasilkan secara otomatis, format: `MTR001`).
  - `name` (string): Nama bahan (Wajib, diubah otomatis menjadi *uppercase*).
  - `material_category_id` (foreign key): Kategori bahan (Wajib).
  - `material_unit_id` (foreign key): Satuan ukur bahan (Wajib).
  - `min_stock` (integer): Batas minimum stok peringatan (Wajib, tidak ada *default* 0).
  - `show_in_stock` (boolean): Menentukan apakah barang ini ditampilkan di daftar opname/stok. *Default* `true`.
  - `is_active` (boolean): Status aktif bahan. *Default* `true`.

## 3. UI/UX Rules & Behavior
1. **Aturan Field Input**:
   - `code` (*Item Code*): Karena sistem sudah menghasilkan kode secara otomatis (*auto-generated* di dalam `booted()` model menggunakan `str_pad`), input form untuk kode disembunyikan secara diam-diam (*silent*) saat melakukan pembuatan data baru (*Create*) maupun pengubahan data (*Edit*). Kode ini menggunakan perintah `->visibleOn('view')` sehingga hanya bisa dilihat melalui halaman detail khusus (View).
   - `min_stock`: Sengaja tidak diberikan nilai bawaan (*default*) agar pengguna tidak abai dan membiarkan nilainya 0. Pengguna wajib mengetikkan batas stok minimal sesuai realita.
   - `name`: Teks otomatis diubah menjadi huruf kapital (*uppercase*) sebelum disimpan.
2. **Navigasi Cluster (Tabs)**:
   - Menu modul turunan seperti *Material Category* dan *Material Unit* yang tergabung dalam *MaterialsCluster* ditampilkan sebagai **Top Tabs** (Pills) di bagian atas halaman (menggunakan `SubNavigationPosition::Top` pada masing-masing *Resource*), membuat antarmuka lebih bersih.
3. **Penerjemahan Bahasa (Bilingual)**:
   - Pengaturan Label pada *Table* dan *Form* (untuk *Material*, *Category*, dan *Unit*) menggunakan skema `fn() => __('...')` (Closure). Hal ini untuk menjamin agar penggantian bahasa (*Indonesian / English*) tereksekusi pada saat halaman dimuat (*runtime*) dan tidak tersangkut di *cache*.
4. **Tombol Aksi Halaman Edit**:
   - Mengikuti *Project Guidelines*, tombol 'Cancel' bawaan di bagian bawah form telah disembunyikan menggunakan *override* metode `getFormActions()`.
   - Sebagai gantinya, tombol **Back** diletakkan di *Header Actions* (sebelah kanan atas) mendampingi tombol **Delete**.
5. **Fitur Ekspor**:
   - Ditambahkan tombol **Excel** berwarna hijau (`success`) di antarmuka tabel (*List Materials*) untuk mempermudah pelaporan data bahan penolong (menggunakan *Exporter* bawaan Filament).

## 4. Logika Bisnis (*Business Logic*)
1. **Auto-Generate Code**: Saat `Material` baru disimpan, model akan memicu *event* `creating` dan menghasilkan `code` dengan menghitung ID terakhir di tabel ditambah 1, kemudian menambahkan awalan `MTR` dan *padding* nol (contoh: `MTR001`).
2. **Show In Stock**: Parameter boolean ini krusial untuk memisahkan barang-barang habis pakai (*office supplies*, sabun) yang tidak perlu dihitung (*stock opname*) dengan bahan penolong utama produksi (plastik kemasan, lakban).

## 5. Fitur Filter Halaman Index (Tabel)
- **Kategori (*Category*)**: Pengguna dapat menyaring data tabel bahan berdasarkan kategori melalui `SelectFilter`.
- **Visibilitas Stok (*Show In Stock*)**: Pengguna dapat memisahkan tabel untuk menampilkan hanya barang yang perlu masuk inventaris atau tidak menggunakan `TernaryFilter` (*Yes / No / All*).


### Pencegahan Duplikasi Data (Unique Validation)
- Semua *field* utama pengenal identitas seperti `name` (dan `code` jika ada) pada form *Create/Edit* telah dilengkapi dengan atribut `->unique(ignoreRecord: true)`. Hal ini bertujuan untuk menangkap kesalahan input data ganda (duplikat) secara elegan (*graceful validation error*) di sisi UI Form, sehingga mencegah *fatal error 500* (Constraint Violation) di level *Database Hosting/Production*.
