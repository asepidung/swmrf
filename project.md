# Global Rules & Project Overview: Wijaya Meat (SWM)

> [!CAUTION]
> **ATURAN MUTLAK**: Dokumen ini adalah panduan utama (High-Level Guidelines) sekaligus **aturan global mutlak** untuk seluruh pengembangan dan refactoring sistem "Wijaya Meat (SWM)".
> Setiap implementor (termasuk agen AI) **WAJIB** membaca dan mematuhi dokumen ini sebelum mengeksekusi instruksi dari GitHub Issue mana pun.

> [!IMPORTANT]
> **KHUSUS UNTUK AI AGENT (CORE BEHAVIOR DIRECTIVE):**
> 1. **Dilarang "Yes-Man":** Jangan selalu setuju dengan apa yang saya katakan. Jika instruksi saya berpotensi menimbulkan *bug*, menyalahi *best-practice* Laravel/Filament, atau ada pendekatan yang lebih efisien, **TAHAN EKSEKUSI**. Sampaikan keberatan Anda secara logis dan berikan opsi diskusi.
> 2. **Wajib Membuat Implementation Plan:** Sebelum menulis kode apa pun untuk sebuah tugas/Issue, Anda WAJIB menyajikan "Implementation Plan" dalam Bahasa Indonesia. Rencana ini minimal harus mencakup: (a) Analisis Masalah, (b) File apa saja yang akan dibuat/diubah, dan (c) Langkah-langkah penyelesaian. Tunggu persetujuan saya sebelum mulai *coding*.

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
* **Default Date Filter (Khusus Transaksional):** Untuk setiap modul transaksional dan pencatatan riwayat, halaman *Index* (tabel data) **wajib** memiliki *Date Range Filter*. Secara *default* (bawaan dari Eloquent *query* awal saat halaman dimuat), data wajib dibatasi mulai dari **tanggal 1 pada bulan berjalan hingga tanggal hari ini**. Penyaringan bawaan (*default filter*) ini harus diterapkan secara diam-diam (*silent filtering*) di latar belakang melalui *hook* `query()` tanpa menaruh *badge indicator* filter aktif di antarmuka UI. Pengguna harus tetap bisa mengubah rentang tanggal ini melalui komponen filter di UI.
* **Filter Data Relasional:** Tabel transaksional wajib menyediakan filter *dropdown* relasional yang relevan untuk mempermudah pencarian spesifik (misalnya: filter Nama Customer pada Penjualan, atau Nama Supplier pada Pembelian). Detail filter apa saja yang dibutuhkan akan dijelaskan lebih rinci pada masing-masing GitHub Issue.
* **Audit Trail / Activity Logging (Khusus Transaksional & Data Krusial):** Setiap model yang berkaitan dengan pencatatan transaksi, pergerakan stok, keuangan, serta master data yang sensitif wajib mengimplementasikan *Activity Log*. Gunakan *package* `spatie/laravel-activitylog` agar setiap *event* `created`, `updated`, dan `deleted` terekam otomatis beserta detail *user* yang mengeksekusi dan perubahan datanya (*old/new values*).
* **Fitur Ekspor Komprehensif (Excel & PDF):** Mengingat kebutuhan pelaporan yang esensial, setiap modul atau tabel wajib mengimplementasikan fitur *export* khusus ke **Excel** dan **PDF** menggunakan *Bulk Actions* maupun *Header Actions*. **PENTING: Ekspor Excel DILARANG menggunakan bawaan Filament Exporter (karena memicu modal/queue yang lambat). Ekspor Excel wajib dibangun menggunakan *Custom Action* yang melakukan *Direct Stream Download* seketika menggunakan `OpenSpout\Writer\XLSX\Writer` (sekali klik langsung unduh) dengan format `.xlsx`.** Sementara ekspor PDF wajib dirender melalui *blade view* khusus dan library `dompdf` (*StreamDownload*). Tombol ekspor wajib dipisah menjadi dua tombol mandiri bernama "PDF" dan "Excel".
* **Standar Halaman Detail (Flat List View):** Semua modul transaksional yang memiliki struktur relasi Induk-Anak (*Parent-Child*, misalnya *Requisition* dan *RequisitionItem*) **wajib** menyediakan satu *Custom Page* khusus (bernama `detail-list`) untuk menampilkan rekapitulasi seluruh rincian *item anak* dalam bentuk tabel datar (*flat list*). Halaman detail ini wajib memiliki *Silent Date Filter* dan menyertakan *Export Action* (Excel & PDF). Tombol akses halaman Detail wajib disematkan di sebelah tombol "Create" pada halaman *Index*.
* **Penamaan Tombol Pembuatan Data:** Hindari penggunaan teks yang panjang seperti "New [Model]". Label tombol bawaan untuk membuat data baru di *Header Actions* halaman Index wajib diubah (*override*) secara eksplisit menjadi **"Create"**.
* **Bilingual UI (Dukungan Bahasa):** Seluruh elemen antarmuka mulai dari menu, label input (field), notifikasi/alert, pesan error validasi, hingga teks sistem statis **wajib** mengikuti lokalisasi (Inggris dan Indonesia). Selalu gunakan *helper* bawaan Laravel seperti `__()` dan daftarkan terjemahannya di file `lang/id.json` maupun `lang/en.json`.
* **Auto Redirect to Index:** Setelah pengguna berhasil membuat data baru (Create) ataupun mengubah data (Edit/Update), *workflow* form **harus dialihkan kembali (*redirect*) ke halaman daftar data (Index/List)**. Jangan membiarkan pengguna berdiam di halaman form setelah tombol "Save" ditekan.
* **Format Angka & Mata Uang (UI vs Database):** Pada form input (UI) biasa, gunakan *masking* otomatis dengan `RawJs` agar angka diformat secara otomatis saat diketik (contoh: pemisah ribuan otomatis menggunakan titik `.` tanpa desimal untuk IDR). Contoh implementasi: `->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')->numeric()`. Pastikan data disimpan ke database sebagai angka murni.
  * **[BUG ALERT: Livewire Morphdom & Alpine Mask]:** Dilarang keras menggunakan `RawJs::make('$money(...)')` atau skrip *masking* AlpineJS kompleks lainnya di dalam form **Repeater** (misalnya form detail item). Terdapat *bug* langka di mana script *mask* ini mencengkeram elemen DOM. Ketika sebuah baris dihapus, Livewire berhasil menghapusnya di sisi server, namun *Morphdom* gagal membersihkan elemen HTML-nya di browser karena tertahan oleh proses *teardown* AlpineJS. Hal ini mengakibatkan munculnya baris "zombie" (baris kosong yang tidak bisa dihilangkan). Untuk input angka di dalam Repeater, **cukup gunakan `->numeric()` standar** tanpa `->mask()`.
