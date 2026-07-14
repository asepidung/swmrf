# Modul Delivery Order (Surat Jalan Pengiriman)

Modul **Delivery Order (DO)** merupakan modul kunci dalam proses rantai pasok hilir (*Outbound Logistics*). Modul ini berfungsi untuk memvalidasi, mencatat, dan menerbitkan surat jalan resmi atas barang/produk jadi yang dikirimkan kepada pelanggan berdasarkan *Sales Order* (Pesanan Penjualan) atau *Delivery Plan* yang telah disetujui.

## 1. Arsitektur & Relasi Database
Model `DeliveryOrder` menghubungkan berbagai entitas krusial dalam siklus penjualan:
- **Sales Order** (`BelongsTo`): Pesanan awal yang mendasari pengiriman ini. DO tidak bisa eksis tanpa adanya SO.
- **Customer** (`BelongsTo`): Entitas penerima barang (ditarik otomatis dari relasi SO).
- **DeliveryItems** (`HasMany`): Rincian aktual produk dan kuantitas yang dimuat ke dalam armada pengiriman.
- **Vehicle & Driver** (`BelongsTo`): Pencatatan identitas logistik (plat nomor armada dan nama pengemudi) yang bertanggung jawab atas pengiriman.
- **Invoice** (`HasOne` / `HasMany`): DO yang berstatus selesai/terkirim (*Delivered*) akan menjadi basis fundamental untuk pembuatan Faktur Tagihan.

## 2. Alur Logika (Business Logic)
1. **Validasi Kuantitas Terbuka (Open Quantity)**: Saat DO dibuat, sistem tidak mengizinkan pengiriman kuantitas melebihi sisa pesanan (*backlog*) di *Sales Order*. Jika SO memesan 100 kg dan 40 kg sudah dikirim sebelumnya, maksimal input di DO baru ini hanya 60 kg.
2. **Pengurangan Stok Otomatis (Stock Depletion)**: Ketika DO berstatus *Shipped* atau *Delivered*, modul ini memicu fungsi *inventory mutator*. Sistem secara otomatis memotong stok produk dari gudang (*Warehouse*) berdasarkan *DeliveryItems* secara *real-time*.
3. **Penguncian SO (Status Lifecycle)**: Jika jumlah keseluruhan barang yang terkirim di DO sudah memenuhi total kuantitas SO, sistem secara otomatis menggerakkan status SO dari `Processing` menjadi `Completed`.
4. **Pembatalan yang Aman (Safe Reversal)**: Jika DO dibatalkan atau dihapus, stok yang sebelumnya terpotong akan dikembalikan (*restored*) ke gudang, dan status/kuantitas terbuka SO akan disesuaikan mundur.

## 3. UI/UX (Antarmuka Pengguna)
- **Otomatisasi Input Form (Auto-Fill)**: Saat pengguna memilih *Sales Order* target di bagian *Header* DO, sistem (menggunakan metode *reactive* / `live()`) langsung menarik profil *Customer* serta menyalin rincian *items* yang ada di SO langsung ke *Repeater* tabel DO. Ini menghemat 90% waktu *data entry*.
- **Peringatan Kuantitas Visual**: *Field* input kuantitas di dalam tabel DO diberi limit/batas atas secara dinamis (berdasarkan sisa stok gudang dan sisa *order*). Jika pengguna mencoba memasukkan angka di atas batas, sistem akan memblokir input dan mengeluarkan peringatan *Error* merah seketika.
- **Status Lifecycle Labeling**: Modul DO banyak menggunakan lencana (*Badge*) warna-warni untuk menunjukkan status pengiriman: *Draft* (Abu-abu), *In Transit/Shipped* (Biru/Kuning), *Delivered* (Hijau). Hal ini membuat staf gudang bisa memantau ratusan pengiriman dengan lirikan mata.
- **Dukungan Pencetakan (Print Layout)**: DO dilengkapi dengan *Custom Action* yang menembak ke jendela *Print/PDF*, didesain khusus dengan format rapi (terdapat blok tanda tangan Penerima, Sopir, dan Admin) untuk memfasilitasi birokrasi cetak kertas konvensional di lapangan.
