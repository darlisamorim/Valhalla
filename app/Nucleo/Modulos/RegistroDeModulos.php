<?php

namespace App\Nucleo\Modulos;

use App\Models\Loja;
use App\Models\LojaModulo;

/**
 * Quem está ligado nesta loja (CLAUDE.md 5.3).
 *
 * O lojista liga/desliga pelo painel; aqui é onde o resto do sistema pergunta. A resposta
 * é memoizada por loja dentro da requisição — isto é consultado a cada disparo de hook, e
 * ir ao banco toda vez seria caro.
 */
class RegistroDeModulos
{
    /** @var array<int, array<string, bool>> */
    protected array $memoria = [];

    /**
     * O módulo está ligado na loja atual (ou na loja informada)?
     *
     * Sem loja resolvida, responde false — exceto para módulo essencial. Fecha em caso de
     * dúvida: melhor um listener não rodar do que rodar na loja errada.
     */
    public function estaAtivo(Modulo $modulo, ?Loja $loja = null): bool
    {
        if ($modulo->essencial()) {
            return true;
        }

        $loja ??= Loja::atualOuNula();

        if ($loja === null) {
            return false;
        }

        return $this->ativosDa($loja)[$modulo->value] ?? false;
    }

    /**
     * Os módulos ligados na loja, na ordem do enum.
     *
     * @return array<int, Modulo>
     */
    public function ativos(?Loja $loja = null): array
    {
        $loja ??= Loja::atualOuNula();

        if ($loja === null) {
            return array_values(array_filter(Modulo::cases(), fn (Modulo $m) => $m->essencial()));
        }

        return array_values(array_filter(
            Modulo::cases(),
            fn (Modulo $modulo) => $this->estaAtivo($modulo, $loja),
        ));
    }

    public function ativar(Modulo $modulo, ?Loja $loja = null): LojaModulo
    {
        return $this->definir($modulo, true, $loja);
    }

    public function desativar(Modulo $modulo, ?Loja $loja = null): LojaModulo
    {
        return $this->definir($modulo, false, $loja);
    }

    protected function definir(Modulo $modulo, bool $ativo, ?Loja $loja): LojaModulo
    {
        $loja ??= Loja::atual();

        $registro = LojaModulo::query()
            ->daLoja($loja)
            ->where('modulo', $modulo->value)
            ->first();

        if ($registro === null) {
            $registro = new LojaModulo(['loja_id' => $loja->getKey(), 'modulo' => $modulo->value]);
        }

        $registro->ativo = $ativo;
        $registro->save();

        unset($this->memoria[$loja->getKey()]);

        return $registro;
    }

    /**
     * Esquece o que foi memoizado (usado após ligar/desligar e nos testes).
     */
    public function esquecer(): void
    {
        $this->memoria = [];
    }

    /**
     * @return array<string, bool>
     */
    protected function ativosDa(Loja $loja): array
    {
        return $this->memoria[$loja->getKey()] ??= LojaModulo::query()
            ->daLoja($loja)
            ->pluck('ativo', 'modulo')
            ->map(fn ($ativo) => (bool) $ativo)
            ->all();
    }
}
