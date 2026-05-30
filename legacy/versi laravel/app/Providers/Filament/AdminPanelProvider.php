<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ForceChangePassword;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->favicon(asset('img/favicon.ico'))

            /* Konfigurasi logo aplikasi pada panel admin */
            ->brandLogo(asset('img/LOGO-Y.png'))
            ->darkModeBrandLogo(asset('img/LOGO-G.png'))
            ->brandLogoHeight('2rem')
            ->homeUrl('/admin')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
            ])

            /* Mengaktifkan fitur halaman profil pengguna bawaan */
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)

            ->pages([
                Dashboard::class,
                ForceChangePassword::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
           ->widgets([
                \App\Filament\Widgets\AccountWidget::class,
                \App\Filament\Widgets\QuoteWidget::class,
            ])

            ->renderHook(
                'panels::head.end',
                fn(): string => \Illuminate\Support\Facades\Blade::render('<style>
                    .fi-sidebar-nav-groups { gap: 0.1rem !important; }
                    .fi-sidebar-group-label { margin-top: 0.2rem !important; margin-bottom: 0.1rem !important; }
                    .fi-sidebar-group-items { gap: 0.1rem !important; }
                    .fi-sidebar-item-button { padding-top: 0.2rem !important; padding-bottom: 0.2rem !important; }
                </style>'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                \Filament\Http\Middleware\AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\ForcePasswordChange::class,
            ]);
    }
}
