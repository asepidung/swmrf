# SWM — Baca Ini Dulu

ERP Wijaya Meat (produsen daging). Laravel 11 + Filament v3 + MySQL.

**Sebelum mengerjakan apa pun, baca dua dokumen ini:**

1. **[`project.md`](project.md)** — aturan main. Apa yang wajib dan apa yang dilarang.
2. **[`.agents/agents.md`](.agents/agents.md)** — apa yang sudah diputuskan dan kenapa. Riwayat, alasan, dan jebakan yang sudah pernah ditabrak.

Penjelasan rinci tiap modul ada di [`docs/modules/`](docs/modules/).

## Yang paling gampang bikin celaka

- **Merge ke `main` = deploy.** GitHub Actions langsung menjalankan `php artisan migrate --force` ke server uji coba.
- **Dilarang `migrate:fresh`.** Selalu migrasi inkremental.
- **Verifikasi `phpunit.xml` memakai SQLite `:memory:`** sebelum menjalankan `php artisan test`.
- **Migrasi wajib bersintaks lintas-driver** (MySQL dan SQLite). Sintaks khusus MySQL akan mematikan seluruh test suite.
- **Repositori ini publik.** Jangan pernah menulis password sungguhan di file mana pun.

## Alur kerja

Implementation Plan dalam Bahasa Indonesia → tunggu persetujuan Project Owner → branch `feature/issue-[nomor]` → test → PR → merge → verifikasi di server.

Berbahasa Indonesia saat berkomunikasi dengan Project Owner.
