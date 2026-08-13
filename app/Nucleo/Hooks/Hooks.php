<?php

namespace App\Nucleo\Hooks;

use App\Nucleo\Modulos\Modulo;
use App\Nucleo\Modulos\RegistroDeModulos;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Hooks: a única forma de um módulo conversar com o mundo (regra de ouro 2).
 *
 * Por baixo são **eventos nativos do Laravel** — nada de barramento paralelo, para não
 * perder fila, teste e ferramenta do framework. Por cima, uma casca simples estilo
 * WordPress: `registrar('pedido.pago', ...)` e `disparar('pedido.pago', $pedido)`.
 *
 * O detalhe que faz a modularidade funcionar de verdade: quando um listener é registrado
 * **em nome de um módulo**, ele só roda se aquele módulo estiver ligado na loja atual. A
 * checagem acontece na hora do disparo, não na hora do registro — assim o lojista pode
 * desligar um módulo no painel e o efeito é imediato, sem rebootar nada.
 *
 * Desligar módulo, portanto, não remove o evento: o evento continua sendo disparado, só
 * não sobra ninguém escutando. A loja não quebra (CLAUDE.md 5.3).
 */
class Hooks
{
    public function __construct(
        protected Dispatcher $eventos,
        protected RegistroDeModulos $modulos,
    ) {}

    /**
     * Escuta um hook.
     *
     * @param  Modulo|null  $modulo  Dono do listener. Se informado, o listener só roda
     *                               enquanto o módulo estiver ligado na loja atual.
     *                               null = núcleo, sempre roda.
     */
    public function registrar(string $hook, callable $callback, ?Modulo $modulo = null): void
    {
        $this->eventos->listen($hook, function (...$payload) use ($callback, $modulo) {
            if ($modulo !== null && ! $this->modulos->estaAtivo($modulo)) {
                return null;
            }

            return $callback(...$payload);
        });
    }

    /**
     * Dispara um hook. O disparo nunca depende de haver alguém escutando.
     *
     * @return array<int, mixed> o que cada listener devolveu
     */
    public function disparar(string $hook, mixed ...$payload): array
    {
        return $this->eventos->dispatch($hook, $payload) ?? [];
    }

    /**
     * Alguém está escutando este hook? (Diagnóstico — não usar para decidir regra.)
     */
    public function temListeners(string $hook): bool
    {
        return $this->eventos->hasListeners($hook);
    }
}
