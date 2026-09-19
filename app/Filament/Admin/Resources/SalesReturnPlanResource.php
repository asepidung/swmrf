<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;
use App\Models\SalesReturnPlan;
use App\Support\TrashedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnPlanResource extends Resource
{
    protected static ?string $model = SalesReturnPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getNavigationLabel(): string
    {
        return __('Sales Return Plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('SALES');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Plan Information'))
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('delivery_order_id', null);
                            })
                            ->required(),

                        Forms\Components\Select::make('delivery_order_id')
                            ->label(__('Delivery Order'))
                            ->relationship('deliveryOrder', 'delivery_order_number', function (Builder $query, callable $get) {
                                $customerId = $get('customer_id');
                                if ($customerId) {
                                    $query->where('customer_id', $customerId);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder(__('Pick a delivery order, or leave it empty for an unidentified return')),

                        Forms\Components\DatePicker::make('plan_date')
                            ->label(__('Plan Date'))
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan_number')
                    ->label(__('Plan No.'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('plan_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.delivery_order_number')
                    ->label(__('DO No.'))
                    ->placeholder(__('Unidentified'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('Items'))
                    ->counts('items')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SalesReturnPlan::STATUS_DRAFT => 'warning',
                        SalesReturnPlan::STATUS_SUBMITTED => 'info',
                        SalesReturnPlan::STATUS_RECEIVED => 'success',
                        SalesReturnPlan::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_sales_return_plans') ?? false),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        SalesReturnPlan::STATUS_DRAFT => SalesReturnPlan::STATUS_DRAFT,
                        SalesReturnPlan::STATUS_SUBMITTED => SalesReturnPlan::STATUS_SUBMITTED,
                        SalesReturnPlan::STATUS_RECEIVED => SalesReturnPlan::STATUS_RECEIVED,
                        SalesReturnPlan::STATUS_CANCELLED => SalesReturnPlan::STATUS_CANCELLED,
                    ]),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (SalesReturnPlan $record) => $record->trashed()
                ? null
                : ($record->status === SalesReturnPlan::STATUS_DRAFT
                    ? static::getUrl('edit', ['record' => $record])
                    : static::getUrl('view', ['record' => $record])));
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
            'index' => Pages\ListSalesReturnPlans::route('/'),
            'create' => Pages\CreateSalesReturnPlan::route('/create'),
            'edit' => Pages\EditSalesReturnPlan::route('/{record}/edit'),
            'view' => Pages\ViewSalesReturnPlan::route('/{record}'),
            'items' => Pages\ManageSalesReturnPlanItems::route('/{record}/items'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_sales_return_plans',
        );
    }
}
