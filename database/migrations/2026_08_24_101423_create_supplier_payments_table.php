<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran ke supplier, termasuk uang muka (DP) yang dibayar saat order.
 *
 * Kenapa tabel tersendiri, bukan kolom nempel di tabel request:
 *
 * Utang (payables) baru lahir saat barang DITERIMA, sementara DP dibayar saat
 * ORDER — jadi DP terjadi ketika utangnya belum ada. Bila DP disimpan di tabel
 * request, saat barang datang sistem akan membuat utang sebesar nilai penuh
 * tanpa ada yang tahu DP sudah dibayar. Utangnya jadi kelebihan catat, dan
 * kesalahan itu tidak menimbulkan error apa pun sehingga baru ketahuan saat
 * supplier menagih.
 *
 * Dengan tabel ini, DP dicatat sebagai dokumen yang berdiri sendiri lalu
 * ditelusuri kembali saat utang dibuat, sehingga saldo utangnya langsung benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();

            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            // Dokumen asal pembayaran. Dibuat polimorfik supaya kelak bisa
            // dipakai dari PO, Goods Receipt, atau langsung dari modul hutang,
            // tanpa perlu mengubah struktur lagi.
            $table->nullableMorphs('source');

            $table->date('payment_date');

            // 'cash' atau 'transfer'. Disimpan sebagai string, bukan enum,
            // supaya menambah metode baru tidak perlu migrasi ubah kolom.
            $table->string('method', 20);
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number')->nullable();

            $table->decimal('amount', 18, 2);

            // Bagian yang sudah dipotongkan ke utang. Selisihnya dengan amount
            // adalah uang muka yang masih menggantung.
            $table->decimal('allocated_amount', 18, 2)->default(0);

            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
