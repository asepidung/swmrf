<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use App\Filament\Concerns\ShowsBomMaterialUsage;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class MaterialUsageRepack extends EditRecord
{
    use ShowsBomMaterialUsage;

    protected static string $resource = RepackResource::class;

    public function getTitle(): string { return __('Material Usage - Repack'); }

    protected function bomUsageLabels(): Collection
    {
        return $this->getRecord()->results;
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
                    ])->columns(2),

                ...$this->materialUsageSections(),

                ...$this->valuationSections(),
            ]);
    }

    /**
     * Blok valuasi (HPP tahap 2, issue #522): hanya untuk repack yang sudah
     * dikunci -- biaya bahan, susut, harga acuan terkunci, dan nilai tiap hasil.
     *
     * @return array<int, Forms\Components\Component>
     */
    protected function valuationSections(): array
    {
        if (! $this->isLockedRecord()) {
            return [];
        }

        return [
            Forms\Components\Section::make(__('Repack Valuation'))
                ->description(__('Cost of the input goods, the shrinkage loss, and the value carried by each result.'))
                ->schema([
                    Forms\Components\View::make('filament.partials.repack-valuation')
                        ->viewData(fn (): array => $this->valuationData()),
                ]),
        ];
    }

    /** @return array{valuation: ?\App\Models\RepackValuation, lines: array<int, array<string, mixed>>} */
    protected function valuationData(): array
    {
        $repack = $this->getRecord()->fresh();
        $valuation = $repack->valuation;
        $lines = $repack->results()->with('product')->get()
            ->groupBy('product_id')
            ->map(function ($group): array {
                $kg = (float) $group->sum('weight');
                $values = $group->map(fn ($result) => \App\Support\BarcodeCosts::valueOf($result->barcode, (float) $result->weight));
                $valued = $values->every(fn ($value) => $value !== null);

                return [
                    'product' => $group->first()->product?->name ?? '-',
                    'kg' => $kg,
                    'cost_per_kg' => $valued && $kg > 0 ? $values->sum() / $kg : null,
                    'value' => $valued ? $values->sum() : null,
                ];
            })
            ->values()
            ->all();

        return ['valuation' => $valuation, 'lines' => $lines];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),

            $this->printMaterialUsageAction('repack'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
