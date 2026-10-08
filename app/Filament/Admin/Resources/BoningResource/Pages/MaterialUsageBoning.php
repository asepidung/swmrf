<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Filament\Concerns\ShowsBomMaterialUsage;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class MaterialUsageBoning extends EditRecord
{
    use ShowsBomMaterialUsage;

    protected static string $resource = BoningResource::class;

    public function getTitle(): string { return __('Material Usage - Boning'); }

    protected function bomUsageLabels(): Collection
    {
        return $this->getRecord()->items;
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
                    ])->columns(2),

                ...$this->materialUsageSections(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back to List'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),

            $this->printMaterialUsageAction('boning'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
