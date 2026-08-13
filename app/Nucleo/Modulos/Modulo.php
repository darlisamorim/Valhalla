<?php

namespace App\Nucleo\Modulos;

/**
 * Lista **fechada e nominal** de módulos (CLAUDE.md 4).
 *
 * Nunca existe categoria vaga tipo "outros": módulo novo entra aqui com nome próprio.
 * É um enum justamente para isso — string solta convida a inventar módulo fantasma, e
 * como os planos comerciais são combinações de módulos ligados (CLAUDE.md 15), um nome
 * errado é um plano errado.
 */
enum Modulo: string
{
    /**
     * Produtos, coleções, pedidos. É empacotado como módulo (etapa 3), mas não compõe
     * plano: sem catálogo não sobra loja, então não é desligável.
     */
    case Ecommerce = 'ecommerce';

    case Pagamento = 'pagamento';
    case Frete = 'frete';
    case Moeda = 'moeda';
    case Fiscal = 'fiscal';
    case Estoque = 'estoque';
    case Catalogo = 'catalogo';
    case Busca = 'busca';
    case Cupom = 'cupom';
    case Chat = 'chat';
    case Idioma = 'idioma';

    /**
     * Módulo essencial não liga/desliga: está sempre presente e não entra em plano.
     *
     * Carrinho, checkout, cache, SEO, performance e mídia **não** aparecem nesta lista —
     * são núcleo, não módulo (CLAUDE.md 4).
     */
    public function essencial(): bool
    {
        return $this === self::Ecommerce;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Ecommerce => 'Ecommerce',
            self::Pagamento => 'Pagamento',
            self::Frete => 'Frete',
            self::Moeda => 'Moeda',
            self::Fiscal => 'Fiscal / ERP',
            self::Estoque => 'Estoque e Variações',
            self::Catalogo => 'Catálogo multi-loja',
            self::Busca => 'Busca',
            self::Cupom => 'Cupom / Desconto',
            self::Chat => 'Chat online',
            self::Idioma => 'Multi-idioma',
        };
    }

    /**
     * Os módulos que o lojista pode ligar/desligar pelo painel — ou seja, os que formam
     * os planos comerciais.
     *
     * @return array<int, self>
     */
    public static function comercializaveis(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $modulo) => ! $modulo->essencial(),
        ));
    }
}
