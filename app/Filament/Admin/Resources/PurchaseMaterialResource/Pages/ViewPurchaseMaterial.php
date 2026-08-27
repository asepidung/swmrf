<?php

namespace App\Filament\Admin\Resources\PurchaseMaterialResource\Pages;

use App\Filament\Admin\Resources\PurchaseMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseMaterial extends ViewRecord
{
    protected static string $resource = PurchaseMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pay_down_payment')
                ->label('Bayar Uang Muka (DP)')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('payment_date')
                        ->label('Tanggal Pembayaran')
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\Select::make('method')
                        ->label('Metode')
                        ->options([
                            \App\Models\SupplierPayment::METHOD_CASH => 'Tunai (Kas Kecil)',
                            \App\Models\SupplierPayment::METHOD_TRANSFER => 'Transfer Bank',
                        ])
                        ->required()
                        ->live(),
                    \Filament\Forms\Components\Select::make('bank_account_id')
                        ->label('Rekening Bank')
                        ->options(\App\Models\BankAccount::where('is_active', true)->where('initial', '!=', 'KAS')->pluck('bank_name', 'id'))
                        ->required(fn (\Filament\Forms\Get $get) => $get('method') === \App\Models\SupplierPayment::METHOD_TRANSFER)
                        ->visible(fn (\Filament\Forms\Get $get) => $get('method') === \App\Models\SupplierPayment::METHOD_TRANSFER),
                    \Filament\Forms\Components\Placeholder::make('total_tagihan')
                        ->label('Total Tagihan (PO)')
                        ->content(fn () => 'Rp ' . number_format($this->record->total_amount, 0, ',', '.')),
                    \Filament\Forms\Components\TextInput::make('amount_input')
                        ->label('Nominal DP (Rp)')
                        ->required()
                        ->extraInputAttributes([
                            'x-on:input' => '
                                let val = $el.value.replace(/[^0-9]/g, "");
                                $el.value = new Intl.NumberFormat("id-ID").format(val);
                            '
                        ])
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                $val = (float) str_replace('.', '', $value);
                                if ($val <= 0) {
                                    $fail('Nominal harus lebih dari 0.');
                                }
                                if ($val > $this->record->total_amount) {
                                    $fail('Nominal DP tidak boleh melebihi Total Tagihan (Rp ' . number_format($this->record->total_amount, 0, ',', '.') . ').');
                                }
                            },
                        ]),
                    \Filament\Forms\Components\TextInput::make('reference_number')
                        ->label('No. Referensi / Bukti Transfer'),
                    \Filament\Forms\Components\Textarea::make('note')
                        ->label('Catatan'),
                ])
                ->action(function (array $data) {
                    $amount = (float) str_replace('.', '', $data['amount_input']);
                    
                    \App\Models\SupplierPayment::create([
                        'supplier_id' => $this->record->supplier_id,
                        'source_type' => get_class($this->record),
                        'source_id' => $this->record->id,
                        'payment_date' => $data['payment_date'],
                        'method' => $data['method'],
                        'bank_account_id' => $data['method'] === \App\Models\SupplierPayment::METHOD_TRANSFER ? $data['bank_account_id'] : null,
                        'amount' => $amount,
                        'reference_number' => $data['reference_number'],
                        'note' => $data['note'],
                        'allocated_amount' => 0,
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Uang Muka Berhasil Dicatat')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('print')
                ->label('Print PO')
                ->visible(fn () => auth()->user()->hasPermission('print_purchase_materials'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.po-material', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
                
            Actions\DeleteAction::make()
                ->tooltip('Delete PO')
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->goodsReceipts()->count() === 0)
                ->action(function () {
                    if ($this->record->materialRequisition) {
                        $this->record->materialRequisition->update([
                            'status' => 'Pending Finance'
                        ]);
                    }
                    $this->record->delete();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('back')
                ->label('Back to List')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
