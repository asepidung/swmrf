<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReceivableResource\Pages;
use App\Models\Receivable;
use App\Models\Invoice;
use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceivableResource extends Resource
{
    protected static ?string $model = \App\Models\CustomerGroup::class;

    /**
     * Hak akses WAJIB diperiksa di sini, tidak bisa mengandalkan Policy.
     *
     * Resource ini memakai model CustomerGroup, yang juga dipakai
     * PriceListResource. Laravel menemukan Policy lewat nama MODEL, jadi
     * keduanya jatuh ke CustomerGroupPolicy -- ReceivablePolicy tidak pernah
     * dipanggil sama sekali. Akibatnya siapa pun yang punya
     * view_customer_groups ikut melihat menu Receivables beserta seluruh data
     * piutang, meski tidak diberi hak Receivables sedikit pun.
     *
     * Selama modelnya masih dipakai bersama, penjagaannya harus di Resource.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_receivables') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    /** Piutang hanya dibaca dan dibayar; tidak ada pembuatan atau penghapusan. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    protected static ?string $slug = 'receivables';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';



    public static function getNavigationGroup(): ?string
    {
        return __('ACCOUNTING');
    }

    public static function getNavigationLabel(): string
    {
        return __('Receivables');
    }

    public static function getModelLabel(): string
    {
        return __('Receivable');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Receivables');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /**
     * Batas "akan jatuh tempo": tujuh hari ke depan.
     */
    protected const DUE_SOON_DAYS = 7;

    /**
     * Ketiga angka di daftar piutang, dihitung SEKALI untuk seluruh halaman.
     *
     * Sebelumnya tiap kolom punya `getStateUsing` dan `description` yang
     * berkueri sendiri-sendiri: enam kueri per baris grup, jadi dua puluh grup
     * berarti seratus dua puluh kueri hanya untuk membuka satu halaman.
     *
     * Sekarang keenamnya menjadi subkueri agregat pada kueri tabelnya, dan
     * yang lebih penting: keenamnya berangkat dari relasi yang SAMA, sehingga
     * nominal dan hitungannya tidak mungkin lagi menjawab dengan aturan yang
     * berbeda.
     */
    protected static function withReceivableTotals(Builder $query): Builder
    {
        $outstanding = fn (Builder $invoices) => $invoices
            ->where('invoices.status', '!=', Invoice::STATUS_PAID);

        $dueSoon = fn (Builder $invoices) => $outstanding($invoices)
            ->whereNotNull('invoices.due_date')
            ->whereBetween('invoices.due_date', [
                now()->toDateString(),
                now()->addDays(static::DUE_SOON_DAYS)->toDateString(),
            ]);

        $overdue = fn (Builder $invoices) => $outstanding($invoices)
            ->whereNotNull('invoices.due_date')
            ->whereDate('invoices.due_date', '<', now()->toDateString());

        // Deposit dihitung dari dua penjumlahan: seluruh uang pembayaran yang
        // masih berlaku, dikurangi yang sudah menempel ke invoice. Keduanya
        // subkueri pada kueri tabelnya, jadi menambah grup tidak menambah
        // kueri.
        $aktif = fn (Builder $q) => $q->whereNull('payments.cancelled_at');

        return $query
            ->withSum(
                ['payments as deposit_received' => $aktif],
                \Illuminate\Support\Facades\DB::raw('amount + total_deduction'),
            )
            ->withSum(['paymentAllocations as deposit_used' => $aktif], 'amount_allocated')
            ->withSum(['invoices as total_receivable' => $outstanding], 'balance')
            ->withCount(['invoices as total_receivable_count' => $outstanding])
            ->withSum(['invoices as due_soon' => $dueSoon], 'balance')
            ->withCount(['invoices as due_soon_count' => $dueSoon])
            ->withSum(['invoices as overdue' => $overdue], 'balance')
            ->withCount(['invoices as overdue_count' => $overdue]);
    }

    /**
     * Deposit sebuah grup, dari dua agregat yang sudah ikut terbawa kueri.
     *
     * Dikembalikan nol -- bukan angka negatif -- kalau alokasinya melebihi
     * uang yang diterima. Itu keadaan yang seharusnya mustahil, dan
     * menampilkannya sebagai deposit negatif hanya akan membingungkan tanpa
     * menjelaskan apa pun.
     */
    protected static function depositOf(\App\Models\CustomerGroup $record): float
    {
        return round(max(
            (float) ($record->deposit_received ?? 0) - (float) ($record->deposit_used ?? 0),
            0,
        ), 2);
    }

    /** Keterangan "N Inv" di bawah nominalnya, kosong bila memang tidak ada. */
    protected static function invoiceCount(?int $count, bool $alwaysShow = false): ?string
    {
        if (! $count) {
            return $alwaysShow ? __(':count Inv', ['count' => 0]) : null;
        }

        return __(':count Inv', ['count' => $count]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::withReceivableTotals($query))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Group Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_receivable')
                    ->label(__('Total Receivable'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->description(fn ($record): ?string => static::invoiceCount(
                        $record->total_receivable_count,
                        alwaysShow: true,
                    ))
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('due_soon')
                    ->label(__('Due Soon'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->description(fn ($record): ?string => static::invoiceCount($record->due_soon_count))
                    ->alignEnd()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('overdue')
                    ->label(__('Overdue'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->description(fn ($record): ?string => static::invoiceCount($record->overdue_count))
                    ->alignEnd()
                    ->color('danger'),

                // Deposit adalah uang PERUSAHAAN YANG DIPEGANG atas nama
                // pelanggan, bukan piutang. Sampai 4 September 2026 ia hanya
                // terlihat di halaman Terima Pembayaran -- artinya tidak ada
                // satu pun layar yang bisa menjawab "pelanggan mana saja yang
                // masih punya deposit".
                Tables\Columns\TextColumn::make('deposit')
                    ->label(__('Customer Deposit'))
                    ->getStateUsing(fn ($record): float => static::depositOf($record))
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->color('success')
                    ->placeholder('-'),
            ])
            ->filters([])
            ->actions([])
            ->headerActions([])
            ->bulkActions([])
            ->recordUrl(
                fn (\App\Models\CustomerGroup $record): string => Pages\ViewReceivable::getUrl([$record->id]),
            )
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\ReceivableResource\RelationManagers\ReceivablesRelationManager::class,
            \App\Filament\Admin\Resources\ReceivableResource\RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceivables::route('/'),
            'view' => Pages\ViewReceivable::route('/{record}'),
            'payment' => Pages\ReceivePayment::route('/{record}/payment'),
        ];
    }

    /**
     * Grup yang perlu terlihat di daftar piutang.
     *
     * Bukan hanya yang masih berutang. Grup yang seluruh invoicenya sudah
     * lunas TETAPI masih menyimpan deposit juga harus muncul -- kalau tidak,
     * uang perusahaan yang dipegang atas nama pelanggan itu lenyap dari layar
     * dan tidak ada satu pun halaman yang bisa menemukannya kembali.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query
                    ->whereHas('receivables.invoice', function (Builder $invoices) {
                        $invoices->where('status', '!=', Invoice::STATUS_PAID);
                    })
                    ->orWhereRaw('EXISTS (
                        SELECT 1 FROM payments
                        WHERE payments.customer_group_id = customer_groups.id
                          AND payments.deleted_at IS NULL
                          AND payments.cancelled_at IS NULL
                          AND (payments.amount + payments.total_deduction) > (
                              SELECT COALESCE(SUM(payment_allocations.amount_allocated), 0)
                              FROM payment_allocations
                              WHERE payment_allocations.payment_id = payments.id
                          )
                    )');
            });
    }
}
