<?php

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invoice yang belum ditukar faktur, dikelompokkan per customer (padanan halaman inv/tf.php di legacy; permintaan user
 * lewat Owner, 2 November 2026). Satu baris satu customer: jumlah invoice dan total tagihannya. Baris dibuka ke daftar
 * invoice yang sudah tersaring untuk customer itu, tempat tukar faktur dilakukan.
 */
class UnexchangedInvoices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'filament.admin.resources.invoice-resource.pages.unexchanged-invoices';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Invoices Not Yet Exchanged');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(InvoiceResource::getUrl('index')),
        ];
    }

    private function belumTukar(Builder $query): Builder
    {
        return $query->where('status', Invoice::STATUS_EXCHANGE_PENDING);
    }

    public function table(Table $table): Table
    {
        $total = (float) $this->belumTukar(Invoice::query())->sum('balance');

        return $table
            ->query(
                Customer::query()
                    ->whereHas('invoices', fn (Builder $q) => $this->belumTukar($q))
                    ->withCount(['invoices as jumlah_invoice' => fn (Builder $q) => $this->belumTukar($q)])
                    ->withSum(['invoices as total_tagihan' => fn (Builder $q) => $this->belumTukar($q)], 'balance')
            )
            ->description(__('Total').': Rp '.number_format($total, 0, ',', '.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->weight('bold')
                    ->color('warning'),
                Tables\Columns\TextColumn::make('jumlah_invoice')
                    ->label(__('Count Inv'))
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_tagihan')
                    ->label(__('Amount'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
            ])
            ->defaultSort('total_tagihan', 'desc')
            ->paginated(false)
            ->recordUrl(fn (Customer $record): string => InvoiceResource::getUrl('index', [
                'tableFilters' => [
                    'customer_id' => ['value' => $record->id],
                    'belum_tukar_faktur' => ['isActive' => true],
                    // Tanpa batas bulan: yang belum ditukar bisa berasal dari bulan-bulan lalu.
                    'invoice_date' => ['date_from' => '2020-01-01', 'date_until' => now()->toDateString()],
                ],
            ]));
    }
}
