<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\FastMovingChart;
use App\Filament\Admin\Widgets\PendingTaskWidget;
use App\Filament\Admin\Widgets\PushSubscriptionCoverageWidget;
use App\Filament\Admin\Widgets\SalesYearlyChart;
use App\Filament\Admin\Widgets\ScheduledReminderHealthWidget;
use Tests\TestCase;

/**
 * Keputusan Owner, 7 September 2026: "Times ordered" (FastMovingChart) dan
 * "Sales trend" (SalesYearlyChart) tidak semua orang boleh lihat -- cukup di
 * menu Report (`FastMovingProducts`, `SalesReport`), jangan di Dashboard.
 *
 * Bawaan Filament, `Dashboard::getWidgets()` mengembalikan SEMUA widget yang
 * terdaftar di panel lewat `discoverWidgets()` -- termasuk keduanya, karena
 * mereka memang harus terdaftar di panel supaya `getHeaderWidgets()` di
 * halaman Report-nya bisa memakainya. Dashboard sekarang meng-override
 * `getWidgets()` dengan daftar eksplisit supaya keduanya tidak ikut nyelonong,
 * dan supaya widget lain yang ditambahkan ke folder Widgets nanti tidak
 * diam-diam muncul di sini tanpa ada yang memutuskannya.
 */
class DashboardTest extends TestCase
{
    public function test_report_charts_are_not_on_the_dashboard(): void
    {
        $widgets = (new Dashboard())->getWidgets();

        $this->assertNotContains(FastMovingChart::class, $widgets, 'Times ordered seharusnya cuma di menu Report.');
        $this->assertNotContains(SalesYearlyChart::class, $widgets, 'Sales trend seharusnya cuma di menu Report.');
    }

    public function test_the_dashboard_still_shows_its_own_widgets(): void
    {
        $widgets = (new Dashboard())->getWidgets();

        $this->assertContains(PendingTaskWidget::class, $widgets);
        $this->assertContains(PushSubscriptionCoverageWidget::class, $widgets);
        $this->assertContains(ScheduledReminderHealthWidget::class, $widgets);
    }

    /**
     * Link permintaan di dashboard membuka daftar berfilter status dan tanpa batas tanggal; filter bulan ini membuat
     * permintaan lama terhitung di dashboard tetapi tidak terlihat di daftarnya (Owner, 2 November 2026).
     */
    public function test_requisition_links_filter_by_status_and_drop_the_date_limit(): void
    {
        $url = (new \ReflectionMethod(PendingTaskWidget::class, 'requisitionUrl'))->invoke(
            new PendingTaskWidget(),
            \App\Filament\Admin\Resources\MaterialRequisitionResource::class,
            'Pending Finance',
        );

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('Pending Finance', $query['tableFilters']['status']['value']);
        $this->assertEmpty($query['tableFilters']['created_at']['created_from'] ?? null);
        $this->assertEmpty($query['tableFilters']['created_at']['created_until'] ?? null);
    }
}
