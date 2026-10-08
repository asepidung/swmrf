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

    public function mount(int | string $record): void
    {
        parent::mount($record);
        abort_if($this->getRecord()->kunci, 403, 'Data has been locked.');
    }

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

                        Forms\Components\TextInput::make('process')
                            ->label(__('Process'))
                            ->default('Repack')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),

                $this->bomUsageSection(),

                $this->drylogSection(),

                $this->wasteSection(),
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
