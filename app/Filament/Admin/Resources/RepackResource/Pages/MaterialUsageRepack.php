<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use App\Models\Material;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;

class MaterialUsageRepack extends EditRecord
{
    protected static string $resource = RepackResource::class;

    public function getTitle(): string { return __('Material Usage - Repack'); }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->kunci, 403, 'Data has been locked.');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/admin/repacks') => __('Repacks'),
            __('Material Usage'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Document Info'))
                    ->schema([
                        Forms\Components\TextInput::make('doc_no')
                            ->label(__('Repack Document'))
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('repack_date')
                            ->label(__('Usage Date (Repack Date)'))
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('process')
                            ->label(__('Process'))
                            ->default('Repack')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),

                Forms\Components\Section::make(__('Material Usages'))
                    ->schema([
                        Forms\Components\Repeater::make('materialUsages')
                            ->relationship('materialUsages')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label(__('Material'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Material'))
                                    ->options(Material::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('unit', Material::find($state)?->unit?->name)),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->label(__('Quantity'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Quantity'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01),

                                Forms\Components\TextInput::make('unit')
                                    ->label(__('Unit'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Unit'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($get) => Material::find($get('material_id'))?->unit?->name),

                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Note'))
                                    ->maxLength(255),
                            ])
                            ->columns(4)
                            ->addActionLabel(__('Add Material'))
                            ->defaultItems(0)
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
