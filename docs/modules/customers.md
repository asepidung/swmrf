# UI/UX & Logic Documentation: Customers Module

## 1. Ikhtisar Modul
Modul **Customers** digunakan untuk mengelola data pelanggan, termasuk pembagian berdasarkan grup (*Customer Group*) dan segmen (*Customer Segment*). Modul ini terintegrasi erat dengan modul transaksi seperti *Sales Order*, *Invoice*, dan *Receivable*.

## 2. Struktur Database Utama
- **Tabel `customers`**:
  - `name` (string): Nama pelanggan (Wajib).
  - `customer_group_id` (foreign key): Grup pelanggan (Opsional saat input).
  - `customer_segment_id` (foreign key): Segmen pelanggan (Wajib).
  - `address` (text): Alamat lengkap pelanggan (Wajib).
  - `top` (integer): *Term of Payment* / Batas waktu pembayaran dalam hari (Wajib, tidak ada *default* 0).
  - `pic` (string): *Person in Charge* (Opsional).
  - `phone` (string): Nomor telepon (Opsional).
  - `invoice_exchange` (boolean): Tukar faktur (Wajib isi Yes/No).
  - `required_documents` (array/json): Daftar dokumen wajib saat pengiriman (Opsional).
  - `is_active` (boolean): Status aktif pelanggan. *Default* `true`.

## 3. UI/UX Rules & Behavior
1. **Aturan Field Input**:
   - `top` (*Term of Payment*): Sengaja tidak diberikan nilai bawaan (*default*) agar pengguna tidak lupa dan mengosongkan/membiarkan nilainya 0. Pengguna wajib mengetikkan angka.
   - `invoice_exchange`: Berbentuk `Select` (Yes/No) tanpa *default* 'No', agar pengguna diwajibkan untuk memilih secara sadar.
   - `name`: Teks otomatis diubah menjadi huruf kapital (*uppercase*) sebelum disimpan ke database menggunakan `mutateFormDataBeforeCreate` dan `mutateFormDataBeforeSave`.
2. **Tombol Navigasi Halaman Edit**:
   - Tombol pembatalan bawaan (*Cancel Button*) di bawah formulir disembunyikan.
   - Tombol **Back** diletakkan di *Header Actions* (Kanan Atas) berdampingan dengan tombol *Delete*.
3. **Penyembunyian Tombol Delete (Safeguard)**:
   - Tombol **Delete** di halaman Edit akan disembunyikan secara otomatis (`hidden()`) jika pelanggan tersebut telah memiliki minimal satu riwayat *Sales Order*. Hal ini mencegah penghapusan data yang memiliki keterkaitan finansial historis.
4. **Status Inaktif (`is_active`)**:
   - Pelanggan yang sudah tidak bertransaksi tidak boleh dihapus, melainkan cukup *Toggle* status `Active` menjadi mati (Inaktif).
   - Pelanggan Inaktif **tidak akan muncul** di *dropdown* pengisian *Sales Order* baru, namun tetap muncul di pencarian *Invoice*, *Sales Return*, dan riwayat tagihan lainnya demi menjaga keutuhan data transaksi lanjutan.

## 4. Logika Bisnis (*Business Logic*)
1. **Auto-Create Customer Group**:
   - Jika pengguna membiarkan field **Customer Group** kosong saat membuat kustomer baru, sistem akan otomatis membuatkan `CustomerGroup` baru menggunakan nama kustomer tersebut.
   - **Injeksi Data Grup**: Sistem juga akan secara cerdas menyalin nilai `pic` kustomer menjadi `head_office_pic` pada grup, dan menyalin `address` kustomer menjadi `head_office_address` pada grup.

## 5. Fitur Filter Halaman Index (Tabel)
- **Select Filter**: Pengguna hanya dapat melakukan penyaringan (*filter*) data kustomer berdasarkan relasi **Customer Group** dan **Segment**.
- Filter untuk elemen-elemen minor seperti *Invoice Exchange* telah ditiadakan agar antarmuka penyaringan tetap bersih dan relevan dengan segmentasi bisnis.

## 6. Fitur Tambahan UI/UX
1. **Navigasi Cluster (Tabs)**:
   - Menu modul turunan seperti *Customer Group* dan *Customer Segment* yang tergabung dalam *CustomersCluster* ditampilkan sebagai **Top Tabs** (Pills) di bagian atas halaman (menggunakan `SubNavigationPosition::Top` pada masing-masing *Resource*), membuat ruang kerja utama lebih luas.
2. **Export Excel**:
   - Ditambahkan fitur Ekspor tabel Kustomer ke dalam format Excel pada antarmuka *List Customers* dengan warna hijau (`success`), sesuai standar tombol ekspor proyek.
3. **Penerjemahan Bahasa (Bilingual)**:
   - Pengaturan Label pada *Table* dan *Form* menggunakan `fn() => __('...')` (Closure) untuk memastikan penerjemahan label (*Indonesian* / *English*) terjadi tepat saat *render* halaman (*runtime*), bukan saat proses inisialisasi awal.