* **Tampilan Repeater Bersih (Clean Repeater UI):** Di dalam form *Repeater* (misalnya detail item transaksional), sembunyikan label pada setiap baris field menggunakan `->label('')` dan `->hiddenLabel()`. Cukup gunakan `->placeholder()` sebagai penunjuk isian agar tampilan ringkas dan bersih menyerupai form *inline* tanpa label *header* yang berulang.
* **Tata Letak Tombol Aksi (Action Buttons Layout):** Pada halaman *Edit*, tombol 'Cancel' bawaan yang berada di bagian bawah form (di sebelah 'Save changes') harus disembunyikan dengan melakukan _override_ method `getFormActions()`. Sebagai gantinya, letakkan tombol 'Cancel' (dengan style abu-abu) di bagian atas (Header Actions) berdampingan dengan aksi halaman lainnya seperti 'Print' dan 'Delete'.
* **Standarisasi Warna & Posisi Tombol Aksi:**
  * **Create:** Warna `Primary` (Biru), letakkan di *Page Header* (Kanan Atas Halaman).
  * **Detail:** Warna `Info` (Biru Muda/Cyan), letakkan berdampingan dengan tombol Create di *Page Header*.
  * **Data Export (Excel/PDF):** Warna `Success` (Hijau), WAJIB diletakkan di dalam `table()->headerActions()` (Kanan Atas Tabel, sejajar dengan kotak Pencarian), **bukan** di *Page Header*. Gunakan *Dropdown ActionGroup* berlabel "Export Data" jika terdapat lebih dari satu format *export*.
