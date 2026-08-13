<?php

namespace App\Nucleo\Tema;

use Illuminate\Support\Facades\File;

/**
 * Um tema instalado em disco (CLAUDE.md 7.3).
 *
 * Esta classe só sabe o que o tema TEM: quais templates, quais seções, quais tokens
 * padrão. Ela não renderiza nada e não conhece loja — quem junta as peças é o MotorDeTema.
 */
class Tema
{
    public function __construct(
        protected string $nome,
        protected string $caminho,
    ) {}

    public function nome(): string
    {
        return $this->nome;
    }

    public function caminho(): string
    {
        return $this->caminho;
    }

    // ---------------------------------------------------------------------- composição

    public function temLayout(): bool
    {
        return File::exists($this->caminhoDoLayout());
    }

    public function caminhoDoLayout(): string
    {
        return $this->caminho.'/'.config('tema.pastas.layout').'/'.config('tema.layout').'.liquid';
    }

    public function temTemplate(string $template): bool
    {
        return File::exists($this->caminhoDoTemplate($template));
    }

    public function caminhoDoTemplate(string $template): string
    {
        return $this->caminho.'/'.config('tema.pastas.templates').'/'.$template.'.liquid';
    }

    public function temSecao(string $secao): bool
    {
        return File::exists($this->caminhoDaSecao($secao));
    }

    public function caminhoDaSecao(string $secao): string
    {
        return $this->caminho.'/'.config('tema.pastas.sections').'/'.$secao.'.liquid';
    }

    /**
     * Templates que o tema oferece.
     *
     * @return array<int, string>
     */
    public function templates(): array
    {
        return $this->arquivosLiquidDe(config('tema.pastas.templates'));
    }

    /**
     * Seções que o lojista pode ligar/desligar e reordenar.
     *
     * @return array<int, string>
     */
    public function secoesDisponiveis(): array
    {
        return $this->arquivosLiquidDe(config('tema.pastas.sections'));
    }

    // -------------------------------------------------------------------------- tokens

    /**
     * Tokens padrão do tema (cor, fonte, logo, raio de borda) vindos de
     * `config/settings.json`. É só o padrão: o que o lojista mexeu vive no banco.
     *
     * @return array<string, mixed>
     */
    public function tokensPadrao(): array
    {
        return $this->json('settings');
    }

    /**
     * Ordem padrão das seções por template, de `config/secoes.json`.
     *
     * O tema precisa declarar isso, senão uma instalação nova abriria a home vazia. O
     * lojista sobrescreve pelo painel.
     *
     * @return array<string, array<int, string>>
     */
    public function secoesPadrao(): array
    {
        return $this->json('secoes');
    }

    /**
     * @return array<int, string>
     */
    protected function arquivosLiquidDe(string $pasta): array
    {
        $caminho = $this->caminho.'/'.$pasta;

        if (! File::isDirectory($caminho)) {
            return [];
        }

        return collect(File::files($caminho))
            ->filter(fn ($arquivo) => $arquivo->getExtension() === 'liquid')
            ->map(fn ($arquivo) => $arquivo->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(string $nome): array
    {
        $caminho = $this->caminho.'/'.config('tema.pastas.config').'/'.$nome.'.json';

        if (! File::exists($caminho)) {
            return [];
        }

        return json_decode(File::get($caminho), associative: true, flags: JSON_THROW_ON_ERROR) ?: [];
    }
}
