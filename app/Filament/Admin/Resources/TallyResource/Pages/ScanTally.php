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

        if ($this->record->status !== 'processing' || in_array($this->record->salesOrder?->status, ['cancelled', 'canceled'])) {
            $this->redirect(TallyResource::getUrl('view', ['record' => $this->record->id]));
            return;
        }

        $this->podLimit = session('tally_pod_limit');
    }

    public function updatedPodLimit($value): void
    {
        if ($value === null || $value === '') {
            session(['tally_pod_limit' => null]);
            return;
        }
        session(['tally_pod_limit' => (int) $value]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('')
                ->tooltip(__('Back to List'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->iconButton()
                ->url(fn () => TallyResource::getUrl('index')),

            Actions\Action::make('print')
                ->label('')
                ->tooltip(__('Print Tally'))
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->iconButton()
                ->url(fn () => route('print.tally', ['record' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('approve')
                ->label('')
                ->tooltip(__('Approve Tally'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->iconButton()
                ->requiresConfirmation()
                ->modalHeading(__('Approve Tally'))
                ->modalDescription(__('Apakah Anda yakin ingin menyetujui Tally ini? Setelah disetujui, data tidak dapat diubah lagi.'))
                ->form([
                    Forms\Components\TextInput::make('seal_number')
                        ->label(__('Seal Number (If Any)'))
                        ->placeholder(__('Seal Number')),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $this->record->update([
                            'status' => 'locked',
                            'seal_number' => $data['seal_number'] ?? null,
                        ]);
                        $this->record->salesOrder->update([
                            'status' => 'ready',
                        ]);

                        activity('tally')
                            ->performedOn($this->record)
                            ->log('Approved Tally: ' . $this->record->tally_number);
                    });

                    Notification::make()
                        ->title(__('Tally Approved Successfully'))
                        ->success()
                        ->send();

                    $this->redirect(TallyResource::getUrl('view', ['record' => $this->record->id]));
                })
                ->visible(fn () => auth()->user()->hasPermission('lock_tallies')),

            Actions\Action::make('delete')
                ->label('')
                ->tooltip(__('Delete Tally'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->iconButton()
                ->requiresConfirmation()
                ->modalHeading(__('Hapus Tally'))
                ->modalDescription(__('Jika Anda menghapus Tally ini, maka semua data barang di dalam Tally akan dikembalikan ke stock.'))
                ->action(function () {
                    DB::transaction(function () {
                        $this->record->delete();
                    });
                    Notification::make()
                        ->title(__('Tally Deleted and Stock Restored'))
                        ->success()
                        ->send();
                    $this->redirect(TallyResource::getUrl('index'));
                })
                ->visible(fn () => $this->record->status === 'processing' && auth()->user()->hasPermission('delete_tallies')),
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
                    ->formatStateUsing(fn ($state) => match(strtoupper($state)) {
                        'CHILL' => 'C',
                        'FROZEN' => 'F',
                        default => $state,
                    })
                    ->color(fn (?TallyItem $record) => $record ? (in_array($record->grade?->name, ['CHILL', 'A']) ? 'info' : 'danger') : null),

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

                // Merah DAN bisa diklik hanya untuk barang yang umurnya sudah
                // MELEWATI batas. Yang masih dalam batas tampil biasa tanpa
                // tautan.
                //
                // Sebelumnya warnanya sudah benar tetapi aksinya dipasang
                // tanpa syarat, sehingga setiap tanggal POD terlihat dan
                // terasa bisa diklik -- termasuk barang yang tidak perlu
                // dilabeli ulang sama sekali.
                //
                // Yang mematikannya harus ->disableClick(), bukan ->hidden()
                // pada aksinya. Filament merender selnya sebagai tombol
                // begitu sebuah aksi terpasang, tanpa memeriksa apakah aksi
                // itu sedang tampil; menyembunyikan aksinya hanya membuat
                // kliknya tidak melakukan apa-apa, sementara selnya tetap
                // terlihat bisa diklik. Dengan disableClick, Filament
                // merendernya sebagai sel biasa.
                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('POD'))
                    ->date('d-m-y')
                    ->alignCenter()
                    ->color(fn (?TallyItem $record, $livewire) => $livewire->isPastPodLimit($record) ? 'danger' : null)
                    ->weight(fn (?TallyItem $record, $livewire) => $livewire->isPastPodLimit($record) ? 'bold' : null)
                    ->tooltip(fn (?TallyItem $record, $livewire) => $livewire->isPastPodLimit($record)
                        ? __('Older than the limit. Click to relabel.')
                        : null)
                    ->disableClick(fn (?TallyItem $record, $livewire) => ! $livewire->isPastPodLimit($record))
                    ->action(
                        Tables\Actions\Action::make('relabel')
                            // Penjagaan kedua. disableClick menghentikan
                            // kliknya di layar; ini menghentikan pemanggilan
                            // yang tidak lewat layar.
                            ->visible(fn (?TallyItem $record, $livewire) => $livewire->isPastPodLimit($record))
                            ->modalHeading(__('Relabel Tally Item'))
                            ->modalDescription(__('Change the POD date and reprint the label.'))
                            ->form(fn (TallyItem $record) => [
                                Forms\Components\TextInput::make('product_name')
                                    ->label(__('Product'))
                                    ->default($record->product?->name)
                                    ->readOnly(),
                                Forms\Components\TextInput::make('old_barcode')
                                    ->label(__('Old Barcode'))
                                    ->default($record->barcode)
                                    ->readOnly(),
                                Forms\Components\DatePicker::make('pack_date')
                                    ->label(__('New Pack Date (POD)'))
                                    ->required()
                                    ->default($record->pack_date?->format('Y-m-d') ?? now()->format('Y-m-d'))
                                    ->live(),
                                Forms\Components\Checkbox::make('show_exp')
                                    ->label(__('Show Expiry Date on Label'))
                                    ->default(false),
                            ])
                            ->action(function (TallyItem $record, array $data, $livewire) {
                                $newPackDate = $data['pack_date'];
                                $showExp = $data['show_exp'];
                                
                                DB::transaction(function () use ($record, $newPackDate, $showExp) {
                                    $oldBarcode = $record->barcode;
                                    $oldPackDate = $record->pack_date?->format('Y-m-d');
                                    $oldExpDate = $record->exp_date?->format('Y-m-d');
                                    
                                    // Calculate expiry date based on product type
                                    $date = \Carbon\Carbon::parse($newPackDate);
                                    if (in_array((int)$record->grade_id, [1, 3])) { // CHILL or A
                                        $newExpDate = $date->copy()->addMonths(3)->format('Y-m-d');
                                    } else {
                                        $newExpDate = $date->copy()->addYear()->format('Y-m-d');
                                    }
                                    
                                    // Generate new barcode starting with prefix '6'
                                    $origin = '6';
                                    $dateStr = \Carbon\Carbon::parse($newPackDate)->format('dmy');
                                    $productCode = $record->product?->code ?? '000000';
                                    if (strlen($productCode) > 6) {
                                        $productCode = substr($productCode, 0, 6);
                                    } else {
                                        $productCode = str_pad($productCode, 6, '0', STR_PAD_LEFT);
                                    }
                                    
                                    $gradeId = $record->grade_id;
                                    $weightStr = str_pad(round($record->weight * 100), 4, '0', STR_PAD_LEFT);
                                    $pcsStr = str_pad($record->qty_pcs, 2, '0', STR_PAD_LEFT);
                                    $phStr = isset($record->ph_level) ? str_pad(round($record->ph_level * 10), 2, '0', STR_PAD_LEFT) : '00';
                                    
                                    $prefix = $origin . $dateStr;
                                    
                                    // Find maximum counter in tally_items
                                    $latestTallyItem = \App\Models\TallyItem::where('barcode', 'like', $prefix . '%')->lockForUpdate()->orderBy('id', 'desc')->first();
                                    $counterTally = ($latestTallyItem && strlen($latestTallyItem->barcode) >= 26) ? ((int) substr($latestTallyItem->barcode, -4)) : 0;

                                    // Find maximum counter in beef_stocks
                                    $latestBeefStock = \App\Models\BeefStock::where('barcode', 'like', $prefix . '%')->lockForUpdate()->orderBy('id', 'desc')->first();
                                    $counterStock = ($latestBeefStock && strlen($latestBeefStock->barcode) >= 26) ? ((int) substr($latestBeefStock->barcode, -4)) : 0;

                                    $counter = max($counterTally, $counterStock) + 1;
                                    $counterStr = str_pad($counter, 4, '0', STR_PAD_LEFT);
                                    
                                    $newBarcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;
                                    
                                    // Update tally item
                                    $record->update([
                                        'barcode' => $newBarcode,
                                        'pack_date' => $newPackDate,
                                        'exp_date' => $newExpDate,
                                    ]);
                                    
                                    // Update matching beef stock movement of type TALLY
                                    \App\Models\BeefStockMovement::where('barcode', $oldBarcode)
                                        ->where('transaction_type', 'TALLY')
                                        ->update([
                                            'barcode' => $newBarcode,
                                        ]);
                                        
                                    // Log the change in beef_stock_movements
                                    \App\Models\BeefStockMovement::create([
                                        'product_id' => $record->product_id,
                                        'warehouse_id' => $record->warehouse_id,
                                        'condition' => $record->grade_id,
                                        'barcode' => $newBarcode,
                                        'transaction_type' => 'TALLY_RELABEL',
                                        'reference_document' => $record->tally?->tally_number ?? $record->tally_id,
                                        'weight_in' => 0,
                                        'weight_out' => 0,
                                        'pcs_in' => 0,
                                        'pcs_out' => 0,
                                        'note' => "Relabel: {$oldBarcode} -> {$newBarcode} (POD: {$oldPackDate} -> {$newPackDate})",
                                        'created_by' => auth()->id() ?? 1,
                                    ]);
                                });
                                
                                Notification::make()
                                    ->title(__('Tally Item Relabeled Successfully'))
                                    ->success()
                                    ->send();
                                    
                                $printUrl = route('tally-item.label', [
                                    'id' => $record->id,
                                    'show_exp' => $showExp ? 1 : 0
                                ]);
                                
                                $livewire->dispatch('auto-print', url: $printUrl);
                            })
                    ),

                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record || !$record->barcode) return $state;
                        $prefix = substr($record->barcode, 0, 1);
                        $map = [
                            '1' => 'BNG',
                            '2' => 'RSTK',
                            '3' => 'RIMP',
                            '4' => 'RRTN',
                            '5' => 'RTRD',
                            '6' => 'RLBT',
                            '7' => 'TRDL',
                            '8' => 'TRDI',
                        ];
                        return $map[$prefix] ?? $state;
                    })
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
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
        if ($this->record->status !== 'processing' || in_array($this->record->salesOrder?->status, ['cancelled', 'canceled'])) {
            return;
        }

        $barcode = trim($this->barcode);
        if (empty($barcode)) {
            return;
        }

        // 1. Cek duplikat barcode di tally_items (hanya dalam Tally ID yang sama)
        $exists = $this->record->items()->where('barcode', $barcode)->exists();
        if ($exists) {
            Notification::make()
                ->title(__('Scan Failed'))
                ->body(__('Barang Sudah Terscan di Tally Ini (Duplikat)'))
                ->warning()
                ->send();
            $this->barcode = '';
            $this->dispatch('focus-barcode');
            return;
        }

        // 4. Proses pemindahan stock
        try {
            DB::transaction(function () use ($barcode) {
                // 2. Ambil data dari stock (beef_stocks) dengan lockForUpdate
                $stock = BeefStock::where('barcode', $barcode)->lockForUpdate()->first();
                if (!$stock) {
                    throw new \Exception(__('ITEM NOT REGISTERED in Stock'));
                }

                // 3. Pastikan product_id ada di sales_order_items
                $soItemExists = $this->record->salesOrder->items()->where('product_id', $stock->product_id)->exists();
                if (!$soItemExists) {
                    throw new \Exception(__('Item Not in PO'));
                }
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
                    'created_by' => auth()->id() ?? 1,
                ]);
            });

            Notification::make()
                ->title(__('Scan Successful'))
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

    /**
     * Barang ini sudah melewati batas umur POD.
     *
     * Satu-satunya tempat aturannya ditulis. Warna, tebal huruf, keterangan
     * bantuan, bisa-tidaknya diklik, dan penjagaan aksinya semua bertanya ke
     * sini -- kalau aturannya disalin, kelimanya bisa berbeda pendapat dan
     * sebuah baris bisa tampil merah tetapi menolak diklik.
     *
     * Batas yang belum diisi berarti TIDAK ADA yang lewat batas. Tanpa angka
     * pembanding kita memang tidak tahu apa-apa, dan menganggap semuanya
     * kedaluwarsa akan menyalakan seluruh kolom menjadi merah.
     */
    public function isPastPodLimit(?TallyItem $record): bool
    {
        if (! $record || ! $record->pack_date) {
            return false;
        }

        if ($this->podLimit === null || $this->podLimit === '') {
            return false;
        }

        $ageInDays = (int) abs(now()->startOfDay()->diffInDays($record->pack_date->startOfDay()));

        return $ageInDays > (int) $this->podLimit;
    }

    public function getSummaryData(): array
    {
        // Dihitung sekali dalam dua kueri, bukan dua kueri per produk.
        // Halaman ini digambar ulang setiap kali satu barcode dipindai, jadi
        // kueri per baris ikut terkena berkali-kali sepanjang penerimaan.
        $scanned = $this->record->items()
            ->selectRaw('product_id, SUM(weight) as total_weight, COUNT(*) as total_box')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        return $this->record->salesOrder->items()->with('product')->get()
            ->map(function ($item) use ($scanned) {
                $row = $scanned->get($item->product_id);

                $scannedWeight = (float) ($row->total_weight ?? 0);
                $scannedBox = (int) ($row->total_box ?? 0);

                return [
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'po_weight' => (float) $item->weight,
                    'scanned_weight' => $scannedWeight,
                    'scanned_box' => $scannedBox,
                    'balance' => $scannedWeight - (float) $item->weight,
                    'notes' => $item->note,
                ];
            })
            ->all();
    }

    public function getViewData(): array
    {
        $productData = [];
        foreach ($this->record->items as $item) {
            $productName = $item->product?->name ?? 'Unknown';
            if (!isset($productData[$productName])) {
                $productData[$productName] = [
                    'weights' => [],
                    'total' => 0,
                ];
            }
            $productData[$productName]['weights'][] = (float) $item->weight;
            $productData[$productName]['total'] += (float) $item->weight;
        }

        $totalBox = $this->record->items()->count();
        $totalQty = (float) $this->record->items()->sum('weight');

        return [
            'productData' => $productData,
            'totalBox' => $totalBox,
            'totalQty' => $totalQty,
        ];
    }
}
