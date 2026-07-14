# UI/UX & Logic Documentation: Delivery Plan

## 1. Ikhtisar Modul
Modul **Plan Delivery** digunakan oleh divisi Distribusi / Pengiriman untuk merencanakan alokasi armada truk dan *Driver*. Melalui modul ini, perusahaan dapat mengelompokkan beberapa *Sales Order* (SO) ke dalam satu rencana perjalanan (*Trip Details*) agar rute logistik lebih terpusat dan mudah dilacak.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Dukungan Bilingual (Translasi Dinamis)**:
   - Mulai dari label navigasi ("Plan Delivery"), status tab aktif ("Active", "History"), hingga _placeholder_ di *Repeater*, semuanya telah disinkronkan dengan lokalisasi `__()` agar antarmuka bisa diubah-ubah bahasanya tanpa hambatan.
2. **Date Range Filter (Silent Filter)**:
   - Karena rencana pengiriman berfokus pada tanggal distribusi, *silent filter* bawaan (dari awal bulan) difokuskan pada kolom `delivery_date`.
   - Modul ini juga memanfaatkan fitur **Tabs** ('Active' dan 'History') dengan filter *query* kustom yang otomatis memisahkan mana pengiriman yang belum selesai dan mana yang merupakan rekam jejak masa lalu.
3. **Clean Repeater UI (Tanpa *Zombie Rows*)**:
   - Sama halnya dengan Repack dan Price List, tabel *inline* atau *Repeater* untuk mendata *"Associated Sales Orders"* dirancang seringkas mungkin. Judul-judul setiap kolom dipusatkan dalam *Grid placeholder* di atasnya, sedangkan input field di dalam rincian *Sales Order* menggunakan parameter `->hiddenLabel()`.
   - Modul ini dipastikan bersih dari injeksi _script masking_ `RawJs` bawaan AlpineJS pada form *repeater*, sehingga aman dari resiko bentrok *Morphdom*.
4. **Tombol "Export Excel" + "Preview"**:
   - Ekspor data tabel diintegrasikan dengan **OpenSpout** untuk kecepatan prima dalam bentuk *Excel*.
   - Tombol tersebut disandingkan dengan harmonis (`ActionGroup`) bersama tombol **Preview** untuk mencetak/melihat laporan distribusi *Driver* dalam bentuk rute (PDF).
