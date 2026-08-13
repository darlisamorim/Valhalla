<?php

namespace App\Nucleo\Tema\Drops;

use App\Models\Loja;
use Keepsuit\Liquid\Attributes\Cache;
use Keepsuit\Liquid\Attributes\Hidden;
use Keepsuit\Liquid\Drop;

/**
 * O que o tema vê da loja — e nada além (CLAUDE.md 7.4, regra de ouro 5).
 *
 * O controller **nunca** entrega o Model Eloquent cru ao tema: entregar `Loja` daria ao
 * tema acesso a `configuracoes`, a relações, a `save()`, ao banco. O drop expõe campo por
 * campo, de propósito.
 *
 * Como funciona o whitelist: o Liquid só alcança propriedades e métodos **públicos sem
 * argumento** desta classe. O model fica guardado numa propriedade `#[Hidden]`, invisível
 * para o template.
 */
class LojaDrop extends Drop
{
    public function __construct(
        #[Hidden]
        protected Loja $loja,
    ) {}

    #[Cache]
    public function nome(): string
    {
        return $this->loja->nome;
    }

    #[Cache]
    public function slug(): string
    {
        return $this->loja->slug;
    }

    #[Cache]
    public function url(): string
    {
        return (string) config('app.url');
    }
}
