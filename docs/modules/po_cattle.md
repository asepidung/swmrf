# Modul Purchase Order Cattle (PO Sapi Hidup)

Modul **PO Cattle** (Pesanan Pembelian Sapi Hidup) merupakan langkah paling pertama dalam siklus rantai pasokan bahan baku daging (RPH). Dokumen kontrak ini diterbitkan oleh bagian Pengadaan (*Purchasing*) dan ditujukan kepada peternak atau *Supplier* sapi sebagai landasan hukum pemesanan sebelum armada kedatangan (*Cattle Receiving*) dieksekusi.

## 1. Arsitektur & Relasi Database
Berdasarkan model `PoCattle`, modul ini merupakan struktur induk (*Parent Header*) untuk kesepakatan jual-beli ternak.
- **Supplier** (`BelongsTo`): Merujuk pada data profil pemasok atau *Feedlot* (Peternakan) yang dituju.
- **PoCattleItem** (`HasMany`): Rincian estimasi jenis sapi (kelas), kuantitas ekor, taksiran berat (opsional), dan harga kesepakatan per-kg atau per-ekor.
- **CattleReceiving** (`HasMany` / Turunan): Saat sapi secara fisik tiba, dokumen penerimaan akan mereferensikan nomor seri PO ini sebagai dasar validasi manifes.

## 2. Alur Logika (Business Logic)
1. **Pengendalian Kesepakatan Harga (Contract Logic)**: PO Sapi Hidup menetapkan metode penagihan (contoh: *Beli Timbang Hidup* atau *Beli Karkas/Meat Bone*). Harga kesepakatan yang dikunci di sini tidak boleh dimodifikasi sepihak saat sapi ditimbang secara fisik di modul *Weighing*. Ini mencegah kebocoran atau manipulasi nilai tagihan Hutang Dagang (*Accounts Payable*).
2. **Lifecycle Status (Workflow)**: Dokumen ini memiliki rantai status baku, dari `Draft` → `Pending Approval` → `Approved/Active` → `Completed/Closed`. 
3. **Peringatan Toleransi Kedatangan**: Sistem melacak jumlah ekor sapi yang sudah tiba (di modul *Cattle Receiving*) versus pesanan di PO ini. Jika *Purchasing* memesan 100 ekor, dan yang tiba baru 80, PO akan bertahan di status `Partial`. Jika kedatangan melampaui 100 ekor, sistem akan membunyikan mekanisme alarm atau menolak penerimaan baru sampai PO di-*amend*/direvisi.
4. **Pembatalan Bersyarat (Reversal Safety)**: Fitur pembatalan (*Void/Cancel*) dokumen PO akan terkunci secara absolut apabila telah terdeteksi setidaknya 1 (satu) ekor sapi yang menginduk pada nomor seri dokumen ini. Hal ini menjaga integritas penagihan hutang kepada pemasok.

## 3. UI/UX (Antarmuka Pengguna)
- **Tampilan Berbasis Dokumen Legal**: Layout formulir (*Form Layout*) didesain menyerupai selembar faktur kertas. *Header* berada di kotak atas (Supplier, Tanggal, Mata Uang, Term of Payment), sedangkan *Items* berada di *Repeater* (tabel dinamis) di bagian tengah. Di pojok kanan bawah terdapat ringkasan Total Harga dan Pajak.
- **Otomatisasi Hitung (Real-time Subtotal)**: Saat bagian *Purchasing* mengetik harga estimasi per ekor atau per kg dan memasukkan kuantitas (jumlah ekor sapi), skrip interaktif *Livewire* (`live(onBlur: true)`) akan seketika mengkalkulasi ulang *Subtotal* tiap baris dan merekapitulasi total keseluruhan dokumen tanpa layar perlu di-*refresh*.
- **Pemilihan Supplier dengan Pencarian**: Karena jumlah *Supplier* bisa ratusan, komponen pemilihan (*Select*) menggunakan fitur pencarian dinamis (kolom pencarian internal) sehingga nama PT atau peternak bisa diketik manual untuk difilter secara instan.
- **Dukungan Pencetakan Standar Industri**: Modul ini menanamkan *Action/Button* untuk mengekspor dokumen persis seperti bentuk aslinya menjadi berkas *PDF* siap cetak (*Print-Ready*), lengkap dengan form kosong untuk kolom Tanda Tangan Manajer dan Pemasok, memudahkan operasional lapangan.
