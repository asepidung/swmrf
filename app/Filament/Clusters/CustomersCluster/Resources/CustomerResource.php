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
                // Satu pelanggan harus muat dalam satu layar, bukan satu
                // halaman penuh.
                //
                // Dua hal yang dulu membuatnya boros. Pertama, formnya memakai
                // dua kolom sama besar, sehingga TOP yang paling banyak tiga
                // angka dan Invoice Exchange yang cuma Ya/Tidak sama lebarnya
                // dengan alamat lengkap. Kedua, isiannya dipecah ke beberapa
                // kartu, dan tiap kartu menambah baris judul serta jarak
                // tepinya sendiri.
                //
                // Sekarang satu kartu dengan kisi 12 kolom: lebar ditentukan
                // per isian, dan seluruhnya selesai dalam tiga baris.
                //
                //   nama 4 | grup 3 | segmen 3 | aktif 2
                //   TOP 2  | diskon 2 | I-Ex 2 | PIC 3 | telepon 3
                //   alamat 12
                Forms\Components\Section::make(__('Basic Information'))
                    ->description(__('The group decides which price list applies. Leave it empty to create a group named after this customer.'))
                    ->compact()
                    ->columns(12)
                    ->schema([
                        Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                            ->label(fn() => __('Customer Name'))
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->columnSpan(4),

                        // Tanpa helperText: keterangannya sudah ada di
                        // deskripsi kartu, satu baris untuk seluruh form,
                        // sehingga tidak menambah tinggi barisnya sendiri.
                        Forms\Components\Select::make('customer_group_id')
                            ->relationship('group', 'name')
                            ->label(fn() => __('Customer Group'))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
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
                            ->columnSpan(3),

                        Forms\Components\Select::make('customer_segment_id')
                            ->relationship('segment', 'name')
                            ->label(fn() => __('Segment'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                                    ->label(fn() => __('Name'))
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                            ])
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->label(fn() => __('Active'))
                            ->default(true)
                            ->visibleOn('edit')
                            ->columnSpan(2),

                        // Tanpa ->numeric(), yang akan membuat input menjadi
                        // type=number lengkap dengan tombol panah. TOP
                        // menentukan tanggal jatuh tempo piutang, jadi
                        // tergeser satu tanpa disadari bukan hal sepele.
                        Forms\Components\TextInput::make('top')
                            ->label(fn() => __('TOP'))
                            ->suffix(__('days'))
                            ->required()
                            ->maxLength(3)
                            ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                            ->rules(['integer', 'min:0'])
                            ->columnSpan(2),

                        // Mengisi NILAI AWAL kolom diskon di Sales Order,
                        // sejajar dengan cara price list mengisi harga. Yang
                        // tersimpan di SO tetap yang menentukan, jadi mengubah
                        // angka di sini tidak menyentuh SO yang sudah ada --
                        // termasuk yang belum ditagih.
                        //
                        // Letaknya di pelanggan, bukan di grup: grup LION
                        // berisi 29 pelanggan dan hanya tiga Distribution
                        // Center-nya yang berhak atas diskon ini.
                        Forms\Components\TextInput::make('default_discount')
                            ->label(fn() => __('Discount'))
                            ->suffix('%')
                            ->default(0)
                            ->maxLength(6)
                            ->extraInputAttributes(['inputmode' => 'decimal', 'class' => 'text-right'])
                            ->rules(['numeric', 'min:0', 'max:100'])
                            ->validationMessages([
                                'numeric' => __('Discount must be a number.'),
                                'min' => __('Discount cannot be negative.'),
                                'max' => __('Discount cannot be more than 100%.'),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('invoice_exchange')
                            ->label(fn() => __('Invoice Exchange'))
                            ->options([
                                '1' => __('Yes'),
                                '0' => __('No'),
                            ])
                            ->required()
                            ->native(false)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('pic')
                            ->label(fn() => __('PIC / Person In Charge'))
                            ->maxLength(255)
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('phone')
                            ->label(fn() => __('Phone Number'))
                            ->tel()
                            ->maxLength(255)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('address')
                            ->label(fn() => __('Full Address'))
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

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
                // Ditampilkan supaya siapa saja yang berdiskon bisa dilihat
                // dari daftar, tanpa harus membuka satu per satu. Dulu hal
                // ini sama sekali tidak terlihat: aturannya tersembunyi di
                // dalam kode dan hanya muncul saat invoice dibuat.
                Tables\Columns\TextColumn::make('default_discount')
                    ->label(fn() => __('Discount'))
                    ->formatStateUsing(fn ($state) => ((float) $state) > 0
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',').'%'
                        : '-')
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
                Tables\Actions\Action::make('export_excel')
                    ->label(fn() => __('Excel'))
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($livewire) {
                        $records = $livewire->getFilteredTableQuery()->get();
                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Customer Name', 'Group', 'Segment', 'Address', 'TOP', 'PIC', 'Phone', 'Invoice Exchange', 'Active']));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->name ?? '',
                                    $record->group?->name ?? '',
                                    $record->segment?->name ?? '',
                                    $record->address ?? '',
                                    $record->top ?? 0,
                                    $record->pic ?? '',
                                    $record->phone ?? '',
                                    $record->invoice_exchange ? 'Yes' : 'No',
                                    $record->is_active ? 'Yes' : 'No',
                                ]));
                            }
                            $writer->close();
                        }, 'Customers.xlsx');
                    }),
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
