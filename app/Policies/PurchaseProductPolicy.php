<?php

namespace App\Policies;

use App\Models\PurchaseProduct;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_purchase_products');
    }

    public function view(User $user, PurchaseProduct $purchaseProduct): bool
    {
        return $user->hasPermission('view_purchase_products');
    }

    public function create(User $user): bool
    {
        return false; // Created automatically
    }

    public function update(User $user, PurchaseProduct $purchaseProduct): bool
    {
        return false; // No edit for PO
    }

    public function delete(User $user, PurchaseProduct $purchaseProduct): bool
    {
        return false;
    }

    public function restore(User $user, PurchaseProduct $purchaseProduct): bool
    {
        return false;
    }

    public function forceDelete(User $user, PurchaseProduct $purchaseProduct): bool
    {
        return false;
    }
}
