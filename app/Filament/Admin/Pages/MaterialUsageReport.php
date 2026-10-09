<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Filament\Admin\Resources\RepackResource;
use App\Services\ProductionMaterialReport;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Laporan pemakaian bahan per periode untuk atasan (issue #509, langkah 5b).
 *
 * Total kebutuhan BOM per material, total drylog, dan bahan terbuang (jumlah
 * dan rupiah) per material, ditambah rincian per dokumen. Hanya Boning dan
 * Repack yang sudah TERKUNCI. Periode bawaan bulan berjalan; formulir dan
 * hitungannya memakai tanggal yang sama (tidak ada filter yang membatasi tanpa
 * terlihat di formnya). Excel dan PDF mengikuti periode yang dipilih.
 *
 * Izin: `view_material_usages` -- izin yang sudah ada, tidak ada yang baru.
 */
class MaterialUsageReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.admin.pages.material-usage-report';

    protected static ?int $navigationSort = 3;

    public ?string $periodFrom = null;

    public ?string $periodUntil = null;

    public static function getNavigationGroup(): ?string
    {
        return __('REPORTS');
    }

    public static function getNavigationLabel(): string
    {
        return __('Material Usage Report');
    }

    public function getTitle(): string
    {
        return __('Material Usage Report');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_material_usages') ?? false;
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
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                DatePicker::make('periodFrom')
                    ->label(__('From Date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->required(),

                DatePicker::make('periodUntil')
                    ->label(__('Until Date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->required(),
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

        return ProductionMaterialReport::between($from, $until);
    }

    /** URL halaman Pemakaian Material sebuah dokumen. */
    public function documentUrl(string $kind, int $id): string
    {
        $resource = $kind === 'boning' ? BoningResource::class : RepackResource::class;

        return $resource::getUrl('material-usage', ['record' => $id]);
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
        $name = 'Material_Usage_Report_'.$report['from'].'_'.$report['until'].'.xlsx';

        return response()->streamDownload(function () use ($report): void {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile('php://output');
            $row = fn (array $values) => \OpenSpout\Common\Entity\Row::fromValues($values);

            $writer->addRow($row([__('Material Usage Report'), $report['from'], $report['until']]));
            $writer->addRow($row([]));

            $writer->addRow($row([__('Usage per BOM')]));
            $writer->addRow($row([__('Material'), __('Quantity'), __('Unit'), __('Value')]));
            foreach ($report['bom'] as $line) {
                $writer->addRow($row([$line['material'], $line['qty'], 'pcs', $line['amount']]));
            }
            $writer->addRow($row([__('Drylog / Pad Absorber'), $report['drylog']['qty'], 'pcs', $report['drylog']['amount']]));
            $writer->addRow($row([__('Total usage value'), '', '', $report['usage_total']]));
            $writer->addRow($row([]));

            $writer->addRow($row([__('Material Waste')]));
            $writer->addRow($row([__('Material'), __('Quantity'), __('Value')]));
            foreach ($report['wastes'] as $line) {
                $writer->addRow($row([$line['material'], $line['qty'], $line['amount']]));
            }
            $writer->addRow($row([__('Total wasted'), $report['waste_total']['qty'], $report['waste_total']['amount']]));
            $writer->addRow($row([]));

            $writer->addRow($row([__('Per document')]));
            $writer->addRow($row([__('Process'), __('Document'), __('Date'), __('Drylog / Pad Absorber'), __('Wasted'), __('Usage value'), __('Waste value')]));
            foreach ($report['documents'] as $document) {
                $writer->addRow($row([
                    $document['kind'] === 'boning' ? 'Boning' : 'Repack',
                    $document['doc_no'],
                    $document['date'],
                    $document['drylog'] ?? '',
                    $document['waste_qty'],
                    $document['usage_amount'],
                    $document['waste_amount'],
                ]));
            }

            $writer->close();
        }, $name);
    }

    public function downloadPdf()
    {
        $report = $this->getReport();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.material-usage-report-pdf', ['report' => $report])
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'Material_Usage_Report_'.$report['from'].'_'.$report['until'].'.pdf',
        );
    }
}
