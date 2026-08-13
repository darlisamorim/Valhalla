<?php

namespace App\Nucleo\Loja;

use RuntimeException;

/**
 * Nenhuma loja resolvida quando o código exigia uma.
 *
 * É de propósito que isso estoure alto em vez de devolver null silenciosamente: gravar
 * dado sem loja, ou ler dado de "loja nenhuma", é vazamento de isolamento. Quando o
 * chamador sabe lidar com a ausência, ele usa `Loja::atualOuNula()`.
 */
class NenhumaLojaConfigurada extends RuntimeException
{
    public static function paraModoSingle(): self
    {
        return new self(
            'Nenhuma loja configurada. No modo loja única é preciso existir exatamente uma '
            .'loja no banco — rode `php artisan db:seed --class=LojaPadraoSeeder` ou crie a '
            .'loja pelo instalador.'
        );
    }
}
