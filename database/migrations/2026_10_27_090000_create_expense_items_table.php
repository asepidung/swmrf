<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desain ulang Expenses (keputusan Owner 27 Oktober 2026): satu pengajuan berisi banyak
 * barang, masing-masing punya kategori, keterangan, dan total harga. Kategori pindah dari
 * kepala dokumen ke baris barang. Pengembalian sisa kasbon bisa sebagian dan bertahap, jadi
 * `returned_amount` mencatat total yang sudah kembali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->change();
            $table->decimal('returned_amount', 15, 2)->default(0)->after('receipt_amount');
        });

        // Dokumen lama menjadi satu barang: nominal nota kalau sudah ada, kalau tidak uang mukanya.
        foreach (DB::table('expenses')->get() as $expense) {
            DB::table('expense_items')->insert([
                'expense_id' => $expense->id,
                'expense_category_id' => $expense->expense_category_id,
                'description' => $expense->description ?: '-',
                'amount' => $expense->receipt_amount ?? $expense->advance_amount ?? 0,
                'created_at' => $expense->created_at,
                'updated_at' => $expense->updated_at,
            ]);

            // Settle lama langsung mengembalikan seluruh sisa dalam satu transaksi.
            if ($expense->type === 'advance' && $expense->status === 'Settled' && $expense->receipt_amount !== null) {
                DB::table('expenses')->where('id', $expense->id)->update([
                    'returned_amount' => max(0, (float) $expense->advance_amount - (float) $expense->receipt_amount),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('returned_amount');
        });

        Schema::dropIfExists('expense_items');
    }
};
