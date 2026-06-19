<?php

namespace App\Filament\Clusters\CustomersCluster\Resources;

use App\Filament\Clusters\CustomersCluster;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = CustomersCluster::class;

    public static function getModelLabel(): string
    {
        return __('Customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customers');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Basic Information'))
                    ->description(__('Customer profile and relations data.'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Customer Name'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                        Forms\Components\Select::make('customer_group_id')
                            ->relationship('group', 'name')
                            ->label(__('Customer Group'))
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                                Forms\Components\TextInput::make('head_office_pic')
                                    ->label(__('Head Office PIC'))
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('head_office_address')
                                    ->label(__('Head Office Address'))
                                    ->columnSpanFull(),
                            ])
                            ->helperText(__('Leave empty if Customer does not have a Group.')),
                        Forms\Components\Select::make('customer_segment_id')
                            ->relationship('segment', 'name')
                            ->label(__('Segment'))
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                            ]),
                        Forms\Components\TextInput::make('top')
                            ->label(__('TOP (Term of Payment) / Days'))
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\Textarea::make('address')
                            ->label(__('Full Address'))
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pic')
                            ->label(__('PIC / Person In Charge'))
                            ->maxLength(255),
                        Forms\Components\Toggle::make('invoice_exchange')
                            ->label(__('Invoice Exchange'))
                            ->default(false),
                    ])->columns(2),

                Forms\Components\Section::make(__('Required Documents'))
                    ->description(__('Check the documents that must be included during delivery.'))
                    ->schema([
                        Forms\Components\CheckboxList::make('required_documents')
                            ->label('')
                            ->options([
                                'PO (Purchase Order)' => 'PO (Purchase Order)',
                                'Invoice' => 'Invoice',
                                'Sertifikat Halal' => 'Sertifikat Halal',
                                'Uji Lab' => 'Uji Lab',
                                'NKV' => 'NKV',
                                'SV' => 'SV',
                                'PHD' => 'PHD',
                                'JOSS' => 'JOSS',
                            ])
                            ->columns(4)
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Customer Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label(__('Customer Group'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment.name')
                    ->label(__('Segment'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('top')
                    ->label(__('TOP (Days)'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('invoice_exchange')
                    ->label(__('Invoice Exchange'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('invoice_exchange')
                    ->label(__('Invoice Exchange')),
            ])
            ->actions([
                //
            ])
            ->recordUrl(
                fn (Customer $record): string => Pages\EditCustomer::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
