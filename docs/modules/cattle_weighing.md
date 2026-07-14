# Modul Cattle Weighing (Penimbangan Sapi)

Modul **Cattle Weighing** menangani proses pencatatan bobot individu setiap sapi, mengidentifikasi kelas biologis sapi, dan mengeksekusi integrasi dengan sistem inventaris stok hidup. Modul ini merupakan titik transisi di mana sapi berubah dari "hewan yang baru tiba" menjadi "aset inventaris yang siap dipotong".

## 1. Arsitektur & Relasi Database
Berbasis model `CattleWeighing`, modul ini memiliki relasi dengan entitas berikut:
- **Cattle Receiving** (`BelongsTo`): Rujukan ke manifes kedatangan truk yang membawa sapi tersebut.
- **Cattle Class** (`BelongsTo`): Kategorisasi jenis sapi (misal: *Steer*, *Heifer*).
- **Supplier** (`BelongsTo`): Pemilik asal sapi tersebut.
- **Beef Stock** (`MorphOne`): Entitas *Inventory* yang tercipta secara otomatis saat sapi berhasil ditimbang.

## 2. Alur Logika (Business Logic)
1. **Ekstraksi Data Kedatangan**: Petugas memilih manifes kedatangan (*Receiving*). Sistem akan otomatis menarik data *Supplier* agar petugas timbang tidak perlu memasukkan data secara ganda.
2. **Pencatatan Fisik**: Setiap sapi dimasukkan ke timbangan tunggal. Petugas mencatat *Ear Tag* (ID RFID/Visual sapi), *Bobot Hidup* (kg), dan mengklasifikasikan kelas biologisnya (*Cattle Class*).
3. **Injeksi Inventaris Otomatis**: Ketika tombol *Submit/Save* ditekan, modul ini memicu *Observer* (atau *Action Lifecycle*). Sistem secara instan (*real-time*) membuat *record* baru pada tabel *Inventory/Beef Stock* dengan status stok "Live" (Hidup).
4. **Validasi Unik Ear Tag**: Sistem mencegah adanya nomor *Ear Tag* yang identik dalam satu *batch* kedatangan, memastikan akurasi ketertelusuran (*traceability*) hingga daging dipotong.

## 3. UI/UX (Antarmuka Pengguna)
- **Input Numerik Spesifik**: Field timbangan (Bobot) disesuaikan menggunakan *masking* numerik agar petugas hanya dapat menginput format angka yang valid (dengan batas desimal tertentu) mencegah *typo* pada data krusial.
- **Readonly Inheritance**: *Field* `Supplier` diatur sebagai *Read Only* (*disabled*) setelah data *Receiving* terpilih. Hal ini menunjukkan arah aliran data (turunan) yang jelas, sehingga *user* tahu bahwa field tersebut tidak boleh (dan tidak bisa) dimodifikasi secara sepihak di luar dokumen hulu.
- **Indikator Label Visual**: Penggunaan *badge* atau status warna pada tabel memudahkan admin untuk membedakan kelas sapi secara visual tanpa harus membaca detail teks satu per satu.
- **Dukungan Operasi Skala Besar (Bulk Actions)**: Dilengkapi dengan fitur pemrosesan ganda (*Export* massal, *Soft Delete* massal) dengan perlindungan *soft-delete* berlapis untuk menghindari hilangnya riwayat aset.
