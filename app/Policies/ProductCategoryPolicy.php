<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_product_categories'); }
    public function view(User $user, ProductCategory $model): bool { return $user->hasPermission('view_product_categories'); }
    public function create(User $user): bool { return $user->hasPermission('create_product_categories'); }
    public function update(User $user, ProductCategory $model): bool { return $user->hasPermission('edit_product_categories'); }
    public function delete(User $user, ProductCategory $model): bool { return $user->hasPermission('delete_product_categories'); }
    public function restore(User $user, ProductCategory $model): bool { return $user->hasPermission('delete_product_categories'); }
    public function forceDelete(User $user, ProductCategory $model): bool { return $user->hasPermission('delete_product_categories'); }
}