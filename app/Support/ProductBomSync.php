<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan seluruh baris BOM sebuah produk SEKALIGUS (issue #507).
 *
 * Form BOM di halaman produk berbentuk baris-baris yang disimpan bersama
 * tombol Save changes, bukan modal per baris. Karena itu satu permintaan bisa
 * menambah, mengubah, dan menghapus baris sekaligus, dan penegakan izinnya
 * harus ada DI SINI -- menyembunyikan tombol di layar tidak menutup permintaan
 * yang dikirim langsung:
 *
 *   - menambah baris  -> `create_product_materials`
 *   - mengubah baris  -> `edit_product_materials`
 *   - menghapus baris -> `delete_product_materials`
 *
 * Mengganti BAHAN sebuah baris dihitung sebagai ubah, tetapi dikerjakan
 * sebagai hapus lalu buat: dua baris yang saling bertukar bahan akan
 * bertabrakan dengan index unik (produk, bahan) di tengah jalan. Id sebuah
 * baris BOM tidak dirujuk siapa pun, jadi ini aman.
 */
class ProductBomSync
{
    /**
     * @param  array<string, array<string, mixed>>  $state  baris dari form, berkunci "record-{id}" untuk yang sudah ada
     */
    public static function sync(Product $product, array $state, ?User $user = null): void
    {
        $user ??= auth()->user();

        $rows = self::normalise($state);

        self::refuseDuplicates($rows);

        DB::transaction(function () use ($product, $rows, $user): void {
            $existing = $product->billOfMaterials()->lockForUpdate()->get()->keyBy('id');

            $keptIds = collect($rows)->pluck('id')->filter()->all();

            $toDelete = $existing->except($keptIds);
            $toCreate = [];
            $toUpdate = [];

            foreach ($rows as $row) {
                $current = $row['id'] ? $existing->get($row['id']) : null;

                if ($row['id'] && ! $current) {
                    // Baris yang sudah dihapus dari sesi lain: perlakukan sebagai baru.
                    $toCreate[] = $row;

                    continue;
                }

                if (! $current) {
                    $toCreate[] = $row;

                    continue;
                }

                if (! self::changed($current, $row)) {
                    continue;
                }

                if ((int) $current->material_id !== $row['material_id']) {
                    $toDelete->put($current->id, $current);
                    $toCreate[] = $row;
                } else {
                    $toUpdate[] = [$current, $row];
                }
            }

            // Penggantian bahan = ubah, jadi cukup izin ubah (bukan hapus + buat).
            $replaced = collect($toCreate)->filter(fn (array $r) => $r['id'] && $existing->has($r['id']))->count();
            $pureDeletes = $toDelete->count() - $replaced;
            $pureCreates = count($toCreate) - $replaced;

            self::authorise($user, $pureCreates > 0, count($toUpdate) + $replaced > 0, $pureDeletes > 0);

            foreach ($toDelete as $row) {
                $row->delete();
            }

            foreach ($toUpdate as [$current, $row]) {
                $current->update(self::attributes($row));
            }

            foreach ($toCreate as $row) {
                $product->billOfMaterials()->create(self::attributes($row));
            }
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $state
     * @return array<int, array{id: int|null, material_id: int, basis: string, quantity: int|null, note: string|null}>
     */
    private static function normalise(array $state): array
    {
        $rows = [];

        foreach ($state as $key => $item) {
            $materialId = (int) ($item['material_id'] ?? 0);

            // Baris kosong (belum memilih bahan) diabaikan, bukan dianggap salah.
            if ($materialId <= 0) {
                continue;
            }

            $quantity = $item['quantity'] ?? null;

            $rows[] = [
                'id' => preg_match('/^record-(\d+)$/', (string) $key, $m) ? (int) $m[1] : null,
                'material_id' => $materialId,
                'basis' => array_key_exists((string) ($item['basis'] ?? ''), ProductMaterial::BASIS) ? $item['basis'] : 'box',
                // Kosong BUKAN nol: kosong = jumlahnya tidak tetap.
                'quantity' => ($quantity === null || $quantity === '') ? null : (int) $quantity,
                'note' => ($note = trim((string) ($item['note'] ?? ''))) === '' ? null : $note,
            ];
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private static function refuseDuplicates(array $rows): void
    {
        $seen = [];

        foreach ($rows as $row) {
            if (isset($seen[$row['material_id']])) {
                throw ValidationException::withMessages([
                    'data.billOfMaterials' => __('Each material can only be listed once per product.'),
                ]);
            }

            $seen[$row['material_id']] = true;

            if ($row['quantity'] !== null && $row['quantity'] < 1) {
                throw ValidationException::withMessages([
                    'data.billOfMaterials' => __('Quantity must be at least 1, or left empty when it is never the same.'),
                ]);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private static function changed(ProductMaterial $current, array $row): bool
    {
        return (int) $current->material_id !== $row['material_id']
            || $current->basis !== $row['basis']
            || $current->quantity !== $row['quantity']
            || ($current->note ?: null) !== $row['note'];
    }

    /** @param array<string, mixed> $row */
    private static function attributes(array $row): array
    {
        return [
            'material_id' => $row['material_id'],
            'basis' => $row['basis'],
            'quantity' => $row['quantity'],
            'note' => $row['note'],
        ];
    }

    private static function authorise(?User $user, bool $creates, bool $updates, bool $deletes): void
    {
        foreach ([
            [$creates, 'create_product_materials', __('You are not allowed to add rows to a bill of material.')],
            [$updates, 'edit_product_materials', __('You are not allowed to change rows of a bill of material.')],
            [$deletes, 'delete_product_materials', __('You are not allowed to delete rows from a bill of material.')],
        ] as [$needed, $permission, $message]) {
            if ($needed && ! ($user?->hasPermission($permission) ?? false)) {
                throw ValidationException::withMessages(['data.billOfMaterials' => $message]);
            }
        }
    }
}
