<?php

namespace App\Nucleo\Loja;

use App\Models\Loja;

/**
 * Contrato único do núcleo que responde "qual é a loja atual" (CLAUDE.md 5.2).
 *
 * Existem dois drivers:
 *
 *  - SingleStoreResolver  — embutido, padrão de fábrica. Devolve sempre a única loja.
 *  - TenancyResolver      — pacote PRIVADO, só na edição SaaS. Resolve pelo domínio via
 *                           stancl/tenancy. Não faz parte desta base de código; o
 *                           comprador da Loja Única não recebe multi-loja.
 *
 * Regra de ouro 3: ninguém acessa a tenancy direto. Todo código pergunta `Loja::atual()`,
 * e nunca sabe qual driver está ativo.
 */
interface ResolvedorDeLoja
{
    /**
     * A loja atual, ou null quando ainda não há loja resolvida (instalação nova,
     * painel mestre fora do contexto de uma loja).
     */
    public function atual(): ?Loja;

    /**
     * Passa a tratar esta loja como a atual pelo resto da requisição.
     *
     * No SaaS é o que acontece quando o dono entra numa loja pelo painel mestre.
     * No modo single serve para teste e para o instalador.
     */
    public function definir(Loja $loja): void;

    /**
     * Esquece a loja resolvida (força a próxima resolução a acontecer de novo).
     */
    public function esquecer(): void;
}
