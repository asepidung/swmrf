# UI/UX & Logic Documentation: Delivery Order

## 1. Ikhtisar Modul
Modul **Delivery Order** (Surat Jalan) digunakan untuk mencatat pengiriman barang secara resmi ke pelanggan berdasarkan Tally yang telah disetujui. Surat Jalan merupakan bukti sah pengiriman fisik barang yang nantinya akan diteruskan menjadi *Invoice* setelah barang diterima (*Delivery Order Receipt*).

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Dukungan Bilingual**:
   - Seluruh label navigasi, tombol, teks kolom, dan judul _section_ pada antarmuka *Delivery Order* telah menggunakan helper `__()` untuk memastikan lokalisasi antar bahasa berjalan dengan baik.
2. **Penyempurnaan Tombol Print**:
   - Tombol **Print** untuk mencetak dokumen Surat Jalan ditempatkan secara ergonomis di halaman **View** dan **Edit**. Tombol aksi ini tidak lagi memenuhi ruang tabel utama (index).
   - Pada tabel utama (index), nomor dokumen Tally (*Tally Number*) diberi warna sekunder (`info`) serta ketebalan teks (`bold`) yang jelas untuk menandakan bahwa tautan tersebut dapat diklik guna membuka dan mencetak langsung dokumen referensi Tally.
3. **Ekspor Data Excel (OpenSpout)**:
   - Fitur ekspor Excel seketika (tanpa loading _modal_ panjang) telah disematkan di tabel *Delivery Order* menggunakan *library* OpenSpout, mempermudah pelaporan harian armada pengiriman.
4. **Perbaikan Presisi Angka**:
   - Penghitungan otomatis rekapitulasi jumlah berat (Weight) yang ditarik dari hasil pindai Tally sebelumnya sering memicu angka berlebih akibat efek presisi kalkulasi pecahan (*floating-point*). Modul ini telah diperbaiki dengan metode *rounding* (2 desimal), sehingga tampilan rekapitulasi berat lebih bersih.
