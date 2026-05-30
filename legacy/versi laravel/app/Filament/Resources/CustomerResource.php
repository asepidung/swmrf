<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // --- PENGATURAN SIDEBAR (BIAR RAPI DAN GANTENG) ---
    protected static ?string $navigationIcon = 'heroicon-o-users'; // Pakai ikon orang
    protected static ?string $navigationGroup = 'CUSTOMERS';
    // Label di menu
    protected static ?int $navigationSort = 7; // Kedua

    protected static ?string $navigationLabel = 'Customer';
    protected static ?string $modelLabel = 'Customer';
    protected static ?string $pluralModelLabel = 'Customers';
    // --------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->description('Data profil dan relasi customer.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']) // Visual huruf besar
                            ->dehydrateStateUsing(fn(string $state): string => strtoupper($state)) // Paksa simpan huruf besar ke DB
                            ->placeholder('Contoh: RESTO SUKAR MAJU'),

                        // Bagian Grup Customer
                        Forms\Components\Select::make('customer_group_id')
                            ->label('Grup Customer')
                            ->relationship('customerGroup', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Kosongkan Jika Customer Tidak Memiliki Grup.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('New Group Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                            ])
                            ->createOptionModalHeading('Buat Grup Customer Baru')
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalWidth('md')
                                    ->color('warning')
                                    ->icon('heroicon-m-plus-circle');
                            }),

                        // Bagian Segmen
                        Forms\Components\Select::make('segment_id')
                            ->label('Segmen')
                            ->relationship('segment', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Segmen Baru')
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn($state) => strtoupper($state)),
                            ])
                            ->createOptionModalHeading('Buat Segmen Baru')
                            // INI KODENYA BIAR TOMBOL JADI KUNING
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalWidth('md')
                                    ->color('warning') // Warna Kuning
                                    ->icon('heroicon-m-plus-circle');
                            }),

                        Forms\Components\TextInput::make('top_days')
                            ->label('TOP (Term of Payment) / Hari')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']) // Visual huruf besar
                            ->dehydrateStateUsing(fn(?string $state): ?string => $state ? strtoupper($state) : null) // Paksa simpan huruf besar ke DB
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel(),
                    ])->columns(2),

                // SECTION KHUSUS DOKUMEN 
                Forms\Components\Section::make('Kebutuhan Dokumen')
                    ->description('Centang dokumen yang wajib disertakan saat pengiriman.')
                    ->schema([
                        Forms\Components\Checkbox::make('req_po')->label('PO (Purchase Order)'),
                        Forms\Components\Checkbox::make('req_invoice')->label('Invoice'),
                        Forms\Components\Checkbox::make('req_halal')->label('Sertifikat Halal'),
                        Forms\Components\Checkbox::make('req_uji_lab')->label('Uji Lab'),
                        Forms\Components\Checkbox::make('req_nkv')->label('NKV'),
                        Forms\Components\Checkbox::make('req_sv')->label('SV'),
                        Forms\Components\Checkbox::make('req_phd')->label('PHD'),
                        Forms\Components\Checkbox::make('req_joss')->label('JOSS'),
                    ])->columns(4), // Dibuat 4 kolom biar rapi berjejer
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customerGroup.name')
                    ->label('Grup')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('segment.name')
                    ->label('Segmen')
                    ->badge(),
                Tables\Columns\TextColumn::make('top_days')
                    ->label('TOP')
                    ->suffix(' Hari'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_group_id')
                    ->label('Filter Grup')
                    ->relationship('customerGroup', 'name'),
            ])

            // --- KUNCI 1: Matikan paksaan link URL ke halaman Edit ---
            ->recordUrl(null)

            // --- KUNCI 2: Perintahkan baris untuk membuka pop-up view ---
            ->recordAction('view')

            ->actions([
                // --- KUNCI 3: Sembunyikan icon mata secara paksa pakai CSS ---
                Tables\Actions\ViewAction::make()
                    ->extraAttributes(['style' => 'display: none !important;']),

                // Yang tampil murni cuma TOMBOL EDIT
                Tables\Actions\EditAction::make(),

                // (Tombol Delete dihapus demi keamanan data)
            ])
            ->bulkActions([
                // (Fitur Delete massal dikosongkan demi keamanan data)
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->defaultSort('name', 'asc');
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
