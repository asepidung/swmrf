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

            Actions\Action::make('lock')
                ->label(__('Lock'))
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->tooltip(fn (\App\Models\Repack $record): string => $record->isWithinShrinkLimit()
                    ? __('Lock repack (final)')
                    : __('Shrinkage is outside the reasonable limit; QC approval is needed.'))
                ->requiresConfirmation()
                ->modalHeading(__('Lock Repack Data'))
                ->modalDescription(__('Are you sure you want to lock this data? Once locked, you cannot modify it.'))
                ->action(function (\App\Models\Repack $record) {
                    try {
                        $record->lock();
                    } catch (\Throwable $e) {
                        report($e);
                        \Filament\Notifications\Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
                        return;
                    }

                    \Filament\Notifications\Notification::make()->title(__('Repack Locked'))->success()->send();
                    return redirect($this->getResource()::getUrl('index'));
                })
                ->hidden(fn (\App\Models\Repack $record) => ! auth()->user()->hasPermission('lock_repacks') || $record->kunci || ! $record->materialUsages()->exists()),

            Actions\Action::make('approve_shrink')
                ->label(__('Approve the shrinkage'))
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->modalHeading(__('Approve shrinkage beyond the limit'))
                ->modalDescription(__('The person who made this repack cannot lock it until QC approves.'))
                ->form(fn (\App\Models\Repack $record): array => [
                    \Filament\Forms\Components\Placeholder::make('ringkasan')
                        ->label(__('Shrinkage'))
                        ->content(fn (): string => number_format($record->shrinkWeight(), 2, ',', '.')
                            .' Kg ('.number_format((float) $record->shrinkPercent(), 2, ',', '.').'%) '
                            .__('of the :limit% limit', [
                                'limit' => number_format((float) \App\Models\Repack::shrinkLimitPercent(), 2, ',', '.'),
                            ])),

                    \Filament\Forms\Components\Textarea::make('yield_override_reason')
                        ->label(__('Why this is being approved'))
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (\App\Models\Repack $record, array $data) {
                    try {
                        $record->grantShrinkOverride($data['yield_override_reason']);
                    } catch (\Throwable $e) {
                        report($e);
                        \Filament\Notifications\Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->title(__('Shrinkage approved'))
                        ->body(__('The repack can be locked now.'))
                        ->success()
                        ->send();
                })
                ->visible(fn (\App\Models\Repack $record): bool => (auth()->user()?->hasPermission('override_repack_yield') ?? false)
                    && ! $record->kunci
                    && ! $record->isWithinShrinkLimit()
                    && ! $record->shrinkLimitWasOverridden()),

            Actions\Action::make('unlock')
                ->label(__('Unlock'))
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->tooltip(__('Unlock'))
                ->requiresConfirmation()
                ->modalHeading(__('Unlock Repack Data'))
                ->modalDescription(__('Are you sure you want to unlock this data? It will become editable again.'))
                ->action(function (\App\Models\Repack $record) {
                    $record->unlock();
                    \Filament\Notifications\Notification::make()->title(__('Repack Unlocked'))->success()->send();
                    return redirect($this->getResource()::getUrl('index'));
                })
                ->hidden(fn (\App\Models\Repack $record) => ! auth()->user()->hasPermission('lock_repacks') || ! $record->kunci),
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
