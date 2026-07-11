<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseCattleResource\Pages;
use App\Filament\Admin\Resources\PurchaseCattleResource\RelationManagers;
use App\Models\PurchaseCattle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Carbon\Carbon;
use Filament\Support\RawJs;
class PurchaseCattleResource extends Resource
{
    protected static ?string $model = PurchaseCattle::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getNavigationGroup(): ?string
    {
        return __('PURCHASE ORDER');
    }

    public static function getModelLabel(): string
    {
        return __('PO Cattle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('PO Cattles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Header Info'))->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->required()
                        ->autofocus()
                        ->label(__('Supplier')),
                    Forms\Components\DatePicker::make('shipping_date')
                        ->required()
                        ->label(__('Shipping Date')),
                    Forms\Components\Textarea::make('summary_note')
                        ->label(__('Summary Note'))
                        ->columnSpanFull(),
                ])->columns(2),
                
                Forms\Components\Section::make(__('Cattle Details'))->schema([
                    // Clean Repeater Header UI
                    Forms\Components\Grid::make(4)
                        ->schema([
                            Forms\Components\Placeholder::make('col_category')->label(__('Category')),
                            Forms\Components\Placeholder::make('col_qty')->label(__('Qty / Head')),
                            Forms\Components\Placeholder::make('col_price')->label(__('Price / Kg')),
                            Forms\Components\Placeholder::make('col_item_notes')->label(__('Item Note')),
                        ])
                        ->extraAttributes(['class' => 'hidden md:grid']),

                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->reorderableWithDragAndDrop(false)
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Select::make('cattle_class_id')
                                ->relationship('cattleClass', 'name')
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(table: 'cattle_classes', column: 'name')
                                        ->label(__('Name')),
                                ])
                                ->placeholder(__('Category'))
                                ->label('')
                                ->hiddenLabel(),
                            Forms\Components\TextInput::make('qty')
                                ->required()
                                ->stripCharacters('.')
                                ->numeric()
                                ->rules(['integer', 'min:1'])
                                ->placeholder(__('Qty / Head'))
                                ->label('')
                                ->hiddenLabel(),
                            Forms\Components\TextInput::make('price')
                                ->required()
                                ->default(0)
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters('.')
                                ->numeric()
                                ->rules(['integer', 'min:0'])
                                ->extraInputAttributes(['x-on:focus' => '$el.select()'])
                                ->placeholder(__('Price / Kg'))
                                ->label('')
                                ->hiddenLabel(),
                            Forms\Components\TextInput::make('item_notes')
                                ->placeholder(__('ITEM NOTE'))
                                ->label('')
                                ->hiddenLabel(),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->hiddenLabel()
                        ->addActionLabel(__('Add Cattle'))
                ])
            ])
            ->disabled(fn (?PurchaseCattle $record) => $record && $record->receivings()->exists());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('PO Date'))
                    ->date('d-M-Y')
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('shipping_date')
                    ->label(__('Shipping Date'))
                    ->date('d-M-Y')
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
                Tables\Columns\TextColumn::make('items_sum_qty')
                    ->sum('items', 'qty')
                    ->label(__('Total Head'))
                    ->numeric()
                    ->badge()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : 'info')
                    ->suffix(' Ekor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary_note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->searchable()
                    ->color(fn (Model $record) => $record->trashed() ? 'danger' : null),
            ])
            ->recordUrl(
                fn (Model $record): string => $record->trashed() 
                    ? Pages\ViewPurchaseCattle::getUrl(['record' => $record]) 
                    : Pages\EditPurchaseCattle::getUrl(['record' => $record]),
            )
            ->recordClasses(fn (Model $record) => $record->trashed() ? 'border-s-2 border-danger-600 dark:border-danger-400 bg-danger-50 dark:bg-danger-900/50' : null)
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_purchase_cattles')),
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label(__('Supplier')),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
                        ->headerActions([
                \Filament\Tables\Actions\ActionGroup::make([
                    \Filament\Tables\Actions\ExportAction::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->exporter(\App\Filament\Exports\PurchaseCattleExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    \Filament\Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.parent-records-pdf', [
                                'records' => $records,
                                'title' => 'Purchase Cattles'
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'export.pdf');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->actions([
                // No action buttons on index page
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseCattle::route('/'),
            'create' => Pages\CreatePurchaseCattle::route('/create'),
            'detail-list' => Pages\PurchaseCattleDetailList::route('/detail-list'),
            'view' => Pages\ViewPurchaseCattle::route('/{record}'),
            'edit' => Pages\EditPurchaseCattle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
