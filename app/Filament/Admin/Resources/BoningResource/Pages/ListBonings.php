<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Models\Boning;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBonings extends ListRecords
{
    protected static string $resource = BoningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /**
             * Batas susut wajar boning, diisi QC.
             *
             * Selama BELUM DIISI, gerbangnya tidak menyala: tiap batch tetap
             * bisa dikunci, dan susutnya tetap dihitung serta terbaca di
             * daftar. Bentuknya sama persis dengan Repack.
             *
             * Angka nyata pertama yang dimiliki proyek ini untuk susut boning
             * datang dari laporan sungguhan 28 Agustus 2026: bahan 3.968,22
             * kg, hasil 3.938,38 kg -- susut 0,75%. Satu batch belum cukup
             * untuk menetapkan ambang, tetapi ia menunjukkan besarannya.
             */
            Actions\Action::make('setShrinkLimit')
                ->label(__('Shrinkage Limit'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading(__('Reasonable Shrinkage Limit'))
                ->modalDescription(__('While this is empty, no boning is held back — the shrinkage is still recorded and shown.'))
                ->fillForm(fn (): array => [
                    'limit' => Boning::shrinkLimitPercent(),
                ])
                ->form([
                    Forms\Components\TextInput::make('limit')
                        ->label(__('Maximum shrinkage (%)'))
                        ->helperText(__('Leave it empty to hold nothing back.'))
                        ->extraInputAttributes(['inputmode' => 'decimal'])
                        ->rules(['nullable', 'numeric', 'min:0', 'max:100']),
                ])
                ->action(function (array $data): void {
                    $nilai = $data['limit'];

                    Setting::write(
                        Setting::BONING_MAX_SHRINK_PERCENT,
                        ($nilai === null || $nilai === '') ? null : $nilai,
                    );

                    Notification::make()->title(__('Saved'))->success()->send();
                })
                ->visible(fn (): bool => auth()->user()?->hasPermission('set_boning_yield_limit') ?? false),

            Actions\CreateAction::make()->label(__('Create')),
        ];
    }
}
