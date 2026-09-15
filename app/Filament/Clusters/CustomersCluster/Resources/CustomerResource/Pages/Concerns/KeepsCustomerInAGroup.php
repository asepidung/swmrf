<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\Concerns;

use App\Models\CustomerGroup;
use Illuminate\Support\Facades\DB;

/**
 * Setiap pelanggan selalu berakhir di dalam sebuah grup.
 *
 * Grup adalah satu-satunya jalan menuju harga: price list dikunci ke
 * `customer_groups`, jadi pelanggan tanpa grup tidak akan pernah bisa punya
 * harga. Karena itu grup yang dikosongkan di form dibuatkan otomatis dengan
 * nama pelanggannya sendiri.
 *
 * Halaman Create dan Edit dulu memuat potongan kode ini masing-masing satu
 * salinan yang persis sama. Disatukan supaya keduanya tidak bisa berbeda
 * diam-diam -- kalau sampai berbeda, pelanggan yang disunting bisa berakhir
 * di grup yang berlainan dengan saat ia dibuat, dan ikut berpindah price
 * list tanpa ada yang meminta.
 */
trait KeepsCustomerInAGroup
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureCustomerGroup(array $data): array
    {
        $data['name'] = strtoupper($data['name']);

        if (empty($data['customer_group_id'])) {
            // Dikunci: dua submit bersamaan dengan nama yang sama persis
            // bisa sama-sama membaca "belum ada" lalu sama-sama INSERT --
            // satu berhasil, satu gagal kena unique constraint
            // `customer_groups.name` (galat SQL mentah, tidak tertangkap).
            // `lockForUpdate()` membuat submit kedua menunggu yang pertama
            // commit, lalu membaca grup yang baru saja dibuat alih-alih
            // mencoba membuatnya lagi.
            $group = DB::transaction(function () use ($data) {
                $existing = CustomerGroup::where('name', $data['name'])
                    ->lockForUpdate()
                    ->first();

                return $existing ?? CustomerGroup::create([
                    'name' => $data['name'],
                    'head_office_pic' => $data['pic'] ?? null,
                    'head_office_address' => $data['address'] ?? null,
                    'top' => $data['top'] ?? null,
                ]);
            });

            $data['customer_group_id'] = $group->id;
        }

        return $data;
    }
}
