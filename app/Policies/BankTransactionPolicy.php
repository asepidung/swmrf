<?php

namespace App\Policies;

use App\Models\BankTransaction;
use App\Models\User;

/**
 * Policy untuk Buku Kas (`CashBookResource`).
 *
 * Resource itu memakai model milik modul lain, sehingga tanpa Policy ini
 * Laravel mengizinkan apa saja pada `BankTransaction` (fail-open). Bacanya
 * dijaga `view_cash_book`; menulisnya tidak boleh oleh siapa pun lewat
 * Resource, karena setiap baris adalah jejak dokumen lain (DP supplier,
 * penerimaan piutang, Expense). Mengubahnya dari sini memutus hubungan itu.
 */
class BankTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_cash_book');
    }

    public function view(User $user, BankTransaction $model): bool
    {
        return $user->hasPermission('view_cash_book');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BankTransaction $model): bool
    {
        return false;
    }

    public function delete(User $user, BankTransaction $model): bool
    {
        return false;
    }

    public function restore(User $user, BankTransaction $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, BankTransaction $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
