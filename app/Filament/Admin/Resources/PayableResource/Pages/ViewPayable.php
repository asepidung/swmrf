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
                ->label(__('Pay'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                // Melihat tagihan dan MEMBAYAR tagihan adalah dua tingkat
                // wewenang yang berbeda. Sebelumnya siapa pun yang bisa
                // melihat utang otomatis bisa mengeluarkan uang perusahaan.
                ->visible(fn () => $this->record->balance > 0
                    && (auth()->user()?->hasPermission('pay_payables') ?? false))
                ->authorize(fn (): bool => auth()->user()?->hasPermission('pay_payables') ?? false)
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
                    $ditolak = false;

                    \Illuminate\Support\Facades\DB::transaction(function () use ($data, $amount, &$ditolak) {
                        // Baris utang dikunci dan saldonya dibaca ULANG dari
                        // basis data sebelum uang dikeluarkan. Validasi form
                        // di atas membaca saldo dari state Livewire yang bisa
                        // sudah basi -- tanpa kunci ini, dua permintaan Pay
                        // yang bersamaan (klik ganda, atau dua tab terbuka)
                        // bisa sama-sama lolos validasi itu dan sama-sama
                        // membuat SupplierPayment: uang keluar dobel untuk
                        // utang yang cuma perlu dibayar sekali.
                        $payable = \App\Models\Payable::whereKey($this->record->id)->lockForUpdate()->first();

                        if ($amount > (float) $payable->balance) {
                            $ditolak = true;

                            return;
                        }

                        // Buat SupplierPayment (mencatat uang keluar ke kas/bank)
                        \App\Models\SupplierPayment::create([
                            'supplier_id' => $payable->supplier_id,
                            'source_type' => get_class($payable),
                            'source_id' => $payable->id,
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
                        $payable->paid_amount += $amount;
                        $payable->recalculate();
                        $payable->save();
                    });

                    if ($ditolak) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('This bill no longer has enough outstanding balance for that amount'))
                            ->body(__('It may have just been paid or compensated from another session. Refresh the page and check the current balance.'))
                            ->danger()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));

                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->title(__('Payment Recorded Successfully'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            // Tiga tombol berdampingan, dengan label sependek mungkin.
            //
            // Menyembunyikan sebagiannya ke dalam tombol titik tiga memang
            // merapikan barisnya, tetapi menukar kerapian dengan satu ketukan
            // tambahan -- dan yang ikut tersembunyi justru Kembali, yang
            // paling sering dicari. Memendekkan labelnya menyelesaikan hal
            // yang sama tanpa menyembunyikan apa pun.

            // Mencatat kompensasi MENGURANGI yang harus dibayar perusahaan,
            // jadi ia keputusan uang -- haknya terpisah dari melihat daftar
            // hutang, dan terpisah pula dari membayar.
            Actions\Action::make('compensation')
                ->label(__('Compensate'))
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->visible(fn () => $this->record->balance > 0
                    && (auth()->user()?->hasPermission('record_payable_compensations') ?? false))
                ->authorize(fn (): bool => auth()->user()?->hasPermission('record_payable_compensations') ?? false)
                ->modalHeading(__('Record Compensation'))
                ->modalDescription(__('The purchase order keeps its agreed price, and the recorded shrinkage loss stays as it is. Only the payable goes down.'))
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

                    // Alasannya dicatat sebagai KETERANGAN saja, dan tidak
                    // mengubah angka apa pun. Rancangan sebelumnya membuatnya
                    // menentukan perlakuan -- yang karena berat ikut mengurangi
                    // kerugian susut -- padahal di lapangan komplainnya selalu
                    // soal mutu. Pembedaan itu membedakan sesuatu yang tidak
                    // dibedakan, dan salah memilihnya menghapus kerugian yang
                    // nyata tanpa satu pun gejala.
                    \Filament\Forms\Components\Textarea::make('note')
                        ->label(__('Reason'))
                        ->placeholder(__('For example: too much fat, low meat yield.'))
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $gagal = null;

                    \Illuminate\Support\Facades\DB::transaction(function () use ($data, &$gagal) {
                        // Dikunci dulu, sama alasannya dengan Pay: dua
                        // permintaan Compensate yang bersamaan tidak boleh
                        // sama-sama membaca sisa hutang yang sama SEBELUM
                        // salah satunya menuliskan kompensasinya.
                        $payable = \App\Models\Payable::whereKey($this->record->id)->lockForUpdate()->first();

                        try {
                            $payable->applyCompensation(
                                (float) $data['amount'],
                                $data['note'] ?? null,
                            );
                        } catch (\InvalidArgumentException $e) {
                            report($e);
                            $gagal = $e->getMessage();
                        }
                    });

                    if ($gagal !== null) {
                        \Filament\Notifications\Notification::make()
                            ->title($gagal)
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
        ];
    }
}
