<?php

namespace App\Filament\Resources\BeefReceivingResource\Pages;

use App\Filament\Resources\BeefReceivingResource;
use App\Models\BeefPurchaseOrder; // Pastikan model ini sesuai nama lu
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class DraftBeefReceiving extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = BeefReceivingResource::class;

    // View blade-nya ngikutin nama folder Filament
    protected static string $view = 'filament.resources.beef-receiving-resource.pages.draft-beef-receiving';

    protected static ?string $title = 'Draft GR Beef (Pending PO)';

    public function table(Table $table): Table
    {
        return $table
            // Tarik data PO Beef yang HANYA status OPEN atau PARTIAL
            ->query(
                BeefPurchaseOrder::query()
                    ->whereIn('status', ['OPEN', 'PARTIAL'])
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('po_date')
                    ->label('PO Date')
                    ->date('d-M-Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'OPEN' => 'gray',
                        'PARTIAL' => 'warning',
                        default => 'primary',
                    }),

                TextColumn::make('note')
                    ->label('Note')
                    ->limit(50),
            ])
            ->actions([
                // Tombol Proses GR yang mengarah ke form Create GR Beef
                Action::make('proses_gr')
                    ->label('Proses GR')
                    ->icon('heroicon-o-truck')
                    ->color('danger') // Gue kasih merah biar beda aura sama logistic
                    ->url(fn($record) => BeefReceivingResource::getUrl('create', ['po_id' => $record->id])),
            ]);
    }
}
