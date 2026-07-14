# Modul Repack (Pengemasan Ulang / Repacking)

Modul **Repack** menangani logika *Manufacturing* (Pabrikasi) ringan di mana produk asal (Bahan Baku) dilebur, dikemas ulang, atau dirakit menjadi satu atau banyak produk baru (Barang Jadi). Modul ini sangat vital dalam operasional industri daging untuk mengakomodasi proses pemotongan lanjutan (misal: memotong blok besar daging 25kg menjadi porsi eceran 1kg untuk ritel).

## 1. Arsitektur & Relasi Database
Model `Repack` berdiri sebagai entitas pusat yang menyeimbangkan neraca *Inbound* (Barang Masuk/Hasil) dan *Outbound* (Barang Keluar/Bahan).
- **Repack (Header)**: Menyimpan tanggal eksekusi, identitas lokasi mesin/ruangan (opsional), dan *User* pengawas (*Supervisor*).
- **Source Items** (`HasMany`): Berisi senarai *Products* yang "dikorbankan" atau ditarik dari gudang sebagai bahan mentah. 
- **Result Items** (`HasMany`): Berisi senarai *Products* baru yang diciptakan (disuntikkan ke gudang) sebagai hasil kemasan.
- **Material Usages** (`HasMany`): Log tambahan yang mencatat konsumsi *Packaging* (plastik, mika, label) yang menyertai aktivitas ini.

## 2. Alur Logika (Business Logic)
1. **Neraca Konversi Kesetaraan (Yield Logic)**: Sistem mengimplementasikan algoritma peringatan penyimpangan berat (jika diaktifkan). Secara teoritis, total tonase (Kg) *Source Items* tidak boleh menyimpang terlalu jauh (lebih besar/kecil) dari total tonase *Result Items* + Susut (*Shrinkage/Loss*). Jika penyimpangan melampaui ambang rasional (misal: bahan baku 10kg, hasil jadi 15kg), sistem akan menolak perintah *Save* dengan status "Error Kesalahan Logika Tonase".
2. **Mutasi Ganda Waktu Nyata (Dual-Direction Mutation)**: Pada momen konfirmasi (Tombol Simpan ditekan), sistem memicu proses transaksi *database* dua arah:
   - *Fase 1 (Decrement)*: Menghapus / mengurangi stok gudang untuk semua produk yang berada dalam tabel *Source Items*.
   - *Fase 2 (Increment)*: Menambahkan / memasukkan saldo stok baru ke gudang untuk produk di tabel *Result Items*.
3. **Sinkronisasi Otomatis Konsumsi Material**: Modul ini menembakkan sinyal ke tabel *Material Usage*. Penggunaan kardus atau plastik kemasan (*Packaging*) di dalam sub-tabel Repack akan otomatis dipindahkan menjadi *log* riwayat pada tabel utama penggunaan material (mengurangi saldo *Materials* sekunder di gudang berbeda).
4. **Pembatalan Terproteksi (Reversal Protection)**: Modul Repack bisa dibatalkan jika terjadi kesalahan *entry*. Algoritma akan menengahi dengan berjalan mundur: sistem menarik stok dari barang hasil (Result), dan memunculkan kembali (menambahkan) stok barang bahan (Source). Proses pembatalan ini dikunci jika barang hasil ternyata sudah terlanjur terjual di *Sales Order*.

## 3. UI/UX (Antarmuka Pengguna)
- **Antarmuka Split-Pane (Kiri-Kanan / Atas-Bawah)**: Halaman *Form* dan *View* didesain cerdas untuk menggambarkan perpindahan fisika (transformasi barang). *Repeater* (tabel) untuk "Bahan Baku (Source)" disejajarkan dengan tabel "Hasil Akhir (Result)" baik dalam format bertumpuk atau bersebelahan. Hal ini secara kognitif membantu petugas *input* mengasosiasikan dari mana barang berasal dan kemana barang itu berakhir.
- **Anti-Duplikasi Berlapis**: Di dalam masing-masing *Repeater*, sistem mengaplikasikan fungsi pencegahan duplikasi *dropdown*. Pengguna yang memilih daging *Slice* di baris pertama tabel *Source* tidak akan bisa menemukan lagi nama daging *Slice* tersebut di baris kedua.
- **Subtotal Indikator Progresif**: Komponen kustom *Livewire* memberikan rangkuman kecil secara terus-menerus di tepi layar mengenai "Total Kg Input" versus "Total Kg Output". Jika angka ini selisih, petugas bisa langsung sadar ada *typo* (salah ketik berat timbangan).
- **Notifikasi Lintas Modul**: Kesalahan input (misal: stok bahan baku ternyata habis di gudang) tidak akan membuat halaman kosong kaku (*White Screen*). Skrip antar-sisi mengirimkan sinyal visual berupa balok peringatan (*Toast/Snackbar*) berwarna merah elegan di pojok atas, membacakan secara rinci barang apa saja yang mengalami defisit.
