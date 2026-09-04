<?php

namespace App\Filament\Admin\Resources\ReceivableResource\RelationManagers;

use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembayaran yang sudah diterima dari sebuah grup pelanggan.
 *
 * Sampai 3 September 2026 pembayaran yang sudah tercatat TIDAK BISA DILIHAT
 * LAGI di mana pun. Tidak ada daftarnya, tidak ada halamannya, dan tidak ada
 * satu pun Resource untuknya -- uang masuk, lalu hilang dari pandangan.
 * Keberadaannya hanya bisa disimpulkan dari sisa tagihan invoice yang
 * berkurang.
 *
 * Akibatnya bukan sekadar tidak nyaman. Kalau pelanggan bertanya "transfer
 * saya tanggal sekian sudah masuk belum", tidak ada satu pun layar yang bisa
 * menjawabnya. Dan karena tidak ada tempatnya, tidak ada pula tempat untuk
 * menaruh tombol cetak bukti terimanya.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Payments Received');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum('allocations', 'amount_allocated'))
            ->columns([
                Tables\Columns\TextColumn::make('payment_number')
                    ->label(__('Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    // Yang sudah dibatalkan TETAP ADA di daftar -- itu inti
                    // dari membalik alih-alih menghapus. Warnanya yang
                    // membedakan, supaya tidak terbaca sebagai pembayaran yang
                    // masih berlaku.
                    ->color(fn (Payment $record): string => $record->isCancelled() ? 'danger' : 'primary')
                    ->description(fn (Payment $record): ?string => $record->isCancelled()
                        ? __('Cancelled: :reason', ['reason' => $record->cancellation_reason])
                        : null),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label(__('Payment Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bankAccount.initial')
                    ->label(__('Into Account')),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('Transfer Reference'))
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount Received in Bank'))
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_deduction')
                    ->label(__('Deductions'))
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->color('warning'),

                // Yang benar-benar melunasi tagihan adalah uang masuk DITAMBAH
                // potongannya, bukan uang masuknya saja. Kolom ini yang
                // dicocokkan dengan berkurangnya piutang.
                Tables\Columns\TextColumn::make('allocations_sum_amount_allocated')
                    ->label(__('Total Settled'))
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->weight('bold'),

                // Sisa yang belum menempel ke invoice mana pun -- inilah
                // deposit, dan di sinilah terlihat dari pembayaran MANA ia
                // berasal.
                Tables\Columns\TextColumn::make('deposit')
                    ->label(__('Customer Deposit'))
                    ->getStateUsing(fn (Payment $record): float => $record->unallocatedAmount())
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->color('success')
                    ->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label(__('Cancel Payment'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    // Izinnya SENDIRI, tidak menumpang receive_receivables.
                    // Keputusan Project Owner: mencatat uang masuk dan
                    // membatalkannya dua kewenangan yang berbeda, dan biasanya
                    // orangnya juga berbeda.
                    ->visible(fn (Payment $record): bool => ! $record->isCancelled()
                        && (auth()->user()?->hasPermission('cancel_receivable_payments') ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(__('Cancel Payment'))
                    ->modalDescription(__('The allocation goes back to each invoice and the cash book gets its reversing lines. The payment itself stays on record.'))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label(__('Reason'))
                            ->placeholder(__('For example: wrong amount, wrong customer group.'))
                            ->required()
                            ->maxLength(255)
                            ->rows(2),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            $record->cancel($data['reason']);
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title(__('Payment cancelled'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Payment $record): string => route('print.payment-receipt', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
