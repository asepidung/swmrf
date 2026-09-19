<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * "Kasbon belum kembali" wajib mudah dijangkau -- prinsip modul ini
     * bukan approval, melainkan keterlihatan (lihat docblock Expense).
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All')),
            'open' => Tab::make(__('Open Advances'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Expense::STATUS_OPEN)),
        ];
    }
}
