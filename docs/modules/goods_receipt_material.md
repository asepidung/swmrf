# Modul Goods Receipt Material (Penerimaan Barang - Material)

Modul **Goods Receipt Material (GRM)** menangani proses validasi dan pencatatan kedatangan barang berupa *Material Pendukung* (seperti plastik, tray, kardus, bumbu, alat kerja) dari pemasok (*Supplier*). Modul ini mencocokkan barang yang diterima secara fisik di gudang logistik terhadap *Purchase Order* (PO) yang dibuat oleh bagian pengadaan (Purchasing).

## 1. Arsitektur & Relasi Database
Model `GoodsReceiptMaterial` berelasi erat dengan lini *Purchasing* dan *Inventory* barang non-daging:
- **Purchase Order (PO)** (`BelongsTo`): Rujukan dokumen pemesanan. GRM tidak dapat diciptakan tanpa dasar hukum PO yang sah.
- **Supplier** (`BelongsTo`): Data *vendor* pemasok material (otomatis diwariskan dari PO).
- **GoodsReceiptMaterialItem** (`HasMany`): Rincian aktual material, kuantitas, dan harga yang tiba di gudang.
- **Material Stock (Inventory)** (`HasMany` / Mutasi): Entri pada GRM yang disetujui akan melahirkan *record* stok material baru di gudang.

## 2. Alur Logika (Business Logic)
1. **Validasi Blind Receiving / PO Matching**: Saat staf gudang membuat GRM, mereka memilih nomor PO. Sistem otomatis menarik data material yang *seharusnya* tiba. Staf gudang bertugas memasukkan "Kuantitas Aktual" yang tiba secara fisik. 
2. **Kalkulasi Selisih (Discrepancy Check)**: Jika Kuantitas Aktual lebih kecil dari Kuantitas PO (Pengiriman Parsial/Kurang), status PO akan bertahan pada `Partially Received`. Jika kuantitas cocok atau melebihi (dengan batas toleransi), status PO bergeser ke `Completed`.
3. **Injeksi Stok Final (Stock Addition)**: Saat dokumen GRM dikonfirmasi/disimpan (status *Approved/Received*), barulah sistem menembakkan fungsi mutasi (*increment*) yang menambah saldo stok (*Quantity on Hand*) pada tabel master `Materials`.
4. **Kunci Audit Pembatalan**: Karena dokumen ini mengubah saldo stok global, fitur penghapusan (Delete) dikunci jika stok barang tersebut sudah dipakai di tempat lain. Jika perlu direvisi, dokumen harus di-*void* (dibatalkan) yang mana akan menjalankan fungsi *decrement* (menarik kembali) saldo stok yang sempat ditambahkan.

## 3. UI/UX (Antarmuka Pengguna)
- **Tarik Otomatis (Auto-Populate Data)**: Pemilihan PO pada *Header* menggunakan metode `reactive()`. Setelah dipilih, *Repeater* (daftar material) di bagian bawah langsung terisi dengan data *Items* dari PO tersebut. Pengguna hanya perlu mengedit field kuantitas jika ada ketidaksesuaian.
- **Read-Only Lock pada Harga**: *Field* Harga Beli (*Unit Price*) bersifat *Disabled/Read-Only* bagi staf gudang penerima. Ini mencegah mereka mengubah harga kesepakatan PO seenaknya, karena tugas mereka murni pada perhitungan fisik barang.
- **Peringatan Toleransi Ganda**: Jika petugas mengisi kuantitas aktual lebih besar dari yang dipesan di PO (terjadi pengiriman berlebih), form akan memberikan notifikasi warna peringatan (kuning/merah) untuk meminta validasi ganda, karena *over-receiving* akan berdampak pada beban tagihan (Hutang).
- **Format Tanggal dan Referensi Eksternal**: Terdapat *field* khusus "Surat Jalan Pemasok" (*Supplier DO Number*) dan "Tanggal Terima". Penempatan elemen ini ditaruh berdekatan di area *Header* menggunakan *Grid* 2 kolom agar staf gudang bisa menyalin data dari secarik kertas surat jalan (*hardcopy*) dengan nyaman.
