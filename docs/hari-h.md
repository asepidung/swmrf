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

Sesudah reset, dua hal manual (keputusan Ayah, 30 Oktober 2026):

- `settings` dipertahankan oleh reset dan masih memuat `repack.max_shrink_percent` = 5 hasil coba-coba peserta. Hapus barisnya supaya kosong;
  QC yang mengisi angka resminya. Kosong berarti pembatas susut Repack belum menyala (`Setting::number()` mengembalikan null).
  `php8.4 artisan tinker --execute="DB::table('settings')->where('key','repack.max_shrink_percent')->delete();"`
- `php8.4 artisan cache:clear`, supaya tidak ada sisa cache latihan.

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

Urutan impor mengikuti `legacy/tarik/gladi-run.sh` (28 langkah; skrip ini tidak ikut git karena `legacy/` di-ignore). Tiap langkah dry-run dulu,
berhenti bila ada penolakan (`DITOLAK: <angka > 0>` atau kode keluar bukan nol), lalu `--apply`. Pemakaian di mesin tujuan: `gladi-run.sh [awal] [akhir]`.
Berkas snapshot diletakkan di `~/gladi30/` dengan pola `SWM-<modul>-<tanggal>-*.sql`; ganti tanggalnya lewat variabel `TGL`.

1-8 master dan data awal: import-master, import-prices, seed-groups, seed-segments, seed-fleet, deactivate-non-request, expense-categories, bank-accounts.
9-26 legacy: stok, stok-material, request-material, request-beef, po-material, po-beef, gr-material, gr-beef, po-sapi, receive-timbang, karkas, boning,
sales-order, tally, delivery-order, invoice, repack, retur-penjualan. 27 hutang (`--before=2026-11-02`). 28 pricelist.

Invoice: `--payment` adalah SATU pilihan untuk seluruh riwayat (`settled` = semua dianggap lunas, `open` = tidak ada yang dibayar). Gladi memakai `settled`.
Legacy tidak mencatat pelunasan, jadi Ayah menentukan mana yang dipakai sebelum hari-H.

Hasil gladi bersih di coba, 30 Oktober 2026 (snapshot legacy hari itu):

| Langkah | Lama |
|---|---|
| Tarik 16 modul snapshot dari legacy (hanya baca) | 34 detik |
| Unggah 137 MB ke server tujuan (scp) | 2 menit |
| Reset (backup 10 MB sebelumnya) | beberapa detik |
| 28 langkah impor (dry-run + apply) | 545 detik (sekitar 9 menit) |
| `legacy:reconcile` | beberapa detik |

Terlama: boning (186 detik), tally (189 detik), repack (75 detik). Hasil `legacy:reconcile`: SEMUA COCOK, 29 dari 29 pemeriksaan.
Baris "konflik" yang dilewati importer (mis. 163 request material, 156 PO material, 46 GR material) bukan penolakan; daftarnya dibaca dari laporan dry-run.

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
