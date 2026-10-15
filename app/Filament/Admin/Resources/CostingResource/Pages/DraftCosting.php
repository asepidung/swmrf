<?php

namespace App\Filament\Admin\Resources\CostingResource\Pages;

use App\Filament\Admin\Resources\CostingResource;
use App\Models\Boning;
use App\Models\Costing;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Draft Costing: boning yang sudah terkunci tetapi belum punya costing -- pola "Draft" modul lain
 * (mis. Draft Carcass). Memilih satu boning membuat costing-nya (Draft) lalu membuka halaman edit.
 * Tanpa pagination: baris yang terlewat berarti boning yang tidak pernah dicosting.
 */
class DraftCosting extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CostingResource::class;

    protected static string $view = 'filament.admin.resources.costing-resource.pages.draft-costing';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('create', Costing::class) ?? false;
    }

    public function getTitle(): string
    {
        return __('Draft Costing');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Boning::query()->where('kunci', true)->whereDoesntHave('costing')->withSum('items', 'weight'))
            ->defaultSort('boning_date', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('doc_no')
                    ->label(__('Boning'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('boning_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('items_sum_weight')
                    ->label(__('Total Kg'))
                    ->numeric(decimalPlaces: 2),
            ])
            ->actions([
                Action::make('process')
                    ->label(__('Create Costing'))
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->action(function (Boning $record) {
                        try {
                            $costing = Costing::createFromBoning($record);
                        } catch (\Exception $e) {
                            report($e);
                            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                            return;
                        }

                        $this->redirect(CostingResource::getUrl('edit', ['record' => $costing]));
                    }),
            ])
            ->emptyStateHeading(__('No locked boning is waiting for a costing.'));
    }
}
