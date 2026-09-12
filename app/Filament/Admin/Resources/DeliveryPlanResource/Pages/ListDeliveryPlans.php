<?php

namespace App\Filament\Admin\Resources\DeliveryPlanResource\Pages;

use App\Filament\Admin\Resources\DeliveryPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryPlans extends ListRecords
{
    protected static string $resource = DeliveryPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_preview')
                ->label(__('Print Plan'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('date')
                        ->label(__('Delivery Date'))
                        ->default(now()->addDay()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data, \Filament\Resources\Pages\ListRecords $livewire) {
                    $url = route('print.delivery-plan.preview', ['date' => $data['date']]);
                    $livewire->js("window.open('{$url}', '_blank');");
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => \Filament\Resources\Components\Tab::make('Active')
                ->label(__('Active'))
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where(function ($q) {
                    $q->whereDate('delivery_date', '>', now()->toDateString())
                      ->orWhere(function ($q2) {
                          $q2->whereDate('delivery_date', '<=', now()->toDateString())
                             ->whereHas('salesOrders', function ($q3) {
                                 $q3->whereNotIn('status', ['on_delivery', 'completed', 'canceled', 'cancelled']);
                             });
                      });
                })),
            'history' => \Filament\Resources\Components\Tab::make('History')
                ->label(__('History'))
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereDate('delivery_date', '<=', now()->toDateString())),
        ];
    }
}
