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

    public function mount(int | string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->kunci, 403, 'Data has been locked.');
    }

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

                        Forms\Components\TextInput::make('process')
                            ->label(__('Process'))
                            ->default('Boning')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),

                $this->bomUsageSection(),

                $this->drylogSection(),
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
