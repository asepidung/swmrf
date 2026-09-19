<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Tidak lewat `Expense::create()` polos -- setiap dokumen HARUS
     * melahirkan BankTransaction-nya sendiri, dan itu hanya terjadi lewat
     * `createAdvance()`/`createReimburse()` (lihat model).
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return $data['type'] === Expense::TYPE_ADVANCE
                ? Expense::createAdvance($data)
                : Expense::createReimburse($data);
        } catch (\Exception $e) {
            report($e);
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
