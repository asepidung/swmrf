# Modul Price List (Daftar Harga Produk)

Modul **Price List** (Daftar Harga) adalah pengatur kebijakan harga jual (*Pricing Engine*) yang mengontrol nominal uang yang akan dikenakan kepada setiap pelanggan saat pembuatan *Sales Order* (Pesanan Penjualan). Modul ini sangat esensial karena mengakomodasi skema harga bertingkat atau harga preferensial B2B dalam industri pasokan daging.

## 1. Arsitektur & Relasi Database
Model `PriceList` bertindak sebagai payung kebijakan yang mewadahi rincian harga untuk berbagai jenis daging/produk.
- **Tabel `price_lists`**: Menyimpan data identitas kebijakan (contoh: "Harga Reguler Jakarta", "Harga Khusus Supermarket A", "Harga Horeca VIP"). Berisi kolom nama dan status aktif.
- **PriceListItem** (`HasMany`): Menyimpan rincian spesifik mengenai pasangan "Produk X = Rp Y".
- **Product** (`BelongsTo` dari `PriceListItem`): Rujukan produk/daging yang akan diberi label harga.
- **Customer** (`HasMany` / Opsional): Setiap *Customer* (Pelanggan) dapat dikaitkan dengan satu *Price List* bawaan (*Default Price List*) di profil mereka.

## 2. Alur Logika (Business Logic)
1. **Otomatisasi Harga pada Penjualan (Pricing Injection)**: Saat tim *Sales* membuat dokumen *Sales Order* dan memilih Pelanggan "Supermarket A", sistem di latar belakang akan melirik profil pelanggan tersebut, menemukan "Daftar Harga VIP", lalu memaksakan seluruh opsi harga produk di dalam *Sales Order* mengikuti harga diskon yang ada dalam *Price List* ini. Ini mematikan ruang manipulasi manual oleh staf penjualan (*Anti-Fraud*).
2. **Pencegahan Bentrokan Item (Deduplication Logic)**: Ketika administrator mendaftarkan produk dan menetapkan harganya di dalam *Repeater* (rincian item daftar harga), sistem memblokir pilihan produk secara reaktif. Jika "Tenderloin Grade A" sudah dimasukkan ke baris ke-1, produk tersebut lenyap dari opsi pilihan di baris ke-2 ke bawah. Hal ini mencegah *error* di mana satu produk memiliki dua definisi harga dalam satu lembar *Price List*.
3. **Mekanisme Harga Jatuh Cadangan (Fallback Pricing)**: Jika sebuah *Price List* ternyata tidak mengandung informasi harga untuk suatu produk yang sedang dipesan, sistem dirancang untuk secara otomatis kembali menggunakan "Harga Eceran Dasar (Base Price)" yang menempel pada tabel master *Product*. Hal ini menjaga transaksi tetap berjalan lancar.
4. **Validasi Pembaruan (Mass Update Protection)**: Karena berpotensi merusak profitabilitas, penggantian seluruh daftar harga biasanya dikunci dengan hak akses (*Gate / Permissions*), memastikan hanya level Manajer/Direktur yang dapat mengubah "Harga Reguler".

## 3. UI/UX (Antarmuka Pengguna)
- **Desain Matriks Harga Cepat**: Formulir pengisian *Price List* diatur agar optimal untuk pengentrian data cepat. *Repeater* didesain lebar merentang penuh layar (*columnSpanFull*), dan tombol aksi (tambah/hapus) ditempatkan sangat dekat dengan zona pandangan mata (foveal). Hal ini dioptimalkan karena petugas mungkin memasukkan ratusan baris produk sekaligus.
- **Masking Format Mata Uang**: Semua isian nominal harga dilapisi dengan komponen *Money Masking* (misal otomatis menyisipkan titik ribuan "Rp 120.000"), sehingga administrator terbebas dari kesalahan mengetik angka nol yang berlebihan.
- **Status Lencana Global**: Daftar utama (Index) pada dasbor menampilkan status "Aktif/Non-Aktif" dengan warna hijau/abu-abu tajam (*Badge*). Jika daftar harga sudah usang (kedaluwarsa), Manajer cukup melakukan *Toggle/Klik* satu tombol tanpa perlu mengedit isi di dalamnya. Hal ini memuaskan sisi *UX* dengan memangkas jumlah interaksi minimum.
