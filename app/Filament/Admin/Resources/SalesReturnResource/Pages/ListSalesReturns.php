<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use App\Models\SalesReturn;
use App\Models\SalesReturnPlan;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturns extends ListRecords
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Issue #451: tombol Create polos dihilangkan -- retur hanya
            // lahir dengan menarik sebuah plan yang sudah Submitted. Guard
            // sungguhannya di SalesReturn::booted() (creating), ini cuma
            // satu-satunya pintu yang tersisa di layar.
            Actions\Action::make('pull_plan')
                ->label(__('Pull Plan'))
                ->icon('heroicon-o-arrow-down-tray')
                ->authorize(fn (): bool => auth()->user()?->can('create', SalesReturn::class) ?? false)
                ->form([
                    Forms\Components\Select::make('sales_return_plan_id')
                        ->label(__('Plan'))
                        ->options(fn () => SalesReturnPlan::query()
                            ->where('status', SalesReturnPlan::STATUS_SUBMITTED)
                            ->whereDoesntHave('salesReturn')
                            ->with('customer')
                            ->get()
                            ->mapWithKeys(fn (SalesReturnPlan $plan) => [
                                $plan->id => $plan->plan_number.' - '.($plan->customer?->name ?? '-'),
                            ]))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $plan = SalesReturnPlan::findOrFail($data['sales_return_plan_id']);

                    try {
                        $salesReturn = SalesReturn::create([
                            'sales_return_plan_id' => $plan->id,
                            'customer_id' => $plan->customer_id,
                            'delivery_order_id' => $plan->delivery_order_id,
                            'return_date' => now(),
                        ]);
                    } catch (\Exception $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $salesReturn]));
                }),

            Actions\Action::make('detail_list')
                ->label(__('Detail List'))
                ->color('info')
                ->url(static::getResource()::getUrl('detail-list')),
        ];
    }
}
