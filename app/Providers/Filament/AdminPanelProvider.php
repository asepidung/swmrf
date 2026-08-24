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
                    ->group(fn() => __('SYSTEM'))
                    ->visible(fn (): bool => auth()->user() && auth()->user()->hasPermission('view_activity_logs'))
                    ->sort(100),
            ])
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('REQUEST')->label(fn() => __('REQUEST')),
                \Filament\Navigation\NavigationGroup::make('PURCHASE ORDER')->label(fn() => __('PURCHASE ORDER')),
                \Filament\Navigation\NavigationGroup::make('GOODS RECEIPT')->label(fn() => __('GOODS RECEIPT')),
                \Filament\Navigation\NavigationGroup::make('CATTLE')->label(fn() => __('CATTLE')),
                \Filament\Navigation\NavigationGroup::make('PRODUCTION')->label(fn() => __('PRODUCTION')),
                \Filament\Navigation\NavigationGroup::make('WAREHOUSE')->label(fn() => __('WAREHOUSE')),
                \Filament\Navigation\NavigationGroup::make('STOCKS')->label(fn() => __('STOCKS')),
                \Filament\Navigation\NavigationGroup::make('DISTRIBUTION')->label(fn() => __('DISTRIBUTION')),
                \Filament\Navigation\NavigationGroup::make('SALES')->label(fn() => __('SALES')),
                \Filament\Navigation\NavigationGroup::make('FINANCE')->label(fn() => __('FINANCE')),
                \Filament\Navigation\NavigationGroup::make('MASTER DATA')->label(fn() => __('MASTER DATA')),
                \Filament\Navigation\NavigationGroup::make('SYSTEM')->label(fn() => __('SYSTEM')),
            ])
            ->brandName(config('app.name', 'WijayaApps'))
            ->brandLogo(asset('img/light.png'))
            ->darkModeBrandLogo(asset('img/dark.png'))
            ->brandLogoHeight('2.5rem')
            ->homeUrl(fn (): string => \App\Filament\Admin\Pages\Dashboard::getUrl())
            ->favicon(asset('img/light.png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                \App\Filament\Admin\Pages\Dashboard::class,
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
                    .fi-sidebar-nav-groups { gap: 0.15rem !important; }
                    .fi-sidebar-group { margin-top: 0.1rem !important; }
                    .fi-sidebar-group-items { gap: 0.1rem !important; }
                    .fi-sidebar-group-label { margin-bottom: 0 !important; padding-top: 0.1rem !important; padding-bottom: 0.1rem !important; text-transform: uppercase !important; font-size: 0.875rem !important; }
                    .fi-sidebar-item { margin-top: 0 !important; margin-bottom: 0 !important; }
                    .fi-sidebar-item-button { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; min-height: 2rem !important; gap: 0.4rem !important; }
                    .fi-sidebar-item-label { font-size: 0.875rem !important; }
                    .fi-sidebar-item-icon { width: 1rem !important; height: 1rem !important; }
                </style>' . '
                <script>
                    document.addEventListener("alpine:initialized", () => {
                        const sidebar = window.Alpine.store("sidebar");
                        if (sidebar) {
                            sidebar.toggleCollapsedGroup = function (group) {
                                const isCurrentlyCollapsed = this.collapsedGroups.includes(group);
                                const allGroups = Array.from(document.querySelectorAll(".fi-sidebar-group"))
                                    .map(el => el.getAttribute("data-group-label"))
                                    .filter(Boolean);
                                
                                if (isCurrentlyCollapsed) {
                                    // Open this group, close all others
                                    this.collapsedGroups = allGroups.filter(g => g !== group);
                                } else {
                                    // Close this group, meaning all are closed
                                    this.collapsedGroups = allGroups;
                                }
                            };
                        }
                    });
                </script>',
            )

            ->renderHook(
                \Filament\View\PanelsRenderHook::FOOTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.admin.footer'),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('<livewire:global-task-poller />'),
            )
            // Tombol berlangganan notifikasi perangkat, di topbar.
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn (): \Illuminate\Contracts\View\View => view('push.subscribe'),
            );
    }
}
