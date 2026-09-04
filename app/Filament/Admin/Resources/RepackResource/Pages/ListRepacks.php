<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use App\Models\Repack;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRepacks extends ListRecords
{
    protected static string $resource = RepackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /**
             * Batas susut wajar, diisi QC.
             *
             * Selama BELUM DIISI, gerbangnya tidak menyala sama sekali: tiap
             * dokumen tetap bisa dikunci, dan susutnya tetap dihitung serta
             * terbaca di daftar. Itu disengaja -- sekarang belum ada satu pun
             * data susut yang pernah tersimpan, jadi angka apa pun yang
             * dikarang di kode akan salah, dan menghalangi pekerjaan dengan
             * angka yang tidak dipilih manusia mana pun sudah pernah menjadi
             * kesalahan di modul Retur hari ini.
             *
             * Sesudah beberapa waktu berjalan, angkanya ada dari pekerjaan
             * nyata, dan QC mengisinya dari situ -- bukan dari tebakan.
             */
            Actions\Action::make('setShrinkLimit')
                ->label(__('Shrinkage Limit'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading(__('Reasonable Shrinkage Limit'))
                ->modalDescription(__('While this is empty, no repack is held back — the shrinkage is still recorded and shown.'))
                ->fillForm(fn (): array => [
                    'limit' => Repack::shrinkLimitPercent(),
                ])
                ->form([
                    Forms\Components\TextInput::make('limit')
                        ->label(__('Maximum shrinkage (%)'))
                        ->helperText(__('Leave it empty to hold nothing back.'))
                        // Tanpa tombol panah, mengikuti keputusan yang sama
                        // pada berat karkas, pH, dan TOP: satu sentuhan yang
                        // tidak disengaja tidak boleh menggeser angka yang
                        // menentukan.
                        ->extraInputAttributes(['inputmode' => 'decimal'])
                        ->rules(['nullable', 'numeric', 'min:0', 'max:100']),
                ])
                ->action(function (array $data): void {
                    $nilai = $data['limit'];

                    Setting::write(
                        Setting::REPACK_MAX_SHRINK_PERCENT,
                        ($nilai === null || $nilai === '') ? null : $nilai,
                    );

                    Notification::make()->title(__('Saved'))->success()->send();
                })
                ->visible(fn (): bool => auth()->user()?->hasPermission('set_repack_yield_limit') ?? false),

            Actions\CreateAction::make()->label(__('Create')),
        ];
    }
}
