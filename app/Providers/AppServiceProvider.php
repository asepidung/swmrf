<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'id'])
                ->visible(outsidePanels: true);
        });

        \Filament\Support\Facades\FilamentAsset::register([
            \Filament\Support\Assets\Js::make('auto-logout', asset('js/auto-logout.js')),
        ]);
    }
}
