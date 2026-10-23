<?php

namespace App\Filament\Admin\Pages;

use App\Models\DeliveryOrder;
use App\Services\CostReports;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * Rincian satu faktur/surat jalan di Laporan HPP Penjualan (Owner, 24 Oktober 2026): daftarnya hanya memuat tanggal, faktur, customer, kg
 * dan margin; penjualan, HPP, barcode belum bernilai dan rincian per barang ada di sini.
 */
class SalesCostReportDetail extends Page
{
    protected static string $view = 'filament.admin.pages.sales-cost-report-detail';

    protected static bool $shouldRegisterNavigation = false;

    public DeliveryOrder $deliveryOrder;

    public static function getRoutePath(): string
    {
        return '/sales-cost-report/{order}';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_cost_reports') ?? false;
    }

    public function mount(int|string $order): void
    {
        abort_unless(static::canAccess(), 403);

        $this->deliveryOrder = DeliveryOrder::with('customer:id,name')->findOrFail($order);
    }

    public function getTitle(): string
    {
        return __('Sales Cost Report').': '.($this->getReport()['invoice'] ?? $this->deliveryOrder->delivery_order_number);
    }

    /** @return array{invoice: ?string, rows: array<int, array<string, mixed>>, total: array<string, mixed>} */
    public function getReport(): array
    {
        $date = Carbon::parse($this->deliveryOrder->delivery_date);
        $report = CostReports::salesCost($date, $date, $this->deliveryOrder->customer_id);
        $rows = array_values(array_filter($report['rows'], fn (array $row): bool => (int) $row['delivery_order_id'] === (int) $this->deliveryOrder->id));

        return [
            'invoice' => $rows[0]['invoice'] ?? null,
            'rows' => $rows,
            'total' => CostReports::summarize($rows),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(SalesCostReport::getUrl()),
        ];
    }
}
