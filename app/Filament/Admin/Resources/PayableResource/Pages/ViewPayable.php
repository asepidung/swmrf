<?php

namespace App\Filament\Admin\Resources\PayableResource\Pages;

use App\Filament\Admin\Resources\PayableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayable extends ViewRecord
{
    protected static string $resource = PayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pay')
                ->label(__('Record Payment'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->balance > 0)
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
                        ->options(\App\Models\BankAccount::where('is_active', true)->where('initial', '!=', 'KAS')->pluck('bank_name', 'id'))
                        ->required(fn (\Filament\Forms\Get $get) => $get('method') === \App\Models\SupplierPayment::METHOD_TRANSFER)
                        ->visible(fn (\Filament\Forms\Get $get) => $get('method') === \App\Models\SupplierPayment::METHOD_TRANSFER),
                    \Filament\Forms\Components\Placeholder::make('sisa_tagihan')
                        ->label(__('Outstanding Balance'))
                        ->content(fn () => 'Rp ' . number_format($this->record->balance, 0, ',', '.')),
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
                                if ($val > $this->record->balance) {
                                    $fail(__('Payment cannot exceed the bill of Rp :total.', ['total' => number_format($this->record->balance, 0, ',', '.')]));
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
                    
                    // Buat SupplierPayment (mencatat uang keluar ke kas/bank)
                    $payment = \App\Models\SupplierPayment::create([
                        'supplier_id' => $this->record->supplier_id,
                        'source_type' => get_class($this->record),
                        'source_id' => $this->record->id,
                        'payment_date' => $data['payment_date'],
                        'method' => $data['method'],
                        'bank_account_id' => $data['method'] === \App\Models\SupplierPayment::METHOD_TRANSFER ? $data['bank_account_id'] : null,
                        'amount' => $amount,
                        'reference_number' => $data['reference_number'],
                        'note' => $data['note'],
                        'allocated_amount' => $amount, // Langsung dialokasikan semua karena ini bayar hutang langsung
                    ]);

                    // Update hutangnya
                    $this->record->paid_amount += $amount;
                    $this->record->balance = $this->record->amount - $this->record->paid_amount;
                    
                    if ($this->record->paid_amount >= $this->record->amount) {
                        $this->record->status = 'paid';
                    } else {
                        $this->record->status = 'partial';
                    }
                    $this->record->save();

                    \Filament\Notifications\Notification::make()
                        ->title(__('Payment Recorded Successfully'))
                        ->success()
                        ->send();
                        
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => $this->getResource()::getUrl('index')),
        ];
    }
}
