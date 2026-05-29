# Global Rules & Project Overview: Wijaya Meat (SWM)

> [!CAUTION]
> **ATURAN MUTLAK**: Dokumen ini adalah panduan utama (High-Level Guidelines) sekaligus **aturan global mutlak** untuk seluruh pengembangan dan refactoring sistem "Wijaya Meat (SWM)".
> Setiap implementor (termasuk agen AI) **WAJIB** membaca dan mematuhi dokumen ini sebelum mengeksekusi instruksi dari GitHub Issue mana pun.

## 1. Project Overview
Proyek ini bertujuan memodernisasi sistem ERP "Wijaya Meat (SWM)" yang sebelumnya berbasis Native/Procedural PHP, AdminLTE 3, dan jQuery.
Migrasi dilakukan menggunakan pendekatan "Strangler Pattern" secara bertahap. Seluruh alur pengerjaan dan pembagian tugas diatur serta didelegasikan secara terperinci melalui tiket **GitHub Issues**.

## 2. Tech Stack & Arsitektur Utama
Implementor wajib menggunakan standard stack berikut:
* **Backend:** Laravel 11. Interaksi database wajib menggunakan Eloquent ORM dan Migration (hindari raw SQL procedural).
* **Admin Panel:** Filament v3. Manfaatkan secara maksimal fitur Filament Resource, Page, dan Widget. Hindari pembuatan view blade/HTML manual untuk CRUD standar.
* **Database:** MySQL. Penamaan tabel dan kolom wajib menggunakan Bahasa Inggris sesuai konvensi Laravel (*snake_case*, *plural*).

## 3. Aturan Standar UI/UX & Modul Global (WAJIB DITERAPKAN DI SEMUA MODUL)
Setiap pengembangan fitur atau penambahan *resource* baru di Filament wajib menerapkan standarisasi berikut:

* **Bilingual UI (Dukungan Bahasa):** Seluruh elemen antarmuka mulai dari menu, label input (field), notifikasi/alert, pesan error validasi, hingga teks sistem statis **wajib** mengikuti lokalisasi (Inggris dan Indonesia). Selalu gunakan *helper* bawaan Laravel seperti `__()` dan daftarkan terjemahannya di file `lang/id.json` maupun `lang/en.json`.
* **Auto Redirect to Index:** Setelah pengguna berhasil membuat data baru (Create) ataupun mengubah data (Edit/Update), *workflow* form **harus dialihkan kembali (*redirect*) ke halaman daftar data (Index/List)**. Jangan membiarkan pengguna berdiam di halaman form setelah tombol "Save" ditekan.
* **Format Angka & Mata Uang (Pemisah Ribuan & Desimal):** Pada setiap *input* tipe numerik atau harga, *form* wajib otomatis mengkonversi nilainya dengan pemisah ribuan. **Format desimal yang disukai adalah titik (`.`) dan pemisah ribuan adalah koma (`,`)**. Contoh: `1,000.34`.
* **Clickable Rows (UI Tabel Bersih):** Untuk menghemat ruang, jangan tampilkan tombol aksi (Actions) statis untuk Edit di dalam *table list*. Jadikan baris data pada tabel itu sendiri sebagai *clickable row* agar bisa diklik langsung menuju ke *form edit* (menggunakan method `recordUrl()`).
* **Manajemen Hak Akses (Permissions) Terkelompok:** Setiap membuat modul baru, **wajib** mendaftarkan set hak akses baru di *database seeder* beserta kolom `module_name`. Form manajemen hak akses di UI juga wajib ditampilkan secara visual terkelompok berdasarkan modul agar rapi menyerupai *card* terpisah. (Gunakan skema generator dinamis dengan `Section` untuk tiap modul dan `CheckboxList` dengan property `dehydrated(false)`, lalu intersep penyimpanannya melalui `afterSave` dan `afterCreate` di *Pages*).
* **Minimalist Dashboard:** Dilarang menampilkan widget non-fungsional bawaan *framework* (seperti `FilamentInfoWidget`) pada *dashboard*. Jaga agar antarmuka hanya memuat informasi fungsional yang berkaitan dengan sistem ERP Wijaya Meat SWM.

## 4. Workflow & Eksekusi Tugas
* **Kerja Berbasis Issue:** Kerjakan fitur hanya berdasarkan ruang lingkup (*scope*) dari GitHub Issue yang sedang ditugaskan. Implementasi harus bersifat sangat mendetail (*low-level*).
* **Referensi Legacy Code:** Sebelum mulai menyusun logika bisnis di framework baru, wajib meninjau direktori `legacy/` untuk memahami proses dan aturan bisnis yang sudah ada pada sistem lama.
* **Praktik Modern:** Hindari kebiasaan pemrograman prosedural. Selalu terapkan standar *best-practice* arsitektur Laravel 11 (seperti Middleware untuk proteksi, Eloquent relationships, dan fitur *built-in* dari Filament).

---
*Catatan: Semua file source code peninggalan versi sebelumnya diselamatkan di dalam folder `legacy/` untuk mempermudah pengecekan referensi.*
