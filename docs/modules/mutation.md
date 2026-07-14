# Modul Mutasi (Mutation)

Modul **Mutasi** berfungsi untuk mencatat, melacak, dan memvalidasi perpindahan stok barang antar gudang (*warehouse*) secara internal. Modul ini esensial untuk menjaga akurasi inventaris multi-lokasi tanpa melibatkan transaksi finansial dengan pihak eksternal (bukan pembelian atau penjualan).

## 1. Arsitektur & Relasi Database
Modul ini bertumpu pada model utama `Mutation` dan terhubung dengan beberapa entitas inventaris:
- **From Warehouse** (`BelongsTo`): Gudang asal lokasi barang sebelum dipindahkan.
- **To Warehouse** (`BelongsTo`): Gudang tujuan lokasi barang yang dipindahkan.
- **Mutation Items** (`HasMany`): Rincian persediaan spesifik (berdasarkan `barcode` atau identitas *inventory*) yang dilibatkan dalam perpindahan ini.
- **Inventory** (`MorphTo`/`BelongsTo`): Entitas stok aktual yang sedang dimutasikan. Setiap item pada mutasi merujuk pada fisik barang di gudang.

## 2. Alur Logika (Business Logic)
Proses mutasi memiliki siklus hidup (status) yang dirancang untuk menjaga integritas data saat barang dalam masa transisi (sedang di jalan):
1. **DRAFT (Penyusunan)**: Dokumen mutasi baru dibuat. Admin *warehouse* asal mulai memindai (*scan*) barang-barang yang akan dikirim. Pada tahap ini, status inventaris barang berubah menjadi terkunci (misalnya "In Transit" atau "On Mutation") sehingga tidak dapat dijual atau dimutasikan ke tempat lain secara bersamaan.
2. **SENT (Pengiriman)**: Setelah semua barang divalidasi dan dimuat, status dokumen dikunci menjadi *Sent*. Tanggung jawab barang secara virtual berpindah dari gudang asal ke sistem pengiriman.
3. **RECEIVE (Penerimaan)**: Setibanya di lokasi tujuan, admin *warehouse* tujuan akan melakukan validasi penerimaan barang. Sistem mencocokkan barang yang datang dengan daftar di dokumen mutasi.
4. **COMPLETED (Selesai)**: Setelah verifikasi penerimaan berhasil, sistem mengeksekusi perpindahan akhir: `warehouse_id` pada setiap entitas inventori (daging) diperbarui menjadi gudang tujuan, dan status barang dikembalikan menjadi *Available*.

## 3. UI/UX (Antarmuka Pengguna)
- **Pemindaian Cepat (Scan Mode)**: Modul ini dilengkapi halaman antarmuka pemindaian (Scan) yang dioptimalkan untuk penggunaan *Barcode Scanner*. Hal ini memastikan kecepatan dan akurasi saat memuat barang ke truk tanpa input manual.
- **Status Berwarna (Badge)**: Penggunaan komponen `Badge` dengan indikator warna (Abu-abu untuk *Draft*, Kuning/Oranye untuk *Sent*, dan Hijau untuk *Completed*) pada *Index List* untuk mempercepat monitoring visual.
- **Validasi Pencegahan (Preventive Validation)**: Fitur pencegahan di tingkat UI, seperti menonaktifkan (*disabled*) field "From Warehouse" setelah ada item yang dimasukkan ke dalam daftar mutasi, untuk mencegah konflik data stok di tengah proses pemindaian.
- **Infolist Detail**: Pada halaman `View`, rincian mutasi ditampilkan secara *compact* (ringkas) menggunakan `Infolist` dan menyematkan ringkasan jumlah *item* menggunakan `ViewEntry` yang merender *blade* khusus tanpa membebani memori browser dengan data tabel besar.

## 4. Keamanan Data & Aktivitas
- **Pencegahan Penghapusan Data (Soft Deletes)**: Dilengkapi dengan fitur *Trashed Filter* dan perlindungan `SoftDeletes` untuk menjaga jejak audit dokumen lama.
- **Pencatatan Aktivitas (Activity Logging)**: Memanfaatkan `Spatie\Activitylog` untuk melacak siapa yang membuat, mengirim, atau menerima mutasi beserta waktu persis eksekusinya.
