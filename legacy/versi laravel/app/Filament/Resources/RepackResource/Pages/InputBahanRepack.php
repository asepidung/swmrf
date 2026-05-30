<?php

namespace App\Filament\Resources\RepackResource\Pages;

use App\Filament\Resources\RepackResource;
use App\Models\Repack;
use App\Models\RepackMaterial;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;

class InputBahanRepack extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = RepackResource::class;
    // Arahkan ke file view blade yang akan kita buat
    protected static string $view = 'filament.resources.repack-resource.pages.input-bahan-repack';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return ''; // Sembunyikan header bawaan
    }

    public Repack $record;
    public ?array $data = [];

    public function mount(Repack $record): void
    {
        $this->record = $record;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('barcode')
                    ->hiddenLabel()
                    ->placeholder('SCAN BARCODE BAHAN DI SINI...')
                    ->autofocus()
                    ->extraInputAttributes([
                        'id' => 'scanner_input',
                        'class' => 'text-3xl font-black text-center text-primary-600 tracking-widest uppercase py-6',
                    ])
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submitBarcode()
    {
        // Mengambil data dari inputan form
        $data = $this->form->getState();
        $scannedBarcode = $data['barcode'];

        // Mencari barang di tabel beef_stocks berdasarkan barcode
        $stock = \App\Models\BeefStock::where('barcode', $scannedBarcode)->first();

        if ($stock) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($stock) {

                // Memasukkan data ke material repack
                \App\Models\RepackMaterial::create([
                    'repack_id' => $this->record->id,
                    'barcode' => $stock->barcode,
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'grade_id' => $stock->grade_id,
                    'weight' => $stock->weight,
                    'qty_pcs' => $stock->qty_pcs,
                    'ph_level' => $stock->ph_level,
                    'pack_date' => $stock->pack_date,
                    'exp_date' => $stock->exp_date,
                    'origin' => $stock->origin,
                ]);

                // Mencatat pergerakan stok keluar untuk proses repack
                \App\Models\BeefStockMovement::create([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'condition' => $stock->grade_id,
                    'barcode' => $stock->barcode,
                    'transaction_type' => 'OUT_TO_REPACK',
                    'reference_document' => $this->record->document_no,
                    'weight_out' => $stock->weight,
                    'pcs_out' => $stock->qty_pcs,
                    'created_by' => auth()->id(),
                ]);

                // Menghapus data asli dari stok karena sedang diproses
                $stock->delete();
            });

            // Mengosongkan form input setelah berhasil
            $this->form->fill();

            \Filament\Notifications\Notification::make()
                ->title('Bahan berhasil ditambahkan')
                ->success()
                ->send();

            // Memperbarui tabel histori dan mengembalikan fokus kursor ke scanner
            $this->dispatch('refreshTable');
            $this->dispatch('focus-scanner');
        } else {
            // Menampilkan notifikasi jika barcode tidak ditemukan
            \Filament\Notifications\Notification::make()
                ->title('Barcode tidak ditemukan di stok!')
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(RepackMaterial::query()->where('repack_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight (Kg)')
                    ->numeric(2)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label('Pcs')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Scan')
                    ->time('H:i:s')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip('Batalkan / Hapus')
                    ->action(function ($record, $livewire) {
                        DB::transaction(function () use ($record) {

                            // Mengembalikan data stok ke tabel beef_stocks dengan atribut lengkap
                            \App\Models\BeefStock::create([
                                'barcode' => $record->barcode,
                                'product_id' => $record->product_id,
                                'warehouse_id' => $record->warehouse_id,
                                'grade_id' => $record->grade_id,
                                'weight' => $record->weight,
                                'qty_pcs' => $record->qty_pcs,
                                'ph_level' => $record->ph_level,
                                'pack_date' => $record->pack_date,
                                'exp_date' => $record->exp_date,
                                'origin' => $record->origin,
                                'status' => 'IN_STOCK',
                            ]);

                            // Mencatat riwayat pergerakan pengembalian stok bahan
                            \App\Models\BeefStockMovement::create([
                                'product_id' => $record->product_id,
                                'warehouse_id' => $record->warehouse_id,
                                'condition' => $record->grade_id,
                                'barcode' => $record->barcode,
                                'transaction_type' => 'VOID_OUT_REPACK',
                                'reference_document' => $record->repack->document_no ?? null,
                                'weight_in' => $record->weight,
                                'pcs_in' => $record->qty_pcs,
                                'created_by' => Auth::id(),
                            ]);

                            // Menghapus data dari keranjang bahan repack
                            $record->delete();
                        });

                        Notification::make()
                            ->title('Bahan dikembalikan ke stok!')
                            ->warning()
                            ->send();

                        // Memanggil event untuk fokus otomatis pada input scanner
                        $livewire->dispatch('focus-scanner');
                    })
            ]);
    }
}
