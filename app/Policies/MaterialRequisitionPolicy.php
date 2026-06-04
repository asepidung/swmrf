<?php

namespace App\Policies;

use App\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MaterialRequisitionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_material_requisitions') || $user->hasRole('programmer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MaterialRequisition $materialRequisition): bool
    {
        return $user->hasPermission('view_material_requisitions') || $user->hasRole('programmer');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_material_requisitions') || $user->hasRole('programmer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MaterialRequisition $materialRequisition): bool
    {
        return $user->hasPermission('edit_material_requisitions') || $user->hasRole('programmer');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MaterialRequisition $materialRequisition): bool
    {
        return $user->hasPermission('delete_material_requisitions') || $user->hasRole('programmer');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MaterialRequisition $materialRequisition): bool
    {
        return $user->hasPermission('delete_material_requisitions') || $user->hasRole('programmer');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MaterialRequisition $materialRequisition): bool
    {
        return $user->hasPermission('delete_material_requisitions') || $user->hasRole('programmer');
    }
}
