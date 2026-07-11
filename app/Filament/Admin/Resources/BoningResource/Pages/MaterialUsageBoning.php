<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Models\Material;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;

class MaterialUsageBoning extends EditRecord
{
    protected static string $resource = BoningResource::class;

    public function getTitle(): string { return __('Material Usage - Boning'); }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->kunci, 403, 'Data has been locked.');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/admin/bonings') => __('Bonings'),
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
                            ->label(__('Boning Document'))
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('boning_date')
                            ->label(__('Usage Date (Boning Date)'))
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('process')
                            ->label(__('Process'))
                            ->default('Boning')
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
                                    ->options(Material::pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('unit', Material::find($state)?->unit?->name)),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->label(__('Quantity'))
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('unit')
                                    ->label(__('Unit'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($get) => Material::find($get('material_id'))?->unit?->name),

                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note'))
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
