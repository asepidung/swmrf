# Modul Products (Master Data Produk / Daging)

Modul **Products** (Master Produk) adalah landasan inventaris utama (pustaka pusat) yang menyimpan data semua tipe barang jadi atau setengah jadi (*Finished/Semi-Finished Goods*). Ini merangkum segala hasil karkas, daging *boneless*, jeroan, daging beku box, hingga olahan lanjutan. Tanpa modul ini, seluruh transaksi masuk (Penerimaan, *Boning*) dan transaksi keluar (Penjualan, Surat Jalan) tidak akan bisa berjalan.

## 1. Arsitektur & Relasi Database
Model `Product` menjadi jantung bagi arsitektur inventaris.
- **Tabel `products`**: Mengandung data inti (SKU/Kode, Nama, Deskripsi), konfigurasi fisik produk (Apakah produk tersebut *Catch Weight* yang butuh input ganda Box dan Kg?), dan parameter akuntansi (Harga Modal / HPP standar).
- **Product Category** (`BelongsTo`): Pengelompokan produk secara taksonomi (contoh: "Tenderloin", "Offal/Jeroan", "Imported Meat").
- **Satuan Ukur (UOM)** (`BelongsTo`): Merujuk pada Unit of Measurement sekunder jika digunakan.
- **Relasi Global**: Model ini direferensikan (melalui status ID) di lebih dari 10 modul operasional lainnya, dari *Boning Items* hingga *Sales Order Items*.

## 2. Alur Logika (Business Logic)
1. **Konfigurasi Catch Weight (Metrik Ganda)**: Karakter unik industri daging adalah ketidakpastian berat produk (1 kotak Daging A bisa 20kg, kotak B bisa 21.5kg). Oleh karena itu, *Products* dibekali atribut logika `is_catch_weight` (Ya/Tidak). Apabila produk tersebut dicentang sebagai produk metrik ganda, maka setiap kali *user* akan menjual atau memutasi produk ini di modul lain, antarmuka akan bereaksi dan memaksa *user* untuk menginputkan 2 variabel secara bersamaan: "Jumlah Ekor/Box" DAN "Berat Aktual Timbangan (Kg)".
2. **Kalkulasi Stok Sintetis (Virtual Current Stock)**: Stok fisik tidak disimpan secara statis sebagai angka tunggal di tabel ini (untuk mencegah konflik asinkron). Nilai "Stok Aktual" diturunkan secara sintetis (dijumlahkan) menggunakan kueri cepat pada *database* di bagian belakang, sehingga sistem dapat menjamin angka ketersediaan 100% akurat *real-time* kapan saja diakses.
3. **Penyusunan Nama SKU Otomatis**: Untuk menekan risiko data kotor (redudansi input staf), model mengimplementasikan intervensi kode. Setiap kode SKU yang dimasukkan, meski diketik dengan huruf acak, akan dimutasi otomatis (melalui fitur *Eloquent Mutator*) menjadi seragam berhuruf besar (Kapitalisasi) di *database*.
4. **Proteksi Ketergantungan**: Sama seperti prinsip Master Data lainnya, Produk yang sudah "kotor" (sudah tersangkut transaksi historis) terlindungi oleh batasan *Soft Delete* (Hapus Halus). Jika produk "Daging Wagyu B" sudah tidak dijual, ia cukup diturunkan sakelar statusnya menjadi "Tidak Aktif" agar tidak merusak faktur lama.

## 3. UI/UX (Antarmuka Pengguna)
- **Visualisasi Gambar Resolusi Tinggi**: Halaman formulir menyediakan fitur unggah (*File Upload*) gambar produk. Hal ini dirancang agar antarmuka internal terlihat memukau (*Wow Factor*) seperti aplikasi *E-Commerce*. Gambar ini nantinya terhubung ke lencana melingkar (*Avatar Image*) di halaman *Index* untuk memudahkan navigasi visual.
- **Pengelompokan Form Berbasis Konteks**: Tidak disajikan sebagai tabel raksasa membosankan. Form input menggunakan *Grid Layout* mutakhir. Detail teknis seperti "Opsi Timbangan" dan "Harga Akuntansi" dimasukkan ke *Card* berbeda di sisi kanan layar, sementara identitas inti (Nama, Kategori) ditempatkan di bidang lebar sebelah kiri.
- **Bilingual Interface**: Antarmuka, pemberitahuan error (validasi), serta penamaan kolom secara penuh patuh pada sistem lokalisasi bawaan. Semua terminologi teknis dapat secara instan dikonversi menjadi bahasa sasaran (*Id/En*) tanpa mengubah satupun kode inti.
- **Toggle Responsif**: Modifikasi pengaturan "Aktif/Non-Aktif" atau pengaturan "Daging Timbang" (*Catch Weight*) dikontrol menggunakan *Switch Toggle* bergaya iOS, alih-alih opsi tarik-turun (*dropdown*), yang menjamin tindakan klik yang efisien.
