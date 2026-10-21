<?php

namespace App\Filament\Admin\Resources\CustomerProductSpecResource\RelationManagers;

use App\Filament\Support\MasterSelect;
use App\Models\CustomerProductSpec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Semua produk beserta spek-nya untuk satu customer. Mengubah dan menghapus mengikuti izin
 * "Kelola spek customer"; cetak (spek customer ini) ada di sini, Excel semua customer ada di Detail List.
 */
class SpecsRelationManager extends RelationManager
{
    protected static string $relationship = 'productSpecs';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Products and specs');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(['default' => 1, 'lg' => 2])
            ->schema([
                MasterSelect::product('product_id')
                    ->label(__('Product'))
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('weight_per_pack')
                    ->label(__('Weight per pack'))
                    ->placeholder('500 gr / pack')
                    ->maxLength(255),

                Forms\Components\TextInput::make('cut')
                    ->label(__('Cut'))
                    ->placeholder('slice 1,5 mm')
                    ->maxLength(255),

                Forms\Components\Select::make('condition')
                    ->label(__('Condition'))
                    ->options(CustomerProductSpec::CONDITIONS),

                Forms\Components\TextInput::make('packaging')
                    ->label(__('Packaging'))
                    ->placeholder('vacuum per pcs')
                    ->maxLength(255),

                Forms\Components\Textarea::make('note')
                    ->label(__('Spec Note'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_per_pack')->label(__('Weight per pack')),
                Tables\Columns\TextColumn::make('cut')->label(__('Cut')),
                Tables\Columns\TextColumn::make('condition')
                    ->label(__('Condition'))
                    ->formatStateUsing(fn (?string $state): ?string => CustomerProductSpec::conditionLabel($state)),
                Tables\Columns\TextColumn::make('packaging')->label(__('Packaging')),
                Tables\Columns\TextColumn::make('note')->label(__('Spec Note'))->wrap(),
            ])
            ->defaultSort('product_id')
            ->paginated(false)
            ->headerActions([
                Tables\Actions\Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (): string => route('print.customer-specs', $this->getOwnerRecord()))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(__('Edit spec'))
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('manage_customer_specs') ?? false),
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('manage_customer_specs') ?? false),
            ]);
    }
}
