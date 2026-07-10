# UI/UX & Logic Documentation: Cattle Class Module

## 1. Ikhtisar Modul
Modul **Cattle Class** merupakan bagian dari Master Data yang digunakan untuk mendefinisikan kelas atau kategori dari hewan ternak (sapi) yang dikelola di dalam sistem. Modul ini bersifat sederhana dan berfokus pada manajemen nama kelas ternak.

## 2. Struktur Database
- **Tabel `cattle_classes`**:
  - `name` (string): Nama kelas sapi (Wajib, diubah otomatis menjadi *uppercase* di form).

## 3. UI/UX Rules & Behavior
1. **Autofocus Form**:
   - Kolom `name` dibekali atribut `->autofocus()`. Saat pengguna menekan tombol Create, kursor akan langsung berada pada *input* Nama, sehingga mempercepat proses *data entry*.
2. **Penerjemahan Bahasa (Bilingual)**:
   - Seluruh elemen antarmuka (label *Table* dan *Form*) menggunakan *Closure* dinamis: `fn() => __('...')`.
   - Hal ini memastikan terjemahan antar bahasa beroperasi lancar (*runtime translation*) tanpa risiko *cache-trap*.
3. **Standarisasi Tombol Aksi Halaman Edit**:
   - Menyelaraskan dengan tata letak modul lainnya, tombol 'Cancel' bawaan di bagian bawah form telah dihilangkan (*hidden via override getFormActions*).
   - Fungsi pembatalan digantikan dengan tombol **Back** berwarna *gray* yang diposisikan di *Header Actions* mendampingi tombol **Delete**.
4. **Fitur Ekspor Langsung (*Direct Stream*)**:
   - Diimplementasikan sesuai standar terbaru: Penggunaan *Custom Action* ekspor tabel Cattle Class ke format `.xlsx` menggunakan library **OpenSpout**.
   - Sistem unduhan seketika ini (*stream download*) menghindari *polling modal* atau antrean antarmuka *Queue* yang lama, dan memberikan pengalaman klik-unduh (*one-click download*) secara *seamless*.
