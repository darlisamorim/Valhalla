<?php

namespace App\Models;

use App\Nucleo\Loja\PertenceALoja;
use App\Nucleo\Modulos\Modulo;
use Illuminate\Database\Eloquent\Model;

/**
 * Um módulo ligado (ou desligado) numa loja.
 *
 * @property int $id
 * @property int $loja_id
 * @property Modulo $modulo
 * @property bool $ativo
 * @property array|null $configuracoes
 */
class LojaModulo extends Model
{
    use PertenceALoja;

    protected $table = 'loja_modulos';

    protected $fillable = [
        'loja_id',
        'modulo',
        'ativo',
        'configuracoes',
    ];

    protected function casts(): array
    {
        return [
            'modulo' => Modulo::class,
            'ativo' => 'boolean',
            'configuracoes' => 'array',
        ];
    }
}
