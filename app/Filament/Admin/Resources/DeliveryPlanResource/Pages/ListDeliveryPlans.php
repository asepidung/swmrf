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
        return [];
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
