<?php

use App\Nucleo\Hooks\Hooks;
use App\Nucleo\Modulos\Modulo;

/**
 * Casca simples dos hooks (CLAUDE.md 5.3). Existe para o código de módulo ficar legível:
 *
 *     registrar('pedido.pago', fn ($pedido) => $this->emitirNota($pedido), Modulo::Fiscal);
 *     disparar('pedido.pago', $pedido);
 */
if (! function_exists('registrar')) {
    /**
     * Escuta um hook. Informe o módulo dono para o listener respeitar o liga/desliga.
     */
    function registrar(string $hook, callable $callback, ?Modulo $modulo = null): void
    {
        app(Hooks::class)->registrar($hook, $callback, $modulo);
    }
}

if (! function_exists('disparar')) {
    /**
     * Dispara um hook. Nunca falha por não haver ninguém escutando.
     *
     * @return array<int, mixed>
     */
    function disparar(string $hook, mixed ...$payload): array
    {
        return app(Hooks::class)->disparar($hook, ...$payload);
    }
}
