<?php

namespace App\Nucleo\Tema;

/**
 * Uma seção posicionada numa página: qual bloco, ligado ou não, com que configuração.
 *
 * É o que o lojista mexe pelo painel — ligar, desligar, reordenar (CLAUDE.md 7.5). Ele
 * nunca edita código do tema.
 */
class Secao
{
    /**
     * @param  array<string, mixed>  $configuracoes
     */
    public function __construct(
        public readonly string $tipo,
        public readonly bool $ativa = true,
        public readonly array $configuracoes = [],
    ) {}

    /**
     * @param  array<string, mixed>|string  $bruto
     */
    public static function deArray(array|string $bruto): self
    {
        if (is_string($bruto)) {
            return new self(tipo: $bruto);
        }

        return new self(
            tipo: (string) ($bruto['tipo'] ?? ''),
            ativa: (bool) ($bruto['ativa'] ?? true),
            configuracoes: (array) ($bruto['configuracoes'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function paraArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'ativa' => $this->ativa,
            'configuracoes' => $this->configuracoes,
        ];
    }
}
