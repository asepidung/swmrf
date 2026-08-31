<?php

namespace App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages\Concerns;

use App\Models\CustomerGroup;

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
            $group = CustomerGroup::firstOrCreate(
                ['name' => $data['name']],
                [
                    'head_office_pic' => $data['pic'] ?? null,
                    'head_office_address' => $data['address'] ?? null,
                ],
            );

            $data['customer_group_id'] = $group->id;
        }

        return $data;
    }
}
