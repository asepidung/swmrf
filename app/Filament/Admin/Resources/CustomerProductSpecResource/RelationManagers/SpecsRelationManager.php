<?php

namespace App\Filament\Admin\Resources\CustomerProductSpecResource\RelationManagers;

use App\Filament\Support\MasterSelect;
use App\Models\CustomerProductSpec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Semua produk beserta spek-nya untuk satu customer. Mengubah dan menghapus mengikuti izin
 * "Kelola spek customer"; Excel (spek customer ini) ada di sini, bukan di daftar.
 */
class SpecsRelationManager extends RelationManager
{
    protected static string $relationship = 'productSpecs';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Products and specs');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(['default' => 1, 'lg' => 2])
            ->schema([
                MasterSelect::product('product_id')
                    ->label(__('Product'))
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('weight_per_pack')
                    ->label(__('Weight per pack'))
                    ->placeholder('500 gr / pack')
                    ->maxLength(255),

                Forms\Components\TextInput::make('cut')
                    ->label(__('Cut'))
                    ->placeholder('slice 1,5 mm')
                    ->maxLength(255),

                Forms\Components\Select::make('condition')
                    ->label(__('Condition'))
                    ->options(CustomerProductSpec::CONDITIONS),

                Forms\Components\TextInput::make('packaging')
                    ->label(__('Packaging'))
                    ->placeholder('vacuum per pcs')
                    ->maxLength(255),

                Forms\Components\Textarea::make('note')
                    ->label(__('Spec Note'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight_per_pack')->label(__('Weight per pack')),
                Tables\Columns\TextColumn::make('cut')->label(__('Cut')),
                Tables\Columns\TextColumn::make('condition')
                    ->label(__('Condition'))
                    ->formatStateUsing(fn (?string $state): ?string => CustomerProductSpec::conditionLabel($state)),
                Tables\Columns\TextColumn::make('packaging')->label(__('Packaging')),
                Tables\Columns\TextColumn::make('note')->label(__('Spec Note'))->wrap(),
            ])
            ->defaultSort('product_id')
            ->paginated(false)
            ->headerActions([
                Tables\Actions\Action::make('excel')
                    ->label(__('Excel'))
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->action(function () {
                        $customer = $this->getOwnerRecord();
                        $records = $customer->productSpecs()->with('product')->get()->sortBy(fn ($r) => $r->product?->name);

                        return response()->streamDownload(function () use ($records) {
                            $writer = new \OpenSpout\Writer\XLSX\Writer();
                            $writer->openToFile('php://output');
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                __('Product'), __('Weight per pack'), __('Cut'),
                                __('Condition'), __('Packaging'), __('Spec Note'),
                            ]));
                            foreach ($records as $record) {
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    $record->product?->name, $record->weight_per_pack, $record->cut,
                                    CustomerProductSpec::conditionLabel($record->condition), $record->packaging, $record->note,
                                ]));
                            }
                            $writer->close();
                        }, 'spek-'.\Illuminate\Support\Str::slug($customer->name).'.xlsx');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalHeading(__('Edit spec')),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
