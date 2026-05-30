<?php

namespace App\Filament\Widgets;

use App\Models\Quote;
use Filament\Forms\Components\RichEditor;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuoteWidget extends Widget implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $view = 'filament.widgets.quote-widget';
    protected int | string | array $columnSpan = 'full';
    public ?Quote $quote = null;

    public function mount()
    {
        $this->loadRandomQuote();
    }

    public function loadRandomQuote()
    {
        $this->quote = Quote::with('user')->inRandomOrder()->first();
    }

    public function addQuoteAction(): Action
    {
        return Action::make('addQuote')
            ->label('Add Your Quote')
            ->icon('heroicon-m-plus-circle')
            ->form([
                // Menggunakan RichEditor untuk input teks + emoji
                RichEditor::make('quote_text')
                    ->label('Tulis Kata-katamu')
                    ->placeholder('Untuk menambahkan emoji, Windows + . ')
                    ->required()
                    // Kita matikan toolbar yang tidak perlu biar fokus ke teks & emoji
                    ->toolbarButtons(['bold', 'italic', 'strike', 'link', 'redo', 'undo']),
            ])
            ->action(function (array $data) {
                Quote::create([
                    'user_id' => Auth::id(),
                    'quote_text' => $data['quote_text'],
                ]);

                Notification::make()->title('Quote berhasil ditambahkan!')->success()->send();
                $this->loadRandomQuote();
            })
            ->modalWidth('lg');
    }

    public function editQuoteAction(): Action
    {
        return Action::make('editQuote')
            ->label('Edit')
            ->icon('heroicon-m-pencil-square')
            ->color('warning')
            ->form([
                 // Menggunakan RichEditor juga saat edit
                RichEditor::make('quote_text')
                    ->label('Update Kata-katamu')
                    ->default($this->quote?->quote_text)
                    ->required()
                    ->toolbarButtons(['bold', 'italic', 'strike', 'link', 'redo', 'undo']),
            ])
            ->action(function (array $data) {
                $this->quote->update([
                    'quote_text' => $data['quote_text'],
                ]);

                Notification::make()->title('Quote berhasil diperbarui!')->success()->send();
                $this->loadRandomQuote();
            })
            ->modalWidth('lg');
    }
}