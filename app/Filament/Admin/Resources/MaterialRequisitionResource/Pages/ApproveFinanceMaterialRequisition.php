<?php

namespace App\Filament\Admin\Resources\MaterialRequisitionResource\Pages;

use App\Filament\Admin\Resources\MaterialRequisitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\User;

class ApproveFinanceMaterialRequisition extends EditRecord
{
    protected static string $resource = MaterialRequisitionResource::class;
    
    public function getTitle(): string
    {
        return 'Finance Approval: ' . $this->record->document_number;
    }

    public array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->mapWithKeys(function ($item) {
            return [(string) \Illuminate\Support\Str::uuid() => [
                'material_id' => $item->material_id,
                'qty' => (float) $item->qty,
                'price' => (float) $item->price,
                'item_total' => (float) ($item->qty * $item->price),
                'note' => $item->note,
            ]];
        })->toArray();
        return $data;
    }

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['material_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    /** Nilai pembayaran dianggap ada bila lebih dari nol setelah di-parse. */
    protected static function hasPaymentAmount($value): bool
    {
        return MaterialRequisitionResource::parseNumber($value) > 0;
    }

    /**
     * Catat pembayaran di muka bila finance mengisi nilainya.
     *
     * Bila nilainya 0 seluruh bagian ini dilewati: dokumen murni menjadi utang
     * dan TOP mulai berjalan saat barang diterima. Itu keputusan Project Owner
     * dan alasan tidak ada dokumen pembayaran bernilai nol di sistem.
     *
     * Pembayaran disimpan sebagai dokumen tersendiri, bukan kolom di tabel
     * request, karena utang baru lahir saat barang diterima. Saat itulah uang
     * muka ini ditelusuri kembali dan dipotongkan ke utangnya.
     */
    protected function recordAdvancePayment(array $data): ?\App\Models\SupplierPayment
    {
        $amount = MaterialRequisitionResource::parseNumber($data['payment_amount'] ?? 0);

        if ($amount <= 0) {
            return null;
        }

        $method = $data['payment_method'] ?? \App\Models\SupplierPayment::METHOD_TRANSFER;

        return \App\Models\SupplierPayment::create([
            'supplier_id' => $this->record->supplier_id,
            'source_type' => get_class($this->record),
            'source_id' => $this->record->getKey(),
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'method' => $method,
            'bank_account_id' => $method === \App\Models\SupplierPayment::METHOD_TRANSFER
                ? ($data['bank_account_id'] ?? null)
                : null,
            'reference_number' => $data['payment_reference'] ?? null,
            'amount' => $amount,
            'note' => $data['payment_note'] ?? null,
        ]);
    }

    protected function afterSave(): void
    {
        $this->record->items()->delete();
        foreach ($this->itemsData as $item) {
            if (!empty($item['material_id'])) {
                $this->record->items()->create([
                    'material_id' => $item['material_id'],
                    'qty' => $item['qty'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'subtotal' => ($item['qty'] ?? 0) * ($item['price'] ?? 0),
                    'note' => $item['note'] ?? null,
                ]);
            }
        }
        $this->record->updateTotalAmount();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getApproveAction(),
            $this->getRejectAction(),
        ];
    }

    protected function getApproveAction(): Actions\Action
    {
        return Actions\Action::make('approve')
            ->label('Approve & Generate PO')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('Approve & Generate PO'))
            ->form([
                \Filament\Forms\Components\Section::make(__('Payment (Optional)'))
                    ->description(__('Fill this in only if the goods were paid or partially paid up front. Leave the amount at 0 to record it purely as a payable.'))
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('payment_amount')
                            ->label(__('Payment Amount'))
                            ->prefix('Rp')
                            ->default(0)
                            ->live(onBlur: true)
                            ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                            ->helperText(__('Leave at 0 if nothing has been paid yet.')),

                        \Filament\Forms\Components\Radio::make('payment_method')
                            ->label(__('Payment Method'))
                            ->options([
                                'cash' => __('Cash'),
                                'transfer' => __('Transfer'),
                            ])
                            ->inline()
                            ->default('transfer')
                            ->required(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount')))
                            ->visible(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount')))
                            ->live(),

                        \Filament\Forms\Components\Select::make('bank_account_id')
                            ->label(__('Bank Account'))
                            ->options(fn () => \App\Models\BankAccount::query()
                                ->orderBy('initial')
                                ->get()
                                ->mapWithKeys(fn ($account) => [
                                    $account->id => trim($account->initial . ' - ' . $account->account_number . ' (' . $account->account_holder . ')'),
                                ])
                                ->all())
                            ->searchable()
                            ->required(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount')) && $get('payment_method') === 'transfer')
                            ->visible(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount')) && $get('payment_method') === 'transfer'),

                        \Filament\Forms\Components\DatePicker::make('payment_date')
                            ->label(__('Payment Date'))
                            ->default(now())
                            ->required(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount')))
                            ->visible(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount'))),

                        \Filament\Forms\Components\TextInput::make('payment_reference')
                            ->label(__('Payment Reference'))
                            ->maxLength(255)
                            ->visible(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount'))),

                        \Filament\Forms\Components\Textarea::make('payment_note')
                            ->label(__('Payment Note'))
                            ->rows(2)
                            ->visible(fn (\Filament\Forms\Get $get) => static::hasPaymentAmount($get('payment_amount'))),
                    ]),
            ])
            ->action(function (array $data) {
                // Argumen KEDUA mematikan toast "Saved" bawaan Filament, supaya
                // pengguna tidak melihat dua toast sekaligus.
                $this->save(false, false);

                \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                    $this->record->update([
                        'status' => 'PO Created',
                        'reject_note' => null,
                    ]);

                    $this->record->generatePurchaseOrder();

                    // Dicatat di dalam transaksi yang sama: PO terbit dan uang
                    // muka tercatat sekaligus, atau dua-duanya batal.
                    $this->recordAdvancePayment($data);
                });

                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'review_material_requisitions',
                    __('Material Request Approved'),
                    __('Approved by finance, the PO has been issued.'),
                    \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('view', ['record' => $this->record]),
                    'material-request-' . $this->record->id,
                    auth()->id(),
                );

                \Filament\Notifications\Notification::make()
                    ->title(__('PO Generated successfully'))
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRejectAction(): Actions\Action
    {
        return Actions\Action::make('reject')
            ->label('Return to Purchasing')
            ->color('danger')
            ->icon('heroicon-s-arrow-uturn-left')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reject_note')
                    ->label('Alasan Pengembalian')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update([
                    'status' => 'Returned to Purchasing',
                    'reject_note' => $data['reject_note'],
                ]);

                // Kembali ke PURCHASING, bukan ke pemohon: purchasing yang harus
                // memperbaiki harganya. Tombol ini memang bukan reject.
                \App\Support\TaskNotifier::notifyPermissionHolders(
                    'review_material_requisitions',
                    __('Material Request Returned'),
                    __('Returned by finance, please review it again.'),
                    \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('review', ['record' => $this->record]),
                    'material-request-' . $this->record->id,
                    auth()->id(),
                );

                \Filament\Notifications\Notification::make()
                    ->title(__('Returned successfully'))
                    ->success()
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Back')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
