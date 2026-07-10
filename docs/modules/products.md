# UI/UX & Logic Documentation: Beef (Products) Module

## 1. Ikhtisar Modul
Modul **Beef** (secara internal/sistem disebut *Products*) digunakan untuk mengelola data master daging dan variasinya (Sub-Beef). Modul ini tergabung dalam *ProductsCluster* bersama dengan **Beef Category** (secara internal *ProductCategory*).

## 2. Struktur Database & Model Tetap Dipertahankan
Meskipun secara visual di antarmuka (UI) seluruh label dan terminologi menggunakan nama **"Beef"**, nama *Model* (`Product`), *Table* (`products`), dan *Foreign Keys* yang terkait tetap menggunakan kata `product` atau `product_id`. Pendekatan hibrida (*hybrid labeling*) ini dirancang untuk:
- Menjaga stabilitas relasi dengan modul eksternal (seperti Sales Order, Purchase, Goods Receipt).
- Mencegah *heavy refactoring* yang berpotensi memunculkan *bug* sistemik.

## 3. UI/UX Rules & Behavior
1. **Aturan Field Input (Create/Edit)**:
   - **Urutan**: `Beef Name` diletakkan **di atas** `Beef Code`. Hal ini agar pengguna dapat langsung memasukkan nama sebelum sistem (atau pengguna) berurusan dengan *auto-generated code*.
   - **Autofocus**: Kolom `Beef Name` dibekali atribut `->autofocus()`, sehingga saat *form* dimuat, pengguna bisa langsung mengetik tanpa perlu mengklik kotak *input* terlebih dahulu.
   - **Label Set Active**: *Toggle status* untuk mengaktifkan/menonaktifkan Daging menggunakan label **Set Active** (sebelumnya rancu dengan tulisan *Status*).
2. **Penerjemahan Bahasa (Bilingual)**:
   - Seluruh label pada *Table* dan *Form* menggunakan *Closure* dinamis: `fn() => __('...')`.
   - Modul ini menggunakan konvensi bahasa seperti `__('Beef Name')`, `__('Beef Code')`, dan `__('Main Beef')`.
3. **Tombol Aksi Halaman Edit**:
   - Mengikuti *Project Guidelines*, tombol 'Cancel' bawaan di bagian bawah form ditiadakan (*hidden via override getFormActions*).
   - Tombol **Back** diletakkan di *Header Actions* mendampingi tombol **Delete**.
4. **Fitur Ekspor Langsung (*Direct Stream*)**:
   - Sesuai dengan pembaharuan aturan (*rule update*) di `project.md`, Ekspor tabel Beef menggunakan **Custom Action (OpenSpout)**.
   - Fitur ekspor ini merender dan menyajikan unduhan file `.xlsx` secara seketika (*synchronous stream download*), melewati *Queue/Modal* bawaan Filament Exporter agar pengalaman pengguna jauh lebih cepat (hanya *one-click download*).
5. **Fitur Filter Halaman Index (Tabel)**:
   - **Category Filter**: Pengguna dapat memilah (*filter*) daftar daging berdasarkan kategori. Filter ini dipasang menggunakan komponen `SelectFilter` bereferensi `category_id`.
