<?php

namespace App\Filament\Admin\Resources\TallyResource\Pages;

use App\Filament\Admin\Resources\TallyResource;
use App\Models\Tally;
use App\Models\TallyItem;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;

class ScanTally extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = TallyResource::class;

    protected static string $view = 'filament.admin.resources.tally-resource.pages.scan-tally';

    public Tally $record;
    public ?string $barcode = '';
    public ?int $podLimit = null;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return __('Tally') . ': ' . $this->record->tally_number;
    }

    public function getSubheading(): ?string
    {
        return $this->record->salesOrder?->customer?->name;
    }

    public function mount(Tally $record): void
    {
        $this->record = $record;
        $this->podLimit = session('tally_pod_limit');
    }

    public function updatedPodLimit($value): void
    {
        if ($value === null || $value === '' || (int) $value < 0) {
            $this->podLimit = session('tally_pod_limit', 30);
            Notification::make()
                ->title(__('Max POD Age wajib diisi'))
                ->warning()
                ->send();
            return;
        }
        session(['tally_pod_limit' => (int) $value]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(fn () => TallyResource::getUrl('index')),

            Actions\Action::make('lock')
                ->label(__('Lock Tally'))
                ->icon('heroicon-m-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\TextInput::make('seal_number')
                        ->label(__('Nomor Segel (Jika Ada)'))
                        ->placeholder(__('Nomor Segel')),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $this->record->update([
                            'status' => 'locked',
                            'seal_number' => $data['seal_number'] ?? null,
                        ]);
                        $this->record->salesOrder->update([
                            'status' => 'prepared',
                        ]);

                        activity('tally')
                            ->performedOn($this->record)
                            ->log('Locked Tally: ' . $this->record->tally_number);
                    });

                    Notification::make()
                        ->title(__('Tally Locked Successfully'))
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status === 'processing' && auth()->user()->hasPermission('lock_tallies')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TallyItem::query()->where('tally_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->weight('semibold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => in_array($state, ['CHILL', 'A']) ? 'info' : 'danger'),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('POD'))
                    ->date('d-m-y')
                    ->alignCenter(),
            ])
            ->recordClasses(function (TallyItem $record) {
                $podLimit = $this->podLimit;
                if ($podLimit !== null && $podLimit !== '' && $record->pack_date) {
                    $ageInDays = (int) abs(now()->startOfDay()->diffInDays($record->pack_date->startOfDay()));
                    if ($ageInDays > (int) $podLimit) {
                        return 'bg-danger-500/10 text-danger-700 dark:text-danger-300 font-semibold';
                    }
                }
                return null;
            })
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn () => $this->record->status === 'locked')
                    ->requiresConfirmation()
                    ->tooltip(__('Delete Data'))
                    ->after(function () {
                        Notification::make()
                            ->title(__('Item removed and returned to stock'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function scan()
    {
        if ($this->record->status !== 'processing') {
            return;
        }

        $barcode = trim($this->barcode);
        if (empty($barcode)) {
            return;
        }

        // 1. Cek duplikat barcode di tally_items
        $exists = $this->record->items()->where('barcode', $barcode)->exists();
        if ($exists) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Barang Sudah Terinput'))
                ->warning()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 2. Ambil data dari stock (beef_stocks)
        $stock = BeefStock::where('barcode', $barcode)->first();
        if (!$stock) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('BARANG TIDAK TERDAFTAR di Stock'))
                ->danger()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 3. Pastikan product_id ada di sales_order_items
        $soItemExists = $this->record->salesOrder->items()->where('product_id', $stock->product_id)->exists();
        if (!$soItemExists) {
            Notification::make()
                ->title(__('Gagal Scan'))
                ->body(__('Barang Tidak ada di PO'))
                ->danger()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 4. Proses pemindahan stock
        try {
            DB::transaction(function () use ($stock) {
                // Buat TallyItem
                TallyItem::create([
                    'tally_id' => $this->record->id,
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

                // Hapus dari beef_stocks
                $stock->delete();

                // Catat di beef_stock_movements
                BeefStockMovement::create([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'condition' => $stock->grade_id,
                    'barcode' => $stock->barcode,
                    'transaction_type' => 'TALLY',
                    'reference_document' => $this->record->tally_number,
                    'weight_in' => 0,
                    'weight_out' => $stock->weight,
                    'pcs_in' => 0,
                    'pcs_out' => $stock->qty_pcs,
                    'note' => 'Scan Tally',
                ]);
            });

            Notification::make()
                ->title(__('Berhasil Scan'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Error!'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->barcode = '';
        $this->dispatch('focus-barcode');
    }

    public function getSummaryData(): array
    {
        $soItems = $this->record->salesOrder->items()->with('product')->get();
        $summary = [];
        foreach ($soItems as $item) {
            $scannedWeight = (float) $this->record->items()->where('product_id', $item->product_id)->sum('weight');
            $scannedBox = $this->record->items()->where('product_id', $item->product_id)->count();
            $balance = $scannedWeight - (float) $item->weight;
            $summary[] = [
                'product_name' => $item->product?->name ?? 'Unknown',
                'po_weight' => (float) $item->weight,
                'scanned_weight' => $scannedWeight,
                'scanned_box' => $scannedBox,
                'balance' => $balance,
                'notes' => $item->note,
            ];
        }
        return $summary;
    }
}
