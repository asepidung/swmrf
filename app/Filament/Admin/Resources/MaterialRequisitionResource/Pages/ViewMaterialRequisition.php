<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMaterialRequisition extends ViewRecord
{
    protected static string $resource = MaterialRequisitionResource::class;

    public function getTitle(): string
    {
        return 'Request: ' . $this->record->document_number;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
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
                ->url(fn() => route('print.material-request', ['id' => $this->record->id]))
                ->openUrlInNewTab(),

            // Diarahkan ke halaman Review, BUKAN modal. Keputusan purchasing bergantung
            // pada harga, dan harga hanya bisa dilihat serta diisi di halaman itu.
            // Modal di halaman View dulu melewati seluruh validasi harga.
            \Filament\Actions\Action::make('review')
                ->tooltip(fn() => __('Review'))
                ->hiddenLabel()
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(function () {
                        $user = auth()->user();
                        return in_array($this->record->status, ['Requested', 'Returned to Purchasing']) && ($user->isProgrammer() || $user->hasPermission('review_material_requisitions'));
                    })
                ->url(fn() => MaterialRequisitionResource::getUrl('review', ['record' => $this->record])),


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

                    \App\Support\TaskNotifier::notifyPermissionHolders(
                        'review_material_requisitions',
                        __('Material Request Resubmitted'),
                        __('A rejected request has been resubmitted.'),
                        \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]),
                        'material-request-' . $this->record->id,
                        auth()->id(),
                    );
                }),

            // Diarahkan ke halaman Finance Approval, BUKAN modal, dengan alasan yang
            // sama seperti tombol Review di atas.
            \Filament\Actions\Action::make('finance_approval')
                ->tooltip(fn() => __('Finance Approval'))
                ->hiddenLabel()
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(function () {
                        $user = auth()->user();
                        return $this->record->status === 'Pending Finance' && ($user->isProgrammer() || $user->hasPermission('approve_material_requisitions'));
                    })
                ->url(fn() => MaterialRequisitionResource::getUrl('approve-finance', ['record' => $this->record])),


            Actions\EditAction::make()
                ->tooltip(fn() => __('Edit'))
                ->icon('heroicon-o-pencil')
                ->hiddenLabel()
                ->visible(function () {
                    if (in_array($this->record->status, ['Requested', 'Returned to Purchasing'])) {
                        return true;
                    }
                    if ($this->record->status === 'Rejected' && $this->record->user_id === auth()->id()) {
                        return true;
                    }
                    return false;
                }),

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
