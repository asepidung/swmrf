<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
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
                    ->placeholder(__('SCAN THE MATERIAL BARCODE HERE...'))
                    ->autofocus()
                    ->extraInputAttributes([
                        'id' => 'scanner_input',
                        'class' => 'text-xl font-bold text-center text-primary-600 tracking-wide uppercase py-3',
                    ])
                    ->required(),
            ])
            ->disabled(fn () => $this->record->kunci == 1)
            ->statePath('data');
    }

    public function submitBarcode()
    {
        if ($this->record->kunci == 1) {
            Notification::make()
                ->title(__('Failed'))
                ->body(__('This repack is locked.'))
                ->danger()
                ->send();
            return;
        }

        // Mengambil data dari inputan form
        $data = $this->form->getState();
        $scannedBarcode = $data['barcode'];

        try {
            DB::transaction(function () use ($scannedBarcode) {
                // Mencari barang di tabel beef_stocks berdasarkan barcode dengan lockForUpdate
                $stock = BeefStock::where('barcode', $scannedBarcode)->lockForUpdate()->first();

                if (!$stock) {
                    throw new \Exception(__('This barcode is not in stock.'));
                }

                // Memasukkan data ke material repack
                RepackMaterial::create([
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
                    'status' => $stock->status,
                ]);

                // Mencatat pergerakan stok keluar untuk proses repack
                BeefStockMovement::create([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'condition' => $stock->grade_id,
                    'barcode' => $stock->barcode,
                    'transaction_type' => 'OUT_TO_REPACK',
                    'reference_document' => $this->record->doc_no,
                    'weight_out' => $stock->weight,
                    'pcs_out' => $stock->qty_pcs,
                    'created_by' => Auth::id(),
                ]);

                // Menghapus data asli dari stok karena sedang diproses
                $stock->delete();
            });

            // Mengosongkan form input setelah berhasil
            $this->form->fill();

            Notification::make()
                ->title(__('Material added'))
                ->success()
                ->send();

            // Memperbarui tabel histori dan mengembalikan fokus kursor ke scanner
            $this->dispatch('refreshTable');
            $this->dispatch('focus-scanner');
        } catch (\Exception $e) {
            report($e);
            // Menampilkan notifikasi jika barcode tidak ditemukan atau gagal
            Notification::make()
                ->title($e->getMessage())
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
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight (Kg)'))
                    ->numeric(2)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Waktu Scan'))
                    ->time('H:i:s')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip(__('Cancel / Delete'))
                    ->hidden(fn () => $this->record->kunci == 1)
                    ->action(function ($record, $livewire) {
                        DB::transaction(function () use ($record) {
                            // Mengembalikan data stok ke tabel beef_stocks dengan atribut lengkap
                            BeefStock::create([
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
                                'status' => $record->status,
                            ]);

                            // Mencatat riwayat pergerakan pengembalian stok bahan
                            BeefStockMovement::create([
                                'product_id' => $record->product_id,
                                'warehouse_id' => $record->warehouse_id,
                                'condition' => $record->grade_id,
                                'barcode' => $record->barcode,
                                'transaction_type' => 'VOID_OUT_REPACK',
                                'reference_document' => $record->repack->doc_no ?? null,
                                'weight_in' => $record->weight,
                                'pcs_in' => $record->qty_pcs,
                                'created_by' => Auth::id(),
                            ]);

                            // Menghapus data dari keranjang bahan repack
                            $record->delete();
                        });

                        Notification::make()
                            ->title(__('The material has been returned to stock'))
                            ->warning()
                            ->send();

                        // Memanggil event untuk fokus otomatis pada input scanner
                        $livewire->dispatch('focus-scanner');
                    })
            ]);
    }
}
