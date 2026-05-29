Global Rules & Project Overview: Wijaya Meat (SWM)
[!CAUTION]
ATURAN MUTLAK: Dokumen ini adalah panduan utama (High-Level Guidelines) sekaligus aturan global mutlak untuk seluruh pengembangan dan refactoring sistem "Wijaya Meat (SWM)".
Setiap implementor (termasuk agen AI) WAJIB membaca dan mematuhi dokumen ini sebelum mengeksekusi instruksi dari GitHub Issue mana pun.

1. Project Overview & Strategi Migrasi
Proyek ini bertujuan memodernisasi sistem ERP "Wijaya Meat (SWM)" yang sebelumnya berbasis Native/Procedural PHP, AdminLTE 3, dan jQuery.
Migrasi dilakukan menggunakan pendekatan "Strangler Pattern" secara bertahap.

Strategi Database Transisi: Selama masa migrasi, sistem baru (Laravel) akan berbagi sumber data dengan sistem lama. Dilarang merubah struktur tabel fundamental yang masih dipakai secara aktif oleh sistem lama tanpa instruksi eksplisit dari Issue.

Seluruh alur pengerjaan dan pembagian tugas diatur serta didelegasikan secara terperinci melalui tiket GitHub Issues.

2. Tech Stack & Arsitektur Utama
Implementor wajib menggunakan standard stack berikut:

Backend: Laravel 11. Interaksi database wajib menggunakan Eloquent ORM dan Migration (hindari raw SQL procedural).

Admin Panel: Filament v3. Manfaatkan secara maksimal fitur Filament Resource, Page, dan Widget. Hindari pembuatan view blade/HTML manual untuk CRUD standar.

Database: MySQL. Penamaan tabel dan kolom (baru) wajib menggunakan Bahasa Inggris sesuai konvensi Laravel (snake_case, plural).

3. Aturan Standar UI/UX & Modul Global (WAJIB DITERAPKAN DI SEMUA MODUL)
Setiap pengembangan fitur atau penambahan resource baru di Filament wajib menerapkan standarisasi berikut:

Fitur Ekspor Komprehensif (Excel & PDF): Mengingat kebutuhan pelaporan dan dokumentasi fisik yang esensial, setiap modul atau tabel wajib mengimplementasikan fitur export (Excel, PDF, dan opsi Print) menggunakan Bulk Actions atau Header Actions standar di Filament.

Bilingual UI (Dukungan Bahasa): Seluruh elemen antarmuka mulai dari menu, label input (field), notifikasi/alert, pesan error validasi, hingga teks sistem statis wajib mengikuti lokalisasi (Inggris dan Indonesia). Selalu gunakan helper bawaan Laravel seperti __() dan daftarkan terjemahannya di file lang/id.json maupun lang/en.json.

Auto Redirect to Index: Setelah pengguna berhasil membuat data baru (Create) ataupun mengubah data (Edit/Update), workflow form harus dialihkan kembali (redirect) ke halaman daftar data (Index/List). Jangan membiarkan pengguna berdiam di halaman form setelah tombol "Save" ditekan.

Format Angka & Mata Uang (UI vs Database): Pada antarmuka pengguna (UI), gunakan masking agar format desimal menggunakan titik (.) dan pemisah ribuan adalah koma (,) contoh: 1,000.34. Namun, pastikan data yang dikirim dan disimpan ke database diproses sebagai angka murni menggunakan Casting atau mutator Laravel yang tepat.

Clickable Rows (UI Tabel Bersih): Untuk menghemat ruang, jangan tampilkan tombol aksi (Actions) statis untuk Edit di dalam table list. Jadikan baris data pada tabel itu sendiri sebagai clickable row agar bisa diklik langsung menuju ke form edit (menggunakan method recordUrl()).

Manajemen Hak Akses (Permissions) Terkelompok: Setiap membuat modul baru, wajib mendaftarkan set hak akses baru di database seeder beserta kolom module_name. Form manajemen hak akses di UI juga wajib ditampilkan secara visual terkelompok berdasarkan modul (Gunakan skema generator dinamis dengan Section untuk tiap modul dan CheckboxList dengan property dehydrated(false), lalu intersep penyimpanannya melalui afterSave dan afterCreate di Pages).

Minimalist Dashboard: Dilarang menampilkan widget non-fungsional bawaan framework (seperti FilamentInfoWidget) pada dashboard. Jaga agar antarmuka hanya memuat informasi fungsional yang berkaitan dengan sistem ERP Wijaya Meat SWM.

4. Workflow & Eksekusi Tugas
Kerja Berbasis Issue: Kerjakan fitur hanya berdasarkan ruang lingkup (scope) dari GitHub Issue yang sedang ditugaskan. Implementasi harus bersifat sangat mendetail (low-level).

Referensi Legacy Code: Sebelum mulai menyusun logika bisnis di framework baru, wajib meninjau direktori legacy/ untuk memahami proses dan aturan bisnis yang sudah ada pada sistem lama.

Praktik Modern: Hindari kebiasaan pemrograman prosedural. Selalu terapkan standar best-practice arsitektur Laravel 11 (seperti Middleware untuk proteksi, Form Requests untuk validasi, Eloquent relationships, dan Service pattern untuk logika yang kompleks).

5. Kualitas Kode & Manajemen Repositori
Standar Format Kode: Seluruh kode PHP wajib ditulis mengikuti standar kebersihan kode (seperti PSR-12).

Konvensi Git Branching: Dilarang melakukan komit langsung ke branch utama (main/master). Setiap eksekusi tugas dari GitHub Issue wajib dikerjakan pada branch baru dengan format: feature/issue-[nomor-issue] (contoh: feature/issue-24).

Pull Request (PR): Setiap branch yang selesai harus diajukan sebagai Pull Request dan melalui proses code review sebelum digabungkan.

Catatan: Semua file source code peninggalan versi sebelumnya diselamatkan di dalam folder legacy/ untuk mempermudah pengecekan referensi.