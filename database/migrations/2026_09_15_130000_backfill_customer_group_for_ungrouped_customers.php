<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan Ayah, 15 September 2026: setiap Customer wajib punya grup.
 *
 * Sebelum ini, `customer_group_id` boleh kosong -- dan Customer yang tidak
 * punya grup lenyap total dari modul Piutang: `ReceivableResource` dibangun
 * di atas model `CustomerGroup`, jadi baris `receivables` ber-
 * `customer_group_id` NULL tidak pernah cocok dengan grup mana pun. Invoice
 * untuk Customer semacam ini terbit dengan tagihan sungguhan, tapi tidak
 * pernah terlihat atau bisa dibayar lewat alur resminya sama sekali.
 *
 * Untuk data lama yang sudah terlanjur begini: setiap Customer tanpa grup
 * dibuatkan CustomerGroup baru bernama sama dengan namanya sendiri (nama
 * dipastikan unik lebih dulu -- `customer_groups.name` punya unique index),
 * dan baris `receivables` miliknya yang `customer_group_id`-nya ikut NULL
 * disambungkan ke grup baru itu.
 *
 * Form Create/Edit Customer sekarang mewajibkan field ini, jadi data BARU
 * tidak akan lagi berakhir tanpa grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tanpaGrup = DB::table('customers')->whereNull('customer_group_id')->get();

        foreach ($tanpaGrup as $customer) {
            $nama = $this->namaGrupUnik($customer->name);

            $groupId = DB::table('customer_groups')->insertGetId([
                'name' => $nama,
                'top' => $customer->top,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('customers')->where('id', $customer->id)->update([
                'customer_group_id' => $groupId,
            ]);

            DB::table('receivables')
                ->where('customer_id', $customer->id)
                ->whereNull('customer_group_id')
                ->update(['customer_group_id' => $groupId]);
        }
    }

    private function namaGrupUnik(string $namaCustomer): string
    {
        $nama = $namaCustomer;
        $urutan = 2;

        while (DB::table('customer_groups')->where('name', $nama)->exists()) {
            $nama = $namaCustomer.' '.$urutan;
            $urutan++;
        }

        return $nama;
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan -- tidak ada cara membedakan grup yang
        // dibuat migrasi ini dari grup yang dibuat orang lewat nama yang
        // sama sesudahnya.
    }
};
