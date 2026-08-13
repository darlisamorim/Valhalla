<?php

namespace App\Nucleo\Loja;

use App\Models\Loja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Isolamento por padrão: toda consulta a uma entidade de negócio é filtrada pela loja
 * atual, sem ninguém precisar lembrar de fazer isso (CLAUDE.md 6).
 *
 * **Fecha em caso de dúvida.** Se não há loja resolvida, a consulta não devolve nada em
 * vez de devolver tudo. Vazar dado de uma loja para outra é o pior defeito possível nesta
 * plataforma; devolver vazio é apenas um bug visível.
 *
 * Escape hatch explícito, para o painel mestre: `->paraTodasAsLojas()`.
 */
class EscopoDeLoja implements Scope
{
    public function apply(Builder $consulta, Model $model): void
    {
        $loja = Loja::atualOuNula();

        if ($loja === null) {
            $consulta->whereRaw('1 = 0');

            return;
        }

        $consulta->where($model->qualifyColumn('loja_id'), $loja->getKey());
    }
}
