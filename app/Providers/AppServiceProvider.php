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

        \Illuminate\Support\Facades\Gate::define('viewLogViewer', function (?\App\Models\User $user) {
            // Allow any authenticated user (or limit to super_admin if desired)
            return $user !== null;
        });

        if (config('app.env') !== 'local' || (request()->getHost() !== 'localhost' && request()->getHost() !== '127.0.0.1')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // \Filament\Support\Facades\FilamentAsset::register([
        //     \Filament\Support\Assets\Js::make('auto-logout', asset('js/auto-logout.js')),
        // ]);

        \App\Models\GoodsReceiptMaterialItem::observe(\App\Observers\GoodsReceiptMaterialItemObserver::class);

        \Illuminate\Support\Facades\Gate::policy(\App\Models\BeefStock::class, \App\Policies\BeefStockPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\BeefStockMovement::class, \App\Policies\BeefStockMovementPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\DeliveryOrderReceipt::class, \App\Policies\DeliveryOrderReceiptPolicy::class);
    }
}
