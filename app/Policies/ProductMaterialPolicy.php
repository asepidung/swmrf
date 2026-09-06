<?php

namespace App\Policies;

use App\Models\ProductMaterial;
use App\Models\User;

class ProductMaterialPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_product_materials'); }
    public function view(User $user, ProductMaterial $model): bool { return $user->hasPermission('view_product_materials'); }
    public function create(User $user): bool { return $user->hasPermission('create_product_materials'); }
    public function update(User $user, ProductMaterial $model): bool { return $user->hasPermission('edit_product_materials'); }
    public function delete(User $user, ProductMaterial $model): bool { return $user->hasPermission('delete_product_materials'); }
}