* **Clickable Rows (UI Tabel Bersih):** Untuk menghemat ruang, jangan tampilkan tombol aksi (Actions) statis untuk Edit di dalam *table list*. Jadikan baris data pada tabel itu sendiri sebagai *clickable row* agar bisa diklik langsung menuju ke *form edit* (menggunakan method `recordUrl()`).
* **Standarisasi Tampilan Data Terhapus (Soft Deletes):** Pada modul yang mendukung fitur penghapusan data secara lunak (*Soft Deletes*), tabel *Index* wajib dilengkapi dengan filter `TrashedFilter` untuk menampilkan data yang dihapus (With Deleted Records). Baris data yang berstatus terhapus wajib diberi penanda visual khusus menggunakan `->recordClasses()` (misalnya baris berwarna merah muda atau *border* merah). Saat baris data terhapus diklik, alur aplikasi dilarang menuju halaman Edit; pengguna wajib diarahkan ke halaman View khusus (Hanya baca/Read-only).
* **Manajemen Hak Akses (Permissions) Custom (Tanpa Shield):** Proyek ini **DILARANG** menggunakan `Filament Shield` atau perintah `shield:generate`. Sistem hak akses (*roles* & *permissions*) dibangun secara custom. Oleh karena itu, setiap pembuatan modul/Resource/Cluster baru **wajib**:
  1. Mendaftarkan set hak akses baru di *Database Seeder* (`DatabaseSeeder.php`) beserta `module_name`.
  2. Membuat *Policy* khusus secara manual di direktori `app/Policies` dan mendaftarkannya di `AppServiceProvider` (jika nama model tidak otomatis terhubung).
  3. Menambahkan pengecekan hak akses `canViewAny()` dan `shouldRegisterNavigation()` secara eksplisit pada kelas **Resource** maupun **Cluster** agar menu navigasinya benar-benar tersembunyi bagi user yang tidak berhak.
  4. Khusus untuk modul yang memiliki fitur `TrashedFilter`, wajib mendaftarkan *permission* tambahan bernama `view_deleted_{module_name}`. Filter `TrashedFilter` tersebut hanya boleh terlihat (`->visible()`) oleh pengguna yang memiliki *permission* ini. Form manajemen hak akses di UI wajib ditampilkan secara visual terkelompok berdasarkan modul.
* **Minimalist Dashboard:** Dilarang menampilkan widget non-fungsional bawaan *framework* (seperti `FilamentInfoWidget`) pada *dashboard*. Jaga agar antarmuka hanya memuat informasi fungsional yang berkaitan dengan sistem ERP Wijaya Meat SWM.
* **Notifikasi Tugas Antar Modul:** Pengingat adanya tugas baru atau perubahan status dokumen menggunakan komponen **Livewire Global Poller** yang ditempatkan pada *render hook* aplikasi (tanpa menggunakan tabel notifikasi khusus di *database*). Komponen ini secara mandiri melakukan *polling* (setiap 5 detik) untuk mengecek jika ada data terbaru (misalnya dengan mengecek `MAX(id)` untuk data baru, atau membandingkan timestamp `updated_at` untuk melacak perubahan status) dan kemudian memicu *toast notification* melayang secara dinamis. Selain itu, sediakan juga sebuah Widget *banner* peringatan statis pada Dashboard menggunakan Eloquent *query* biasa (seperti `doesntHave()`) tanpa *polling* untuk menghitung sisa tugas yang belum dikerjakan. **PENTING**: Setiap pembuatan modul atau resource transaksional baru, implementor **WAJIB** menanyakan terlebih dahulu kepada Project Owner apakah modul tersebut membutuhkan notifikasi (baik berupa task alert di Dashboard maupun Toast notification di latar belakang).

