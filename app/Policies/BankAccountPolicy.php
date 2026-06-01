<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view_bank_accounts'); }
    public function view(User $user, BankAccount $model): bool { return $user->hasPermission('view_bank_accounts'); }
    public function create(User $user): bool { return $user->hasPermission('create_bank_accounts'); }
    public function update(User $user, BankAccount $model): bool { return $user->hasPermission('edit_bank_accounts'); }
    public function delete(User $user, BankAccount $model): bool { return $user->hasPermission('delete_bank_accounts'); }
    public function restore(User $user, BankAccount $model): bool { return $user->hasPermission('delete_bank_accounts'); }
    public function forceDelete(User $user, BankAccount $model): bool { return $user->hasPermission('delete_bank_accounts'); }
}