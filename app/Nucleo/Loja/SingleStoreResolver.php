<?php

namespace App\Nucleo\Loja;

use App\Models\Loja;

/**
 * Driver de fábrica: modo loja única (STORE_MODE=single).
 *
 * Devolve sempre a única loja do banco. Não há domínio para inspecionar, não há tenancy
 * envolvida — e é justamente por isso que o resto do sistema não precisa saber de nada:
 * ele pergunta `Loja::atual()` e recebe a mesma resposta sempre.
 *
 * A resolução é memoizada na requisição para não repetir consulta em cada chamada.
 */
class SingleStoreResolver implements ResolvedorDeLoja
{
    protected ?Loja $loja = null;

    protected bool $resolvida = false;

    public function atual(): ?Loja
    {
        if ($this->resolvida) {
            return $this->loja;
        }

        $this->loja = Loja::query()->orderBy('id')->first();
        $this->resolvida = true;

        return $this->loja;
    }

    public function definir(Loja $loja): void
    {
        $this->loja = $loja;
        $this->resolvida = true;
    }

    public function esquecer(): void
    {
        $this->loja = null;
        $this->resolvida = false;
    }
}
