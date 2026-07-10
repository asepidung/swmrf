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
use Filament\Pages\SubNavigationPosition;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = CustomersCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

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
                            ->label(fn() => __('Customer Name'))
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                        Forms\Components\Select::make('customer_group_id')
                            ->relationship('group', 'name')
                            ->label(fn() => __('Customer Group'))
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(fn() => __('Name'))
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                                Forms\Components\TextInput::make('head_office_pic')
                                    ->label(fn() => __('Head Office PIC'))
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('head_office_address')
                                    ->label(fn() => __('Head Office Address'))
                                    ->columnSpanFull(),
                            ])
                            ->helperText(__('Leave empty if Customer does not have a Group.')),
                        Forms\Components\Select::make('customer_segment_id')
                            ->relationship('segment', 'name')
                            ->label(fn() => __('Segment'))
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(fn() => __('Name'))
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                            ]),
                        Forms\Components\TextInput::make('top')
                            ->label(fn() => __('TOP (Term of Payment) / Days'))
                            ->required()
                            ->numeric(),
                        Forms\Components\Textarea::make('address')
                            ->label(fn() => __('Full Address'))
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->label(fn() => __('Phone Number'))
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pic')
                            ->label(fn() => __('PIC / Person In Charge'))
                            ->maxLength(255),
                        Forms\Components\Select::make('invoice_exchange')
                            ->label(fn() => __('Invoice Exchange'))
                            ->options([
                                '1' => __('Yes'),
                                '0' => __('No'),
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(fn() => __('Active'))
                            ->default(true),
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
                    ->label(fn() => __('Customer Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label(fn() => __('Customer Group'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment.name')
                    ->label(fn() => __('Segment'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('top')
                    ->label(fn() => __('TOP (Days)'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_exchange')
                    ->label(fn() => __('I-Ex'))
                    ->formatStateUsing(fn ($state) => $state ? __('YES') : __('NO'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(fn() => __('Active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(fn() => __('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(fn() => __('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_group_id')
                    ->relationship('group', 'name')
                    ->label(fn() => __('Customer Group')),
                Tables\Filters\SelectFilter::make('customer_segment_id')
                    ->relationship('segment', 'name')
                    ->label(fn() => __('Segment')),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make('excel')
                    ->label('Excel')
                    ->color('success')
                    ->exporter(\App\Filament\Exports\CustomerExporter::class),
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
