# Modul Requisition PO (Permintaan Pembelian Internal)

Modul **Requisition PO** bertindak sebagai permohonan pendahuluan atau proposal pra-pembelian (*Purchase Request*). Modul ini digunakan oleh departemen (seperti operasional atau gudang) untuk meminta persetujuan pembelian barang/daging kepada bagian pengadaan (*Purchasing*), sebelum sebuah dokumen *Purchase Order* resmi (*Legal Contract*) diterbitkan ke pihak luar.

## 1. Arsitektur & Relasi Database
Model `RequisitionPo` menjadi jembatan awal dalam alur rantai pasokan masuk:
- **Tabel `requisition_pos`**: Menyimpan identitas pemohon, departemen yang meminta, tanggal permohonan, batas target kedatangan (*Required Date*), dan *User* Pemberi Persetujuan (*Approver*).
- **RequisitionPoItem** (`HasMany`): Menyimpan rincian usulan daftar material atau produk yang ingin dibeli, estimasi jumlah (kuantitas), serta perkiraan biaya sementara (opsional).
- **Purchase Order (PO)** (`HasMany` / Opsional): Jika disetujui, dokumen ini secara logis akan diturunkan atau ditautkan dengan satu atau banyak *Purchase Order* fisik yang menindaklanjuti permintaan ini.

## 2. Alur Logika (Business Logic)
1. **Approval Workflow (Siklus Persetujuan Multi-Lapis)**: Modul ini mengatur gerbang lalu lintas biaya. Dokumen secara bawaan berstatus *Draft* saat diajukan. Hanya pengguna dengan otoritas (Manajer/Direktur) yang dapat menekan tombol `Approve` atau `Reject`. Saat dokumen ditolak, ia terkunci sebagai arsip batal.
2. **Konsolidasi Anggaran (Budget Flagging)**: Sebelum persetujuan diberikan, modul ini mengakumulasi total taksiran belanja seluruh baris barang. Sistem menyediakan visibilitas instan agar pejabat penandatangan bisa mengukur pengeluaran departemen.
3. **Eksekusi Penurunan ke PO (PO Generation)**: Sebagai fitur penunjang produktivitas tingkat dewa, setelah disetujui, modul akan memunculkan sebuah *Action Button* tersembunyi ("Generate Purchase Order"). Sistem akan dengan cerdas mengambil rincian baris dari dokumen ini dan secara otomatis membuat draf *Purchase Order* resmi di modul hilir tanpa perlu bagian Pengadaan (*Purchasing*) mengetik ulang puluhan nama barang.
4. **Pencegahan Penghapusan Liar (Audit Trail)**: Seluruh *draft* permohonan yang ditolak atau disetujui, beserta log siapa pengaju dan penyetujunya (terekam di tabel Log Aktivitas bawaan Filament/Spatie), dijaga ketat agar tidak bisa dihapus (*Hard Delete*) untuk keperluan audit internal (*Fraud Prevention*).

## 3. UI/UX (Antarmuka Pengguna)
- **Badge Status yang Berbicara**: Tabel pendaftaran (*List/Index*) dipenuhi dengan lencana (*Pill Badges*) warna-warni yang mewakili hirarki wewenang: Abu-abu (Draft), Kuning (Menunggu Persetujuan Manajer), Merah Muda (Ditolak), Hijau Segar (Disetujui). Ini merampingkan waktu staf ketika memindai daftar dokumen harian.
- **Read-Only Lock (Pembekuan Formulir)**: Sistem secara reaktif membekukan (*Disable*) seluruh komponen formulir. Ketika dokumen sudah berpindah ke tangan Manajer (status "Menunggu Persetujuan"), tombol simpan dan field isian menghilang dari staf pengaju. Mereka hanya disuguhi form abu-abu (*Read-Only*) yang cantik. Hal ini membangun batasan psikologis bahwa kontrol sekarang berada di pihak lain.
- **Layout Fungsionalitas Cepat (Header-Details)**: Informasi *Meta* dokumen dipadatkan di ruang atas (Tanggal, No Referensi, Departemen) sehingga pandangan mata pengguna langsung tertuju ke *Repeater* tabel di tengah yang menampung jantung utama dokumen: *Item-item* yang ingin dibeli.
- **Tingkat Responsivitas Global (Bilingual & Interaktif)**: Fitur lokalisasi (penerjemahan ke Bahasa Indonesia) diaplikasikan total, membuat tombol peringatan seperti "Approve" (Setuju) atau notifikasi galat terdengar sangat lokal bagi pengguna akar rumput.
