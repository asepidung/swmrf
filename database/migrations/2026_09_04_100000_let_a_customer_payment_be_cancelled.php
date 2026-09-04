<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran pelanggan akhirnya bisa dibatalkan.
 *
 * Sampai 4 September 2026 tidak ada jalan mundur sama sekali. Sekali tombol
 * Catat Pembayaran ditekan, salah ketik nominal -- 5.500.000 menjadi
 * 55.000.000 -- hanya bisa diperbaiki lewat basis data langsung.
 *
 * Taruhannya lima hal sekaligus: dokumen pembayaran beserta nomornya, baris
 * potongan, alokasi ke tiap invoice, dua baris buku kas, dan `paid_amount`
 * setiap invoice yang tersentuh. Satu di antaranya salah berarti saldo bank
 * atau piutang ikut salah.
 *
 * MEMBALIK, BUKAN MENGHAPUS. Barisnya tetap ada dengan tanda dibatalkan,
 * lengkap dengan siapa yang membatalkan dan alasannya, lalu dibuatkan
 * lawannya. Keuangan bisa membaca "ada pembayaran, dibatalkan tanggal sekian
 * oleh siapa" -- bukan angka yang tiba-tiba lenyap seolah tidak pernah ada.
 *
 * Nomor dokumennya sengaja TETAP TERPAKAI. Nomor yang sudah terbit tidak boleh
 * dipakai ulang, dan penomoran kita memang sudah menghitung yang terhapus.
 *
 * IZINNYA SENDIRI, bukan menumpang `receive_receivables`. Keputusan Project
 * Owner: mencatat uang masuk dan membatalkannya dua kewenangan yang berbeda,
 * dan biasanya orangnya juga berbeda.
 */
return new class extends Migration
{
    private const PERMISSION = [
        'name' => 'cancel_receivable_payments',
        'module_name' => 'Receivables',
        'description' => 'Cancel a recorded customer payment',
    ];

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('note');

            $table->foreignId('cancelled_by')
                ->nullable()
                ->after('cancelled_at')
                ->constrained('users')
                ->nullOnDelete();

            // Alasannya WAJIB diisi saat membatalkan, dan disimpan supaya
            // pembatalan tidak pernah menjadi peristiwa tanpa penjelasan.
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
        });

        Permission::updateOrCreate(['name' => self::PERMISSION['name']], self::PERMISSION);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });

        Permission::where('name', self::PERMISSION['name'])->delete();
    }
};
