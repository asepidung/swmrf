# Runbook hari-H: dari legacy ke aplikasi baru

Keputusan Ayah (30 Oktober 2026): VPS `app.wijayameat.co.id` dipakai lagi untuk produksi. Setelah ditutup, hanya tim yang masuk.
User, hak akses, notifikasi, dan master hasil kesepakatan dibawa; data latihan dikosongkan. Legacy tetap dipakai sampai 1 November
00:00, lalu datanya ditangkap dan dipindahkan. VPS dibuka untuk semua user pada 2 November 06:00.

Aturan: setiap perintah impor dijalankan **dry-run dulu**, hasilnya dibaca, baru `--apply`. Kalau ada "DITOLAK" atau angka yang
tidak masuk akal, berhenti dan tanya Ayah. Jangan memaksa lanjut.

## 0. Syarat sebelum mulai

- Backup database VPS (dump gzip, uji `gzip -t`), simpan di `~/backup/`. Simpan juga satu salinan di luar VPS.
- `main` yang akan dipakai sudah di-deploy ke VPS dan ke coba. (Termasuk fix login PR #701.)
- Daftar user dan hak akses yang disepakati sudah final di VPS (tidak ada perubahan hak akses lagi dari peserta).
- Berkas snapshot legacy tidak boleh masuk git. Pengambilan memakai `legacy/tarik/tarik.sh`.

## 1. Tutup VPS (malam penutupan)

Pakai mode pemeliharaan Laravel. Tidak butuh root, dan orang tim tetap bisa masuk lewat alamat rahasia.

```bash
cd ~/htdocs/app.wijayameat.co.id && php8.4 artisan down --secret="<kata-rahasia-tim>" --render="errors::503"
```

Tim membuka `https://app.wijayameat.co.id/<kata-rahasia-tim>` sekali, lalu cookie bypass terpasang. Peserta lain melihat halaman 503.
Buka kembali dengan `php8.4 artisan up`.

## 2. Reset data latihan

```bash
php8.4 artisan app:reset-training-data            # dry-run, baca laporan
php8.4 artisan app:reset-training-data --apply    # mengetik nama database
```

Dipertahankan: users, hak akses, langganan push, grade, gudang, akun kas (saldo awal KAS tetap). Dikosongkan: transaksi dan master latihan.

## 3. Master dan data awal

Urutan disusun dari ketergantungan antartabel. Semuanya dry-run dulu, lalu `--apply`.

1. `legacy:import-master --source=<snapshot master>.sql` (kategori, grade, supplier, kelas sapi, grup, pelanggan, produk, material, BOM). Konfirmasi
   bahwa kolom `is_auxiliary` dan `is_active` ikut terbawa.
2. `legacy:import-prices` (price list dari xlsx terbaru, dry-run ditunjukkan ke Ayah).
3. `master:seed-groups`, `master:seed-segments`, `master:seed-fleet`.
4. `materials:deactivate-non-request`.
5. `expenses:seed-categories --apply` dan `bank-accounts:seed-company --apply`. Dua ini TIDAK ikut snapshot master, jadi wajib dijalankan di setiap lingkungan.

## 4. Tangkap data legacy (setelah legacy berhenti 1 November 00:00)

Pastikan dulu tidak ada transaksi baru di legacy. Tarik semua snapshot dengan `legacy/tarik/tarik.sh`, catat waktu tarik.

Urutan impor (setiap langkah: dry-run, baca, `--apply`):

1. `legacy:import-stock`
2. `legacy:import-material-stock`
3. `legacy:import-requests` (material, lalu `--beef`)
4. `legacy:import-pos` (material, lalu `--beef`)
5. `legacy:import-grs`, `legacy:import-beef-grs`
6. `legacy:import-cattle-pos`, `legacy:import-cattle-receipts`, `legacy:import-carcasses`, `legacy:import-bonings`
7. `legacy:import-tallies`
8. `legacy:import-sales-orders`, `legacy:import-delivery-orders`
9. `legacy:import-invoices` (wajib `--payment=settled` atau `open`; dua kali jalan untuk dua kelompok)
10. `legacy:import-sales-returns`, `legacy:import-repacks`
11. `legacy:import-payables --before=2026-11-02`
12. `pricelist:build-from-sales --apply`

Catatan: urutan ini disusun dari ketergantungan, bukan dari catatan pelaksanaan. Gladi di coba (bagian 6) yang memastikannya.

## 5. Periksa sebelum dibuka

- `legacy:reconcile --dir=<folder snapshot>` untuk modul stok, boning, so, tally, do, invoice, repack, retur. Selisih harus dijelaskan satu per satu.
- `php artisan optimize`, `filament:optimize`, cek halaman login 200.
- Masuk dengan akun Ayah dan akun satu orang dari tiap peran: izin sesuai, dashboard muncul, notifikasi push terkirim.
- Buat satu dokumen uji dari tiap alur utama (tidak disimpan; hapus sesudahnya bila perlu) untuk memastikan tidak ada error 500.
- Hitung ulang: stok per gudang, piutang, hutang, saldo KAS dibandingkan dengan legacy.

## 6. Gladi bersih (sebelum malam penutupan)

Jalankan bagian 2-5 penuh di coba memakai snapshot terbaru, dengan stopwatch. Catat lama tiap langkah, temuan, dan perintah yang gagal.
Data expense dan price list di coba harus direset sebelum gladi supaya hasilnya bisa dibandingkan.

## 7. Buka VPS (2 November 06:00)

```bash
php8.4 artisan up
```

Umumkan ke semua user. Sandi bawaan user impor wajib diganti saat masuk pertama. Siapkan satu orang tim di lapangan jam pertama.

## 8. Jalan mundur

Sebelum bagian 2 sudah ada backup. Kalau ada kegagalan yang tidak bisa diperbaiki: tetap dalam mode pemeliharaan, pulihkan backup,
analisis, ulangi. Legacy tidak dimatikan sampai Ayah menyatakan hasil periksa bagian 5 lolos.
