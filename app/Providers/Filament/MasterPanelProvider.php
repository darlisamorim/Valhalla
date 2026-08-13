<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Painel MESTRE — contexto do super-admin (nós, a plataforma).
 *
 * Só existe de fato na edição SaaS, onde lista as lojas do dono. Na edição loja única
 * continua acessível para nós, mas não é o painel do cliente.
 *
 * NÃO misturar com o painel do lojista (LojaPanelProvider) nem com o auth do cliente
 * do storefront (Breeze) — CLAUDE.md 5.9.
 */
class MasterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('master')
            ->path('master')
            ->login()
            ->colors([
                'primary' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Master/Resources'), for: 'App\Filament\Master\Resources')
            ->discoverPages(in: app_path('Filament/Master/Pages'), for: 'App\Filament\Master\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Master/Widgets'), for: 'App\Filament\Master\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
