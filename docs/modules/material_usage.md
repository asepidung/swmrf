# Modul Material Usage (Pemakaian Material)

Modul **Material Usage** adalah modul pengelolaan stok non-daging yang mencatat secara spesifik penarikan atau pemakaian barang-barang pendukung produksi (seperti kotak karton, lakban, plastik vakum, label, atau bumbu) dari gudang ke lantai produksi. Modul ini menjadi fondasi untuk kontrol biaya operasional (HPP tambahan) dan ketertelusuran barang (*Traceability*).

## 1. Arsitektur & Relasi Database
Model `MaterialUsage` bertindak sebagai sentral transaksi *outbound* (keluar) untuk kategori barang tipe *Material*.
- **Tabel `material_usages`**: Menyimpan informasi *header* seperti Tanggal Pemakaian, Departemen yang meminta, Penanggung Jawab (*PIC*), dan Catatan Khusus.
- **MaterialUsageItem** (`HasMany`): Rincian baris dari masing-masing jenis material yang dipakai beserta kuantitasnya.
- **Material / Inventory** (`HasMany` / Mutasi): Berelasi langsung dengan tabel stok master. Pemakaian yang disahkan akan mengurangi saldo stok di tabel master.
- **Relasi Balik (Reverse Relation)**: Pemakaian material ini seringkali dipicu dari dalam modul produksi lain (seperti modul *Boning* atau *Repack*), sehingga dalam kondisi tertentu, tabel ini akan berelasi *Polymorphic* terhadap dokumen sumber pemanggilnya.

## 2. Alur Logika (Business Logic)
1. **Validasi Saldo Stok Tersedia (Stock Availability Constraint)**: Sistem menerapkan logika ketat saat pemakaian di-*input*. Kuantitas material yang dicatat tidak boleh melebihi saldo *On Hand* (stok fisik yang ada) di gudang. Jika *user* mencoba meng-*input* 100 *pcs* padahal stok hanya 90 *pcs*, transaksi akan dibatalkan (*rejected*) dengan peringatan untuk mencegah stok bernilai negatif (minus).
2. **Kalkulasi Biaya Pemakaian (Cost Apportionment)**: Di latar belakang, sistem mengambil Harga Pokok (HPP) dari profil master *Material* untuk mengalikan kuantitas yang ditarik. Total biaya ini selanjutnya akan diakumulasikan sebagai Biaya *Overhead* / Tambahan Produksi (Biaya Pengemasan) pada pembukuan akuntansi.
3. **Trigger Pengurangan Stok Otomatis**: Saat dokumen ini disimpan secara final, fungsi *inventory mutator* akan langsung (*real-time*) mengeksekusi perintah pengurangan (decrement) stok di tabel gudang. 
4. **Pencegahan Kunci Ganda (Double Lock)**: Jika dokumen pemakaian ini adalah hasil *generate* otomatis dari modul produksi hilir (contoh: *Boning*), maka field dan dokumen ini dikunci (*Read-Only*) agar tidak bisa diubah seenaknya. Modifikasi hanya bisa dilakukan dari modul sumbernya.

## 3. UI/UX (Antarmuka Pengguna)
- **Repeater dengan Cek Duplikasi**: Pada saat pengguna mengklik tombol *Add Material* di dalam komponen *Repeater*, sistem menerapkan fungsi *disable selected options*. Artinya, material A yang sudah dipilih di baris ke-1 akan menjadi nonaktif (*disabled* / abu-abu) pada daftar *dropdown* di baris ke-2, mencegah penginputan ganda pada material yang sama.
- **Live Stock Indicator**: Meskipun form fokus pada pemakaian, saat staf gudang memilih *dropdown* nama material, antarmuka dapat dikonfigurasi (melalui komponen deskriptif atau *reactive hint*) untuk menampilkan saldo stok saat ini ("Sisa Stok: 90 pcs"), membantu *user* mengambil keputusan pengisian form secara mandiri.
- **Layout Minimalis**: Karena bersifat administratif cepat, form menggunakan kombinasi *Section* yang sempit dan berstruktur 2-kolom. Semua aksi *Add/Remove/Reorder* pada item ditempatkan rapat untuk efisiensi ruang layar (*screen real estate*).
- **Notifikasi dan *Validation State***: Warna merah (untuk *error*) dan hijau (untuk sukses) diaplikasikan sesuai standar *feedback* sistem ERP, memastikan *user* memahami apakah operasi penarikan material mereka sukses tercatat atau dibatalkan akibat defisit stok.
