<?php

namespace App\Filament\Admin\Resources\MaterialUsageResource\Pages;

use App\Filament\Admin\Resources\MaterialUsageResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions;

class ViewMaterialUsage extends ViewRecord
{
    protected static string $resource = MaterialUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->url(fn() => static::getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('print')
                ->label(__('Print'))
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->url(fn ($record): string => route('material-usage.print', $record->id))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Process Details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('usageable_type')
                            ->label(__('Process Type'))
                            ->formatStateUsing(fn ($state) => class_basename($state))
                            ->badge()
                            ->color(fn ($state) => match (class_basename($state)) {
                                'Boning' => 'info',
                                'Repack' => 'warning',
                                'MaterialAdjustment' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('usageable.doc_no')
                            ->label(__('Document Number'))
                            ->default(fn (Model $record) => $record->usageable_id),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Usage Date'))
                            ->date('d M Y H:i'),
                        Infolists\Components\TextEntry::make('material_count')
                            ->label(__('Material Items Count')),
                        Infolists\Components\TextEntry::make('total_qty')
                            ->label(__('Total Qty')),
                    ])->columns(3),
                    
                Infolists\Components\Section::make(__('Material Usage Details'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('usages')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('material.name')
                                    ->label(__('Material')),
                                Infolists\Components\TextEntry::make('qty')
                                    ->label(__('Qty'))
                                    ->color('danger'),
                                Infolists\Components\TextEntry::make('note')
                                    ->label(__('Note')),
                            ])
                            ->columns(3)
                    ])
            ]);
    }
}
