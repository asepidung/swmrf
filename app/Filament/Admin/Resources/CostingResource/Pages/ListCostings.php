<?php

namespace App\Filament\Admin\Resources\CostingResource\Pages;

use App\Filament\Admin\Resources\CostingResource;
use App\Models\Boning;
use App\Models\Costing;
use App\Services\CostingCalculator;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

/**
 * Tidak ada tombol Create polos -- costing hanya lahir dari memilih
 * sebuah `Boning` yang sudah `kunci` dan belum punya costing, lalu
 * langsung dihitung (issue #480). Pola sama dengan "Tarik Plan" di
 * `ListSalesReturns` (issue #451).
 */
class ListCostings extends ListRecords
{
    protected static string $resource = CostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create_costing')
                ->label(__('Create Costing'))
                ->icon('heroicon-o-calculator')
                ->authorize(fn (): bool => auth()->user()?->can('create', Costing::class) ?? false)
                ->form([
                    Forms\Components\Select::make('boning_id')
                        ->label(__('Boning'))
                        ->options(fn () => Boning::query()
                            ->where('kunci', true)
                            ->whereDoesntHave('costing')
                            ->get()
                            ->mapWithKeys(fn (Boning $boning) => [
                                $boning->id => $boning->doc_no.' -- '.$boning->boning_date?->format('d M Y'),
                            ]))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $boning = Boning::findOrFail($data['boning_id']);

                    try {
                        $result = CostingCalculator::forBoning($boning)->calculate();
                        $costing = Costing::createFromCalculation($boning, $result);
                    } catch (\Exception $e) {
                        report($e);
                        Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $costing]));
                }),
        ];
    }
}
