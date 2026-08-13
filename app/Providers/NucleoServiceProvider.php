<?php

namespace App\Providers;

use App\Nucleo\Hooks\Hooks;
use App\Nucleo\Loja\ResolvedorDeLoja;
use App\Nucleo\Loja\SingleStoreResolver;
use App\Nucleo\Modulos\RegistroDeModulos;
use App\Nucleo\Tema\ConfiguracaoDeTema;
use App\Nucleo\Tema\MotorDeTema;
use App\Nucleo\Tema\RepositorioDeTemas;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Amarra o núcleo no container.
 *
 * É o único lugar do sistema que sabe qual driver de resolução de loja está ativo. Trocar
 * loja única por SaaS é trocar uma flag (`STORE_MODE`) — nenhum módulo, controller ou tema
 * muda de linha (CLAUDE.md 5.2, 6).
 */
class NucleoServiceProvider extends ServiceProvider
{
    /**
     * Driver da edição SaaS. Vive num pacote PRIVADO: o comprador da Loja Única não recebe
     * este código, e por isso a referência aqui é por nome, resolvida só se a classe existir.
     */
    protected const RESOLVEDOR_MULTI = 'App\Nucleo\Loja\TenancyResolver';

    public function register(): void
    {
        $this->app->singleton(ResolvedorDeLoja::class, function () {
            $modo = config('app.store_mode', 'single');

            return match ($modo) {
                'single' => new SingleStoreResolver,
                'multi' => $this->resolvedorMulti(),
                default => throw new RuntimeException(
                    "STORE_MODE inválido: [{$modo}]. Use 'single' (loja única) ou 'multi' (SaaS)."
                ),
            };
        });

        $this->app->singleton(RegistroDeModulos::class);
        $this->app->singleton(Hooks::class);

        // Motor de tema (CLAUDE.md 7). Singletons porque memoizam descoberta de tema em
        // disco — isso é lido em toda renderização de página.
        $this->app->singleton(RepositorioDeTemas::class);
        $this->app->singleton(ConfiguracaoDeTema::class);
        $this->app->singleton(MotorDeTema::class);
    }

    protected function resolvedorMulti(): ResolvedorDeLoja
    {
        if (! class_exists(self::RESOLVEDOR_MULTI)) {
            throw new RuntimeException(
                'STORE_MODE=multi exige o pacote de multi-tenancy da edição SaaS, que fornece '
                .self::RESOLVEDOR_MULTI.'. Esta build é Loja Única: use STORE_MODE=single.'
            );
        }

        return $this->app->make(self::RESOLVEDOR_MULTI);
    }
}
