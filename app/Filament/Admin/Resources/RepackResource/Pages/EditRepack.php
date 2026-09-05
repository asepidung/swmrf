<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRepack extends EditRecord
{
    protected static string $resource = RepackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->icon('heroicon-m-arrow-left')
                ->url($this->getResource()::getUrl('index')),
            Actions\Action::make('print')
                ->label(__('Print Summary'))
                ->color('success')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('repack.summary', ['id' => $this->getRecord()->id]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->hidden(function (\App\Models\Repack $record) {
                    if ($record->kunci == 1) return true;
                    $hasBahan = \Illuminate\Support\Facades\DB::table('repack_materials')->where('repack_id', $record->id)->exists();
                    $hasHasil = \Illuminate\Support\Facades\DB::table('repack_results')->where('repack_id', $record->id)->whereNull('deleted_at')->exists();
                    return $hasBahan || $hasHasil;
                }),
        ];
    }

    protected function getFormActions(): array
    {
        /** @var \App\Models\Repack $record */
        $record = $this->getRecord();
        if ($record->kunci == 1) {
            return [];
        }
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
