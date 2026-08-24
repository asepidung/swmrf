<?php

namespace App\Filament\Admin\Resources\ProductRequisitionResource\Pages;

use App\Filament\Admin\Resources\ProductRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductRequisition extends ViewRecord
{
    protected static string $resource = ProductRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Request: ' . $this->record->document_number;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'product_id' => $item->product_id,
                // Boleh diformat karena qty dan price BUKAN lagi ->numeric().
                // Selama masih <input type="number">, string ber-pemisah ribuan
                // ditolak browser dan fieldnya tampil kosong.
                'qty' => number_format((float) $item->qty, 2, ',', '.'),
                'price' => number_format((float) $item->price, 0, ',', '.'),
                'item_total' => number_format((float) $item->qty * (float) $item->price, 0, ',', '.'),
                'note' => $item->note,
            ]];
        })->toArray();
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->tooltip(fn() => __('Print'))
                ->hiddenLabel()
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.product-request', ['id' => $this->record->id]))
                ->openUrlInNewTab(),

                        \Filament\Actions\Action::make('review')
                ->tooltip(fn() => __('Review'))
                ->hiddenLabel()
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(function () {
                    $user = auth()->user();
                    return in_array($this->record->status, ['Requested', 'Returned to Purchasing']) && ($user->isProgrammer() || $user->hasPermission('review_product_requisitions'));
                })
                ->modalHeading(fn() => __('Review Request'))
                ->modalDescription(fn() => __('Review request ini dan setujui untuk dilanjutkan ke Finance, atau reject ke pembuat.'))
                ->modalSubmitActionLabel(fn() => __('Submit Review'))
                ->modalIcon('heroicon-o-clipboard-document-check')
                ->form([
                    \Filament\Forms\Components\Radio::make('decision')
                        ->label(fn() => __('Decision'))
                        ->options([
                            'approve' => 'Approve (Send to Finance)',
                            'reject' => 'Reject',
                        ])
                        ->required()
                        ->live(),
                    \Filament\Forms\Components\Textarea::make('reject_note')
                        ->label(fn() => __('Reason for Rejection'))
                        ->required(fn (\Filament\Forms\Get $get) => $get('decision') === 'reject')
                        ->visible(fn (\Filament\Forms\Get $get) => $get('decision') === 'reject'),
                ])
                ->action(function (array $data) {
                    if ($data['decision'] === 'approve') {
                        $this->record->update([
                            'status' => 'Pending Finance',
                            'reject_note' => null,
                        ]);
                        \Filament\Notifications\Notification::make()->title('Request sent to Finance')->success()->send();
                    } else {
                        $this->record->update([
                            'status' => 'Rejected',
                            'reject_note' => $data['reject_note'],
                        ]);
                        \Filament\Notifications\Notification::make()->title('Request rejected')->warning()->send();
                    }
                }),

            Actions\Action::make('resubmit')
                ->tooltip(fn() => __('Resubmit Request'))
                ->hiddenLabel()
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === 'Rejected' && $this->record->user_id === auth()->id())
                ->action(function () {
                    $this->record->update([
                        'status' => 'Requested',
                        'reject_note' => null,
                    ]);
                }),

            Actions\Action::make('finance_approval')
                ->tooltip(fn() => __('Finance Approval'))
                ->hiddenLabel()
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(function () {
                    $user = auth()->user();
                    return $this->record->status === 'Pending Finance' && ($user->isProgrammer() || $user->hasPermission('approve_product_requisitions'));
                })
                                ->modalHeading(fn() => __('Finance Approval'))
                ->modalDescription(fn() => __('Silakan setujui untuk lanjut dibuatkan PO, atau kembalikan ke Purchasing.'))
                ->modalSubmitActionLabel(fn() => __('Submit Decision'))
                ->modalIcon('heroicon-o-shield-check')
                ->form([
                    \Filament\Forms\Components\Radio::make('decision')
                        ->label(fn() => __('Decision'))
                        ->options([
                            'approve' => 'Approve & Generate PO',
                            'reject' => 'Reject (Return to Purchasing)',
                        ])
                        ->required()
                        ->live(),
                    \Filament\Forms\Components\Textarea::make('reject_note')
                        ->label(fn() => __('Reason for Rejection'))
                        ->required(fn (\Filament\Forms\Get $get) => $get('decision') === 'reject')
                        ->visible(fn (\Filament\Forms\Get $get) => $get('decision') === 'reject'),
                ])
                ->action(function (array $data) {
                    if ($data['decision'] === 'approve') {
                        \Illuminate\Support\Facades\DB::transaction(function () {
                            $this->record->update([
                                'status' => 'PO Created',
                                'reject_note' => null,
                            ]);
                            $this->record->generatePurchaseOrder();
                        });
                        \Filament\Notifications\Notification::make()->title('PO Created Successfully')->success()->send();
                    } else {
                        $this->record->update([
                            'status' => 'Returned to Purchasing',
                            'reject_note' => $data['reject_note'],
                        ]);
                        \Filament\Notifications\Notification::make()->title('Returned to Purchasing')->warning()->send();
                    }
                }),

            Actions\EditAction::make()
                ->tooltip(fn() => __('Edit'))
                ->icon('heroicon-o-pencil')
                ->hiddenLabel()
                ->visible(fn() => in_array($this->record->status, ['Requested', 'Returned to Purchasing'])),

            Actions\DeleteAction::make()
                ->tooltip(fn() => __('Delete'))
                ->icon('heroicon-o-trash')
                ->hiddenLabel()
                ->visible(fn() => $this->record->status === 'Requested'),

            Actions\Action::make('back')
                ->tooltip(fn() => __('Back to List'))
                ->hiddenLabel()
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
