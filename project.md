# Global Rules & Project Overview: Wijaya Meat (SWM)

Dokumen ini adalah panduan tingkat tinggi (High-Level Guidelines) untuk pengembangan dan refactoring sistem "Wijaya Meat (SWM)". Detail teknis dan implementasi per modul akan dijelaskan secara spesifik pada masing-masing GitHub Issue.

## 1. Project Overview
Proyek ini bertujuan memodernisasi dan melakukan refactoring total pada sistem ERP "Wijaya Meat (SWM)" yang sebelumnya berbasis Native/Procedural PHP, AdminLTE 3, dan jQuery.
Migrasi dilakukan menggunakan pendekatan "Strangler Pattern" secara bertahap. Seluruh alur pengerjaan dan pembagian tugas akan diatur serta didelegasikan melalui tiket **GitHub Issues**.

## 2. Tech Stack & Arsitektur Utama
Implementor yang mengerjakan proyek ini wajib menggunakan standard stack berikut:
* **Backend:** Laravel 11. Seluruh interaksi database wajib menggunakan Eloquent ORM dan Migration (hindari raw SQL procedural).
* **Admin Panel:** Filament v3. Sebisa mungkin manfaatkan fitur Filament Resource, Page, dan Widget. Hindari pembuatan view blade/HTML manual untuk CRUD standar.
* **Database:** MySQL. Penamaan tabel dan kolom wajib menggunakan Bahasa Inggris sesuai konvensi Laravel (snake_case, plural).
* **Bilingual UI:** Aplikasi harus mendukung 2 bahasa (Inggris dan Indonesia) dengan memanfaatkan fitur lokalisasi (Translation/Lang) bawaan Laravel dan Filament.

## 3. Workflow & Eksekusi Tugas
* **Kerja Berbasis Issue:** Kerjakan fitur hanya berdasarkan *scope* atau instruksi dari GitHub Issue yang sedang ditugaskan. Jangan membuat detail fitur di luar *scope*.
* **Referensi Legacy Code:** Sebelum mulai menyusun logika bisnis di framework baru, wajib meninjau direktori `legacy/` untuk memahami proses yang ada pada sistem sebelumnya.
* **Konsultasi & Klarifikasi:** Jika ada *requirement* di dalam Issue yang rancu atau kurang detail, pastikan untuk bertanya atau meminta klarifikasi sebelum menulis kode.
* **Gunakan Praktik Modern:** Hindari gaya *procedural programming* peninggalan sistem lama (seperti session native atau routing manual). Terapkan arsitektur dan best-practice Laravel.

---
*Catatan: Semua file source code lama ditempatkan pada folder `legacy/` sebagai referensi logika bisnis.*
