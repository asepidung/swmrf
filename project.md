# Global Rules & Project Overview: Wijaya Meat (SWM)

> [!CAUTION]
> **ATURAN MUTLAK**: Dokumen ini adalah panduan utama (High-Level Guidelines) sekaligus **aturan global mutlak** untuk seluruh pengembangan dan refactoring sistem "Wijaya Meat (SWM)".
> Setiap implementor (termasuk agen AI) **WAJIB** membaca dan mematuhi dokumen ini sebelum mengeksekusi instruksi dari GitHub Issue mana pun.

## 1. Project Overview & Strategi Migrasi

Proyek ini bertujuan memodernisasi sistem ERP "Wijaya Meat (SWM)" yang sebelumnya berbasis Native/Procedural PHP, AdminLTE 3, dan jQuery.
Migrasi dilakukan menggunakan pendekatan **"Strangler Pattern"** secara bertahap.

* **Strategi Database Transisi:** Selama masa migrasi, sistem baru (Laravel) akan berbagi sumber data dengan sistem lama. Dilarang merubah struktur tabel fundamental yang masih dipakai secara aktif oleh sistem lama tanpa instruksi eksplisit dari Issue.
* Seluruh alur pengerjaan dan pembagian tugas diatur serta didelegasikan secara terperinci melalui tiket **GitHub Issues**.

## 2. Tech Stack & Arsitektur Utama

Implementor wajib menggunakan standard stack berikut:

* **Backend:** Laravel 11. Interaksi database wajib menggunakan Eloquent ORM dan Migration (hindari raw SQL procedural).
* **Admin Panel:** Filament v3. Manfaatkan secara maksimal fitur Filament Resource, Page, dan Widget. Hindari pembuatan view blade/HTML manual untuk CRUD standar.
* **Database:** MySQL. Penamaan tabel dan kolom (baru) wajib menggunakan Bahasa Inggris sesuai konvensi Laravel (*snake_case*, *plural*).

## 3. Aturan Standar UI/UX & Modul Global (WAJIB DITERAPKAN DI SEMUA MODUL)

Setiap pengembangan fitur atau penambahan *resource* baru di Filament wajib menerapkan standarisasi berikut:

* **Kenyamanan Entry Data (Ergonomi UI/UX):** Form input harus dirancang untuk kecepatan dan kenyamanan pengguna operasional. Selalu terapkan fungsi `autofocus()` pada *field* pertama di form *Create* atau *Edit*. Susun urutan *field* secara logis dan natural agar pengguna dapat bernavigasi dengan lancar murni menggunakan tombol `Tab` pada keyboard tanpa lompatan kursor yang membingungkan.
* **Default Date Filter (Khusus Transaksional):** Untuk setiap modul transaksional dan pencatatan riwayat, halaman *Index* (tabel data) **wajib** memiliki *Date Range Filter*. Secara *default* (bawaan dari Eloquent *query* awal saat halaman dimuat), data wajib dibatasi mulai dari **tanggal 1 pada bulan berjalan hingga tanggal hari ini**. Pengguna harus tetap bisa mengubah rentang tanggal ini melalui komponen filter di UI.
* **Filter Data Relasional:** Tabel transaksional wajib menyediakan filter *dropdown* relasional yang relevan untuk mempermudah pencarian spesifik (misalnya: filter Nama Customer pada Penjualan, atau Nama Supplier pada Pembelian). Detail filter apa saja yang dibutuhkan akan dijelaskan lebih rinci pada masing-masing GitHub Issue.
* **Audit Trail / Activity Logging (Khusus Transaksional & Data Krusial):** Setiap model yang berkaitan dengan pencatatan transaksi, pergerakan stok, keuangan, serta master data yang sensitif wajib mengimplementasikan *Activity Log*. Gunakan *package* `spatie/laravel-activitylog` agar setiap *event* `created`, `updated`, dan `deleted` terekam otomatis beserta detail *user* yang mengeksekusi dan perubahan datanya (*old/new values*).
* **Fitur Ekspor Komprehensif (Excel & PDF):** Mengingat kebutuhan pelaporan yang esensial, setiap modul atau tabel wajib mengimplementasikan fitur *export* (Excel, PDF, dan opsi Print) menggunakan *Bulk Actions* atau *Header Actions* standar di Filament.
* **Bilingual UI (Dukungan Bahasa):** Seluruh elemen antarmuka mulai dari menu, label input (field), notifikasi/alert, pesan error validasi, hingga teks sistem statis **wajib** mengikuti lokalisasi (Inggris dan Indonesia). Selalu gunakan *helper* bawaan Laravel seperti `__()` dan daftarkan terjemahannya di file `lang/id.json` maupun `lang/en.json`.
* **Auto Redirect to Index:** Setelah pengguna berhasil membuat data baru (Create) ataupun mengubah data (Edit/Update), *workflow* form **harus dialihkan kembali (*redirect*) ke halaman daftar data (Index/List)**. Jangan membiarkan pengguna berdiam di halaman form setelah tombol "Save" ditekan.
* **Format Angka & Mata Uang (UI vs Database):** Pada form input (UI), gunakan *masking* otomatis dengan `RawJs` agar angka diformat secara otomatis saat diketik (contoh: pemisah ribuan otomatis menggunakan titik `.` tanpa desimal untuk IDR). Contoh implementasi: `->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')->numeric()`. Pastikan data disimpan ke database sebagai angka murni.
* **Tampilan Repeater Bersih (Clean Repeater UI):** Di dalam form *Repeater* (misalnya detail item transaksional), sembunyikan label pada setiap baris field menggunakan `->label('')` dan `->hiddenLabel()`. Cukup gunakan `->placeholder()` sebagai penunjuk isian agar tampilan ringkas dan bersih menyerupai form *inline* tanpa label *header* yang berulang.
* **Tata Letak Tombol Aksi (Action Buttons Layout):** Pada halaman *Edit*, tombol 'Cancel' bawaan yang berada di bagian bawah form (di sebelah 'Save changes') harus disembunyikan dengan melakukan _override_ method `getFormActions()`. Sebagai gantinya, letakkan tombol 'Cancel' (dengan style abu-abu) di bagian atas (Header Actions) berdampingan dengan aksi halaman lainnya seperti 'Print' dan 'Delete'.
* **Clickable Rows (UI Tabel Bersih):** Untuk menghemat ruang, jangan tampilkan tombol aksi (Actions) statis untuk Edit di dalam *table list*. Jadikan baris data pada tabel itu sendiri sebagai *clickable row* agar bisa diklik langsung menuju ke *form edit* (menggunakan method `recordUrl()`).
* **Manajemen Hak Akses (Permissions) Terkelompok:** Setiap membuat modul baru, **wajib** mendaftarkan set hak akses baru di *database seeder* beserta kolom `module_name`. Form manajemen hak akses di UI juga wajib ditampilkan secara visual terkelompok berdasarkan modul (Gunakan skema generator dinamis dengan `Section` untuk tiap modul dan `CheckboxList`).
* **Minimalist Dashboard:** Dilarang menampilkan widget non-fungsional bawaan *framework* (seperti `FilamentInfoWidget`) pada *dashboard*. Jaga agar antarmuka hanya memuat informasi fungsional yang berkaitan dengan sistem ERP Wijaya Meat SWM.

