# Modul Sales Orders (Pesanan Penjualan)

Modul **Sales Orders (SO)** adalah gerbang utama pendapatan (*Revenue Gate*) di dalam ekosistem sistem ERP ini. Modul bertugas menangkap kesepakatan akhir antara pihak *Sales* dengan *Customer* (Pelanggan), menentukan harga jual, serta menjadi titik tolak yang memicu aktivitas operasional hilir: perintah pengiriman (*Delivery Order*) dan penagihan kas (*Invoice*).

## 1. Arsitektur & Relasi Database
Model `SalesOrder` merupakan simpul (Hub) kompleks yang menarik berbagai Master Data secara krusial:
- **Tabel `sales_orders`**: Menyimpan stempel pesanan (No. Dokumen, Tanggal, *Customer ID*, Jatuh Tempo (*Term of Payment*), Status Pembayaran).
- **SalesOrderItems** (`HasMany`): Menyimpan rincian per-baris nama barang, kuantitas dipesan (Box dan/atau Kg), harga khusus, potongan harga (diskon), dan total bersih baris tersebut.
- **Customer & Price List** (`BelongsTo`): Merujuk secara absolut ke profil Pelanggan dan kebijakan Skema Harga aktif untuk validasi tarif jual.
- **Delivery Orders & Invoices** (`HasMany`): Relasi hilir, di mana SO ini akan "dipecah-pecah" atau disatukan ke dalam dokumen pengiriman logistik (DO) dan Faktur Tagihan.

## 2. Alur Logika (Business Logic)
1. **Harga Penjualan Kuat (Rigid Pricing Injection)**: Sistem melindungi aset dengan mengambil alih hak pengisian harga dari tim lapangan. Ketika tim *Sales* memilih nama Pelanggan, sistem otomatis memindai jenis *Price List* (Daftar Harga) yang mengikat pada Pelanggan tersebut. Jika *Price List* ditemukan, harga akan dipaksakan masuk (*auto-fill*) dan dikunci (*read-only*) ke dalam kolom harga setiap barang. Ini menghancurkan ruang gerak manipulasi mark-up nakal oleh oknum.
2. **Beban Diskon Berlapis**: Modul menyediakan 2 lapisan insentif penjualan. Pertama, diskon parsial di level baris barang (misal: Barang X diskon 5%). Kedua, diskon global dokumen (potongan akhir rupiah) yang ditempatkan di ringkasan paling bawah. Sistem mengalokasikan perhitungan logika secara vertikal dan hirarkis untuk mendapatkan nilai total tagihan mutlak.
3. **Ketersediaan Semu (Virtual Stock Visibility)**: Secara reaktif, jika fungsi ini dinyalakan, staf penjualan dapat melihat angka stok fisik barang di layar (sebagai peringatan) sebelum mereka mengklik 'Simpan'. Modul tidak serta merta memotong stok di saat form SO disimpan (stok murni baru terpotong saat pengiriman DO). Namun SO ini membekukan angka "Stok Dipesan" (*Committed Stock*) di tabel pelaporan untuk membantu gudang.
4. **Lifecycle Kelengkapan Pengiriman (Fulfillment Check)**: Status siklus SO tidak bisa diselesaikan sepihak dengan tangan. Status `Completed` hanya bisa tercapai dengan aman jika mekanisme mesin latar belakang mendeteksi bahwa total Kg barang yang sudah berstatus "Terkirim di dalam DO" menyamai atau melebihi total Kg pesanan di dalam SO ini.

## 3. UI/UX (Antarmuka Pengguna)
- **Perhitungan Otomatis Interaktif Berkecepatan Tinggi**: Segala *field* yang berkaitan dengan angka (Kuantitas, Harga, Diskon Nominal, Diskon Persen) direkayasa menggunakan *Livewire Data Binding* yang agresif. Cukup dengan memindahkan jari/kursor dari kotak isian, layar bawah dan baris tabel (Subtotal & Grand Total) memancarkan angka baru yang terhitung akurat. Ini mengurangi keluhan beban menghitung manual.
- **Masking Mata Uang Standar Bank**: Keseluruhan antarmuka finansial ini dilapisi topeng format mata uang lokal (Rupiah/Desimal). Input "150000" secara visual mekar dengan elegan menjadi "150.000", sehingga mata dengan cepat meraba ketepatan nilai.
- **Struktur Dokumen Korporat yang Kokoh**: Bagian "Summary" (Total Nilai Kotor, Pajak PPN yang ditarik sebagai konfigurasi centang, Diskon, dan Tagihan Bersih) ditempatkan rapi mengelompok pada *Card* mandiri di pojok kanan bawah, berjejer paralel dengan deretan baris barang. Layout standar ERP dunia (*Odoo/SAP*) ini membuat staf akunting merasa familiar.
- **Lokalisasi Menyeluruh**: Bahasa menu, tombol *Checkout*, hingga balok konfirmasi merah hijau ditautkan secara utuh ke mekanisme *Bilingual*, memudahkan pengoperasian operasional perusahaan bagi para *User* di Indonesia tanpa kendala linguistik.

## 4. Spek customer per produk (#579, 21 Oktober 2026)

Spek (gramasi, bentuk potong, kondisi, kemasan, catatan spek) milik **pasangan customer + produk**, bukan milik satu SO. Catatan SO (`note`) tetap untuk hal per order (jumlah, logistik sekali jalan) dan TIDAK dipakai sebagai sumber spek.

- **Master:** tabel `customer_product_specs` (customer_id + product_id unik; `weight_per_pack`, `cut`, `condition` [chill / frozen / chill_aging / frozen_aging], `packaging`, `note`; semua boleh kosong). Dikelola di cluster Customers, menu **Customer Specs**, dengan saringan customer dan produk. Memakai `LogsActivity`.
- **Di baris SO:** bar atas tiap baris menampilkan ringkasan ("Spek: 1 kg/pack · soup 4x4 cm · Chill · vacuum") dan tombol **+ Spek** (belum ada) atau **Spek ✓** (sudah ada). Begitu customer + produk terpilih, spek tetap terisi otomatis. Mengganti customer atau produk di baris mengisi ulang spek-nya (seperti harga dan diskon). Modal spek punya pilihan **Simpan sebagai spek tetap customer ini** (bawaan) atau **Hanya untuk SO ini**. Mengosongkan semua isian lalu menyimpan sebagai spek tetap menghapus spek tetapnya.
- **Salinan:** baris SO menyimpan salinan spek di kolom json `sales_order_items.spec` (nullable). SO lama tidak berubah bila spek tetap diubah kemudian, dan membuka lalu menyimpan ulang SO lama tidak mengisinya otomatis.
- **Tampil di:** cetak SO, layar scan Tally (di bawah nama produk pada tabel ringkasan). Cetak surat jalan TIDAK memuat spek (permintaan Owner, 21 Oktober 2026).
- **Izin:** tidak ada izin baru. Mengisi/mengubah/menghapus spek tetap = `create_sales_orders` ATAU `edit_sales_orders`; melihat = `view_sales_orders` ATAU `view_tallies`. Tulis ke master dijaga di `CustomerProductSpec::saveFixed()` (bukan hanya di tombol), jadi permintaan Livewire langsung tanpa izin ditolak.
- **Tidak dikerjakan:** nama di label (LION/HPM) dan isi otomatis dari catatan SO legacy.