* **Standar Konkurensi & Integritas Data (Transaksi Stok/Barcode):** Setiap operasi yang menyisipkan barcode atau memutasi stok wajib mengikuti pola berikut:
  1. **Pengecekan duplikat wajib berada DI DALAM `DB::transaction()` dan memakai `->lockForUpdate()`.** Pengecekan di luar transaksi tidak mengikat: dua permintaan yang tiba bersamaan (klik ganda operator atau *glitch* jaringan dari *scanner*) akan sama-sama melihat "belum ada" lalu sama-sama menyimpan.
  2. Pengecekan di luar transaksi **boleh dipertahankan sebagai *fast-path*** supaya pesan ke operator tetap ramah dan spesifik (misalnya notifikasi *warning* "Barang Sudah Terinput", bukan *error* generik). Anggap itu murni lapisan UX — penjagaan yang sebenarnya tetap yang di dalam transaksi.
  3. **Generator nomor urut barcode wajib dikunci.** Query pengambilan counter terakhir (`where('barcode', 'like', $prefix . '%')` lalu `orderBy('id', 'desc')`) harus memakai `->lockForUpdate()`; tanpa itu dua proses labeling bersamaan bisa membaca counter yang sama dan menghasilkan barcode kembar.
  4. **Pembacaan yang menjadi dasar keputusan mutasi wajib dikunci.** Bila stok dibaca untuk divalidasi lalu dihapus/diubah (misalnya *unlock* retur), baca dengan `->lockForUpdate()` agar dua user tidak sama-sama lolos validasi terhadap baris yang sama.
  5. Kunci di level aplikasi bukan pengganti *constraint* database. Kolom `barcode` pada tabel yang menyimpan identitas barang sebaiknya tetap punya index `unique` sebagai jaring pengaman terakhir.
* **Halaman Bertipe Paksaan (Forced Flow):** Halaman yang menahan user sampai suatu tindakan diselesaikan (misalnya `ForceChangePassword`) wajib memakai layout `simple` Filament (`protected static string $layout = 'filament-panels::components.layout.simple';`) supaya sidebar dan seluruh tautan navigasi tidak tampil — mengandalkan *middleware* saja membuat user mengklik menu lalu terpental, dan itu membingungkan. Topbar wajib tetap ditampilkan (`hasTopbar: true`) agar user menu dan tombol *Sign out* terjangkau sehingga user tidak terjebak. Kelas halaman tetap meng-*extend* `Page` (bukan `SimplePage`), karena panel memakai `discoverPages()` yang hanya mengenali turunan `Page`; halaman tersebut juga perlu meng-*override* `getLayoutData()` dan menambahkan `hasLogo()`.

## 4. Workflow, Eksekusi Tugas & Komunikasi

* **Standar Penulisan Issue:** Setiap GitHub Issue wajib dijabarkan sedetail dan sespesifik mungkin (*low-level blueprint*). Ketegasan detail ini mutlak diperlukan karena *issue* akan dijadikan acuan kerja langsung oleh *programmer junior* maupun *agen AI* level eksekutor.
* **Konfirmasi Sebelum Eksekusi:** Sebelum mengeksekusi kode, implementor wajib mendiskusikan *issue* yang sedang ditugaskan dengan *Project Owner* dan melakukan konfirmasi hingga tercapai kesepakatan.
* **Alur Wajib Eksekusi (Pre-Execution Flow):** Saat akan mengerjakan *issue* baru, implementor dilarang langsung melakukan *coding*. Anda wajib:
  1. Membaca ulang aturan global di `project.md` ini.
  2. Membaca riwayat seluruh *issue* sebelumnya untuk memahami konteks dan dependensi sistem yang sudah ada.
  3. Menganalisis *file* dan struktur yang berkaitan secara saksama.
  4. Baru kemudian boleh menulis kode sesuai *issue* yang berjalan.
