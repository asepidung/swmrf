<?php

namespace App\Policies;

use App\Models\ProductRequisition;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_product_requisitions');
    }

    public function view(User $user, ProductRequisition $productRequisition): bool
    {
        return $user->hasPermission('view_product_requisitions');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create_product_requisitions');
    }

    public function update(User $user, ProductRequisition $productRequisition): bool
    {
        return $user->hasPermission('edit_product_requisitions');
    }

    public function delete(User $user, ProductRequisition $productRequisition): bool
    {
        return $user->hasPermission('delete_product_requisitions');
    }

    public function restore(User $user, ProductRequisition $productRequisition): bool
    {
        return $user->hasPermission('delete_product_requisitions');
    }

    public function forceDelete(User $user, ProductRequisition $productRequisition): bool
    {
        return $user->hasPermission('delete_product_requisitions');
    }
}
