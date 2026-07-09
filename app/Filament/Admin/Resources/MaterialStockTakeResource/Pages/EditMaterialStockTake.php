<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Models\MaterialStock;
use App\Models\MaterialStockMovement;
use Illuminate\Support\Facades\DB;

class EditMaterialStockTake extends EditRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        // Hide save button if not in progress/draft
        if (!in_array($this->record->status, ['DRAFT', 'IN_PROGRESS', 'REVIEW'])) {
            return [];
        }

        $actions = parent::getFormActions();
        
        // Hide default cancel button at the bottom
        $actions = array_filter($actions, fn ($action) => $action->getName() !== 'cancel');
        
        return $actions;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Actions\Action::make('cancel_button')
            ->label(__('Cancel'))
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        if (in_array($this->record->status, ['DRAFT', 'IN_PROGRESS'])) {
            $actions[] = Actions\Action::make('submit_for_review')
                ->label(__('Submit for Review'))
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading('Submit Opname for Review?')
                ->modalDescription('Once submitted, the physical counts cannot be edited by standard users and the variance will be shown for review.')
                ->action(function () {
                    $this->record->update(['status' => 'REVIEW']);
                    Notification::make()->title('Opname submitted for review.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                });
        }

        if ($this->record->status === 'REVIEW') {
            $actions[] = Actions\Action::make('complete_opname')
                ->label(__('Complete Opname'))
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Complete Material Opname?')
                ->modalDescription('This will finalize the stock take and adjust the material stocks permanently.')
                ->action(function () {
                    DB::transaction(function () {
                        $record = $this->record;
                        $items = $record->items;

                        foreach ($items as $item) {
                            if ($item->difference_qty != 0 && $item->physical_qty !== null) {
                                // Adjust stock
                                $stock = MaterialStock::firstOrCreate(
                                    ['material_id' => $item->material_id],
                                    ['qty' => 0]
                                );

                                $stock->qty = $item->physical_qty;
                                $stock->save();

                                // Log movement
                                MaterialStockMovement::create([
                                    'material_id' => $item->material_id,
                                    'transaction_type' => 'STOCK_TAKE_ADJUSTMENT',
                                    'reference_document' => $record->document_number,
                                    'qty_in' => $item->difference_qty > 0 ? $item->difference_qty : 0,
                                    'qty_out' => $item->difference_qty < 0 ? abs($item->difference_qty) : 0,
                                    'balance' => $item->physical_qty,
                                    'note' => 'Stock Take Adjustment',
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }

                        $record->update([
                            'status' => 'COMPLETED',
                            'completed_by' => auth()->id(),
                            'completed_at' => now(),
                        ]);
                    });

                    Notification::make()->title('Stock Opname Completed and Stock Adjusted.')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        $actions[] = Actions\DeleteAction::make();
        $actions[] = Actions\ForceDeleteAction::make();
        $actions[] = Actions\RestoreAction::make();

        return $actions;
    }
}