* **Tanggung Jawab Penutup:** Setelah *issue* dianggap selesai, implementor harus membaca kembali instruksi awal pada *issue* tersebut dan memastikan tidak ada satu pun spesifikasi yang terlewat atau tidak berjalan semestinya sebelum menyatakan tugas selesai.

### Struktur Barcode Origin (Digit Pertama)
Sistem ini menggunakan digit pertama pada barcode untuk mendefinisikan asal-usul (origin) daging tersebut:
- `1` = Boning -> Boning
- `2` = Repack Stock -> R-STCK
- `3` = Repack Import
- `4` = Repack Return
- `5` = Repack Trading
- `6` = Relabel Tally
- `7` = Pembelian Trading Lokal (Goods Receipt Beef)
- `8` = Pembelian Trading Import (Goods Receipt Beef)

## Catatan Khusus
- **Status Pajak Perusahaan (nonPKP):** Wijaya Meat adalah produsen daging berstatus **nonPKP**. **Invoice dan penjualan tidak dikenai PPN sama sekali** — ini benar secara desain, bukan fitur yang belum dikerjakan. Pajak hanya relevan pada **pembelian material lain** di sisi procurement. Kolom `invoices.tax` dan flag `customers.is_taxable` adalah sisa desain lama yang tidak terpakai di sisi penjualan; jangan menambahkan perhitungan pajak pada modul Invoice/Sales dan jangan menandai absennya sebagai bug.
- **Project Structure**: Modul Filament diletakkan di `app/Filament/Admin/Resources`.
* **Evaluasi Pasca-Pembuatan (Post-Execution Review):** Setelah modul atau *issue* disepakati selesai dibuat, implementor wajib meninjau ulang:
  * Apakah dokumen *Issue* terkait perlu direvisi untuk menyesuaikan dengan hasil akhir pengembangan?
  * Apakah ditemukan aturan main baru yang mengharuskan pembaruan/revisi pada dokumen `project.md` ini?
* **Referensi Legacy Code:** Sebelum mulai menyusun logika bisnis di framework baru, wajib meninjau direktori `legacy/`. Di dalamnya terdapat 2 folder: versi prosedural (modul sudah lengkap) dan versi laravel (baru sebagian). **Utamakan merujuk ke versi laravel terlebih dahulu**, baru gunakan prosedural jika ada perbedaan atau jika modul di Laravel belum ada.
* **Proaktif Berdiskusi & Memberikan Opsi:** Jika terdapat instruksi, alur logika, atau batasan sistem yang ambigu/kurang jelas, implementor (terutama agen AI) **DILARANG mengambil asumsi sepihak**. Anda wajib berhenti mengeksekusi kode, paparkan masalahnya, dan **berikan beberapa pilihan/opsi penyelesaian** agar *Project Owner* dapat memilih jalan yang paling tepat.
* **Pencatatan & Cabang Git (GitHub Issues & Branching):** Setiap rencana implementasi (Implementation Plan) yang telah ditinjau dan disetujui oleh Project Owner wajib dibuatkan tiket GitHub Issue-nya (jika belum ada). Jika selama pengerjaan ditemukan kendala, bug, atau permasalahan baru, temuan tersebut wajib didokumentasikan/diunggah ke GitHub Issue terkait dan dikerjakan pada branch Git yang sesuai dengan nomor issue tersebut.

## 5. Kualitas Kode & Manajemen Repositori

* **Standar Format Kode:** Seluruh kode PHP wajib ditulis mengikuti standar kebersihan kode (seperti PSR-12).
* **Konvensi Git Branching:** Dilarang melakukan komit langsung ke *branch* utama (`main`/`master`). Setiap eksekusi tugas dari GitHub Issue wajib dikerjakan pada *branch* baru dengan format: `feature/issue-[nomor-issue]` (contoh: `feature/issue-24`).
* **Pull Request (PR):** Setiap *branch* yang selesai harus diajukan sebagai *Pull Request* dan melalui proses *code review* sebelum digabungkan.
* **Konsistensi URL Lokal (Domain Mismatch):** Selama proses *development*, akses URL di *browser* wajib disamakan dengan `APP_URL` di file `.env`. Jangan pernah mencampur penggunaan `localhost` dengan `127.0.0.1` dalam satu sesi karena akan merusak token CSRF dan memicu *bug logout* mendadak atau *error* 419 (*Page Expired*).
* **Larangan Manipulasi Global Error (Hindari Hack JS):** Dilarang keras menyisipkan *JavaScript global hook* (seperti `Livewire.hook`) di dalam Provider untuk mem-*bypass* atau memaksa *auto-reload* pada *error* bawaan sistem (seperti *Error 419 / Page Expired*). Biarkan sistem *framework* menampilkan kotak *alert/modal* secara natural agar *developer* maupun *user* tahu akar masalahnya.

