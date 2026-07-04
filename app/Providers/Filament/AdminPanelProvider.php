<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
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

use App\Filament\Auth\Login;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label(fn() => auth()->user()->name)
                    ->url(fn (): string => \App\Filament\Admin\Pages\MyProfile::getUrl())
                    ->icon('heroicon-o-user'),
            ])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Log Viewer')
                    ->url(fn (): string => route('log-viewer.index'))
                    ->icon('heroicon-o-document-text')
                    ->group('Settings')
                    ->sort(100),
            ])
            ->brandName(config('app.name', 'WijayaApps'))
            ->brandLogo(asset('img/light.png'))
            ->darkModeBrandLogo(asset('img/dark.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('img/light.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\CheckActiveUser::class,
                \App\Http\Middleware\CheckPasswordChange::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_START,
                fn (): string => '<style>
                    /* Compact sidebar spacing */
                    .fi-sidebar-nav-groups { gap: 0.25rem !important; }
                    .fi-sidebar-nav-group { margin-top: 0.25rem !important; }
                    .fi-sidebar-nav-group-label { margin-bottom: 0.1rem !important; padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
                    .fi-sidebar-item { margin-top: 0 !important; margin-bottom: 0 !important; }
                    .fi-sidebar-item-button { padding-top: 0.35rem !important; padding-bottom: 0.35rem !important; gap: 0.5rem !important; }
                    .fi-sidebar-item-label { font-size: 0.875rem !important; }
                    .fi-sidebar-item-icon { width: 1.25rem !important; height: 1.25rem !important; }
                </style>',
            )

            ->renderHook(
                \Filament\View\PanelsRenderHook::FOOTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.admin.footer'),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('<livewire:global-task-poller />'),
            );
    }
}
