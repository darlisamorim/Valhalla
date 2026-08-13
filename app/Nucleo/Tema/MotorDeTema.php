<?php

namespace App\Nucleo\Tema;

use App\Models\Loja;
use App\Nucleo\Tema\Drops\LojaDrop;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;

/**
 * O motor de tema (CLAUDE.md 7.6).
 *
 *     requisição → Loja::atual() → controller busca dados (escopo da loja) →
 *     monta os drops → motor junta layout + template + sections → HTML
 *
 * Duas regras que este arquivo existe para garantir:
 *
 *  - **O tema nunca toca no banco** (regra de ouro 5). O motor só entrega o que recebeu:
 *    drops e valores já prontos. Não há Eloquent no contexto do Liquid.
 *  - **O tema roda em sandbox** (7.1). Liquid não executa PHP, não abre arquivo, não lê
 *    env. Nada aqui abre essa porta.
 *
 * Composição: as seções ativas do template são renderizadas na ordem que o lojista
 * configurou e entregues ao template em `content_for_sections`; o template renderizado é
 * entregue ao layout em `content_for_layout`.
 */
class MotorDeTema
{
    public function __construct(
        protected RepositorioDeTemas $temas,
        protected ConfiguracaoDeTema $configuracao,
        protected ViewFactory $views,
    ) {}

    /**
     * Renderiza uma página do storefront.
     *
     * @param  string  $template  nome do template do tema (index, product, collection, cart)
     * @param  array<string, mixed>  $dados  drops e valores que o controller preparou
     */
    public function renderizar(string $template, array $dados = [], ?Loja $loja = null): string
    {
        $loja ??= Loja::atual();
        $tema = $this->temas->daLoja($loja);

        if (! $tema->temLayout()) {
            throw TemaNaoEncontrado::layout($tema);
        }

        if (! $tema->temTemplate($template)) {
            throw TemaNaoEncontrado::template($tema, $template);
        }

        return $this->comOTemaAtivo($tema, function () use ($template, $dados, $loja): string {
            $contexto = $this->contextoBase($template, $loja) + $dados;

            $secoes = $this->renderizarSecoes($template, $contexto, $loja);

            $conteudo = $this->renderizarArquivo(
                config('tema.pastas.templates').'.'.$template,
                $contexto + ['content_for_sections' => $secoes],
            );

            return $this->renderizarArquivo(
                config('tema.pastas.layout').'.'.config('tema.layout'),
                $contexto + ['content_for_layout' => $conteudo],
            );
        });
    }

    /**
     * Renderiza uma seção sozinha — é o que as interações sem reload usam para trocar só
     * um trecho da tela (CLAUDE.md 11).
     *
     * @param  array<string, mixed>  $dados
     */
    public function renderizarSecao(string $secao, array $dados = [], ?Loja $loja = null): string
    {
        $loja ??= Loja::atual();
        $tema = $this->temas->daLoja($loja);

        if (! $tema->temSecao($secao)) {
            return '';
        }

        return $this->comOTemaAtivo($tema, fn (): string => $this->renderizarArquivo(
            config('tema.pastas.sections').'.'.$secao,
            $this->contextoBase(null, $loja) + $dados,
        ));
    }

    /**
     * O que TODA página vê, sem o controller precisar passar (CLAUDE.md 7.4).
     *
     * @return array<string, mixed>
     */
    protected function contextoBase(?string $template, Loja $loja): array
    {
        return [
            'loja' => new LojaDrop($loja),
            'settings' => $this->configuracao->tokens($loja),
            'template' => $template,
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    protected function renderizarSecoes(string $template, array $contexto, Loja $loja): string
    {
        $html = '';

        foreach ($this->configuracao->secoesAtivas($template, $loja) as $secao) {
            $html .= $this->renderizarArquivo(
                config('tema.pastas.sections').'.'.$secao->tipo,
                $contexto + ['secao' => $secao->configuracoes],
            );
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected function renderizarArquivo(string $view, array $dados): string
    {
        return $this->views->make($view, $dados)->render();
    }

    /**
     * Roda o callback com o diretório do tema na frente das view paths, e devolve as
     * paths originais no fim — inclusive se der exceção.
     *
     * É assim que `{% render 'sections.card' %}` dentro do tema resolve, e é assim que
     * dois temas diferentes (SaaS, fila) não vazam um no outro dentro do mesmo processo.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function comOTemaAtivo(Tema $tema, callable $callback): mixed
    {
        $finder = $this->views->getFinder();

        if (! $finder instanceof FileViewFinder) {
            return $callback();
        }

        $original = $finder->getPaths();

        $finder->setPaths([$tema->caminho(), ...$original]);
        $finder->flush();

        try {
            return $callback();
        } finally {
            $finder->setPaths($original);
            $finder->flush();
        }
    }
}
