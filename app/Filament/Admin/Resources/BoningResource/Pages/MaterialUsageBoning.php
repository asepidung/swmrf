<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Models\Material;
use App\Services\BomUsageCalculator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Str;

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
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 5,
                                    ])
                                    ->label(__('Material'))
                                    ->options(Material::pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live(),
                                
                                Forms\Components\TextInput::make('qty')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 3,
                                    ])
                                    ->label(__('Quantity'))
                                    ->suffix(fn ($get) => Material::find($get('material_id'))?->unit?->name)
                                    // Tanpa komponen angka bawaan (tombol
                                    // panahnya gampang tertekan), dan dengan
                                    // batas bawah -- sebelumnya qty nol atau
                                    // negatif lolos begitu saja.
                                    ->extraInputAttributes(['inputmode' => 'decimal'])
                                    ->rules(['numeric', 'gt:0'])
                                    ->validationMessages([
                                        'gt' => __('Quantity must be greater than zero.'),
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('note')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 4,
                                    ])
                                    ->label(__('Note'))
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

            // BOM MENGUSULKAN, manusia MEMUTUSKAN (issue #344 -- cerita
            // Owner 7 September 2026: potongan stok tetap manual per
            // box/ikat, karton per pcs tidak mungkin dihitung di lapangan).
            // Tombol ini cuma MENGISI Repeater-nya, tidak menyimpan apa
            // pun -- baris manual lain (materialnya beda) tidak disentuh.
            Actions\Action::make('fill_from_bom')
                ->label(__('Fill from BOM'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->action(function () {
                    $result = BomUsageCalculator::calculate($this->getRecord()->items);

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

                    // BUKAN $this->form->fill() -- Repeater ini terikat
                    // ->relationship('materialUsages'), dan fill() memicu
                    // ulang hidrasinya DARI RELASI (baris yang belum
                    // tersimpan lenyap lagi). Menulis langsung ke $this->data
                    // cukup untuk mengubah apa yang tampil di layar; yang
                    // benar-benar tersimpan tetap lewat tombol Save.
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
