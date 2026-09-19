<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Models\SalesReturnPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Tidak ada `SalesReturnPlanItemPolicy` -- item baris, bukan dokumen
 * sendiri, pola yang sama dengan `MaterialStockTakeItem`. `authorize()`
 * bawaan Filament jatuh ke ALLOW tanpa Policy, jadi gerbang sesungguhnya
 * di sini: `canAccess()`, yang diperiksa ULANG di setiap request lewat
 * `hydrate()` bawaan `ManageRelatedRecords` -- bukan cuma sekali di
 * `mount()`.
 */
class ManageSalesReturnPlanItems extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $izin = auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_sales_return_plans') ?? false);

        if (! $izin) {
            return false;
        }

        $record = $parameters['record'] ?? null;

        // Received/Cancelled: negosiasi klaim sudah tidak relevan lagi --
        // lihat SalesReturnPlanItem::booted() untuk penjagaan yang sama di
        // lapis model.
        if ($record instanceof SalesReturnPlan && ! in_array($record->status, [
            SalesReturnPlan::STATUS_DRAFT, SalesReturnPlan::STATUS_SUBMITTED,
        ], true)) {
            return false;
        }

        return true;
    }

    protected static string $resource = SalesReturnPlanResource::class;

    protected static string $relationship = 'items';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    public function getTitle(): string
    {
        return __('Manage Items for :document', ['document' => $this->getOwnerRecord()->plan_number]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label(__('Back'))
                ->color('gray')
                ->url(fn (): string => $this->getOwnerRecord()->status === SalesReturnPlan::STATUS_DRAFT
                    ? $this->getResource()::getUrl('edit', ['record' => $this->getOwnerRecord()])
                    : $this->getResource()::getUrl('view', ['record' => $this->getOwnerRecord()])),
        ];
    }

    /** Draft: form lengkap termasuk produk. Submitted: cuma klaim yang boleh dinego. */
    public function form(Form $form): Form
    {
        $isDraft = $this->getOwnerRecord()->status === SalesReturnPlan::STATUS_DRAFT;

        return $form->schema(array_values(array_filter([
            $isDraft ? Forms\Components\Select::make('product_id')
                ->label(__('Product'))
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required() : null,
            Forms\Components\TextInput::make('claimed_weight')
                ->label(__('Claimed Weight (Kg)'))
                ->numeric()
                ->minValue(0.01)
                ->required(),
            Forms\Components\TextInput::make('claimed_qty_pcs')
                ->label(__('Claimed Qty (Pcs)'))
                ->numeric()
                ->integer(),
            Forms\Components\Textarea::make('note')
                ->label(__('Note'))
                ->maxLength(255)
                ->columnSpanFull(),
        ])));
    }

    protected function configureCreateAction(Tables\Actions\CreateAction $action): void
    {
        parent::configureCreateAction($action);

        $action
            ->visible(fn (): bool => $this->getOwnerRecord()->status === SalesReturnPlan::STATUS_DRAFT)
            ->using(function (array $data): Model {
                try {
                    return $this->getOwnerRecord()->items()->create($data);
                } catch (\Exception $e) {
                    Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                    throw new Halt();
                }
            });
    }

    protected function configureEditAction(Tables\Actions\EditAction $action): void
    {
        parent::configureEditAction($action);

        $action->using(function (Model $record, array $data): Model {
            try {
                $record->update($data);
            } catch (\Exception $e) {
                Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                throw new Halt();
            }

            return $record;
        });
    }

    protected function configureDeleteAction(Tables\Actions\DeleteAction $action): void
    {
        parent::configureDeleteAction($action);

        $action
            ->visible(fn (): bool => $this->getOwnerRecord()->status === SalesReturnPlan::STATUS_DRAFT)
            ->action(function (Model $record): void {
                try {
                    $record->delete();
                    Notification::make()->title(__('Item removed.'))->success()->send();
                } catch (\Exception $e) {
                    report($e);
                    Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product')),
                Tables\Columns\TextColumn::make('claimed_weight')
                    ->label(__('Claimed Weight (Kg)'))
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('claimed_qty_pcs')
                    ->label(__('Claimed Qty (Pcs)'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
