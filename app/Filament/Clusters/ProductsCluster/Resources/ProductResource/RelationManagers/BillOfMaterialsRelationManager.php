<?php

namespace App\Filament\Clusters\ProductsCluster\Resources\ProductResource\RelationManagers;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bill of Material sebuah produk, di halaman produknya sendiri.
 *
 * Ditaruh menempel pada produk, bukan sebagai layar tersendiri, karena
 * pertanyaan yang dijawabnya selalu berbentuk "produk INI pakai bahan apa" --
 * tidak pernah "bahan ini dipakai produk mana". Layar tersendiri akan menuntut
 * pemakainya memilih produk dua kali: sekali untuk membukanya, sekali lagi di
 * dalam formnya.
 *
 * Barisnya bebas, bukan slot yang sudah disiapkan. Legacy menyiapkan tujuh
 * slot tetap (karung, karton top, karton bottom, plastik, linier, drylog,
 * tray) dan membedakan Top dari Bottom lewat NAMA bahannya -- sehingga
 * mengganti nama sebuah bahan diam-diam merusak BOM-nya, dan menambah jenis
 * bahan baru berarti mengubah kode.
 */
class BillOfMaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'billOfMaterials';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Bill of Material');
    }

    /**
     * Yang tidak boleh membaca BOM tidak melihat panelnya sama sekali.
     *
     * Filament menampilkan panel relasi tanpa bertanya apa pun kalau ini tidak
     * ditulis -- dan panel yang tampil tanpa hak adalah persis jenis kebocoran
     * yang paling sulit disadari, karena tidak ada pesan galat yang
     * menyertainya.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasPermission('view_product_materials') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('material_id')
                    ->label(__('Material'))
                    ->relationship(
                        name: 'material',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, ?ProductMaterial $record): Builder => $query
                            ->where('is_active', true)
                            // Bahan yang sudah punya baris tidak ditawarkan
                            // lagi -- kecuali bahan dari baris yang sedang
                            // diubah, yang justru harus tetap terpilih.
                            ->whereNotIn('id', $this->materialYangSudahDipakai($record))
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Material $record): string => $record->code.' - '.$record->name)
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('basis')
                    ->label(__('Basis'))
                    ->options(collect(ProductMaterial::BASIS)->map(fn (string $label): string => __($label))->all())
                    ->default('box')
                    ->required()
                    ->helperText(__('One box is one barcoded package; pcs is how many pieces of meat are inside it.')),

                Forms\Components\TextInput::make('quantity')
                    ->label(__('Quantity'))
                    ->numeric()
                    // Boleh kosong, dan kosong BUKAN nol. Drylog dipakai di
                    // hampir semua produk tetapi jumlahnya berbeda-beda walau
                    // produknya sama; menuliskannya nol berarti "tidak
                    // dipakai", dan itu keterangan yang salah.
                    ->rules(['nullable', 'integer', 'min:1'])
                    ->helperText(__('Leave it empty when the amount is never the same, like Drylog.')),

                Forms\Components\Textarea::make('note')
                    ->label(__('Note'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            ->defaultSort('id')
            ->emptyStateHeading(__('No bill of material yet'))
            ->emptyStateDescription(__('List the packaging this product uses, so its cost can be worked out when it is needed.'))
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label(__('Material Code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Material Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    // Kosong ditulis apa adanya, bukan dibiarkan menjadi sel
                    // kosong yang terbaca seperti isian yang terlupa.
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? __('Not fixed')
                        : number_format($state, 0, ',', '.'))
                    ->badge(fn (ProductMaterial $record): bool => $record->jumlahnyaTidakTetap())
                    ->color(fn (ProductMaterial $record): ?string => $record->jumlahnyaTidakTetap() ? 'gray' : null),

                Tables\Columns\TextColumn::make('basis')
                    ->label(__('Basis'))
                    ->formatStateUsing(fn (ProductMaterial $record): string => __($record->labelBasis()))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('material.unit.name')
                    ->label(__('Stock Unit'))
                    ->color('gray')
                    ->tooltip(__('The unit its stock is counted in, which is not the basis it is used on.')),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Add Material'))
                    ->visible(fn (): bool => auth()->user()?->hasPermission('create_product_materials') ?? false),

                $this->salinDariProdukLain(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->hasPermission('edit_product_materials') ?? false),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->hasPermission('delete_product_materials') ?? false),
            ])
            ->bulkActions([]);
    }

    /**
     * Menyalin BOM produk lain ke produk ini.
     *
     * Owner sempat menyebut "cluster barang" -- mengelompokkan produk supaya
     * satu BOM dipakai bersama. Yang ingin dihindarinya adalah mengetik ulang
     * daftar yang sama berkali-kali, dan penyalinan menyelesaikan itu tanpa
     * menambah satu konsep baru pun.
     *
     * Kelompok yang dipakai BERSAMA justru membuat perubahan pada satu produk
     * diam-diam mengubah produk lain, dan itu tidak bisa ditarik kembali
     * begitu daftarnya berbeda sedikit saja -- padahal data produksinya
     * menunjukkan daftar itu memang sering berbeda sedikit: BACKRIB memakai
     * karton top, BACKRIB CUT tidak.
     *
     * Karena itu salinannya PUTUS dari asalnya begitu tersalin.
     */
    private function salinDariProdukLain(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('salin_bom')
            ->label(__('Copy from Another Product'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission('create_product_materials') ?? false)
            ->form([
                Forms\Components\Select::make('product_id')
                    ->label(__('Source Product'))
                    ->options(fn (): array => Product::query()
                        ->whereKeyNot($this->getOwnerRecord()->getKey())
                        ->whereHas('billOfMaterials')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->helperText(__('Only products that already have a bill of material are listed.')),
            ])
            ->action(function (array $data): void {
                $tujuan = $this->getOwnerRecord();

                // Bahan yang sudah ada barisnya di sini TIDAK ditimpa. Yang
                // menyalin sedang melengkapi daftarnya, bukan menggantinya --
                // dan jumlah yang sudah disesuaikan tangan tidak boleh hilang
                // karena satu klik.
                $sudahAda = $tujuan->billOfMaterials()->pluck('material_id')->all();

                $sumber = ProductMaterial::query()
                    ->where('product_id', $data['product_id'])
                    ->whereNotIn('material_id', $sudahAda)
                    ->get();

                if ($sumber->isEmpty()) {
                    Notification::make()
                        ->title(__('Nothing new to copy'))
                        ->body(__('Every material of that product is already listed here.'))
                        ->warning()
                        ->send();

                    return;
                }

                foreach ($sumber as $baris) {
                    $tujuan->billOfMaterials()->create([
                        'material_id' => $baris->material_id,
                        'quantity' => $baris->quantity,
                        'basis' => $baris->basis,
                        'note' => $baris->note,
                    ]);
                }

                Notification::make()
                    ->title(trans_choice(':count material copied|:count materials copied', $sumber->count(), [
                        'count' => $sumber->count(),
                    ]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Bahan yang sudah punya baris di produk ini.
     *
     * Baris yang sedang diubah dikecualikan, supaya bahannya sendiri tetap
     * bisa dipilih dan tidak terlihat menghilang dari daftar.
     *
     * @return array<int, int>
     */
    private function materialYangSudahDipakai(?ProductMaterial $record): array
    {
        return $this->getOwnerRecord()
            ->billOfMaterials()
            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
            ->pluck('material_id')
            ->all();
    }
}
