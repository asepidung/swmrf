<?php

use App\Models\CattleWeighing;
use Illuminate\Database\Migrations\Migration;

/**
 * Isi berat yang hilang pada kerugian yang sudah tercatat sebelumnya.
 *
 * Kolom `quantity` baru lahir 4 September 2026, jadi baris kerugian yang
 * dicatat sebelum itu hanya punya rupiahnya. Project Owner: *"kita gak punya
 * tempat untuk liat berat lost nya kalo bisa ambil beratnya di finansial lost
 * mangga soalnya polymorphic"* -- kolomnya sudah ada, tetapi baris lamanya
 * masih kosong, sehingga permintaan itu baru terpenuhi untuk kejadian baru.
 *
 * Angkanya DIHITUNG ULANG dari sumbernya, bukan ditebak dari rupiahnya.
 * Membagi rupiah dengan harga akan meleset begitu satu dokumen memuat lebih
 * dari satu kelas sapi dengan harga berbeda -- dan itu keadaan biasa.
 *
 * `calculateAndSaveFinancialLoss()` memakai `updateOrCreate`, jadi menjalankan
 * ulang tidak menggandakan apa pun. Dokumen yang ternyata tidak rugi akan
 * menghapus baris kerugiannya sendiri, dan itu memang benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        CattleWeighing::query()
            ->whereHas('financialLoss', fn ($q) => $q->whereNull('quantity'))
            ->get()
            ->each
            ->calculateAndSaveFinancialLoss();
    }

    public function down(): void
    {
        // Tidak ada yang perlu dibalik: yang dilakukan hanya mengisi angka
        // yang memang sudah bisa dihitung sejak dokumennya dibuat.
    }
};
