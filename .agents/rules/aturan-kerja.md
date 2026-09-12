# Aturan kerja di repositori ini

Berlaku untuk agen mana pun -- Claude, Gemini, atau yang lain. Ditulis
13 September 2026 setelah tinjauan lima hari kerja tanpa commit menemukan
empat kesalahan yang semuanya sudah punya aturan tetapi aturannya tidak
terbaca.

## Baca dulu, sebelum menyentuh apa pun

1. `project.md` -- aturan main yang mengikat.
2. `.agents/agents.md` **bagian 0** -- tenggat, siapa mengerjakan apa, dan
   cara merge. Lalu cari entri modul yang akan disentuh; hampir semua bentuk
   yang terlihat aneh adalah hasil keputusan yang sudah selesai.
3. `.agents/tertunda.md` -- yang sengaja belum dikerjakan. Jangan dikerjakan
   tanpa diminta.
4. `.agents/hpp.md` -- kalau menyentuh HPP, BOM, atau costing. Jangan
   menurunkan ulang rumusnya; sudah diuji ke berkas aslinya.

## Yang dilarang

- `php artisan migrate:fresh` di basis data mana pun.
- `php artisan db:seed` di server -- ia mengatur ulang kata sandi superuser.
- `php artisan test --filter=...` -- sebut path berkasnya.
- Commit langsung ke `main`. Setiap pekerjaan lewat branch `feature/issue-N`
  dengan Issue GitHub-nya, lalu PR.
- Menulis kata sandi sungguhan. **Repositori ini publik.**
- Menaikkan berkas dari `REFERENSI/` -- sudah di-ignore, jangan dilepas.
- Meninggalkan skrip sekali pakai di akar repositori (`fix_*.php` dan
  sejenisnya). Pakai folder sementara di luar repo, lalu hapus.
- Memakai Filament Shield.

## Yang wajib

- **Izin baru lahir lewat MIGRASI**, bukan hanya seeder. Seeder tidak pernah
  dijalankan di server, jadi izin yang hanya ada di seeder tidak akan pernah
  sampai ke hosting -- policy menolak semua orang dan centangnya tidak bisa
  diberikan. Contoh polanya: `create_the_fleet_permissions`.
- **Grup navigasi ditulis persis sama** dengan yang sudah ada: `MASTER DATA`,
  bukan `Master Data`. Dalam bahasa Indonesia keduanya diterjemahkan berbeda
  dan menjadi dua grup sidebar. Ini berlaku untuk Cluster juga, bukan hanya
  Resource. Ada test yang menjaganya.
- **Kalau skema kolom berubah, cari SEMUA pembacanya** -- form, tabel,
  cetakan, export, test. Mengubah `affected_count` dari angka ke teks sambil
  membiarkan cetakannya memakai `number_format()` menghasilkan error 500 saat
  tombol Print ditekan.
- **Suite PENUH sebelum push**: `php artisan test`, sekitar 3 menit.
- Setiap perubahan ditulis di `.agents/agents.md` dengan tanggal, dalam
  bahasa Indonesia, beserta ALASANNYA -- bukan hanya apa yang diubah.
- Kunci terjemahan dalam bahasa Inggris, terdaftar di `lang/en.json` DAN
  `lang/id.json`. Ada test yang menjaganya.
- Jumlah "belum diisi" disimpan `null`, bukan `0`. Keduanya berarti hal yang
  berbeda di proyek ini.

## Menulis catatan lewat terminal

Kalau menulis ke `agents.md` lewat heredoc atau echo, **hindari backslash**.
`\a`, `\n`, `\t` akan ditelan shell dan mengubah `afterStateUpdated` menjadi
`fterStateUpdated`. Itu sudah terjadi. Tulis lewat editor, atau pakai tool
penulis berkas.

## Merge dan deploy

Sampai tenggat lewat, agen **tidak me-merge sendiri**. Kerjakan sampai
membuka PR, lalu sebutkan nomor PR-nya ke Owner. Sesi yang ditunjuk Owner
(Hafizh) menjalankan suite penuh, meninjau, lalu merge dan deploy.
