<?php

namespace App\Nucleo\Tema;

use RuntimeException;

class TemaNaoEncontrado extends RuntimeException
{
    public static function tema(string $nome, string $raiz): self
    {
        return new self("Tema [{$nome}] não encontrado em [{$raiz}].");
    }

    public static function layout(Tema $tema): self
    {
        return new self(
            "O tema [{$tema->nome()}] não tem layout em [{$tema->caminhoDoLayout()}]. "
            .'Todo tema precisa de um layout: é a casca que envolve os templates.'
        );
    }

    public static function template(Tema $tema, string $template): self
    {
        return new self(
            "O tema [{$tema->nome()}] não tem o template [{$template}] "
            ."(esperado em [{$tema->caminhoDoTemplate($template)}])."
        );
    }
}
