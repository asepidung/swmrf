<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Support\MasterSelect;
use App\Services\CostReports;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Laporan HPP Penjualan (HPP tahap 2, issue #522): per periode (tanggal surat
 * jalan; bulan berjalan sebagai bawaan, terlihat di formnya) per surat jalan,
 * invoice, customer, dan produk -- kg, penjualan, HPP, margin. HPP dibaca saat
 * laporan dibuat dari nilai barcode yang dikirim; barcode yang belum bernilai
 * ditandai dan margin barisnya tidak dihitung. Excel dan PDF mengikuti filter.
 *
 * Izin: `view_cost_reports` (baru, lewat migrasi; `tertunda.md` §G).
 */
class SalesCostReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.admin.pages.sales-cost-report';

    protected static ?int $navigationSort = 5;

    public ?string $periodFrom = null;

    public ?string $periodUntil = null;

    public ?int $customerId = null;

    public ?int $productId = null;

    public static function getNavigationGroup(): ?string
    {
        return __('REPORTS');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sales Cost Report');
    }

    public function getTitle(): string
    {
        return __('Sales Cost Report');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_cost_reports') ?? false;
    }

    public function mount(): void
    {
        $this->periodFrom = now()->startOfMonth()->toDateString();
        $this->periodUntil = now()->toDateString();

        $this->form->fill(['periodFrom' => $this->periodFrom, 'periodUntil' => $this->periodUntil]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('')
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                DatePicker::make('periodFrom')->label(__('From Date'))->native(false)->displayFormat('d/m/Y')->live()->required(),
                DatePicker::make('periodUntil')->label(__('Until Date'))->native(false)->displayFormat('d/m/Y')->live()->required(),

                MasterSelect::customer('customerId', activeOnly: false)
                    ->label(__('Customer'))
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
        $from = Carbon::parse($this->periodFrom ?: now()->startOfMonth());
        $until = Carbon::parse($this->periodUntil ?: now());

        if ($until->lt($from)) {
            [$from, $until] = [$until, $from];
        }

        return CostReports::salesCost($from, $until, $this->customerId ?: null, $this->productId ?: null);
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

            $writer->addRow($row([__('Sales Cost Report'), $report['from'], $report['until']]));
            $writer->addRow($row([__('Date'), __('Delivery Order'), __('Invoice'), __('Customer'), __('Product'), 'Kg', __('Sales'), 'HPP', __('Margin'), __('Unvalued barcodes'), __('Unvalued Kg')]));

            foreach ($report['rows'] as $line) {
                $writer->addRow($row([
                    $line['date'], $line['delivery_order'], $line['invoice'] ?? '', $line['customer'], $line['product'],
                    $line['kg'], $line['sales'] ?? '', $line['hpp'], $line['margin'] ?? '', $line['unvalued_barcodes'], $line['unvalued_kg'],
                ]));
            }

            $t = $report['total'];
            $writer->addRow($row([__('Total'), '', '', '', '', $t['kg'], $t['sales'], $t['hpp'], $t['margin'], $t['unvalued_barcodes'], $t['unvalued_kg']]));

            $writer->close();
        }, 'Sales_Cost_Report_'.$report['from'].'_'.$report['until'].'.xlsx');
    }

    public function downloadPdf()
    {
        $report = $this->getReport();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.sales-cost-report-pdf', ['report' => $report])
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), 'Sales_Cost_Report_'.$report['from'].'_'.$report['until'].'.pdf');
    }
}
