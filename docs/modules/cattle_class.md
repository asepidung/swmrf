# Modul Cattle Class (Kelas Sapi)

Modul **Cattle Class** merupakan komponen fondasi dalam sistem Master Data yang digunakan untuk mengkategorikan dan mendefinisikan kelas/jenis kelamin ternak (misalnya: *Steer*, *Heifer*, *Cow*, *Bull*). Modul ini sangat vital karena klasifikasi sapi berdampak pada penentuan harga, kualitas karkas, dan analisis data produksi hilir.

## 1. Arsitektur & Relasi Database
Modul ini bertumpu pada model `CattleClass`. Walaupun strukturnya sederhana, tabel ini menjadi rujukan (*foreign key*) yang sangat krusial bagi modul lain:
- **Tabel `cattle_classes`**: Hanya berisi ID, nama kelas (`name`), dan stempel waktu.
- **Relasi ke Modul Lain**: Model ini diwariskan ke `CattleReceiving` dan `CattleWeighing` untuk menandai identitas biologis setiap individu sapi yang masuk ke RPH (Rumah Potong Hewan).

## 2. Alur Logika (Business Logic)
1. **Pusat Referensi (Single Source of Truth)**: Kelas sapi dikonfigurasi di satu tempat. Perubahan nama kelas di modul ini akan langsung tercermin secara global pada seluruh dokumen *Purchase Order*, *Receiving*, dan *Weighing*.
2. **Standardisasi Data**: Untuk menjaga konsistensi laporan pencarian (mencegah duplikasi akibat *case-sensitive*), *business logic* pada model memaksa (*mutator*) setiap nama kelas yang dimasukkan untuk diformat menjadi huruf kapital secara otomatis (*uppercase*) sebelum disimpan ke dalam *database*.
3. **Pencegahan Penghapusan Data (Hard Delete)**: Karena posisinya sebagai referensi absolut, penghapusan pada *Cattle Class* akan ditolak oleh *database* (melalui *Foreign Key Constraint*) jika kelas tersebut sudah pernah digunakan oleh sapi yang terdaftar dalam sistem.

## 3. UI/UX (Antarmuka Pengguna)
- **Manajemen Simpel (CRUD Tunggal)**: Karena entitas datanya sangat minim, UI dirancang sangat sederhana menggunakan form standar Filament tanpa *tabs* atau *steps*. Admin dapat menambahkan kelas sapi hanya dalam waktu kurang dari 5 detik.
- **Validasi Wajib**: Form dilengkapi dengan validasi *Required* dan pengecekan unik (*Unique Validation*) agar tidak ada administrator yang memasukkan kelas dengan nama ganda.
- **Bilingual Terintegrasi**: Seluruh antarmuka (*Resource*, Tabel, Form, Navigasi) telah dibungkus dengan metode terjemahan `__()` sehingga dapat beradaptasi ketika *user* mengganti bahasa preferensi dari Inggris ke Indonesia.
