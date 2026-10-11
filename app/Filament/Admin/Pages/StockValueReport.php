<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Support\MasterSelect;
use App\Models\Warehouse;
use App\Services\CostReports;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Laporan Nilai Stok (HPP tahap 2, issue #522): stok di tangan per gudang dan
 * produk -- kg, nilai (jumlah kg x harga pokok barcode), dan jumlah barcode
 * yang BELUM bernilai, ditampilkan terpisah (tidak dihitung nol). Excel dan
 * PDF mengikuti filter yang dipilih.
 *
 * Izin: `view_cost_reports` (baru, lewat migrasi; `tertunda.md` §G).
 */
class StockValueReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.admin.pages.stock-value-report';

    protected static ?int $navigationSort = 4;

    public ?int $warehouseId = null;

    public ?int $productId = null;

    public static function getNavigationGroup(): ?string
    {
        return __('REPORTS');
    }

    public static function getNavigationLabel(): string
    {
        return __('Stock Value Report');
    }

    public function getTitle(): string
    {
        return __('Stock Value Report');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_cost_reports') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('warehouseId')
                    ->label(__('Warehouse'))
                    ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id'))
                    ->placeholder(__('All'))
                    ->live(),

                MasterSelect::product('productId', activeOnly: false)
                    ->label(__('Product'))
                    ->placeholder(__('All'))
                    ->live(),
            ]);
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        return CostReports::stockValue($this->warehouseId ?: null, $this->productId ?: null);
    }

    protected function getHeaderActions(): array
    {
        return [
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

    public function downloadExcel()
    {
        $report = $this->getReport();

        return response()->streamDownload(function () use ($report): void {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile('php://output');
            $row = fn (array $values) => \OpenSpout\Common\Entity\Row::fromValues($values);

            $writer->addRow($row([__('Stock Value Report'), now()->format('Y-m-d H:i')]));
            $writer->addRow($row([__('Warehouse'), __('Product'), __('Barcodes'), 'Kg', __('Valued Kg'), __('Value'), __('Unvalued barcodes'), __('Unvalued Kg')]));

            foreach ($report['rows'] as $line) {
                $writer->addRow($row([$line['warehouse'], $line['product'], $line['barcodes'], $line['kg'], $line['valued_kg'], $line['value'], $line['unvalued_barcodes'], $line['unvalued_kg']]));
            }

            $t = $report['total'];
            $writer->addRow($row([__('Total'), '', $t['barcodes'], $t['kg'], $t['valued_kg'], $t['value'], $t['unvalued_barcodes'], $t['unvalued_kg']]));

            $writer->close();
        }, 'Stock_Value_Report_'.now()->format('Ymd_His').'.xlsx');
    }

    public function downloadPdf()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.stock-value-report-pdf', ['report' => $this->getReport()])
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), 'Stock_Value_Report_'.now()->format('Ymd_His').'.pdf');
    }
}