## 6. Keamanan Database Utama & Aturan Testing (CRITICAL)

* **Proteksi Database Utama:** Agen AI **dilarang keras** menjalankan perintah pengujian (`php artisan test`) sebelum memverifikasi secara pasti bahwa file `phpunit.xml` menggunakan environment database in-memory (SQLite `:memory:`) atau database testing terpisah. Trait `RefreshDatabase` pada proses *testing* tidak boleh menyentuh database MySQL utama.
* **Larangan Reset Database:** Dilarang mengeksekusi perintah destruktif seperti `php artisan migrate:fresh` pada database utama kecuali diinstruksikan secara eksplisit di dalam Issue. Gunakan `php artisan migrate` standar untuk penambahan modul baru.
* **Kredensial Akun Default:** Akun superuser bawaan di `DatabaseSeeder` wajib dipertahankan dan harus selalu bisa dipakai untuk *development*:
  * **Username:** saepullrock
  * **Password bawaan:** `1234`
  
  **Password bawaan WAJIB tetap `1234`** dan dilarang diganti menjadi password yang benar-benar dipakai. Repositori ini **publik**, sehingga apa pun yang ditulis di seeder otomatis terpublikasi. Nilai `1234` dipilih justru karena ia sepele: *middleware* `CheckPasswordChange` mendeteksinya dan langsung memaksa pengguna mengganti password pada login pertama, sebelum sempat membuka menu apa pun. Jangan pernah menuliskan password sungguhan di `project.md`, di *seeder*, atau di file mana pun dalam repositori.
* **Auto-Deploy (Otomasi Github):** Server *hosting / production* telah menggunakan konfigurasi *auto-deploy* (Webhook / Github pintar). Segala perubahan kode dan penambahan tabel (lewat *migration* biasa) yang di-push ke branch main akan secara otomatis ditarik (git pull) ke *server production*. Perlu dicatat: *auto-deploy* ini **tidak akan** menjalankan perintah destruktif seperti migrate:fresh. Eksekusi reset database (fresh migration) hanya boleh dilakukan secara manual oleh *Project Owner* / Administrator langsung di dalam *server*.

## 7. Blueprint UI & Notifikasi (PENTING)

* **Notifikasi Real-time Filament (Blueprint):** Jika ingin mengimplementasikan notifikasi *toast* real-time berbasis *polling* di masa mendatang tanpa menggunakan *Websockets*, gunakan pendekatan di `GlobalTaskPoller.php` (Livewire Component) sebagai *blueprint* sempurna. 
  * **Konfigurasi Poller:** Tambahkan tag `<div wire:poll.5s="checkTasks" class="hidden"></div>` di *view* komponen agar memicu pengecekan di sisi *server* setiap 5 detik.
  * **Logika Notifikasi:** Pada method `checkTasks`, ambil data terbaru dari database (mengacu pada `$lastCheckAt`), bandingkan *status* dokumen (seperti 'Requested', 'Pending Finance'), dan kondisikan dengan kewenangan user `auth()->user()->hasPermission(...)`.
  * **Trigger Toast:** Panggil notifikasi standar seperti `Notification::make()->title('...')->success()->send()` secara langsung di dalam kondisi tersebut. Filament otomatis akan meneruskan *trigger* ini sebagai notifikasi *toast* real-time ke *browser* pengguna yang bersangkutan tanpa perlu me-reload halaman.
