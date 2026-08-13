<?php

namespace App\Nucleo\Loja;

use App\Models\Loja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca uma entidade de negócio como pertencente a uma loja.
 *
 * Dá três coisas de graça, e é isso que faz "loja única = multi-loja com N=1" ser verdade
 * sem nenhum `if` espalhado pelo código:
 *
 *  1. toda consulta já vem filtrada pela loja atual (EscopoDeLoja);
 *  2. todo registro criado já nasce com `loja_id` da loja atual;
 *  3. `->paraTodasAsLojas()` para o painel mestre furar o escopo de propósito.
 *
 * Módulo nenhum precisa saber se estamos em single ou multi — só usa este trait.
 */
trait PertenceALoja
{
    public static function bootPertenceALoja(): void
    {
        static::addGlobalScope(new EscopoDeLoja);

        static::creating(function ($model): void {
            if ($model->loja_id !== null) {
                return;
            }

            $model->loja_id = Loja::atual()->getKey();
        });
    }

    /**
     * @return BelongsTo<Loja, $this>
     */
    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    /**
     * Fura o isolamento de propósito — só o painel mestre tem motivo para isso.
     */
    public function scopeParaTodasAsLojas(Builder $consulta): Builder
    {
        return $consulta->withoutGlobalScope(EscopoDeLoja::class);
    }

    /**
     * Consulta o dado de uma loja específica, independente da loja atual.
     */
    public function scopeDaLoja(Builder $consulta, Loja|int $loja): Builder
    {
        return $consulta
            ->withoutGlobalScope(EscopoDeLoja::class)
            ->where($this->qualifyColumn('loja_id'), $loja instanceof Loja ? $loja->getKey() : $loja);
    }
}
