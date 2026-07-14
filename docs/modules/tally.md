# Modul Tally (Pencatatan Rincian Barang)

Modul **Tally** (Pencatatan Rinci) bukanlah sebuah dokumen operasional independen yang berdiri sendiri. Ini adalah sub-sistem atau alat utilitas tingkat lanjut (*Advanced Utility Tool*) yang ditautkan ke dalam modul-modul transaksi besar seperti *Goods Receipt*, *Delivery Order*, atau *Repack*. Fungsinya adalah mencatat berat dan spesifikasi per satu kotak / koli / *pieces* secara individual untuk barang berkarakter *Catch Weight*.

## 1. Arsitektur & Relasi Database
Model `Tally` bersifat parasitik (mendampingi entitas lain) secara spesifik melalui *Polymorphic Relations* atau ditautkan langsung ke baris item (`Items Table`) dari sebuah dokumen transaksi.
- **Tally Records**: Baris *database* yang menyimpan nilai numerik tunggal (misal: "Kotak Ke-1 = 20.5kg", "Kotak Ke-2 = 19.8kg", "Kotak Ke-3 = 21.1kg").
- **Tally Sheet (Induk)**: Dokumen kontainer yang menyatukan seluruh baris catatan di atas.
- **Relasi Pemanggil (Host Relation)**: Baris hasil kalkulasi akhir akan disuntikkan ke kolom "Total Bobot" dari tabel pemanggil (Contohnya, *Delivery Order Items*).

## 2. Alur Logika (Business Logic)
1. **Rekapitulasi Agregat Otomatis (The Summation Engine)**: Ketika seorang staf mencatat 50 kotak daging satu per satu dalam selembar dokumen *Tally*, sistem di latar belakang secara agresif menghitung total jumlahan matematika dari 50 kotak tersebut. Hasil hitungan ini akan disuplai secara paksa (timpa/paksa masuk) ke kolom *Total Kuantitas* dan *Total Berat Akhir* di dokumen transaksi induknya (seperti Surat Jalan), sehingga tidak ada celah untuk manipulasi total berat.
2. **Pencegahan Penghapusan Data (Hard Link Protection)**: Ketika selembar *Tally* telah disetujui, nilainya menyatu secara genetik ke transaksi. Staf tidak dapat memanipulasi baris *Tally* jika dokumen induk (misal *Sales Order*) telah memasuki fase Penagihan (*Invoiced*) atau Selesai (*Closed*).
3. **Standar Presisi Ekstrem**: Sistem mengaplikasikan penguncian tipe data presisi pada pangkalan data (misal: `decimal(10,2)`). Hal ini memastikan bahwa berat daging hingga gram terakhir (dua angka di belakang koma) tidak akan dibulatkan ke bawah yang bisa berakibat kerugian harga (mengingat daging Wagyu sangat mahal per gramnya).

## 3. UI/UX (Antarmuka Pengguna)
- **Komponen Input Barcode & Rapid Entry**: Mengingat pencatatan ini berulang sangat cepat, antarmuka direkayasa untuk kelancaran ekstrem. Input angka tidak dibebani *dropdown* berlebih. Setelah pengguna mengetik "20.5" dan menekan tombol *Enter*, fokus kursor layar akan secara otomatis diciptakan melompat ke baris baru di bawahnya. Ini merupakan implementasi fundamental UX *Data Entry* setara aplikasi *Desktop*.
- **Indikator Progres (*Progress Summary*)**: Di puncak halaman, aplikasi secara *real-time* menyorot total sementara kotak yang diproses ("Count: 5") dan total bebannya ("Weight: 100.4 Kg") menggunakan komponen huruf tebal dengan lencana dinamis yang selalu diperbarui setiap tombol ketik dilepaskan (menggunakan intervensi skrip kustom *Livewire Reactive Polling* atau eksekusi *hook* JS lokal).
- **Format Label Barcode (Siap Cetak)**: Data *Tally* yang rinci menyediakan titik *trigger* cetak cepat. Admin bisa menembakkan data ini ke *Thermal Printer* atau mencetak *Tally Sheet* vertikal panjang. Elemen desainnya dibuat memanjang dan sederhana, dirancang agar ramah dicetak pada kertas kertas bon.
- **Dukungan Lintas Bahasa (*Bilingual*)**: *Title* pada form (seperti "Berat Kotak" atau "Total") tersambung ke kamus terjemahan, sehingga memudahkan mandor gudang yang mungkin beroperasi dengan instruksi mesin non-Bahasa Inggris.