## 4. Workflow, Eksekusi Tugas & Komunikasi

* **Standar Penulisan Issue:** Setiap GitHub Issue wajib dijabarkan sedetail dan sespesifik mungkin (*low-level blueprint*). Ketegasan detail ini mutlak diperlukan karena *issue* akan dijadikan acuan kerja langsung oleh *programmer junior* maupun *agen AI* level eksekutor.
* **Konfirmasi Sebelum Eksekusi:** Sebelum mengeksekusi kode, implementor wajib mendiskusikan *issue* yang sedang ditugaskan dengan *Project Owner* dan melakukan konfirmasi hingga tercapai kesepakatan.
* **Alur Wajib Eksekusi (Pre-Execution Flow):** Saat akan mengerjakan *issue* baru, implementor dilarang langsung melakukan *coding*. Anda wajib:
1. Membaca ulang aturan global di `project.md` ini.
2. Membaca riwayat seluruh *issue* sebelumnya untuk memahami konteks dan dependensi sistem yang sudah ada.
3. Mengeksekusi instruksi pada *Issue* target.


* **Evaluasi Pasca-Pembuatan (Post-Execution Review):** Setelah modul atau *issue* disepakati selesai dibuat, implementor wajib meninjau ulang:
* Apakah dokumen *Issue* terkait perlu direvisi untuk menyesuaikan dengan hasil akhir pengembangan?
* Apakah ditemukan aturan main baru yang mengharuskan pembaruan/revisi pada dokumen `project.md` ini?


* **Referensi Legacy Code:** Sebelum mulai menyusun logika bisnis di framework baru, wajib meninjau direktori `legacy/` untuk memahami proses dan aturan bisnis yang sudah ada pada sistem lama. dan didalamnya ada 2 folder versi prosedural yang sudah lengkap modulnya, dan versi laravel yang baru sebagian, utamakan merujuk ke versi laravel dulu baru prosedural jika ada perbedaan
* **Proaktif Berdiskusi & Memberikan Opsi:** Jika terdapat instruksi, alur logika, atau batasan sistem yang ambigu/kurang jelas, implementor (terutama agen AI) **DILARANG mengambil asumsi sepihak**. Anda wajib berhenti mengeksekusi kode, paparkan masalahnya, dan **berikan beberapa pilihan/opsi penyelesaian** agar *Project Owner* dapat memilih jalan yang paling tepat.

## 5. Kualitas Kode & Manajemen Repositori

* **Standar Format Kode:** Seluruh kode PHP wajib ditulis mengikuti standar kebersihan kode (seperti PSR-12).
* **Konvensi Git Branching:** Dilarang melakukan komit langsung ke *branch* utama (`main`/`master`). Setiap eksekusi tugas dari GitHub Issue wajib dikerjakan pada *branch* baru dengan format: `feature/issue-[nomor-issue]` (contoh: `feature/issue-24`).
* **Pull Request (PR):** Setiap *branch* yang selesai harus diajukan sebagai *Pull Request* dan melalui proses *code review* sebelum digabungkan.

## 6. Keamanan Database Utama & Aturan Testing (CRITICAL)

* **Proteksi Database Utama:** Agen AI **dilarang keras** menjalankan perintah pengujian (`php artisan test`) sebelum memverifikasi secara pasti bahwa file `phpunit.xml` menggunakan environment database in-memory (SQLite `:memory:`) atau database testing terpisah. Trait `RefreshDatabase` pada proses *testing* tidak boleh menyentuh database MySQL utama.
* **Larangan Reset Database:** Dilarang mengeksekusi perintah destruktif seperti `php artisan migrate:fresh` pada database utama kecuali diinstruksikan secara eksplisit di dalam Issue. Gunakan `php artisan migrate` standar untuk penambahan modul baru.
* **Kredensial Akun Default Tetap:** Konfigurasi akun *default* untuk *development* tidak boleh diubah, dihapus, atau dimodifikasi oleh AI saat membuat modul atau *seeder* baru. Kredensial berikut wajib dipertahankan dan harus selalu bisa digunakan:
* **Username:** programmer
* **Password:** programmerpassword