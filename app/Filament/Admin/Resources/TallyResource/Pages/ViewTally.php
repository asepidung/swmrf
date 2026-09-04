<?php

namespace App\Filament\Admin\Resources\TallyResource\Pages;

use App\Filament\Admin\Resources\TallyResource;
use App\Models\SalesOrder;
use App\Models\Tally;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ViewTally extends ViewRecord
{
    protected static string $resource = TallyResource::class;

    protected static string $view = 'filament.admin.resources.tally-resource.pages.view-tally';

    public function getHeading(): string
    {
        return __('View Tally') . ': ' . $this->record->tally_number;
    }

    public function getTitle(): string
    {
        return __('View Tally') . ': ' . $this->record->tally_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('')
                ->tooltip(__('Back to List'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->iconButton()
                ->url(fn () => $this->getResource()::getUrl('index')),

            Actions\Action::make('scan')
                ->label('')
                ->tooltip(__('Scan Barcodes'))
                ->icon('heroicon-m-qr-code')
                ->color('primary')
                ->iconButton()
                ->url(fn () => $this->getResource()::getUrl('scan', ['record' => $this->record->id]))
                ->visible(fn () => $this->record->status === Tally::STATUS_PROCESSING && $this->record->salesOrder?->status !== SalesOrder::STATUS_CANCELLED),

            Actions\Action::make('approve')
                ->label('')
                ->tooltip(__('Approve Tally'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->iconButton()
                ->requiresConfirmation()
                ->modalHeading(__('Approve Tally'))
                ->modalDescription(__('Apakah Anda yakin ingin menyetujui Tally ini? Setelah disetujui, data tidak dapat diubah lagi.'))
                ->form([
                    Forms\Components\TextInput::make('seal_number')
                        ->label(__('Seal Number (If Any)'))
                        ->placeholder(__('Seal Number')),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $this->record->update([
                            'status' => Tally::STATUS_LOCKED,
                            'seal_number' => $data['seal_number'] ?? null,
                        ]);
                        $this->record->salesOrder->update([
                            'status' => 'ready',
                        ]);

                        activity('tally')
                            ->performedOn($this->record)
                            ->log('Approved Tally: ' . $this->record->tally_number);
                    });

                    Notification::make()
                        ->title(__('Tally Approved Successfully'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record->id]));
                })
                ->visible(fn () => $this->record->status === Tally::STATUS_PROCESSING && auth()->user()->hasPermission('lock_tallies')),

            Actions\Action::make('print')
                ->label('')
                ->tooltip(__('Print Tally'))
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->iconButton()
                ->url(fn () => route('print.tally', ['record' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('unapprove')
                ->label('')
                ->tooltip(__('Unapprove Tally'))
                ->icon('heroicon-m-x-circle')
                ->color('warning')
                ->iconButton()
                ->requiresConfirmation()
                ->modalHeading(__('Batal Setujui Tally'))
                ->modalDescription(__('Apakah Anda yakin ingin membatalkan persetujuan Tally ini? Status Tally akan kembali ke processing dan Sales Order akan kembali ke processing.'))
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->update([
                            'status' => Tally::STATUS_PROCESSING,
                        ]);
                        $this->record->salesOrder->update([
                            'status' => Tally::STATUS_PROCESSING,
                        ]);

                        activity('tally')
                            ->performedOn($this->record)
                            ->log('Unapproved Tally: ' . $this->record->tally_number);
                    });

                    Notification::make()
                        ->title(__('Tally Unapproved Successfully'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('scan', ['record' => $this->record->id]));
                })
                ->visible(fn () => $this->record->status === Tally::STATUS_LOCKED && auth()->user()->hasPermission('lock_tallies')),

            Actions\Action::make('delete')
                ->label('')
                ->tooltip(__('Delete Tally'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->iconButton()
                ->requiresConfirmation()
                ->modalHeading(__('Hapus Tally'))
                ->modalDescription(__('Jika Anda menghapus Tally ini, maka semua data barang di dalam Tally akan dikembalikan ke stock.'))
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->delete();
                    });
                    Notification::make()
                        ->title(__('Tally Deleted and Stock Restored'))
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->visible(fn () => $this->record->status === Tally::STATUS_PROCESSING && auth()->user()->hasPermission('delete_tallies')),
        ];
    }

    public function getViewData(): array
    {
        $productData = [];
        foreach ($this->record->items as $item) {
            $productName = $item->product?->name ?? 'Unknown';
            if (!isset($productData[$productName])) {
                $productData[$productName] = [
                    'weights' => [],
                    'total' => 0,
                ];
            }
            $productData[$productName]['weights'][] = (float) $item->weight;
            $productData[$productName]['total'] += (float) $item->weight;
        }

        $totalBox = $this->record->items()->count();
        $totalQty = (float) $this->record->items()->sum('weight');

        return [
            'productData' => $productData,
            'totalBox' => $totalBox,
            'totalQty' => $totalQty,
        ];
    }
}
