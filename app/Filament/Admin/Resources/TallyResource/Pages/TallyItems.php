<?php

namespace App\Filament\Admin\Resources\TallyResource\Pages;

use App\Filament\Admin\Resources\TallyResource;
use App\Models\Tally;
use App\Models\TallyItem;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detail tally: seluruh barcode yang discan pada satu tally (barcode, produk, gudang, grade, berat, pcs, POD, asal, jam scan),
 * seperti halaman detail tally di aplikasi lama (permintaan Owner, 24 Oktober 2026). Bisa dicari, diurutkan, dan diekspor.
 */
class TallyItems extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TallyResource::class;

    protected static string $view = 'filament.admin.resources.tally-resource.pages.tally-items';

    public ?Tally $record = null;

    public function mount(int|string|Tally $record): void
    {
        abort_unless(auth()->user()?->hasPermission('view_tallies') ?? false, 403);

        $this->record = $record instanceof Tally ? $record : Tally::findOrFail($record);
    }

    public function getTitle(): string
    {
        return __('Tally Detail').': '.$this->record->tally_number;
    }

    public function getBreadcrumb(): string
    {
        return __('Detail');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => TallyResource::getUrl('view', ['record' => $this->record->id])),

            Action::make('excel')
                ->label(__('Excel'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn () => $this->downloadExcel()),

            Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => $this->downloadPdf()),
        ];
    }

    private function itemsQuery(): Builder
    {
        return TallyItem::query()
            ->where('tally_id', $this->record->id)
            ->with(['product:id,name', 'warehouse:id,code', 'grade:id,name']);
    }

    /** Kolom `origin` bisa berisi nama (BONING, R-STCK, ...) atau digit asal barcode; digit diterjemahkan, nama ditampilkan apa adanya. */
    public static function originName(?string $origin): string
    {
        if ($origin === null || $origin === '') {
            return '-';
        }

        $byDigit = ['1' => 'BONING', '2' => 'R-STCK', '3' => 'R-IMPT', '4' => 'R-RTRN', '5' => 'R-TRDG', '6' => 'RLB-TL', '7' => 'TRD-LC', '8' => 'TRD-IM', '0' => '-FOUND'];

        return $byDigit[$origin] ?? $origin;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->itemsQuery())
            ->columns([
                Tables\Columns\TextColumn::make('index')->label('#')->rowIndex(),
                Tables\Columns\TextColumn::make('barcode')->label(__('Barcode'))->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('product.name')->label(__('Item'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('warehouse.code')->label(__('Warehouse')),
                Tables\Columns\TextColumn::make('grade.name')->label(__('Grade')),
                Tables\Columns\TextColumn::make('weight')->label(__('Weight'))->numeric(2, ',', '.')->alignEnd()->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label(__('Total'))->numeric(2, ',', '.')),
                Tables\Columns\TextColumn::make('qty_pcs')->label(__('Pcs'))->alignEnd()->placeholder('-'),
                Tables\Columns\TextColumn::make('pack_date')->label(__('POD'))->date('d-M-Y')->sortable(),
                Tables\Columns\TextColumn::make('origin')->label(__('Origin'))->formatStateUsing(fn ($state): string => static::originName($state)),
                Tables\Columns\TextColumn::make('created_at')->label(__('Time Scan'))->dateTime('H:i:s')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /** @return array<int, array<int, mixed>> */
    private function exportRows(): array
    {
        return $this->itemsQuery()->orderBy('created_at')->get()->map(fn (TallyItem $item, int $i): array => [
            $i + 1,
            $item->barcode,
            $item->product?->name ?? '-',
            $item->warehouse?->code ?? '-',
            $item->grade?->name ?? '-',
            (float) $item->weight,
            $item->qty_pcs,
            $item->pack_date?->format('d-M-Y'),
            static::originName($item->origin),
            $item->created_at?->format('H:i:s'),
        ])->all();
    }

    public function downloadExcel()
    {
        $rows = $this->exportRows();
        $number = $this->record->tally_number;

        return response()->streamDownload(function () use ($rows, $number): void {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile('php://output');
            $row = fn (array $values) => \OpenSpout\Common\Entity\Row::fromValues($values);

            $writer->addRow($row([__('Tally Detail'), $number]));
            $writer->addRow($row(['#', __('Barcode'), __('Item'), __('Warehouse'), __('Grade'), __('Weight'), __('Pcs'), __('POD'), __('Origin'), __('Time Scan')]));

            foreach ($rows as $values) {
                $writer->addRow($row($values));
            }

            $writer->addRow($row([__('Total'), count($rows).' '.__('items'), '', '', '', array_sum(array_column($rows, 5))]));
            $writer->close();
        }, 'Tally_Detail_'.str_replace('#', '', $number).'.xlsx');
    }

    public function downloadPdf()
    {
        $rows = $this->exportRows();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.tally-items-pdf', ['tally' => $this->record, 'rows' => $rows])
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), 'Tally_Detail_'.str_replace('#', '', $this->record->tally_number).'.pdf');
    }
}
