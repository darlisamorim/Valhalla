<?php

namespace App\Nucleo\Tema;

use App\Models\Loja;

/**
 * A customização do lojista: tokens e arranjo de seções (CLAUDE.md 7.2, 7.5).
 *
 * Mora no banco (`lojas.configuracoes`), não em disco — o lojista nunca recebe o
 * código-fonte do tema, ele monta e testa tudo online. O tema em disco só entrega os
 * **padrões**; o que o lojista mexeu sobrescreve por cima, campo a campo.
 *
 * Formato guardado em `lojas.configuracoes`:
 *
 *     {
 *       "tema": "default",
 *       "tokens": { "cor_primaria": "#111" },
 *       "secoes": {
 *         "index": [ {"tipo": "banner", "ativa": true, "configuracoes": {...}} ]
 *       }
 *     }
 */
class ConfiguracaoDeTema
{
    public function __construct(
        protected RepositorioDeTemas $temas,
    ) {}

    /**
     * Tokens efetivos: o padrão do tema com o que o lojista mexeu por cima.
     *
     * @return array<string, mixed>
     */
    public function tokens(?Loja $loja = null): array
    {
        $loja ??= Loja::atualOuNula();
        $tema = $this->temas->daLoja($loja);

        return array_replace_recursive(
            $tema->tokensPadrao(),
            (array) ($loja?->configuracoes['tokens'] ?? []),
        );
    }

    /**
     * As seções daquele template, na ordem configurada, já sem as desligadas.
     *
     * @return array<int, Secao>
     */
    public function secoesAtivas(string $template, ?Loja $loja = null): array
    {
        return array_values(array_filter(
            $this->secoes($template, $loja),
            fn (Secao $secao) => $secao->ativa,
        ));
    }

    /**
     * As seções daquele template, na ordem configurada, incluindo as desligadas (é o que
     * o painel do lojista precisa mostrar).
     *
     * @return array<int, Secao>
     */
    public function secoes(string $template, ?Loja $loja = null): array
    {
        $loja ??= Loja::atualOuNula();
        $tema = $this->temas->daLoja($loja);

        $doLojista = $loja?->configuracoes['secoes'][$template] ?? null;

        $bruto = $doLojista ?? ($tema->secoesPadrao()[$template] ?? []);

        return collect($bruto)
            ->map(fn ($item) => Secao::deArray($item))
            // Seção que não existe mais no tema é ignorada em silêncio: trocar de tema
            // não pode derrubar a loja.
            ->filter(fn (Secao $secao) => $secao->tipo !== '' && $tema->temSecao($secao->tipo))
            ->values()
            ->all();
    }

    /**
     * Salva o arranjo de seções de um template (o que o painel do lojista chama ao
     * reordenar ou ligar/desligar bloco).
     *
     * @param  array<int, Secao>  $secoes
     */
    public function salvarSecoes(string $template, array $secoes, ?Loja $loja = null): void
    {
        $loja ??= Loja::atual();

        $configuracoes = $loja->configuracoes ?? [];
        $configuracoes['secoes'][$template] = array_map(fn (Secao $s) => $s->paraArray(), $secoes);

        $loja->configuracoes = $configuracoes;
        $loja->save();
    }

    /**
     * Salva os tokens que o lojista mexeu (cor, fonte, logo, raio de borda).
     *
     * @param  array<string, mixed>  $tokens
     */
    public function salvarTokens(array $tokens, ?Loja $loja = null): void
    {
        $loja ??= Loja::atual();

        $configuracoes = $loja->configuracoes ?? [];
        $configuracoes['tokens'] = array_replace_recursive((array) ($configuracoes['tokens'] ?? []), $tokens);

        $loja->configuracoes = $configuracoes;
        $loja->save();
    }

    public function escolherTema(string $nome, ?Loja $loja = null): void
    {
        $loja ??= Loja::atual();

        // Estoura se o tema não existe, antes de gravar.
        $this->temas->encontrar($nome);

        $configuracoes = $loja->configuracoes ?? [];
        $configuracoes['tema'] = $nome;

        $loja->configuracoes = $configuracoes;
        $loja->save();
    }
}
