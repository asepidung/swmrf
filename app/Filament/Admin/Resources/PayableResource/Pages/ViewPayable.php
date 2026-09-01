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
                // Melihat tagihan dan MEMBAYAR tagihan adalah dua tingkat
                // wewenang yang berbeda. Sebelumnya siapa pun yang bisa
                // melihat utang otomatis bisa mengeluarkan uang perusahaan.
                ->visible(fn () => $this->record->balance > 0
                    && (auth()->user()?->hasPermission('pay_payables') ?? false))
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

                    // Update hutangnya lewat satu-satunya tempat rumus saldo
                    // dan status ditulis. Salinan rumus di sini dulu tidak
                    // mengenal kompensasi, sehingga hutang yang sama bisa
                    // menunjukkan angka berbeda tergantung apakah ia terakhir
                    // disentuh lewat halaman ini atau lewat modelnya.
                    $this->record->paid_amount += $amount;
                    $this->record->recalculate();
                    $this->record->save();

                    \Filament\Notifications\Notification::make()
                        ->title(__('Payment Recorded Successfully'))
                        ->success()
                        ->send();
                        
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            // Di layar HP tiga tombol sejajar terlipat menjadi dua baris
            // yang tidak rata, dan yang paling mencolok justru bukan aksi
            // yang paling sering dipakai.
            //
            // Mencatat pembayaran adalah pekerjaan sehari-hari, jadi ia
            // yang tetap berdiri sendiri. Mencatat kompensasi jarang, dan
            // Kembali selalu ada di mana-mana -- keduanya masuk ke satu
            // kelompok, sehingga layar sempit cukup menampilkan satu
            // tombol utama dan satu tombol titik tiga.
            Actions\ActionGroup::make([
                // Mencatat kompensasi MENGURANGI yang harus dibayar perusahaan,
                // jadi ia keputusan uang -- haknya terpisah dari melihat daftar
                // hutang, dan terpisah pula dari membayar.
                Actions\Action::make('compensation')
                    ->label(__('Record Compensation'))
                    ->icon('heroicon-o-receipt-percent')
                    ->color('warning')
                    ->visible(fn () => $this->record->balance > 0
                        && (auth()->user()?->hasPermission('record_payable_compensations') ?? false))
                    ->modalDescription(__('The purchase order keeps its agreed price. Only the payable goes down.'))
                    ->form([
                        \Filament\Forms\Components\Placeholder::make('sisa')
                            ->label(__('Outstanding'))
                            ->content(fn () => 'Rp '.number_format(
                                (float) $this->record->amount
                                - (float) $this->record->compensation
                                - (float) $this->record->paid_amount,
                                0,
                                ',',
                                '.',
                            )),

                        // Alasannya menentukan perlakuannya, bukan sekadar
                        // keterangan -- karena itu wajib dipilih dan tidak
                        // punya nilai bawaan.
                        \Filament\Forms\Components\Radio::make('reason')
                            ->label(__('Reason'))
                            ->options([
                                \App\Models\Payable::COMPENSATION_FOR_QUALITY => __('Poor quality'),
                                \App\Models\Payable::COMPENSATION_FOR_WEIGHT => __('Weight shortfall'),
                            ])
                            ->descriptions([
                                \App\Models\Payable::COMPENSATION_FOR_QUALITY => __('Reduces the payable only. The recorded shrinkage loss stays as it is.'),
                                \App\Models\Payable::COMPENSATION_FOR_WEIGHT => __('Also reduces the recorded shrinkage loss, because it recovers the same thing.'),
                            ])
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label(__('Compensation'))
                            ->prefix('Rp')
                            ->required()
                            ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                            // Isian ini selalu kosong saat dibuka, jadi bahaya
                            // "seratus kali lipat" tidak berlaku di sini. Tetap
                            // dipasang karena aturannya memang menyeluruh:
                            // pengecualian yang beralasan "yang ini aman" persis
                            // cara bug itu kembali.
                            ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                                ? null
                                : number_format((float) $state, 0, ',', '.'))
                            ->mask(\Filament\Support\RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->rules(['numeric', 'gt:0']),

                        \Filament\Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $this->record->applyCompensation(
                                (float) $data['amount'],
                                $data['reason'],
                                $data['note'] ?? null,
                            );
                        } catch (\InvalidArgumentException $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title(__('Compensation recorded'))
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('back')
                    ->label(__('Back'))
                    ->color('gray')
                    ->url(fn (): string => $this->getResource()::getUrl('index')),
            ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->label(__('More')),
        ];
    }
}
