<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\PendingTaskWidget;
use App\Filament\Admin\Widgets\PushSubscriptionCoverageWidget;
use App\Filament\Admin\Widgets\ScheduledReminderHealthWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static bool $shouldRegisterNavigation = false;

    /**
     * Bawaan Filament (`Dashboard::getWidgets()`) mengembalikan SEMUA widget
     * yang terdaftar di panel -- termasuk FastMovingChart dan SalesYearlyChart,
     * yang `discoverWidgets()` di AdminPanelProvider daftarkan supaya bisa
     * dipakai `getHeaderWidgets()` di halaman Report masing-masing.
     *
     * Akibatnya kedua chart itu ikut tampil di Dashboard untuk SIAPA PUN yang
     * bisa membuka Dashboard, memotong `canAccess()` (`view_fast_moving_products`,
     * `view_sales_report`) yang menjaga halaman Report-nya -- keduanya tidak
     * punya `canView()` sendiri karena memang dirancang hanya lewat jalur itu.
     *
     * Keputusan Owner, 7 September 2026: kedua chart itu cukup di menu Report,
     * tidak semua orang boleh melihatnya. Daftar di sini SENGAJA eksplisit
     * (bukan "semua widget kecuali dua ini") supaya widget baru yang
     * ditambahkan ke folder Widgets tidak diam-diam ikut muncul di Dashboard
     * tanpa ada yang memutuskannya.
     *
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    public function getWidgets(): array
    {
        return [
            PendingTaskWidget::class,
            PushSubscriptionCoverageWidget::class,
            ScheduledReminderHealthWidget::class,
        ];
    }
}
