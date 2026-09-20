<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use App\Models\Material;
use App\Services\BomUsageCalculator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Str;

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
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 5,
                                    ])
                                    ->label(__('Material'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Material'))
                                    ->options(Material::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 3,
                                    ])
                                    ->label(__('Quantity'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Quantity'))
                                    ->suffix(fn ($get) => Material::find($get('material_id'))?->unit?->name)
                                    // Tanpa komponen angka bawaan; tombol panahnya
                                    // gampang tertekan tanpa sengaja.
                                    ->extraInputAttributes(['inputmode' => 'decimal'])
                                    ->required()
                                    ->rules(['numeric', 'gt:0'])
                                    ->validationMessages(['gt' => __('Quantity must be greater than zero.')]),

                                Forms\Components\TextInput::make('note')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 4,
                                    ])
                                    ->label(__('Note'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Note'))
                                    ->maxLength(255),
                            ])
                            ->columns(['default' => 1, 'md' => 12])
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

            // Sama dengan MaterialUsageBoning -- BOM MENGUSULKAN, manusia
            // MEMUTUSKAN (issue #344). `repack_results` berbentuk sama
            // dengan `boning_items` (satu baris = satu box, `qty_pcs`
            // isinya), jadi kalkulatornya sama persis.
            Actions\Action::make('fill_from_bom')
                ->label(__('Fill from BOM'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->action(function () {
                    $result = BomUsageCalculator::calculate($this->getRecord()->results);

                    $current = $this->data['materialUsages'] ?? [];

                    $byMaterial = [];
                    foreach ($current as $key => $row) {
                        if (filled($row['material_id'] ?? null)) {
                            $byMaterial[$row['material_id']] = $key;
                        }
                    }

                    foreach ($result['usage'] as $materialId => $qty) {
                        if (isset($byMaterial[$materialId])) {
                            $current[$byMaterial[$materialId]]['qty'] = $qty;
                        } else {
                            $current[(string) Str::uuid()] = [
                                'material_id' => $materialId,
                                'qty' => $qty,
                                'note' => null,
                            ];
                        }
                    }

                    // BUKAN $this->form->fill() -- lihat catatan yang sama
                    // di MaterialUsageBoning: Repeater ini terikat
                    // ->relationship(), dan fill() memicu ulang hidrasinya
                    // dari relasi (baris yang belum tersimpan lenyap lagi).
                    $this->data['materialUsages'] = $current;

                    $lines = [];
                    foreach ($result['products'] as $product) {
                        $lines[] = "{$product['product_name']}: {$product['box']} box, {$product['pcs']} pcs";
                    }
                    foreach ($result['without_bom'] as $product) {
                        $lines[] = __('No BOM').": {$product['product_name']}";
                    }
                    foreach ($result['skipped'] as $skip) {
                        $lines[] = __('Variable quantity, filled in manually').": {$skip['product_name']} -- {$skip['material_name']}";
                    }

                    Notification::make()
                        ->title(__('Filled from BOM'))
                        ->body(implode('<br>', $lines))
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
