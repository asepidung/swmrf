# Audit Modul Project - Evaluasi Kepatuhan `project.md`

Berdasarkan pengecekan menyeluruh pada *source code* aplikasi (terutama di direktori `app/Filament/Admin/Resources`) dan membandingkannya dengan panduan standar yang tertulis pada `project.md`, berikut adalah laporan audit mengenai kekurangan, ketidaksesuaian, dan potensi *bugs* yang ditemukan serta status penyelesaiannya.

---

## 1. Halaman Detail (Flat List View) Belum Lengkap - **[SELESAI]**
**Aturan `project.md` (Poin 34):** Semua modul transaksional yang memiliki struktur relasi Induk-Anak (Parent-Child) WAJIB menyediakan satu Custom Page khusus bernama `detail-list`.

**Status:** **SELESAI** (Sudah diselesaikan pada modul-modul terkait).

---

## 2. Penyaringan Tanggal Bawaan (Silent Date Filter) Tidak Diam-diam - **[SELESAI]**
**Aturan `project.md` (Poin 30):** Filter rentang tanggal *default* (tanggal 1 awal bulan s/d hari ini) harus diterapkan secara **diam-diam (*silent filtering*) di latar belakang melalui *hook* `query()`** tanpa menaruh *badge indicator* filter aktif di antarmuka UI.

**Status:** **SELESAI** (Sudah disesuaikan agar filter tanggal berjalan secara *silent* di latar belakang).

---

## 3. Fitur Ekspor Komprehensif (Excel & PDF) Absen - **[SELESAI]**
**Aturan `project.md` (Poin 33):** Setiap modul atau tabel wajib mengimplementasikan fitur ekspor ke Excel (menggunakan `openspout`) dan PDF (menggunakan `dompdf`).

**Status:** **SELESAI** (Fitur ekspor Excel/PDF sudah diimplementasikan sesuai standar).

---

## 4. Pelanggaran *Clickable Rows* & UI Tabel Bersih - **[BELUM SELESAI]**
**Aturan `project.md` (Poin 46):** Baris tabel wajib dapat diklik (*clickable rows*) menggunakan method `recordUrl()`. Jangan tampilkan tombol aksi statis di dalam *table list*.

**Temuan:**
Modul `BoningResource` secara eksplisit mematikan fitur ini dengan menggunakan `->recordUrl(null)` dan meletakkan banyak tombol aksi (Lock, Labeling, Summary, Edit, Delete) secara statis di setiap baris tabel. Hal ini memakan banyak ruang dan menyalahi prinsip *Clean UI Tabel*. Tombol-tombol spesifik tersebut seharusnya dipindahkan ke *Header Actions* di halaman View/Edit.

**Status:** **BELUM SELESAI / PENDING** (Menunggu instruksi/Issue pengerjaan berikutnya).

---

## 5. Standar UI Repeater Bersih (Clean Repeater UI) - **[SELESAI]**
**Aturan `project.md` (Poin 40):** Di dalam form *Repeater*, sembunyikan label pada setiap baris field dan gunakan tampilan menyerupai tabel (dengan *header grid* statis di atasnya).

**Status:** **SELESAI** (Sudah disesuaikan dengan menggunakan *Grid Placeholder* di atas Repeater).

---

## 6. Absennya Notifikasi Dashboard & Toast pada Beberapa Modul - **[SELESAI]**
**Aturan `project.md` (Poin 50):** Pengingat adanya tugas baru atau perubahan status dokumen menggunakan komponen **Livewire Global Poller** untuk toast notification dan Widget **PendingTaskWidget** pada Dashboard untuk banner statis.

**Status Rincian Modul:**

1. **Goods Receipt Material (GRM)**:
   - *Status:* **SELESAI** (Dashboard task count & background Toast notification aktif untuk PO Material baru yang siap diterima/dibuatkan GRM).
2. **Goods Receipt Beef (GRB / Product)**:
   - *Status:* **SELESAI** (Dashboard task count & background Toast notification aktif ketika ada PO Beef baru yang siap diterima/dibuatkan GRB).
3. **Boning**:
   - *Status:* **SELESAI** (Dashboard alert aktif untuk user dengan akses `lock_bonings` jika ada boning batch yang belum dikunci; tidak menggunakan Toast).
4. **Delivery Order (DO)**:
   - *Status:* **SELESAI** (Dashboard task count & background Toast notification aktif ketika ada Tally yang selesai dikunci (Locked) dan siap dibuatkan DO).
5. **Delivery Order Receipt (Pemeriksaan Penerimaan DO)**:
   - *Status:* **SELESAI** (Dashboard alert aktif ketika status DO "Ready"; tidak menggunakan Toast).
6. **Repack**:
   - *Status:* **SELESAI** (Memiliki counter tugas di Dashboard; sesuai request, tidak memerlukan Toast notification di latar belakang).
7. **Delivery Plan**:
   - *Status:* **SELESAI** (Memiliki counter tugas di Dashboard; sesuai request, tidak memerlukan Toast notification di latar belakang).
