# UI/UX & Logic Documentation: Tally

## 1. Ikhtisar Modul
Modul **Tally** digunakan di area gudang (Warehouse) untuk mencatat dan memverifikasi barang fisik sebelum dikirim ke pelanggan. Tally biasanya bertindak sebagai lembar periksa (checklist) atau hasil pindai (scan barcode) terhadap produk yang akan dikirim sesuai dengan *Sales Order*.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Dukungan Bilingual Penuh**:
   - Seluruh nama kolom tabel, label filter, teks aksi, dan status dalam tabel Tally telah disesuaikan agar mendukung translasi dinamis menggunakan metode `__()`.
2. **Date Range Filter (Silent Filter)**:
   - Karena merupakan modul transaksional operasional, *Tally* dilengkapi dengan *silent filter* bawaan berdasarkan kolom `created_at`. Data yang ditampilkan pertama kali dibatasi hanya untuk bulan berjalan, guna menghemat *resource* *database*.
3. **Ekspor Excel Langsung (OpenSpout)**:
   - Telah ditambahkan tombol aksi untuk mengekspor daftar Tally langsung ke file **Excel (.xlsx)**. Proses ekspor dilakukan *on-the-fly* menggunakan library OpenSpout yang lebih cepat dan efisien dibandingkan *exporter queue* bawaan Filament.
4. **Tidak Ada Halaman Detail-List Khusus**:
   - Sesuai dengan instruksi *Project Owner*, modul Tally dirancang cukup ringkas dan interaktif (khususnya dengan integrasi *Scan* barcode). Oleh karena itu, modul ini secara eksplisit tidak mengaktifkan dan tidak memerlukan halaman *Detail-List* tersendiri untuk menghemat kerumitan navigasi.
