<?php

namespace App\Filament\Admin\Resources\MaterialUsageResource\Pages;

use App\Support\DocumentNumber;

use App\Filament\Admin\Resources\MaterialUsageResource;
use App\Models\MaterialAdjustment;
use App\Models\Material;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;

class CreateManualUsage extends CreateRecord
{
    protected static string $resource = MaterialUsageResource::class;

    protected ?string $heading = 'Create Manual Usage';

    public function getModel(): string
    {
        return MaterialAdjustment::class;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Adjustment Info'))
                    ->schema([
                        Forms\Components\Hidden::make('doc_no')
                            ->default(function () {
                                $currentYear = date('Y');
                                // Pratinjau nomor berikutnya. Memakai jalur yang SAMA dengan
                                // penyimpanannya, supaya yang ditampilkan tidak pernah
                                // berbeda dari yang akhirnya tersimpan.
                                return DocumentNumber::next(
                                    query: MaterialAdjustment::withTrashed(),
                                    column: 'doc_no',
                                    prefix: 'MA#'.date('y'),
                                    padding: 3,
                                );
                            }),

                        Forms\Components\DatePicker::make('adjustment_date')
                            ->label(__('Date'))
                            ->required()
                            ->default(now())
                            ->autofocus()
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note / description'))
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('status')
                            ->default('OPEN'),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::id()),
                    ])->columns(2),

                Forms\Components\Section::make(__('Material Usage / Expense'))
                    ->schema([
                        Forms\Components\Repeater::make('materialUsages')
                            ->relationship('materialUsages')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label(__('Material'))
                                    ->options(Material::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->label(__('Qty (Minus)'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01),

                                Forms\Components\TextInput::make('note')
                                    ->label(__('Note'))
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('Add Material Usage'))
                            ->defaultItems(1)
                    ])
            ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
