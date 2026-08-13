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
 * Painel da LOJA — contexto do lojista (dono da loja).
 *
 * É aqui que o lojista liga/desliga módulos, escolhe tema, mexe nos tokens e opera o
 * catálogo. Os recursos de cada módulo aparecem neste painel.
 *
 * É o painel padrão (`->default()`) porque é o que roda no dia a dia.
 *
 * NÃO misturar com o painel mestre (MasterPanelProvider) nem com o auth do cliente do
 * storefront (Breeze) — CLAUDE.md 5.9.
 */
class LojaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('loja')
            ->path('painel')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Loja/Resources'), for: 'App\Filament\Loja\Resources')
            ->discoverPages(in: app_path('Filament/Loja/Pages'), for: 'App\Filament\Loja\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Loja/Widgets'), for: 'App\Filament\Loja\Widgets')
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
