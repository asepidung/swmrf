<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_sales_orders');
    }

    public function view(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('view_sales_orders');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_sales_orders');
    }

    public function update(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('edit_sales_orders');
    }

    public function delete(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('delete_sales_orders') && in_array($model->status, [SalesOrder::STATUS_WAITING, SalesOrder::STATUS_CANCELLED], true);
    }

    public function restore(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('delete_sales_orders') && in_array($model->status, [SalesOrder::STATUS_WAITING, SalesOrder::STATUS_CANCELLED], true);
    }

    public function forceDelete(User $user, SalesOrder $model): bool
    {
        return $user->hasPermission('delete_sales_orders') && in_array($model->status, [SalesOrder::STATUS_WAITING, SalesOrder::STATUS_CANCELLED], true);
    }

    /**
     * Gerbang BULK, dievaluasi tanpa satu baris tertentu -- karena itu
     * cuma memeriksa izinnya saja, tidak seperti `delete()` yang juga
     * mensyaratkan status baris (`STATUS_WAITING`/`STATUS_CANCELLED`).
     *
     * Ini BUKAN celah baru: `DeleteBulkAction` bawaan Filament
     * (`$records->each(fn ($r) => $r->delete())`) tidak pernah memanggil
     * `delete($user, $record)` per baris -- itu murni panggilan Eloquent,
     * bukan Gate. Artinya syarat status di `delete()` TIDAK PERNAH
     * ditegakkan lewat jalur bulk, sebelum maupun sesudah tambalan ini;
     * yang ditambal di sini cuma "siapa pun boleh mencoba", bukan
     * "boleh menghapus baris apa pun". Dicatat sebagai temuan terpisah
     * untuk PR mendatang, di luar cakupan tambalan *Any() ini.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_sales_orders');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('delete_sales_orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('delete_sales_orders');
    }
}
