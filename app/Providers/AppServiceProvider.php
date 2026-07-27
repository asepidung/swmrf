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
            return $user ? $user->hasPermission('view_activity_logs') : false;
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
        \Illuminate\Support\Facades\Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);

        // Inject PWA Manifest and Service Worker
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::HEAD_END,
            fn (): string => '<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#000080">
<link rel="apple-touch-icon" href="/img/pwalogo.png">'
        );

        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn (): string => "<script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function() {
                        navigator.serviceWorker.register('/sw.js').then(function(registration) {
                            console.log('ServiceWorker registration successful with scope: ', registration.scope);
                        }, function(err) {
                            console.log('ServiceWorker registration failed: ', err);
                        });
                    });
                }
            </script>"
        );
    }
}
