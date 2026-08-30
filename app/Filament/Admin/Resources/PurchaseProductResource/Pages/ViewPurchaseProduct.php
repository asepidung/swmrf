<?php

namespace App\Filament\Admin\Resources\PurchaseProductResource\Pages;

use App\Filament\Admin\Resources\PurchaseProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseProduct extends ViewRecord
{
    protected static string $resource = PurchaseProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pay_down_payment')
                ->label(__('Pay Down Payment'))
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                // Melihat PO dan MEMBAYAR DP adalah dua tingkat wewenang
                // yang berbeda. Sebelumnya tidak dibedakan sama sekali.
                ->visible(fn () => auth()->user()?->hasPermission('pay_purchase_products') ?? false)
                ->form([
                    \Filament\Forms\Components\DatePicker::make('payment_date')
                        ->label(__('Payment Date'))
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\Select::make('method')
                        ->label(__('Method'))
                        ->options([
                            \App\Models\SupplierPayment::METHOD_CASH => __('Cash'),
                            \App\Models\SupplierPayment::METHOD_TRANSFER => __('Bank Transfer'),
                        ])
                        ->required()
                        ->live(),
                    \Filament\Forms\Components\Select::make('bank_account_id')
                        ->label(__('Bank Account'))
                        ->options(\App\Models\BankAccount::where('is_active', true)->where('initial', '!=', 'KAS')->pluck('initial', 'id'))
                        ->required(fn (\Filament\Forms\Get $get) => $get('method') === \App\Models\SupplierPayment::METHOD_TRANSFER)
                        ->visible(fn (\Filament\Forms\Get $get) => $get('method') === \App\Models\SupplierPayment::METHOD_TRANSFER),
                    \Filament\Forms\Components\Placeholder::make('total_tagihan')
                        ->label(__('Total Bill'))
                        ->content(fn () => 'Rp ' . number_format($this->record->total_amount, 0, ',', '.')),
                    \Filament\Forms\Components\TextInput::make('amount_input')
                        ->label(__('Amount (Rp)'))
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
                                    $fail(__('Amount must be greater than 0.'));
                                }
                                if ($val > $this->record->total_amount) {
                                    $fail(__('Payment cannot exceed the bill of Rp :total.', ['total' => number_format($this->record->total_amount, 0, ',', '.')]));
                                }
                            },
                        ]),
                    \Filament\Forms\Components\TextInput::make('reference_number')
                        ->label(__('Reference Number')),
                    \Filament\Forms\Components\Textarea::make('note')
                        ->label(__('Note')),
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
                        ->title(__('Payment Recorded Successfully'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('print')
                ->label('Print PO')
                ->visible(fn () => auth()->user()->hasPermission('print_purchase_products'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('print.po-product', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
                
            Actions\DeleteAction::make()
                ->label(__('Delete PO'))
                ->tooltip(__('Delete PO'))
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->goodsReceipts()->count() === 0)
                ->action(function () {
                    if ($this->record->productRequisition) {
                        $this->record->productRequisition->update([
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
