<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('expense.print', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('receipt_photo')
                ->label(__('Receipt Photo'))
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->receipt_photo))
                ->url(fn () => route('expense.receipt-photo', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('edit')
                ->label(__('Edit'))
                ->icon('heroicon-o-pencil')
                ->visible(fn (): bool => $this->record->status === Expense::STATUS_OPEN
                    && (auth()->user()?->hasPermission('edit_expenses') ?? false))
                ->url(fn () => ExpenseResource::getUrl('edit', ['record' => $this->record])),

            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn () => $this->getResource()::getUrl('index')),
        ];
    }
}
