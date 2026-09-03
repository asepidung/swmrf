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
                    ->color('primary'),

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
            ])
            ->actions([
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
