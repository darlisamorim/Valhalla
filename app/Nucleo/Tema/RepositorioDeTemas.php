<?php

namespace App\Nucleo\Tema;

use App\Models\Loja;
use Illuminate\Support\Facades\File;

/**
 * Acha os temas instalados e diz qual é o tema da loja.
 */
class RepositorioDeTemas
{
    /** @var array<string, Tema> */
    protected array $memoria = [];

    public function raiz(): string
    {
        return (string) config('tema.raiz');
    }

    /**
     * @return array<int, Tema>
     */
    public function todos(): array
    {
        if (! File::isDirectory($this->raiz())) {
            return [];
        }

        return collect(File::directories($this->raiz()))
            ->map(fn (string $caminho) => $this->encontrar(basename($caminho)))
            ->all();
    }

    public function existe(string $nome): bool
    {
        return File::isDirectory($this->raiz().'/'.$nome);
    }

    public function encontrar(string $nome): Tema
    {
        if (isset($this->memoria[$nome])) {
            return $this->memoria[$nome];
        }

        if (! $this->existe($nome)) {
            throw TemaNaoEncontrado::tema($nome, $this->raiz());
        }

        return $this->memoria[$nome] = new Tema($nome, $this->raiz().'/'.$nome);
    }

    /**
     * O tema da loja. Cai no tema padrão quando a loja não escolheu.
     */
    public function daLoja(?Loja $loja = null): Tema
    {
        $loja ??= Loja::atualOuNula();

        $escolhido = $loja?->configuracoes['tema'] ?? null;

        return $this->encontrar($escolhido ?: (string) config('tema.padrao'));
    }

    public function esquecer(): void
    {
        $this->memoria = [];
    }
}
