<?php

namespace App\Filament\Clusters\CustomersCluster\Resources;

use App\Filament\Clusters\CustomersCluster;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\Pages;
use App\Filament\Clusters\CustomersCluster\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Support\MasterDataDeletion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                // per isian, dan tiap baris genap dua belas -- MULAI breakpoint
                // `lg`. Keputusan Owner, 7 September 2026: halaman Create belum
                // nyaman di HP. Pecahan 2/3/4 dari 12 kolom hanya masuk akal di
                // layar lebar; di HP dan TABLET setiap isian sengaja dibuat SATU
                // kolom penuh (`columnSpan(['default' => 1, 'lg' => ...])`)
                // supaya tidak ada input yang lebih sempit daripada jarinya
                // sendiri.
                //
                // DUA JEBAKAN, bukan satu -- ditemukan lewat dua ronde koreksi:
                //
                // 1. `Section::columns(12)` (angka polos) diterjemahkan
                //    Filament sebagai grid 1 kolom di breakpoint `default` dan
                //    12 kolom mulai `lg` (`Concerns\HasColumns::columns()` di
                //    vendor cuma mengisi kunci `lg` untuk angka polos) --
                //    BUKAN 12 kolom di semua breakpoint. `columnSpan(['default'
                //    => 12, ...])` (ronde pertama) minta setiap isian
                //    membentang 12 kolom padahal wadahnya di layar sempit cuma
                //    1 -- grid-nya terpaksa membuat 11 kolom TERSIRAT, dan
                //    lebar 1fr-nya nyaris nol. Nilai `default` yang benar
                //    mengikuti jumlah kolom WADAHNYA di breakpoint itu (1).
                // 2. `columnSpan()` (angka polos ATAU array) mengisi kunci
                //    breakpoint yang BEDA dari `columns()`: bawaan
                //    `Concerns\CanSpanColumns::columnSpan()` mengisi kunci
                //    `default`, bukan `lg`. Ronde kedua (perbaikan sebelum ini)
                //    mengira memberi kunci `md` sudah cukup -- tapi wadahnya
                //    baru berganti kolom di `lg` (1024px), bukan `md` (768px).
                //    Antara md dan lg isian tetap membentang seolah wadahnya
                //    sudah 12 kolom padahal masih 1 -- bug yang sama, cuma
                //    berpindah ke lebar TABLET. Kunci breakpoint anak WAJIB
                //    sama dengan kunci yang benar-benar dipakai wadahnya.
                //
                // Jebakan #2 menular ke MaterialRequisitionResource,
                // ProductRequisitionResource, PurchaseProductResource,
                // PurchaseMaterialResource, dan CarcassResource -- lihat #354
                // untuk sapuan penuhnya dan test yang sekarang menjaganya.
                //
                //   lg ke atas:
                //   nama 4 | grup 4   | segmen 4
                //   TOP 2  | diskon 2 | I-Ex 2 | PIC 3 | telepon 3
                //   alamat 12
                //
                //   di bawah lg (HP maupun tablet): semuanya satu kolom
                //   penuh, urut ke bawah.
                //
                // Toggle Aktif sengaja ditaruh PALING BAWAH, bukan disisipkan
                // di baris pertama. Ia hanya muncul di halaman Edit, dan isian
                // yang menghilang di halaman Create akan menyedot isian
                // berikutnya naik mengisi slotnya -- itulah yang dulu membuat
                // TOP terlempar ke baris identitas, terpisah dari Diskon dan
                // Invoice Exchange yang sejenis dengannya. Di bawah sendiri,
                // hilangnya tidak menggeser apa pun.
                Forms\Components\Section::make(__('Basic Information'))
                    ->description(__('The group decides which price list applies. Pick an existing one or create a new one from the field below.'))
                    ->compact()
                    ->columns(12)
                    ->schema([
                        Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                            ->label(fn() => __('Customer Name'))
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->columnSpan(['default' => 1, 'lg' => 4]),

                        // Tanpa helperText: keterangannya sudah ada di
                        // deskripsi kartu, satu baris untuk seluruh form,
                        // sehingga tidak menambah tinggi barisnya sendiri.
                        // Keputusan Ayah, 15 September 2026: customer TANPA
                        // grup lenyap total dari modul Piutang (piutangnya
                        // tidak pernah muncul di daftar, tidak pernah bisa
                        // dibayar lewat alur resminya) -- lihat migrasi
                        // backfill `2026_09_15_130000_...`.
                        Forms\Components\Select::make('customer_group_id')
                            ->relationship('group', 'name')
                            ->label(fn() => __('Customer Group'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $group = \App\Models\CustomerGroup::find($state);
                                    if ($group) {
                                        $set('top', $group->top);
                                    }
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->unique(ignoreRecord: true)
                                    ->label(fn() => __('Name'))
                                    ->required()
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                                Forms\Components\TextInput::make('top')
                                    ->label(fn() => __('TOP'))
                                    ->suffix(__('days'))
                                    ->required()
                                    ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                                    ->rules(['integer', 'min:0']),
                                Forms\Components\TextInput::make('head_office_pic')
                                    ->label(fn() => __('Head Office PIC'))
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                                Forms\Components\Textarea::make('head_office_address')
                                    ->label(fn() => __('Head Office Address'))
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 4]),

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
                            ->columnSpan(['default' => 1, 'lg' => 4]),

                        // Tanpa ->numeric(), yang akan membuat input menjadi
                        // type=number lengkap dengan tombol panah. TOP
                        // menentukan tanggal jatuh tempo piutang, jadi
                        // tergeser satu tanpa disadari bukan hal sepele.
                        //
                        // TIDAK diberi maxLength. Keputusan Owner, 7 September
                        // 2026: TOP sangat variatif lamanya -- `maxLength(3)`
                        // (dibatasi 999) di sini murni batas UI, tidak pernah
                        // ada padanannya di kolom DB (`integer` polos, lihat
                        // `create_customers_table`) atau di Supplier
                        // (`top_days` tidak dibatasi sama sekali). Yang tetap
                        // menjaga isian tidak masuk akal adalah
                        // `rules(['integer', 'min:0'])`.
                        Forms\Components\TextInput::make('top')
                            ->label(fn() => __('TOP'))
                            ->suffix(__('days'))
                            ->required()
                            ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                            ->rules(['integer', 'min:0'])
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        // Mengisi NILAI AWAL kolom diskon di Sales Order,
                        // sejajar dengan cara price list mengisi harga. Yang
                        // tersimpan di SO tetap yang menentukan, jadi mengubah
                        // angka di sini tidak menyentuh SO yang sudah ada --
                        // termasuk yang belum ditagih.
                        //
                        // Letaknya di pelanggan, bukan di grup: grup LION
                        // berisi 29 pelanggan dan hanya tiga Distribution
                        // Center-nya yang berhak atas diskon ini.
                        // Persen BULAT, disamakan dengan kolom diskon di
                        // Sales Order yang menerimanya. Dulu kolom ini desimal
                        // sementara yang di SO bilangan bulat, dan penyimpanan
                        // SO membuang titik desimalnya alih-alih membulatkan:
                        // 2,5% berubah menjadi 25%. Kalau suatu saat diskon
                        // berkoma dibutuhkan, KEDUA kolom harus dilebarkan
                        // bersamaan.
                        Forms\Components\TextInput::make('default_discount')
                            ->label(fn() => __('Discount'))
                            ->suffix('%')
                            ->default(0)
                            ->maxLength(3)
                            // Keputusan Owner, 7 September 2026: default 0
                            // terpilih otomatis saat difokus, supaya bisa
                            // langsung diketik tanpa menghapus nolnya dulu.
                            // Pola sama dipakai Invoice/SalesOrder untuk
                            // field angka yang sudah terisi nilai awal.
                            ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right', 'onfocus' => 'this.select()'])
                            ->rules(['integer', 'min:0', 'max:100'])
                            ->validationMessages([
                                'integer' => __('Discount must be a whole percent.'),
                                'min' => __('Discount cannot be negative.'),
                                'max' => __('Discount cannot be more than 100%.'),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        Forms\Components\Select::make('invoice_exchange')
                            ->label(fn() => __('Invoice Exchange'))
                            ->options([
                                '1' => __('Yes'),
                                '0' => __('No'),
                            ])
                            ->required()
                            ->native(false)
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        Forms\Components\TextInput::make('pic')
                            ->label(fn() => __('PIC / Person In Charge'))
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->columnSpan(['default' => 1, 'lg' => 3]),

                        Forms\Components\TextInput::make('phone')
                            ->label(fn() => __('Phone Number'))
                            ->tel()
                            ->maxLength(255)
                            ->columnSpan(['default' => 1, 'lg' => 3]),

                        Forms\Components\Textarea::make('address')
                            ->label(fn() => __('Full Address'))
                            ->required()
                            ->rows(2)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(fn() => __('Active'))
                            ->default(true)
                            ->visibleOn('edit')
                            ->columnSpan(['default' => 1, 'lg' => 2]),
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
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
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
                    ->formatStateUsing(fn ($state) => ((int) $state) > 0 ? ((int) $state).'%' : '-')
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
            ->defaultSort('name')
            ->actions([
                //
            ])
            ->recordUrl(
                fn (Customer $record): string => Pages\EditCustomer::getUrl([$record->id])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Jalur bulk sebelumnya tidak dijaga sama sekali, beda
                    // dari Delete tunggal di EditCustomer yang sudah benar
                    // menyembunyikan tombol saat ada salesOrders(). Baris
                    // dengan salesOrders dilewati (bukan diam-diam ikut
                    // diproses lalu menampilkan galat SQL), sisanya dibungkus
                    // MasterDataDeletion::attempt() pola WarehouseResource.
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $dilewati = 0;

                            foreach ($records as $record) {
                                if ($record->salesOrders()->exists()) {
                                    $dilewati++;

                                    continue;
                                }

                                MasterDataDeletion::attempt(
                                    fn () => $record->delete(),
                                    __('Customer').' '.$record->name,
                                );
                            }

                            if ($dilewati > 0) {
                                Notification::make()
                                    ->title(__('Some customers were not deleted'))
                                    ->body(__(':count customer(s) already have sales orders and were skipped.', ['count' => $dilewati]))
                                    ->danger()
                                    ->send();
                            }
                        }),
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
