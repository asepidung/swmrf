<?php

namespace App\Filament\Admin\Resources\CostingResource\RelationManagers;

use App\Filament\Admin\Resources\CostingResource;
use App\Models\Costing;
use App\Models\CostingItem;
use App\Services\CostingCalculator;
use App\Services\CostingPrices;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Baris produk sebuah costing. Angkanya snapshot dari `CostingCalculator`; satu-satunya yang boleh
 * diketik adalah gross dan net (selagi Draft, #541) -- setiap ketikan langsung menghitung ulang.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Products');
    }

    private function isDraft(): bool
    {
        return $this->getOwnerRecord()->status === Costing::STATUS_DRAFT;
    }

    /**
     * Simpan harga yang diketik lalu hitung ulang costing. Net SELALU turunan, kecuali produk tanpa gross
     * (offal, tulang: hanya net yang dikenal) -- di sana net diketik langsung.
     *
     * - Gross diketik: net = gross x (net lama / gross lama); produk yang belum punya harga memakai
     *   potongan costing terakhirnya, atau tanpa potongan.
     * - Terms % diketik: net = gross x (1 - terms/100).
     * - Net diketik (hanya bila gross 0): net apa adanya.
     */
    private function typePrice(CostingItem $item, ?float $gross = null, ?float $net = null, ?float $terms = null): float
    {
        $costing = $this->getOwnerRecord();

        if ($costing->status !== Costing::STATUS_DRAFT) {
            throw new \RuntimeException(__('This costing is locked and can no longer be changed.'));
        }

        $prices = $costing->currentPrices();
        $current = $prices[$item->product_id] ?? ['gross' => 0.0, 'net' => 0.0];

        if ($gross !== null) {
            $ratio = $current['gross'] > 0 && $current['net'] > 0 ? $current['net'] / $current['gross'] : null;

            $prices[$item->product_id] = [
                'gross' => $gross,
                'net' => $ratio !== null ? CostingPrices::roundNet($gross * $ratio) : CostingPrices::netForGross($item->product_id, $gross),
            ];
        } elseif ($terms !== null) {
            $terms = min(max($terms, 0.0), 100.0);
            $prices[$item->product_id] = ['gross' => $current['gross'], 'net' => CostingPrices::roundNet($current['gross'] * (1 - $terms / 100))];
        } else {
            $prices[$item->product_id] = ['gross' => $current['gross'], 'net' => CostingPrices::roundNet((float) $net)];
        }

        $result = CostingCalculator::forBoning($costing->boning, (float) $costing->overhead_per_kg, $prices)->calculate();
        $costing->recalculate($result);

        $this->redirect(CostingResource::getUrl('edit', ['record' => $costing]));

        return $gross ?? $terms ?? $net;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product')),
                Tables\Columns\TextColumn::make('weight_kg')
                    ->label(__('Weight (Kg)'))
                    ->numeric(decimalPlaces: 2),
                // Gross dan net bisa diketik langsung selagi Draft (#541); mengetik gross saja mengisi
                // net dengan potongan terakhir produk itu. Setiap ketikan langsung menghitung ulang costing.
                Tables\Columns\TextInputColumn::make('gross_price')
                    ->label(__('Gross'))
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'decimal', 'x-on:focus' => 'if ($el.value === "0") $el.select()'])
                    ->getStateUsing(fn (CostingItem $record): string => CostingPrices::formatMoney($record->gross_price))
                    ->disabled(fn (): bool => ! $this->isDraft())
                    ->updateStateUsing(fn (CostingItem $record, $state) => $this->typePrice($record, gross: CostingPrices::parseMoney($state))),
                // Terms % bisa diketik (net mengikuti); costing berikutnya mengikuti potongan costing sebelumnya.
                Tables\Columns\TextInputColumn::make('trading_terms_percent')
                    ->label(__('Terms %'))
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'decimal', 'x-on:focus' => 'if ($el.value === "0") $el.select()'])
                    ->getStateUsing(fn (CostingItem $record): string => CostingPrices::formatMoney($record->trading_terms_percent))
                    ->disabled(fn (CostingItem $record): bool => ! $this->isDraft() || (float) $record->gross_price <= 0)
                    ->updateStateUsing(fn (CostingItem $record, $state) => $this->typePrice($record, terms: CostingPrices::parseMoney($state))),
                Tables\Columns\TextInputColumn::make('net_price')
                    ->label(__('Net'))
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                    ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'decimal', 'x-on:focus' => 'if ($el.value === "0") $el.select()'])
                    ->getStateUsing(fn (CostingItem $record): string => CostingPrices::formatMoney($record->net_price))
                    ->disabled(fn (CostingItem $record): bool => ! $this->isDraft() || (float) $record->gross_price > 0)
                    ->updateStateUsing(fn (CostingItem $record, $state) => $this->typePrice($record, net: CostingPrices::parseMoney($state))),
                Tables\Columns\TextColumn::make('sales_value')
                    ->label(__('Sales Value'))
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('hpp_per_kg')
                    ->label(__('HPP / Kg'))
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('flag')
                    ->label(__('Flag'))
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        CostingItem::FLAG_NO_REFERENCE => __('No reference group -- valued using general price'),
                        CostingItem::FLAG_NO_PRICE => __('No price yet -- type the gross and net price'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        CostingItem::FLAG_NO_REFERENCE => 'warning',
                        CostingItem::FLAG_NO_PRICE => 'danger',
                        default => 'gray',
                    }),
            ])
            ->paginated(false)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
