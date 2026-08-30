<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Dashboard minimalis: tidak boleh ada widget BAWAAN framework yang terdaftar.
 *
 * `project.md` sudah melarangnya, tetapi hanya menyebut `FilamentInfoWidget`
 * sebagai contoh -- sehingga `AccountWidget` (sapaan "Welcome", nama user, dan
 * tombol Sign out yang ketiganya sudah ada di user menu topbar) hidup
 * berbulan-bulan tanpa ada yang menganggapnya melanggar.
 *
 * Karena itu yang dijaga di sini adalah POLANYA, bukan satu kelas yang
 * kebetulan sudah ketahuan: widget mana pun dari namespace `Filament\Widgets`
 * ditolak. Menjaga `AccountWidget` saja tidak cukup -- yang berikutnya akan
 * masuk dengan cara yang persis sama, dan tidak akan menimbulkan error apa pun
 * karena widget bawaan memang berfungsi normal. Yang salah bukan kodenya,
 * melainkan keputusannya.
 *
 * Widget milik proyek (`App\Filament\Admin\Widgets\*`) tentu tetap lolos.
 */
class WidgetRegistrationTest extends TestCase
{
    public function test_no_framework_default_widget_is_registered_on_the_panel(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        $frameworkWidgets = array_values(array_filter(
            $widgets,
            fn (string $widget): bool => str_starts_with($widget, 'Filament\\Widgets\\'),
        ));

        $this->assertSame(
            [],
            $frameworkWidgets,
            'Dashboard wajib minimalis: widget bawaan Filament tidak boleh didaftarkan di panel. '
                .'Hapus dari `->widgets([])` di AdminPanelProvider. Yang terdaftar: '
                .implode(', ', $frameworkWidgets),
        );
    }

    public function test_project_owned_widgets_are_still_discovered(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        // Penjaga di atas dibuat seketat mungkin, jadi ini memastikan ia tidak
        // ikut menyapu widget yang memang sengaja dipasang -- termasuk
        // PushSubscriptionCoverageWidget, yang menghitung berapa orang benar-
        // benar berlangganan notifikasi dan bukan hiasan.
        $this->assertContains(\App\Filament\Admin\Widgets\PushSubscriptionCoverageWidget::class, $widgets);
        $this->assertContains(\App\Filament\Admin\Widgets\PendingTaskWidget::class, $widgets);
    }
}
